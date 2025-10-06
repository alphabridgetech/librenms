<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatBotController extends Controller
{
    public function index()
    {
        return view('chatbot.index');
    }

    public function message(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'mode' => 'required|in:bubble,terminal',
        ]);

        $userMessage = $request->input('message');

        // 1️⃣ Gather structured NMS data for LLM
        $nmsData = $this->getAllLibreNMSData();

        // 2️⃣ Build prompt for LLM
        $prompt = [
            'user_message' => $userMessage,
            'nms_data' => $nmsData,
            'instructions' => 'You are a network assistant. Respond in structured JSON if any action is needed (POST, PUT, DELETE) with keys: action, endpoint, params. 
            If no action, return only "reply" text.'
        ];

        // 3️⃣ Send prompt to LLM
        $llmResponse = $this->handleLLMQuery(json_encode($prompt, JSON_PRETTY_PRINT));

        $responseJson = $llmResponse->getData();

        // 4️⃣ Execute LLM-suggested actions dynamically
        if (!empty($responseJson->action) && in_array(strtoupper($responseJson->action), ['POST','PUT','DELETE'])) {
            return response()->json([
                'reply' => $this->executeDynamicAction($responseJson),
                'type' => 'librenms'
            ]);
        }

        // 5️⃣ Otherwise return LLM textual reply
        return $llmResponse;
    }

    // ======== DYNAMIC ACTION EXECUTION ========
    private function executeDynamicAction($llmData)
    {
        $method = strtoupper($llmData->action);
        $endpoint = $llmData->endpoint ?? '';
        $params = (array)($llmData->params ?? []);

        if (!$endpoint) return "Error: Endpoint not specified.";

        $url = rtrim(config('services.librenms.url'), '/') . '/' . $endpoint;
        $token = config('services.librenms.token');

        try {
            $res = match($method) {
                'POST' => Http::withHeaders(['X-Auth-Token'=>$token])->post($url, $params),
                'PUT' => Http::withHeaders(['X-Auth-Token'=>$token])->put($url, $params),
                'DELETE' => Http::withHeaders(['X-Auth-Token'=>$token])->delete($url, $params),
                default => null
            };

            if ($res && $res->successful()) {
                return ucfirst(strtolower($method)) . " action on {$endpoint} executed successfully.";
            }

            $error = $res ? ($res->json()['message'] ?? $res->body()) : 'Unknown error';
            return "Failed {$method} on {$endpoint}: {$error}";

        } catch (\Exception $e) {
            Log::error("LibreNMS {$method} API error", ['err'=>$e->getMessage()]);
            return "Error executing {$method} on {$endpoint}: " . $e->getMessage();
        }
    }

    // ======== LLM QUERY ========
    private function handleLLMQuery($prompt)
{
    try {
        $endpoint = config('services.gemini.endpoint'); 
        $apiKey = config('services.gemini.key');

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ];

        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($endpoint, $payload);

        $data = $response->json();
        Log::info('Gemini raw response', $data);

        $replyText = 'No response from LLM.';
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $replyText = $data['candidates'][0]['content']['parts'][0]['text'];
        }

        // Try to extract JSON from LLM reply
        $jsonPart = $this->extractJson($replyText);
        if ($jsonPart && isset($jsonPart->action)) {
            // Return full JSON so we can execute action
            return response()->json($jsonPart);
        }

        return response()->json(['reply' => $replyText, 'type'=>'llm']);

    } catch (\Exception $e) {
        Log::error('LLM request failed', ['err' => $e->getMessage()]);
        return response()->json(['reply' => 'LLM server failed.', 'type' => 'llm']);
    }
}

/**
 * Extract JSON from a string even if wrapped in ```json ... ```
 */
private function extractJson($text)
{
    // Remove ```json and ```
    $text = preg_replace('/```json|```/i', '', $text);
    $text = trim($text);

    $decoded = json_decode($text);
    return $decoded ?: null;
}


    // ======== FETCH ALL LIBRENMS DATA ========
    private function getAllLibreNMSData()
    {
        return [
            'devices' => $this->getStructuredDevices(),
            'ports' => $this->getStructuredPorts(),
            'services' => $this->getStructuredServices(),
            'alerts' => $this->getStructuredAlerts(),
            'graphs' => $this->getStructuredGraphs(),
            'device_groups' => $this->getStructuredDeviceGroups(),
            'locations' => $this->getStructuredLocations()
        ];
    }

    private function getStructuredDevices()
    {
        $devices = $this->callLibreNMS('devices');
        if ($devices === false || !isset($devices['devices'])) return [];

        return array_map(fn($d) => [
            'hostname' => $d['hostname'] ?? '',
            'ip' => $d['ip'] ?? '',
            'sysName' => $d['sysName'] ?? '',
            'status' => ($d['status'] === 1 || strtolower($d['status']) === 'up') ? 'up' : 'down',
            'uptime' => isset($d['uptime']) ? gmdate("H:i:s", $d['uptime']) : 'N/A',
            'last_polled' => $d['last_polled'] ?? '',
            'location' => $d['location'] ?? '',
            'os' => $d['os'] ?? '',
            'version' => $d['version'] ?? '',
        ], $devices['devices']);
    }

    private function getStructuredPorts()
    {
        $ports = $this->callLibreNMS('ports');
        if ($ports === false || !isset($ports['ports'])) return [];

        return array_map(fn($p) => [
            'port_id' => $p['port_id'] ?? '',
            'hostname' => $p['hostname'] ?? '',
            'ifName' => $p['ifName'] ?? '',
            'ifAlias' => $p['ifAlias'] ?? '',
            'admin_status' => $p['admin_status'] ?? '',
            'oper_status' => $p['oper_status'] ?? '',
            'last_polled' => $p['last_polled'] ?? '',
        ], $ports['ports']);
    }

    private function getStructuredServices()
    {
        $services = $this->callLibreNMS('services');
        if ($services === false || !isset($services['services'])) return [];

        return array_map(fn($s) => [
            'service_id' => $s['service_id'] ?? '',
            'hostname' => $s['hostname'] ?? '',
            'service_name' => $s['service_name'] ?? '',
            'status' => $s['status'] ?? '',
        ], $services['services']);
    }

    private function getStructuredAlerts()
    {
        $alerts = $this->callLibreNMS('alerts');
        if ($alerts === false || !isset($alerts['alerts'])) return [];

        return array_map(fn($a) => [
            'alert_id' => $a['alert_id'] ?? '',
            'hostname' => $a['hostname'] ?? '',
            'severity' => $a['severity'] ?? '',
            'message' => $a['message'] ?? '',
            'time' => $a['time'] ?? '',
        ], $alerts['alerts']);
    }

    private function getStructuredGraphs()
    {
        $devices = $this->getStructuredDevices();
        $graphs = [];
        foreach ($devices as $d) {
            $deviceGraphs = $this->callLibreNMS("devices/{$d['hostname']}/graphs");
            $graphs[$d['hostname']] = $deviceGraphs['graphs'] ?? [];
        }
        return $graphs;
    }

    private function getStructuredDeviceGroups()
    {
        $groups = $this->callLibreNMS('devicegroups');
        if ($groups === false || !isset($groups['groups'])) return [];

        return array_map(fn($g) => [
            'name' => $g['name'] ?? '',
            'devices' => $g['devices'] ?? []
        ], $groups['groups']);
    }

    private function getStructuredLocations()
    {
        $locations = $this->callLibreNMS('locations');
        if ($locations === false || !isset($locations['locations'])) return [];

        return array_map(fn($l) => [
            'id' => $l['id'] ?? '',
            'name' => $l['name'] ?? '',
            'address' => $l['address'] ?? '',
            'lat' => $l['lat'] ?? '',
            'lng' => $l['lng'] ?? ''
        ], $locations['locations']);
    }

    // ======== CALL LIBRENMS API ========
    private function callLibreNMS($endpoint)
    {
        $url = config('services.librenms.url');
        $token = config('services.librenms.token');

        try {
            $res = Http::withHeaders(['X-Auth-Token' => $token])
                        ->get(rtrim($url,'/').'/'.$endpoint);
            if ($res->successful()) {
                return $res->json();
            }
        } catch (\Exception $e) {
            Log::error('LibreNMS API error', ['err'=>$e->getMessage()]);
        }

        return false;
    }
}
