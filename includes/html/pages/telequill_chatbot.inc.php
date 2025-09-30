<?php
use Illuminate\Support\Facades\Http;

session_start();

$user_id = Auth::user()->user_id ?? null;
$token = null;

// Get user API token
try {
    $apiToken = \App\Models\ApiToken::select('token_hash')->where('user_id', $user_id)->firstOrFail();
    $token = $apiToken->token_hash;
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    $token = null;
}

$TELEQUILL_URL = "http://127.0.0.1:8000";
$GEMINI_API_KEY = "AIzaSyDMYopkI7e4B1zHuSgTRu7wWcxaQjWKTko";

// ---------------- Helper Functions ----------------
function fetchTelequillData($endpoint, $token) {
    
    global $TELEQUILL_URL;
    try {
        $res = Http::withHeaders([
            'X-Auth-Token' => $token
        ])->timeout(10)->get("$TELEQUILL_URL/api/v0/$endpoint");
        return $res->json();
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function addDevice($hostname, $token) {
    global $TELEQUILL_URL;
    try {
        $res = Http::withHeaders(['X-Auth-Token'=>$token])
            ->timeout(10)
            ->post("$TELEQUILL_URL/api/v0/devices", ['hostname'=>$hostname]);
        return $res->successful() ? "Device '$hostname' added successfully." : "Failed to add device: ".$res->body();
    } catch (\Exception $e) {
        return "Exception: ".$e->getMessage();
    }
}

function deleteDevice($hostname, $token) {
    global $TELEQUILL_URL;
    try {
        $res = Http::withHeaders(['X-Auth-Token'=>$token])
            ->timeout(10)
            ->delete("$TELEQUILL_URL/api/v0/devices/$hostname");
        return $res->successful() ? "Device '$hostname' deleted successfully." : "Failed to delete device: ".$res->body();
    } catch (\Exception $e) {
        return "Exception: ".$e->getMessage();
    }
}

function queryGemini($prompt) {
    global $GEMINI_API_KEY;
    try {
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ];

        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-goog-api-key' => $GEMINI_API_KEY
        ])->timeout(20)
          ->withBody(json_encode($payload), 'application/json')
          ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent");

        $json = $res->json();

        if(isset($json['candidates'][0]['content'])){
            $content = $json['candidates'][0]['content'];
            // If array of parts, get text
            if(is_array($content) && isset($content[0]['text'])){
                return $content[0]['text'];
            }
            return is_string($content) ? $content : json_encode($content);
        }

        return "No response from Gemini.";

    } catch (\Exception $e) {
        return "Exception contacting Gemini: ".$e->getMessage();
    }
}

function detectAction($question) {
    $q = strtolower($question);
    if(str_contains($q,'add device')){
        return ['add_device', trim(str_ireplace('add device','',$q))];
    } elseif(str_contains($q,'delete device') || str_contains($q,'remove device')) {
        $dev = str_ireplace(['delete device','remove device'],'',$q);
        return ['delete_device', trim($dev)];
    } else {
        return ['info',''];
    }
}

// ---------------- Process Request ----------------
if($_SERVER['REQUEST_METHOD']==='POST' && $token){
    $input = json_decode(file_get_contents('php://input'), true);
    $question = $input['question'] ?? '';
    $question = trim($question);

    if(!$question){
        echo json_encode(['answer'=>'Please type a question.']); exit;
    }

    [$action, $device_info] = detectAction($question);

    if($action==='add_device' && $device_info){
        $res = addDevice($device_info, $token);
        echo json_encode(['answer'=>$res]); exit;
    }
    if($action==='delete_device' && $device_info){
        $res = deleteDevice($device_info, $token);
        echo json_encode(['answer'=>$res]); exit;
    }

    // Info query → fetch devices & ports
    $devices = fetchTelequillData('devices', $token)['devices'] ?? [];
    $ports = fetchTelequillData('ports', $token)['ports'] ?? [];

    $device_lines = [];
    foreach($devices as $d){
        // Find all ports for this device
        $dev_ports = array_filter($ports, fn($p)=>($p['hostname']??'') === ($d['hostname']??''));
        $ports_text = $dev_ports ? implode("\n", array_map(fn($p)=>"  {$p['ifName']} - status: ".($p['status']??'Unknown'), $dev_ports)) : "  No ports found.";
        $device_lines[] = "{$d['hostname']} ({$d['ip']}) - status: ".($d['status']??'Unknown')."\nPorts:\n$ports_text";
    }
    $devices_text = implode("\n\n",$device_lines);

    $prompt = "Answer the user question: \"$question\" concisely.\n\nTelequill Device and Port Data:\n$devices_text";

    $llm_answer = queryGemini($prompt);
    echo json_encode(['answer'=>$llm_answer]);
    exit;
}
?>
