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
        $token = $this->getUserLibreNMSToken();

        if (!$token) {
            return response()->json([
                'reply' => 'No TeleQuillNMS API token found for your account. Please contact the admin.',
                'type' => 'error'
            ]);
        }

        // Gather NMS data
        $nmsData = $this->getAllLibreNMSData($token);

        // Build prompt for Gemini
        $prompt = [
            'user_message' => $userMessage,
            'nms_data' => $nmsData,
            'instructions' => 'You are an NMS assistant. When user requests to add, update, or delete data, respond ONLY with JSON like: {"action": "DELETE", "resource": "device", "data": {"ip": "x.x.x.x"}} or {"action": "POST", "resource": "device", "data": {"ip": "x.x.x.x"}}. Otherwise, answer normally.'
        ];

        return $this->handleLLMQuery(json_encode($prompt, JSON_PRETTY_PRINT));
    }

    /** -----------------------
     * GET USER TOKEN
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
     * GET METHODS
     * --------------------- */
    private function getAllLibreNMSData($token)
    {
    $devices = $this->getStructuredDevices($token);

    $devicePorts = [];
    foreach ($devices as $d) {
        if (!empty($d['hostname'])) {
            $devicePorts[$d['hostname']] = $this->getDevicePorts($d['hostname'], $token);
        }
    }
        return [
            'devices' => $this->getStructuredDevices($token),
            'ports' => $this->getStructuredPorts($token),
            'services' => $this->getStructuredServices($token),
            'alerts' => $this->getStructuredAlerts($token),
            'graphs' => $this->getStructuredGraphs($token),
            'device_ports' => $devicePorts,
            'sensors' => $this->getStructuredSensors($token),
        ];
    }

     private function getStructuredSensors($token)
    {
        // Get all devices first
        $devices = $this->getStructuredDevices($token);

        // Create mapping: device_id → hostname
        $deviceMap = [];
        foreach ($devices as $d) {
            if (!empty($d['device_id']) && !empty($d['hostname'])) {
                $deviceMap[$d['device_id']] = $d['hostname'];
            }
        }

        // Fetch sensors
        $sensors = $this->callLibreNMS('resources/sensors', 'GET', [], $token);

        if ($sensors === false || !isset($sensors['sensors'])) return [];

        return array_map(function($s) use ($deviceMap) {

            $hostname = $deviceMap[$s['device_id']] ?? 'Unknown';

            return [
                'sensor_id' => $s['sensor_id'] ?? '',
                'hostname'  => $hostname,                         // ✅ replaced value
                'class'     => $s['sensor_class'] ?? '',
                'type'      => $s['sensor_type'] ?? '',
                'descr'     => $s['sensor_descr'] ?? '',
                'current'   => $s['sensor_current'] ?? '',
                'limit_high' => $s['sensor_limit'] ?? '',
                'limit_low'  => $s['sensor_limit_low'] ?? '',
                'alert'     => $s['sensor_alert'] ?? '',
                'poller_type'=> $s['poller_type'] ?? '',
                'oid'       => $s['sensor_oid'] ?? '',
                'last_update'=> $s['lastupdate'] ?? '',
            ];
        }, $sensors['sensors']);
    }



        private function getDevicePorts($ip, $token)
    {
        // GET /api/v0/devices/{hostname}/ports
        $response = $this->callLibreNMS("devices/$ip/ports", 'GET', [], $token);

        if ($response === false || !isset($response['ports'])) return [];

        return array_map(function($p){
            return [
                'port_id'     => $p['port_id'] ?? '',
                'ifName'      => $p['ifName'] ?? '',
                'ifAlias'     => $p['ifAlias'] ?? '',
                'admin_status'=> $p['admin_status'] ?? '',
                'oper_status' => $p['oper_status'] ?? '',
                'speed'       => $p['ifSpeed'] ?? '',
                'last_change' => $p['ifLastChange'] ?? '',
            ];
        }, $response['ports']);
    }


    private function getStructuredDevices($token)
    {
        $devices = $this->callLibreNMS('devices', 'GET', [], $token);
        if ($devices === false || !isset($devices['devices'])) return [];

        return array_map(function($d){
            return [
                'device_id' => $d['device_id'] ?? '',
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


    /** -----------------------
     * ADD DEVICE (POST)
     * --------------------- */
    private function addDevice($ip, $token, $community = 'public', $version = 'v2c', $port = 161)
    {
        $params = [
            'hostname' => $ip,
            'version' => $version,
            'community' => $community,
            'port' => $port,
            'force_add' => true
        ];

        $response = $this->callLibreNMS('devices', 'POST', $params, $token);

        if (isset($response['status']) && $response['status'] === 'ok') {
            return "✅ Device $ip added successfully.";
        }
        return "❌ Failed to add device $ip: " . json_encode($response);
    }

    /** -----------------------
     * GENERIC LIBRENMS CALL
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
                    $res = $http->post("$url/$endpoint", $params);
                    break;
                case 'PUT':
                    $res = $http->put("$url/$endpoint", $params);
                    break;
                case 'DELETE':
                    $res = $http->delete("$url/$endpoint", $params);
                    break;
                default:
                    $res = $http->get("$url/$endpoint", $params);
            }

            if ($res->successful()) return $res->json();

            $error = $res->json()['message'] ?? $res->body();
            Log::error("LibreNMS {$method} failed", ['endpoint' => $endpoint, 'body' => $res->body()]);
            return ['error' => $error];

        } catch (\Exception $e) {
            Log::error('LibreNMS API error', ['err' => $e->getMessage()]);
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
            $reply = 'No response from LLM.';

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = trim($data['candidates'][0]['content']['parts'][0]['text']);
            }

            // detect JSON command
            if (preg_match('/\{[\s\S]*\}/', $reply, $match)) {
                $jsonStr = $match[0];
                $actionData = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($actionData['action'])) {
                    $result = $this->executeLLMAction($actionData);
                    return response()->json([
                        'reply' => "🧠 Executed Action:\n" . json_encode($actionData, JSON_PRETTY_PRINT) . "\n\nResult: $result",
                        'type' => 'action'
                    ]);
                }
            }

            return response()->json(['reply' => $reply, 'type' => 'llm']);

        } catch (\Exception $e) {
            Log::error('LLM request failed', ['err' => $e->getMessage()]);
            return response()->json(['reply' => 'LLM server failed.', 'type' => 'llm']);
        }
    }

    /** -----------------------
     * EXECUTE LLM ACTION
     * --------------------- */
    private function executeLLMAction(array $actionData)
    {
        $token = $this->getUserLibreNMSToken();
        if (!$token) return 'No LibreNMS token found.';

        $action = strtoupper($actionData['action'] ?? '');
        $resource = strtolower($actionData['resource'] ?? '');
        $data = $actionData['data'] ?? [];

        switch ($action) {
            case 'DELETE':
                if ($resource === 'device' && isset($data['ip'])) {
                    $ip = $data['ip'];
                    $deviceId = $this->getDeviceIdByIp($ip, $token);
                    if (!$deviceId) return "Device with IP $ip not found.";
                    $response = $this->callLibreNMS("devices/$deviceId", 'DELETE', [], $token);
                    return isset($response['status']) && $response['status'] === 'ok'
                        ? "✅ Device $ip deleted successfully."
                        : "❌ Failed to delete: " . json_encode($response);
                }
                return "DELETE action not supported for resource: $resource";

            case 'POST':
                if ($resource === 'device' && isset($data['ip'])) {
                    $ip = $data['ip'];
                    return $this->addDevice(
                        $ip,
                        $token,
                        $data['community'] ?? 'public',
                        $data['version'] ?? 'v2c',
                        $data['port'] ?? 161
                    );
                }
                return "POST action not supported for resource: $resource";

            default:
                return "Unsupported action: $action";
        }
    }

    private function getDeviceIdByIp($ip, $token)
    {
        $devices = $this->callLibreNMS('devices', 'GET', [], $token);
        if (!isset($devices['devices'])) return null;

        foreach ($devices['devices'] as $d) {
            if (($d['ip'] ?? '') === $ip || ($d['hostname'] ?? '') === $ip) {
                return $d['device_id'] ?? null;
            }
        }
        return null;
    }
}
