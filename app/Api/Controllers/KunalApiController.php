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
        $this->venv = base_path('bin/activate');
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

        $playbook = "{$this->pluginPath}/atest1.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";


        $output = $this->runAnsible($playbook, $hosts);

        preg_match('/"output.stdout":\s*"([\s\S]*?)"\s*}/', $output, $match);
        if (empty($match[1])) {
            return $this->error("output.stdout not found", $output);
        }

        $raw = preg_replace("/[\r\n]+/", "", $match[1]);

        $info = [
            "device_type"  => $this->extract($raw, 'Welcome to ABTPL (.*?) Ethernet'),
            "bios_version" => $this->extract($raw, 'Bootstrap, Version ([0-9\.]+)'),
            "firmware"     => $this->extract($raw, 'Software, Version (.*?), RELEASE'),
            "serial"       => $this->extract($raw, 'Serial num:(.*?),'),
            "mac"          => $this->extract($raw, 'Base ethernet MAC Address:\s*([0-9a-fA-F:]+)'),
            "current_time" => $this->extract($raw, 'The current time:\s*([0-9\-:\s]+)'),
            "uptime"       => $this->extract($raw, 'uptime is (.*?),'),
        ];

        return $this->success([
            "data" => $info,
            "raw"  => $raw
        ]);
    }

    #------------------------------------------------------------
    #                       GET HOSTNAME
    #------------------------------------------------------------
    public function gethostname($hostname)
{   
    $playbook = "{$this->pluginPath}/gethostname.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    $output = $this->runAnsible($playbook, $hosts);

    // Extract hostname from output
    preg_match('/Hostname:\s*([A-Za-z0-9\-_]+)/', $output, $match);

    if (empty($match[1])) {
        return $this->error("Hostname not found", $output);
    }

    return $this->success([
        "hostname" => $match[1],
        "raw"      => $output
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
    #                            Add Vlan
    #------------------------------------------------------------

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

    private function error(string $msg, string $raw)
    {
        return response()->json([
            "status" => "error",
            "message" => $msg,
            "raw_output" => $raw
        ], 500);
    }
}
