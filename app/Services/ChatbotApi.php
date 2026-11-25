<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route as Router;

/**
 * Lets the chatbot use the TeleQuill API (/api/v0) on behalf of the user.
 *
 * - The AI picks endpoints from Telequill_Automation_API_Reference.md.
 * - Only real /api/v0 routes can be called (nothing else on the network).
 * - Calls run with the user's own "Telequill UI (auto)" token, so the API's
 *   normal permission checks apply: admins can do everything, other users only
 *   what their permissions allow.
 */
class ChatbotApi
{
    public const UI_TOKEN_DESCRIPTION = 'Telequill UI (auto)';

    /** The API reference the AI chooses endpoints from. */
    public static function reference(): string
    {
        $file = base_path('Telequill_Automation_API_Reference.md');

        return Cache::remember('chatbot.api_reference.' . (is_file($file) ? filemtime($file) : 0), 86400,
            fn () => is_file($file) ? file_get_contents($file) : '');
    }

    /** Normalise "/api/v0/x", "api/v0/x" and "/x" to "/api/v0/x". */
    public static function normalisePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');

        return str_starts_with($path, '/api/v0/') ? $path : '/api/v0' . $path;
    }

    /** The /api/v0 route this call would hit, or null if there is none. */
    public static function route(string $method, string $path): ?Route
    {
        try {
            $route = Router::getRoutes()->match(Request::create(self::normalisePath($path), strtoupper($method)));
        } catch (\Throwable $e) {
            return null;
        }

        // The catch-all "api/v0/{path?}" (route not found) is not a real endpoint.
        return str_starts_with($route->uri(), 'api/v0/') && ! str_contains($route->uri(), '{path?}') ? $route : null;
    }

    /**
     * Reads run straight away; anything that changes something needs the user
     * to confirm first. Some TeleQuill "show" endpoints are POST but only read.
     */
    public static function isRead(string $method, Route $route): bool
    {
        return strtoupper($method) === 'GET'
            || str_contains('/' . $route->uri() . '/', '/show/')
            || str_ends_with((string) $route->getName(), '.show');
    }

    /** Call the API as the given user. Returns [http status, decoded body or text]. */
    public static function call(User $user, string $method, string $path, array $body = []): array
    {
        $url = rtrim(config('services.librenms.url'), '/') . self::normalisePath($path);
        $http = Http::withHeaders(['X-Auth-Token' => self::uiToken($user), 'Accept' => 'application/json'])
            ->connectTimeout(10)
            ->timeout(600); // switch playbooks can take minutes

        try {
            $res = strtoupper($method) === 'GET'
                ? $http->get($url, $body)
                : $http->send(strtoupper($method), $url, ['json' => (object) $body]);
        } catch (\Throwable $e) {
            return [0, ['message' => 'Could not reach the TeleQuill API: ' . $e->getMessage()]];
        }

        return [$res->status(), $res->json() ?? $res->body()];
    }

    /** Short, readable reason from a failed API response. */
    public static function errorText(int $status, $data): string
    {
        $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? null) : null;
        if (is_array($data) && ! empty($data['errors']) && is_array($data['errors'])) {
            $msg = collect($data['errors'])->flatten()->implode(' ');
        }

        return match (true) {
            is_string($msg) && $msg !== '' => $msg,
            $status === 401 => 'not signed in to the API.',
            $status === 403 => "you don't have permission for this.",
            $status === 404 => 'not found.',
            default => "the API returned an error (HTTP $status).",
        };
    }

    /** The user's own enabled UI token, created if missing. */
    public static function uiToken(User $user): string
    {
        $token = ApiToken::query()->isEnabled()
            ->where('user_id', $user->user_id)
            ->where('description', self::UI_TOKEN_DESCRIPTION)
            ->value('token_hash');

        return $token ?: ApiToken::generateToken($user, self::UI_TOKEN_DESCRIPTION)->token_hash;
    }
}
