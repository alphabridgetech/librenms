<?php

namespace App\Api\Controllers;
use Illuminate\Http\Request;

class KunalApiController
{
    /**
     * Pass through api functions to api_functions.inc.php
     *
     * @param  string  $method_name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($method_name, $arguments)
    {
        $init_modules = ['web', 'alerts'];
        require base_path('/includes/init.php');
        require_once base_path('includes/html/api_functions.inc.php');
        return app()->call($method_name, $arguments);
        $this->base_path = base_path();
    }

    public function testFunction()
    {
        return "Test function called with param d";
    }

public function systeminfo($hostname)
{
    $venvPath = base_path('bin/activate');
    $playbook = base_path('librenms-ansible-inventory-plugin/atest1.yml');
    $hosts = base_path('librenms-ansible-inventory-plugin/ahosts.yml');

    $cmd = "source {$venvPath} && ansible-playbook -i {$hosts} {$playbook} 2>&1";
    $output = shell_exec($cmd);

    // Extract only the output.stdout field inside quotes (multiline safe)
    preg_match('/"output.stdout":\s*"([\s\S]*?)"\s*}/', $output, $match);

    if (empty($match[1])) {
        return response()->json([
            "status" => "error",
            "message" => "output.stdout not found",
            "raw_output" => $output
        ], 500);
    }

    // Clean raw output
    $raw = str_replace(["\r", '\r', '\n'], "", $match[1]);

    // Parse fields
    $info = [
        "device_type"   => $this->extract($raw, 'Welcome to ABTPL (.*?) Ethernet'),
        "bios_version"  => $this->extract($raw, 'Bootstrap, Version ([0-9\.]+)'),
        "firmware"      => $this->extract($raw, 'Software, Version (.*?), RELEASE'),
        "serial"        => $this->extract($raw, 'Serial num:(.*?),'),
        "mac"           => $this->extract($raw, 'Base ethernet MAC Address:\s*([0-9a-fA-F:]+)'),
        "current_time"  => $this->extract($raw, 'The current time:\s*([0-9\-:\s]+)'),
        "uptime"        => $this->extract($raw, 'uptime is (.*?),'),
    ];

    return response()->json([
        "status" => "success",
        "data"   => $info,
        "raw"    => $raw
    ]);
}

public function gethostname()
{
    $venvPath = base_path('bin/activate');
    $playbook = base_path('librenms-ansible-inventory-plugin/atest1.yml');
    $hosts = base_path('librenms-ansible-inventory-plugin/ahosts.yml');

    $cmd = "source {$venvPath} && ansible-playbook -i {$hosts} {$playbook} 2>&1";
    $output = shell_exec($cmd);

    // Extract only the output.stdout field inside quotes (multiline safe)
    preg_match('/"output.stdout":\s*"([\s\S]*?)"\s*}/', $output, $match);

    if (empty($match[1])) {
        return response()->json([
            "status" => "error",
            "message" => "output.stdout not found",
            "raw_output" => $output
        ], 500);
    }

    // Clean raw output
    $raw = str_replace(["\r", '\r', '\n'], "", $match[1]);

    // Parse hostname
    $hostname = $this->extract($raw, 'Hostname is (.*?),');

    return response()->json([
        "status" => "success",
        "hostname"   => $hostname,
        "raw"    => $raw
    ]);
}


private function extract($text, $pattern)
{
    if (preg_match('/'.$pattern.'/i', $text, $m)) {
        return trim($m[1]);
    }
    return "N/A";
}




}
