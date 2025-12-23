<?php

namespace App\Api\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;


class KunalApiController
{
    private string $venv;
    private string $pluginPath;

    public function __construct()
    {
        $this->venv = base_path('librenms-ansible-inventory-plugin/bin/activate');
        $this->pluginPath = base_path('librenms-ansible-inventory-plugin');
    }

    public function __call($method_name, $arguments)
    {
        require base_path('/includes/init.php');
        require_once base_path('includes/html/api_functions.inc.php');
        return app()->call($method_name, $arguments);
    }

    public function testFunction()
    {
        return "Test function called.";
    }

    #------------------------------------------------------------
    #               REUSABLE ANSIBLE EXECUTION WRAPPER
    #------------------------------------------------------------
    private function runAnsible(string $playbook, string $hosts, array $extraVars = []): string
    {
        $extraVarsString = "";

        if (!empty($extraVars)) {
            foreach ($extraVars as $key => $value) {
                $extraVarsString .= " --extra-vars \"{$key}={$value}\"";
            }
        }

        $cmd = "source {$this->venv} && ansible-playbook -i {$hosts} {$playbook}{$extraVarsString} 2>&1";
        return shell_exec($cmd);
    }

    #------------------------------------------------------------
    #                       SYSTEM INFO
    #------------------------------------------------------------
    public function systeminfo($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/devicedetails.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    // Run Ansible
    $ansibleOutput = $this->runAnsible($playbook, $hosts);

    // Correct YAML file
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_devicedetails.yml";

    if (!file_exists($yamlFile)) {
        return $this->error(
            "System info output file not found",
            $ansibleOutput
        );
    }

    // YAML extension check (Alpine issue safe)
    if (!function_exists('yaml_parse_file')) {
        return $this->error(
            "PHP YAML extension missing (php-yaml not installed)",
            null
        );
    }

    $data = yaml_parse_file($yamlFile);

    if (empty($data['show_version'])) {
        return $this->error(
            "show_version not found in YAML",
            json_encode($data)
        );
    }

    $raw = trim($data['show_version']);

    // ---------- Extract system info ----------
    $info = [
    "device_type" => $this->extract(
        $raw,
        '([A-Z0-9\/]+)\s+Software, Version'
    ),

    "bios_version" => $this->extract(
        $raw,
        'Bootstrap, Version ([0-9\.]+)'
    ),

    "firmware" => $this->extract(
        $raw,
        'Software, Version ([^,]+), RELEASE'
    ),

    "serial" => $this->extract(
        $raw,
        'Serial num:([^,]+)'
    ),

    "mac" => $this->extract(
        $raw,
        'Base ethernet MAC Address:\s*([0-9a-fA-F:]+)'
    ),

    
    "current_time" => $this->extract(
    $raw,
        'The current time:\s*([0-9\-: ]+)'
    ),


    "uptime" => $this->extract(
        $raw,
        'uptime is ([^,]+)'
    ),

    "model" => $this->extract(
        $raw,
        'ABTPL\s+([A-Z0-9\/\-]+)'
    ),
];


    return $this->success([
        "ip"   => $data['ip'] ?? $hostname,
        "data" => $info,
        "raw"  => $raw
    ]);
}


    #------------------------------------------------------------
    #                       GET HOSTNAME
    #------------------------------------------------------------


public function gethostname($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/gethostname.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    // Run ansible
    $output = $this->runAnsible($playbook, $hosts);

    // Expected output file
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_gethostname.yml";
    
    if (!file_exists($yamlFile)) {
        return $this->error("Hostname output file not found", $output);
    }
    
    

    $data = yaml_parse_file($yamlFile);

    if (empty($data['hostname'])) {
        return $this->error("Hostname not found in YAML", $data);
    }

    return $this->success([
        "ip"       => $data['ip'] ?? $hostname,
        "hostname" => $data['hostname'],
        "raw"      => $data
    ]);
}


    #------------------------------------------------------------
    #                       DEVICE REBOOT
    #------------------------------------------------------------

    public function devicereboot(Request $request, $hostname)
    {
        $playbook = "{$this->pluginPath}/rebootdevice.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts);

        return $this->success([
            "message" => "Device reboot initiated successfully",
            "raw"     => $output
        ]);
    }

    #------------------------------------------------------------
    #                     CHANGE HOSTNAME
    #------------------------------------------------------------
    public function changehostname(Request $request, $hostname)
    {
        $new = $request->validate([
            'hostname' => 'required|string'
        ])['hostname'];

        $playbook = "{$this->pluginPath}/sethostname.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts, [
            "new_hostname" => $new
        ]);

        return $this->success([
            "message" => "Hostname changed successfully",
            "raw"     => $output
        ]);
    }

    #------------------------------------------------------------
    #                            get vlan
    #------------------------------------------------------------

    public function getvlan($hostname)
{
    $playbook = "{$this->pluginPath}/getvlan.yml";
    $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

    $output = $this->runAnsible($playbook, $hosts);

    /**
     * STEP 1 — Extract "msg" content
     *
     * This captures everything between "msg": " ... "
     */
    preg_match('/"msg":\s*"((?:\\\\.|[^"\\\\])*)"/s', $output, $match);

    if (empty($match[1])) {
        return $this->error("VLAN JSON not found", $output);
    }

    $escapedJson = $match[1]; // Contains: [{\"id\":\"1\" ... }]

    /**
     * STEP 2 — Remove escape slashes
     */
    $cleanJson = stripcslashes($escapedJson);

    /**
     * STEP 3 — Decode JSON
     */
    $vlanList = json_decode($cleanJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->error("JSON decode failed", [
            "error" => json_last_error_msg(),
            "raw" => $cleanJson
        ]);
    }

    return $this->success([
        "vlans" => $vlanList,
        "count" => count($vlanList),
        "raw"   => $cleanJson
    ]);
}


    #------------------------------------------------------------
    #                            Add Vlan interface
    #------------------------------------------------------------

    public function showvlaninterface($hostname)
    {
        
        $playbook = "{$this->pluginPath}/showvlaninterface.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts);

        return $this->success([
            "message" => "VLAN interface details retrieved successfully",
            "raw"     => $output
        ]);
    }

    public function addvlan(Request $request, $hostname)
    {
        $data = $request->validate([
            'vlan_id' => 'required|integer',
            'vlan_name' => 'required|string',
        ]);

        $playbook = "{$this->pluginPath}/addvlan.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts, [
            "vlan_id"   => $data['vlan_id'],
            "vlan_name" => $data['vlan_name'],
        ]);

        return $this->success([
            "message" => "VLAN added successfully",
            "raw"     => $output
        ]);
    }

    #------------------------------------------------------------
    #                            Add Vlan BATCH
    #------------------------------------------------------------

    public function addvlanbatch(Request $request, $hostname)
    {
        $data = $request->validate([
            'vlan_add' => 'nullable|string',
            'vlan_delete' => 'nullable|string',
        ]);

        $playbook = "{$this->pluginPath}/addvlanbatch.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $extraVars = [];
        if (!empty($data['vlan_add'])) {
            $extraVars['vlan_add'] = $data['vlan_add'];
        }
        if (!empty($data['vlan_delete'])) {
            $extraVars['vlan_delete'] = $data['vlan_delete'];
        }

        $output = $this->runAnsible($playbook, $hosts, $extraVars);

        return $this->success([
            "message" => "VLAN batch operation completed successfully",
            "raw"     => $output
        ]);
    }





    #------------------------------------------------------------
    #                            UTIL
    #------------------------------------------------------------
    private function extract($text, $pattern)
    {
        return preg_match('/'.$pattern.'/i', $text, $m)
            ? trim($m[1])
            : "N/A";
    }

    private function success(array $data)
    {
        return response()->json(["status" => "success"] + $data);
    }

    public function error(string $message, $raw = null)
    {
        if (is_array($raw) || is_object($raw)) {
            $raw = json_encode($raw, JSON_PRETTY_PRINT);
        }

        return response()->json([
            "status" => "error",
            "message" => $message,
            "raw_output" => $raw
        ], 400);
    }

}
