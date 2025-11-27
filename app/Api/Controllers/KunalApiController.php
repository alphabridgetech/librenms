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

    // print_r($output);
    // die();

    // Extract "output.stdout": "....."
    preg_match('/"output.stdout"\s*:\s*"([^"]*)"/s', $output, $match);

    if (!empty($match[1])) {
        $stdout = trim(str_replace("\r", "", $match[1]));
        return response()->json([
            "status" => "success",
            "stdout" => $stdout
        ]);
    }

    return response()->json([
        "status" => "error",
        "message" => "output.stdout not found",
        "raw" => $output
    ]);
}



}
