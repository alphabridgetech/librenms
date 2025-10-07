<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

        // 1️⃣ Determine dynamic token for this user
        $token = $this->getUserLibreNMSToken();

        if (!$token) {
            return response()->json([
                'reply' => 'No TeleQuillNMS API token found for your account. Please contact the admin.',
                'type' => 'error'
            ]);
        }




        // 2️⃣ Detect if the message is a POST action (add/edit/delete)
        $postAction = $this->parsePostAction($userMessage);
        if ($postAction) {
            $response = $this->handlePostAction($postAction, $token);
            return response()->json(['reply' => $response, 'type' => 'librenms']);
        }

        // 3️⃣ Gather structured GET NMS data
        $nmsData = $this->getAllLibreNMSData($token);

        // 4️⃣ Build JSON prompt for LLM
        $prompt = [
            'user_message' => $userMessage,
            'nms_data' => $nmsData,
            'instructions' => 'Use this structured NMS data to answer user queries accurately. Respond concisely and return data systematically for all endpoints (devices, ports, services, alerts, graphs, groups, locations). If user intends to add/update/delete, provide POST instructions in structured JSON format for the chatbot to process.'
        ];

        // 5️⃣ Send prompt to LLM
        return $this->handleLLMQuery(json_encode($prompt, JSON_PRETTY_PRINT));
    }

    /** -----------------------
     * GET USER TOKEN DYNAMICALLY
     * --------------------- */
    private function getUserLibreNMSToken()
    {
        try {
            $apiToken = ApiToken::select('token_hash')
                ->where('user_id', Auth::user()->user_id)
                ->firstOrFail();
               
            return $apiToken->token_hash;
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    /** -----------------------
     * POST ACTION PARSER
     * --------------------- */
    private function parsePostAction($message)
    {
        if (preg_match('/add device/i', $message)) {
            preg_match_all('/(\w+)=([^\s]+)/', $message, $matches, PREG_SET_ORDER);
            $params = [];
            foreach ($matches as $m) $params[$m[1]] = $m[2];

            return [
                'endpoint' => 'devices',
                'identifier' => $params['hostname'] ?? ($params['ip'] ?? null),
                'params' => $params
            ];
        }
        // Additional POST actions (edit/delete) can be added here
        return null;
    }

    private function handlePostAction($action, $token)
    {
        if (!$action['identifier']) return "Error: Missing identifier (hostname or IP).";

        return $this->postLibreNMSData(
            $action['endpoint'],
            $action['identifier'],
            $action['params'],
            $token
        );
    }

    /** -----------------------
     * GET METHODS
     * --------------------- */
    private function getAllLibreNMSData($token)
    {
        return [
            'devices' => $this->getStructuredDevices($token),
            'ports' => $this->getStructuredPorts($token),
            'services' => $this->getStructuredServices($token),
            'alerts' => $this->getStructuredAlerts($token),
            'graphs' => $this->getStructuredGraphs($token),
            'device_groups' => $this->getStructuredDeviceGroups($token),
            'locations' => $this->getStructuredLocations($token)
        ];
    }

    private function getStructuredDevices($token)
    {
        $devices = $this->callLibreNMS('devices', 'GET', [], $token);
        if ($devices === false || !isset($devices['devices'])) return [];

        return array_map(function($d){
            return [
                'hostname' => $d['hostname'] ?? '',
                'ip' => $d['ip'] ?? '',
                'sysName' => $d['sysName'] ?? '',
                'status' => ($d['status'] === 1 || strtolower($d['status']) === 'up') ? 'up' : 'down',
                'uptime' => isset($d['uptime']) ? gmdate("H:i:s", $d['uptime']) : 'N/A',
                'last_polled' => $d['last_polled'] ?? '',
                'location' => $d['location'] ?? '',
                'os' => $d['os'] ?? '',
                'version' => $d['version'] ?? '',
            ];
        }, $devices['devices']);
    }

    private function getStructuredPorts($token)
    {
        $ports = $this->callLibreNMS('ports', 'GET', [], $token);
        if ($ports === false || !isset($ports['ports'])) return [];

        return array_map(function($p){
            return [
                'port_id' => $p['port_id'] ?? '',
                'hostname' => $p['hostname'] ?? '',
                'ifName' => $p['ifName'] ?? '',
                'ifAlias' => $p['ifAlias'] ?? '',
                'admin_status' => $p['admin_status'] ?? '',
                'oper_status' => $p['oper_status'] ?? '',
                'last_polled' => $p['last_polled'] ?? '',
            ];
        }, $ports['ports']);
    }

    private function getStructuredServices($token)
    {
        $services = $this->callLibreNMS('services', 'GET', [], $token);
        if ($services === false || !isset($services['services'])) return [];

        return array_map(function($s){
            return [
                'service_id' => $s['service_id'] ?? '',
                'hostname' => $s['hostname'] ?? '',
                'service_name' => $s['service_name'] ?? '',
                'status' => $s['status'] ?? '',
            ];
        }, $services['services']);
    }

    private function getStructuredAlerts($token)
    {
        $alerts = $this->callLibreNMS('alerts', 'GET', [], $token);
        if ($alerts === false || !isset($alerts['alerts'])) return [];

        return array_map(function($a){
            return [
                'alert_id' => $a['alert_id'] ?? '',
                'hostname' => $a['hostname'] ?? '',
                'severity' => $a['severity'] ?? '',
                'message' => $a['message'] ?? '',
                'time' => $a['time'] ?? '',
            ];
        }, $alerts['alerts']);
    }

    private function getStructuredGraphs($token)
    {
        $devices = $this->getStructuredDevices($token);
        $graphs = [];
        foreach ($devices as $d) {
            $deviceGraphs = $this->callLibreNMS("devices/{$d['hostname']}/graphs", 'GET', [], $token);
            $graphs[$d['hostname']] = $deviceGraphs['graphs'] ?? [];
        }
        return $graphs;
    }

    private function getStructuredDeviceGroups($token)
    {
        $groups = $this->callLibreNMS('devicegroups', 'GET', [], $token);
        if ($groups === false || !isset($groups['groups'])) return [];

        return array_map(function($g){
            return [
                'name' => $g['name'] ?? '',
                'devices' => $g['devices'] ?? []
            ];
        }, $groups['groups']);
    }

    private function getStructuredLocations($token)
    {
        $locations = $this->callLibreNMS('locations', 'GET', [], $token);
        if ($locations === false || !isset($locations['locations'])) return [];

        return array_map(function($l){
            return [
                'id' => $l['id'] ?? '',
                'name' => $l['name'] ?? '',
                'address' => $l['address'] ?? '',
                'lat' => $l['lat'] ?? '',
                'lng' => $l['lng'] ?? ''
            ];
        }, $locations['locations']);
    }

    /** -----------------------
     * POST METHODS (Dynamic)
     * --------------------- */
    private function postLibreNMSData($endpoint, $identifier, $params = [], $token)
    {
        // Merge defaults for device creation
        if (!isset($params['hostname'])) $params['hostname'] = $identifier;
        if (!isset($params['ip'])) $params['ip'] = $identifier;
        if (!isset($params['version'])) $params['version'] = 'v2c';
        if (!isset($params['community'])) $params['community'] = 'public';
        if (!isset($params['port'])) $params['port'] = 161;
        if (!isset($params['force_add'])) $params['force_add'] = true;

        $response = $this->callLibreNMS($endpoint, 'POST', $params, $token);

        if (isset($response['error'])) return "Failed POST on /$endpoint: {$response['error']}";
        return "Device '{$params['hostname']}' added successfully.";
    }

    /** -----------------------
     * CALL LIBRENMS GENERIC
     * --------------------- */
    private function callLibreNMS($endpoint, $method = 'GET', $params = [], $token)
    {
        $url = rtrim(config('services.librenms.url'), '/');
        if (!str_starts_with($endpoint, 'api/v0/')) {
            $endpoint = 'api/v0/' . ltrim($endpoint, '/');
        }
        

        try {
            $http = Http::withHeaders(['X-Auth-Token' => $token]);

            switch (strtoupper($method)) {
                case 'POST':
                    $res = $http->asForm()->post("$url/$endpoint", $params);
                    break;
                case 'PUT':
                    $res = $http->asForm()->put("$url/$endpoint", $params);
                    break;
                case 'DELETE':
                    $res = $http->delete("$url/$endpoint", $params);
                    break;
                default:
                    $res = $http->get("$url/$endpoint", $params);
            }

            if ($res->successful()) return $res->json();

            $error = $res->json()['message'] ?? $res->body();
            Log::error("TeleQuillNMS API {$method} failed", ['endpoint' => $endpoint, 'body' => $res->body()]);
            return ['error' => $error];

        } catch (\Exception $e) {
            Log::error('TeleQuillNMS API error', ['err' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /** -----------------------
     * LLM HANDLER
     * --------------------- */
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

            $reply = 'No response from LLM.';
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = $data['candidates'][0]['content']['parts'][0]['text'];
            }

            return response()->json(['reply' => $reply, 'type' => 'llm']);

        } catch (\Exception $e) {
            Log::error('LLM request failed', ['err' => $e->getMessage()]);
            return response()->json(['reply' => 'LLM server failed.', 'type' => 'llm']);
        }
    }
}
