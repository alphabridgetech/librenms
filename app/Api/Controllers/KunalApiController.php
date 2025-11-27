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
   
    // Correct variable name
    $venvPath =base_path('bin/activate');

    // Correct paths
    $inventoryPlaybook = base_path('librenms-ansible-inventory-plugin/atest.yml');
    $hostsFile = base_path('librenms-ansible-inventory-plugin/ahosts.yml');

    // Build command
    $cmd = "source $venvPath && ansible-playbook -i $hostsFile $inventoryPlaybook";

    // Execute
    $output = shell_exec($cmd);

    // For debugging only:
    print_r($output);
    die;
    // Extract JSON from the Ansible output
    preg_match('/\"msg\":\s*\"({.*})\"/s', $output, $match);

    if (!empty($match[1])) {
        // Decode JSON
        $json = str_replace('\"', '"', $match[1]);
        $result = json_decode($json, true);
    } else {
        $result = ["error" => "Unable to parse device data", "raw" => $output];
    }

    return view("devices.info", ["data" => $result]);
}

}
