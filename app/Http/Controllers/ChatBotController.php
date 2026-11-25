<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Facades\LibrenmsConfig;
use App\Models\ChatbotConversation;
use App\Services\ChatbotApi;
use App\Services\ChatbotAssistant;

class ChatBotController extends Controller
{
    public function index()
    {
        return view('chatbot.index', ['aiAvailable' => $this->aiUsable()]);
    }

    /**
     * AI settings come from Settings > AI Chatbot (stored in the database);
     * CHATBOT_API_KEY / CHATBOT_ENDPOINT in the env file are only a fallback.
     */
    private function aiSetting(string $name): string
    {
        $value = LibrenmsConfig::get("chatbot.$name");
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        // The CHATBOT_* env values are Gemini settings: only used for the Gemini provider.
        if ($this->aiProvider() !== 'gemini') {
            return '';
        }
        $env = ['api_key' => 'key'][$name] ?? $name; // config/services.php names

        return trim((string) config("services.chatbot.$env"));
    }

    /** gemini | openai (any OpenAI-compatible service, including local Ollama). */
    private function aiProvider(): string
    {
        return LibrenmsConfig::get('chatbot.provider') === 'openai' ? 'openai' : 'gemini';
    }

    /** The URL to call for the chosen provider ('' when it can't be worked out). */
    private function aiEndpoint(): string
    {
        $endpoint = $this->aiSetting('endpoint');
        if ($endpoint !== '') {
            return $endpoint;
        }
        $model = $this->aiSetting('model');

        return match ($this->aiProvider()) {
            'openai' => 'https://api.openai.com/v1/chat/completions',
            default => $model !== '' ? 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent' : '',
        };
    }

    /**
     * The AI can only be used when it is enabled and has what its provider needs:
     * Gemini a key and endpoint; OpenAI-compatible a model, and a key unless it
     * is a local server (custom endpoint) such as Ollama.
     */
    private function aiConfigured(): bool
    {
        if (LibrenmsConfig::get('chatbot.enabled', true) === false || $this->aiEndpoint() === '') {
            return false;
        }

        return $this->aiProvider() === 'openai'
            ? $this->aiSetting('model') !== '' && ($this->aiSetting('api_key') !== '' || $this->aiSetting('endpoint') !== '')
            : $this->aiSetting('api_key') !== '';
    }

    // ------------------------------------------------------------ saved chats

    /** The logged-in user's chats, newest first. */
    public function conversations(Request $request)
    {
        return response()->json(
            ChatbotConversation::where('user_id', $request->user()->user_id)
                ->orderByDesc('updated_at')->orderByDesc('id')->limit(200)
                ->get(['id', 'title', 'updated_at'])
        );
    }

    /** One chat with its messages. */
    public function showConversation(Request $request, int $id)
    {
        $conversation = $this->ownConversation($request, $id);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $conversation->messages()->get(['id', 'role', 'type', 'content'])
                ->map(fn ($m) => $m->toArray() + ['html' => $m->role === 'assistant' ? self::markdown($m->content) : null]),
            'pending' => Cache::has($this->pendingKey($conversation)), // a change waits for Confirm/Cancel
        ]);
    }

    public function renameConversation(Request $request, int $id)
    {
        $data = $request->validate(['title' => 'required|string|max:120']);
        $conversation = $this->ownConversation($request, $id);
        $conversation->update(['title' => trim($data['title'])]);

        return response()->json(['id' => $conversation->id, 'title' => $conversation->title]);
    }

    /** "Clear chat": remove the messages, keep the chat. */
    public function clearConversation(Request $request, int $id)
    {
        $this->ownConversation($request, $id)->messages()->delete();

        return response()->json(['status' => 'ok']);
    }

    public function deleteConversation(Request $request, int $id)
    {
        $this->ownConversation($request, $id)->delete();

        return response()->json(['status' => 'ok']);
    }

    /** A chat belonging to the logged-in user; anyone else's chat is "not found". */
    private function ownConversation(Request $request, int $id): ChatbotConversation
    {
        return ChatbotConversation::where('user_id', $request->user()->user_id)->findOrFail($id);
    }

    // ------------------------------------------------------------ messages

    /**
     * Answer a message and save the question and the answer in the chat
     * (a new chat is created when no conversation_id is given).
     */
    public function message(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'mode' => 'required|in:bubble,terminal',
            'conversation_id' => 'nullable|integer',
        ]);

        $conversation = $request->filled('conversation_id')
            ? $this->ownConversation($request, (int) $request->input('conversation_id'))
            : ChatbotConversation::create([
                'user_id' => $request->user()->user_id,
                'title' => ChatbotConversation::titleFrom($request->input('message')),
            ]);

        return $this->answerUserMessage($request, $conversation, trim($request->input('message')));
    }

    /**
     * Edit or Regenerate: replace one of your earlier messages (and everything
     * after it) with new text, then answer again. Changes already made on a
     * switch are not undone; only the chat history is replaced.
     */
    public function resend(Request $request, int $id)
    {
        $data = $request->validate([
            'message_id' => 'required|integer',
            'message' => 'required|string|max:5000',
            'mode' => 'required|in:bubble,terminal',
        ]);
        $conversation = $this->ownConversation($request, $id);
        $from = $conversation->messages()->where('role', 'user')->findOrFail($data['message_id']);

        $conversation->messages()->where('id', '>=', $from->id)->delete();
        Cache::forget($this->pendingKey($conversation)); // a waiting change belonged to the old answer

        return $this->answerUserMessage($request, $conversation, trim($data['message']));
    }

    /** Save the user's message, answer it and save the answer. */
    private function answerUserMessage(Request $request, ChatbotConversation $conversation, string $text)
    {
        $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => $text]);
        $request->attributes->set('chatbot_user_message_id', $userMessage->id);

        // A change is waiting for confirmation: "yes" runs it, "no" cancels it,
        // anything else means the user moved on, so it is dropped.
        if (Cache::has($this->pendingKey($conversation))) {
            if (preg_match('/^(yes|y|ok|okay|confirm|do it|go ahead|haan|ha|han|ji)[.! ]*$/i', $text)) {
                return $this->saveReply($conversation, $this->runPending($request, $conversation));
            }
            if (preg_match('/^(no|n|cancel|stop|nahi|nhi|mat karo)[.! ]*$/i', $text)) {
                Cache::forget($this->pendingKey($conversation));

                return $this->saveReply($conversation, ['reply' => 'Cancelled. Nothing was changed.', 'type' => 'cancelled']);
            }
            Cache::forget($this->pendingKey($conversation));
        }

        return $this->saveReply($conversation, $this->reply($request, $conversation, $text));
    }

    /** Confirm button: run the change that is waiting in this chat. */
    public function confirmAction(Request $request, int $id)
    {
        $conversation = $this->ownConversation($request, $id);

        return $this->saveReply($conversation, $this->runPending($request, $conversation));
    }

    /** Cancel button. */
    public function cancelAction(Request $request, int $id)
    {
        $conversation = $this->ownConversation($request, $id);
        Cache::forget($this->pendingKey($conversation));

        return $this->saveReply($conversation, ['reply' => 'Cancelled. Nothing was changed.', 'type' => 'cancelled']);
    }

    /** Save the assistant's reply in the chat and return it to the page. */
    private function saveReply(ChatbotConversation $conversation, array $data)
    {
        $type = ($data['type'] ?? 'llm') === 'notice' ? 'notice-' . ($data['reason'] ?? 'disabled') : ($data['type'] ?? 'llm');
        $saved = $conversation->messages()->create(['role' => 'assistant', 'type' => $type, 'content' => (string) ($data['reply'] ?? '')]);
        $conversation->touch(); // newest chat moves to the top of the list

        return response()->json($data + [
            'message_id' => $saved->id,
            'user_message_id' => request()->attributes->get('chatbot_user_message_id'),
            'html' => self::markdown((string) ($data['reply'] ?? '')),
            'conversation_id' => $conversation->id,
            'title' => $conversation->title,
            'pending' => Cache::has($this->pendingKey($conversation)),
        ]);
    }

    /**
     * Automatic online/offline:
     *  - AI set up and reachable -> the AI answers, using the user's own NMS
     *    data and, when needed, the TeleQuill API (reads run at once, changes
     *    wait for the user's confirmation);
     *  - no AI key, no internet or AI error -> built-in answers from the
     *    TeleQuill database. After a failure the AI is skipped for a minute so
     *    offline sites don't wait on every question.
     */
    private function reply(Request $request, ChatbotConversation $conversation, string $userMessage): array
    {
        $assistant = new ChatbotAssistant($request->user());

        if ($this->aiUsable()) {
            $answer = $this->handleLLMQuery(json_encode([
                'instructions' => $this->instructions($request->user()->isAdmin()),
                'conversation' => $this->history($conversation),
                'user_message' => $userMessage,
                'nms_data' => $assistant->context(), // limited to the devices this user may see
                'api_reference' => ChatbotApi::reference(),
            ]));

            if ($answer !== null) {
                return $this->handleAiAnswer($request, $conversation, $userMessage, $answer);
            }
            Cache::put(self::AI_DOWN_KEY, true, self::AI_RETRY_SECONDS);
        }

        // Offline: built-in answers from TeleQuill's own database.
        $builtin = $assistant->answer($userMessage);
        if ($builtin !== null) {
            return ['reply' => $builtin, 'type' => 'builtin'];
        }

        return $this->builtinFallback($assistant, $this->aiConfigured() ? 'failed' : 'disabled');
    }

    private function instructions(bool $isAdmin): string
    {
        return implode("\n", [
            'You are TeleQuill AI, the assistant of the TeleQuill network management system (built on LibreNMS) for AlphaBridge switches.',
            'You have: nms_data (current devices, ports, alerts, sensors, CPU and memory this user may see), api_reference (every TeleQuill API endpoint you may use; paths are under /api/v0) and conversation (earlier messages in this chat).',
            '1. If nms_data or the conversation already answers the question, reply in plain text. Markdown lists, **bold** and `code` are fine. Never show raw JSON to the user.',
            '2. If you need the API, to look something up that is not in nms_data or to change something, reply with ONLY this JSON and nothing else:',
            '{"api": {"method": "GET", "path": "/api/v0/...", "body": {}}, "summary": "one plain-English sentence saying exactly what this does"}',
            '   - Use exactly one endpoint from api_reference, with its method. {hostname} is the switch IP address. Put the request fields in body.',
            '   - Never invent endpoints or fields. If a required value is missing or unclear (which switch, port, VLAN id, ...), ask the user instead of guessing.',
            '   - Changes are shown to the user to confirm before they run, so the summary must say precisely what will change and on which device.',
            '   - Never mention API endpoints, paths, HTTP methods or JSON to the user, in the summary or in answers. Describe everything in network terms.',
            '3. ' . ($isAdmin
                ? 'This user is an administrator and may use every endpoint.'
                : 'This user is not an administrator: the API only lets them configure devices they have permission for, and not add or delete devices or change global settings. Do not offer those.'),
        ]);
    }

    /** Recent messages of this chat, so the AI understands follow-ups. */
    private function history(ChatbotConversation $conversation): array
    {
        return $conversation->messages()->reorder('id', 'desc')->skip(1)->take(12)->get(['role', 'content'])
            ->reverse()->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => mb_substr($m->content, 0, 2000)])
            ->all();
    }

    /** Plain answer, or an API call: reads run now, changes wait for confirmation. */
    private function handleAiAnswer(Request $request, ChatbotConversation $conversation, string $userMessage, string $answer): array
    {
        $call = $this->parseApiCall($answer);
        if ($call === null) {
            return ['reply' => $answer, 'type' => 'llm'];
        }

        [$method, $path, $body, $summary] = $call;
        $route = ChatbotApi::route($method, $path);
        if (! $route) {
            Log::warning('Chatbot AI picked an unknown API endpoint', ['method' => $method, 'path' => $path]);

            return ['reply' => "Sorry, I can't do that from the chat. Please rephrase your request.", 'type' => 'error'];
        }

        if (! ChatbotApi::isRead($method, $route)) {
            Cache::put($this->pendingKey($conversation), [
                'user_id' => $request->user()->user_id,
                'method' => $method, 'path' => ChatbotApi::normalisePath($path), 'body' => $body, 'summary' => $summary,
            ], self::PENDING_SECONDS);

            return ['reply' => $this->confirmText($method, $path, $body, $summary), 'type' => 'confirm'];
        }

        [$status, $data] = ChatbotApi::call($request->user(), $method, $path, $body);
        if ($status < 200 || $status >= 300) {
            return ['reply' => "❌ I couldn't get that: " . ChatbotApi::errorText($status, $data), 'type' => 'error'];
        }

        $result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $explained = $this->handleLLMQuery(json_encode([
            'instructions' => 'Answer the user\'s question using this API result. Plain text only: summarise, use short lists or a markdown-free table layout if helpful. Never show raw JSON or mention the API call.',
            'user_message' => $userMessage,
            'api_call' => "$method $path",
            'api_result' => mb_substr($result, 0, 60000),
        ]));

        return $explained !== null && $this->parseApiCall($explained) === null
            ? ['reply' => $explained, 'type' => 'llm']
            : ['reply' => "Here is what TeleQuill returned for **$summary**:\n```\n" . mb_substr($result, 0, 3000) . "\n```", 'type' => 'llm'];
    }

    /** Run the change waiting in this chat (after the user confirmed it). */
    private function runPending(Request $request, ChatbotConversation $conversation): array
    {
        $pending = Cache::pull($this->pendingKey($conversation));
        if (! $pending || $pending['user_id'] !== $request->user()->user_id) {
            return ['reply' => 'There is nothing waiting for confirmation here (it may have expired after 15 minutes). Please ask again.', 'type' => 'notice-expired'];
        }

        [$status, $data] = ChatbotApi::call($request->user(), $pending['method'], $pending['path'], $pending['body']);
        $apiStatus = is_array($data) ? strtolower((string) ($data['status'] ?? '')) : '';
        $ok = $status >= 200 && $status < 300 && ! in_array($apiStatus, ['error', 'failed', 'fail'], true);

        if (! $ok) {
            return ['reply' => "❌ **Not done:** {$pending['summary']}\n\nReason: " . ChatbotApi::errorText($status, $data), 'type' => 'error'];
        }

        $message = is_array($data) && is_string($data['message'] ?? null) ? "\n\n" . $data['message'] : '';

        return ['reply' => "✅ **Done:** {$pending['summary']}" . $message, 'type' => 'action'];
    }

    /** The confirmation question, in plain words (no JSON). */
    private function confirmText(string $method, string $path, array $body, string $summary): string
    {
        $value = function ($v): string {
            if ($v === null || $v === '') {
                return '(empty)';
            }
            if (is_bool($v)) {
                return $v ? 'yes' : 'no';
            }

            return is_scalar($v) ? (string) $v : implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), (array) $v));
        };
        $details = collect($body)->map(fn ($v, $k) => '- ' . str_replace('_', ' ', (string) $k) . ': **' . $value($v) . '**')->implode("\n");

        // Plain words only: the API method and path are not shown to the user.
        return "**Please confirm this change:**\n$summary"
            . ($details !== '' ? "\n\n$details" : '');
    }

    /** [method, path, body, summary] if the AI asked for an API call, else null. */
    private function parseApiCall(string $answer): ?array
    {
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($answer)));
        if (! str_starts_with($json, '{')) {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['api']['method'], $data['api']['path'])) {
            return null;
        }
        $body = $data['api']['body'] ?? [];

        return [
            strtoupper((string) $data['api']['method']),
            (string) $data['api']['path'],
            is_array($body) ? $body : [],
            trim((string) ($data['summary'] ?? '')) ?: strtoupper((string) $data['api']['method']) . ' ' . $data['api']['path'],
        ];
    }

    /**
     * Assistant replies are Markdown (headings, lists, tables, bold, italics,
     * code). Rendered here, offline, in safe mode: raw HTML in the text is
     * shown as text and javascript: style links are dropped.
     */
    public static function markdown(string $text): string
    {
        return Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    private function pendingKey(ChatbotConversation $conversation): string
    {
        return 'chatbot.pending.' . $conversation->id;
    }

    private const PENDING_SECONDS = 900;
    private const AI_DOWN_KEY = 'chatbot.ai_unreachable';
    private const AI_RETRY_SECONDS = 60;

    /** AI is set up and did not fail in the last minute. */
    private function aiUsable(): bool
    {
        return $this->aiConfigured() && ! Cache::has(self::AI_DOWN_KEY);
    }

    /**
     * Reply used when the question is not a built-in one and the AI is not
     * configured or not reachable (e.g. an offline site).
     */
    private function builtinFallback(ChatbotAssistant $assistant, string $reason = 'disabled'): array
    {
        $intro = match ($reason) {
            'failed' => "No internet connection to the AI right now, so I'm working offline and can only answer built-in questions.",
            default => "I didn't understand that one. The AI assistant isn't set up on this server, so I can only answer built-in questions.",
        };

        // 'notice' (not 'builtin') so the page can say why the AI did not answer.
        return ['reply' => $intro . "\n\n" . $assistant->help(), 'type' => 'notice', 'reason' => $reason];
    }

    /** Ask the AI. Returns its text, or null when it can't be reached or gives no answer. */
    private function handleLLMQuery(string $prompt): ?string
    {
        try {
            $key = $this->aiSetting('api_key');

            if ($this->aiProvider() === 'openai') {
                // OpenAI chat completions format (OpenAI, Groq, Mistral, OpenRouter, Ollama, LM Studio, vLLM ...)
                $http = Http::withHeaders(['Content-Type' => 'application/json']);
                if ($key !== '') {
                    $http = $http->withToken($key);
                }
                $response = $http->connectTimeout(5)->timeout(180) // local models on CPU can be slow
                    ->post($this->aiEndpoint(), [
                        'model' => $this->aiSetting('model'),
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                    ]);
                $text = $response->json('choices.0.message.content');
            } else {
                // Google Gemini format
                $response = Http::withHeaders([
                    'x-goog-api-key' => $key,
                    'Content-Type' => 'application/json',
                ])->connectTimeout(5)->timeout(90)->post($this->aiEndpoint(), [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                ]);
                $text = $response->json('candidates.0.content.parts.0.text');
            }

            if (! is_string($text) || trim($text) === '') {
                Log::warning('LLM returned no answer', ['provider' => $this->aiProvider(), 'status' => $response->status()]);

                return null;
            }

            return trim($text);
        } catch (\Exception $e) {
            Log::error('LLM request failed', ['provider' => $this->aiProvider(), 'err' => $e->getMessage()]);

            return null;
        }
    }
}
