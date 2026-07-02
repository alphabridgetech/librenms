<?php

namespace App\Api\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;


class KunalApiController
{
    private string $venv;
    private string $pluginPath;
    private string $tftpPath;

    public function __construct()
    {
        $this->venv = base_path('librenms-ansible-inventory-plugin/bin/activate');
        $this->pluginPath = base_path('librenms-ansible-inventory-plugin');
        $this->tftpPath = '/tftpboot';
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

    private function runAnsibleJson(string $playbook, string $hosts, array $extraVars = []): string
    {
        $extraVarsString = "";

        if (!empty($extraVars)) {
            foreach ($extraVars as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $extraVarsString .= " --extra-vars '{$key}={$value}'";
            }
        }

        $cmd = "source {$this->venv} && ansible-playbook -i {$hosts} {$playbook}{$extraVarsString} 2>&1";
        return shell_exec($cmd);
    }

    private function runAnsiblejs(string $playbook, string $hosts, array $extraVars = []): string
    {
        $extraVarsString = "";

        if (!empty($extraVars)) {
            // ✅ Convert to JSON (BEST PRACTICE)
            $json = json_encode($extraVars);
            $extraVarsString = " --extra-vars '" . $json . "'";
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


    public function interfacereset(Request $request, $hostname)
    {
        $new = $request->validate([
            'interface' => 'required|string'
        ])['interface'];

        $playbook = "{$this->pluginPath}/playbooks/interfacereset.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts, [
            "interface" => $new
        ]);

        return $this->success([
            "message" => "Interface reset successfully",
            "raw"     => $output
        ]);
    }   

    #------------------------------------------------------------
    #                       CHANGE PORT STATUS
    #------------------------------------------------------------

    public function cngportstatus(Request $request, $hostname)
{
    
    $validated = $request->validate([
        'interface'  => 'required|string',
        'status' => 'required|string'
    ]);

    $playbook = "{$this->pluginPath}/playbooks/cngportstatus.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    $output = $this->runAnsible($playbook, $hosts, [
        "interface"  => $validated['interface'],
        "status" => $validated['status']
    ]);

    return $this->success([
        "message" => "Port status changed successfully",
        "raw"     => $output
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
    #                       GET MTU
    #------------------------------------------------------------
public function getmtu($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/getmtu.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
    // Run ansible
    $output = $this->runAnsible($playbook, $hosts);

    // Expected output file
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_getmtu.yml";

    if (!file_exists($yamlFile)) {
        return $this->error("MTU output file not found", $output);
    }

    $data = yaml_parse_file($yamlFile);

    if (empty($data['mtu'])) {
        return $this->error("MTU not found in YAML", $data);
    }

    return $this->success([
        "ip"   => $data['ip'] ?? $hostname,
        "mtu"  => $data['mtu'],
        "raw"  => $data
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

        $playbook = "{$this->pluginPath}/playbooks/changehostname.yml";
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
    #                     vlan configure
    #------------------------------------------------------------
    public function vlanconfigure(Request $request, $hostname)
    {
        $data = $request->validate([
            'vlan_id' => 'required|integer',
            'interface' => 'required|string',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/vlanconfigure.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts, [
            "vlan_id"   => $data['vlan_id'],
            "interface" => $data['interface'],
        ]);

        return $this->success([
            "message" => "VLAN configured successfully",
            "raw"     => $output
        ]);
    }

    #------------------------------------------------------------
    #                     vlan configure trunk
    #------------------------------------------------------------

    public function vlanconfiguretrunk(Request $request, $hostname)
    {
        $data = $request->validate([
            'vlan_ids' => 'required|string',
            'interface' => 'required|string',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/vlanconfiguretrunk.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts, [
            "vlan_ids"  => $data['vlan_ids'],
            "interface" => $data['interface'],
        ]);

        return $this->success([
            "message" => "Trunk VLANs configured successfully",
            "raw"     => $output
        ]);
    }

    #------------------------------------------------------------
    #                     NTP
    #------------------------------------------------------------
    public function ntp(Request $request, $hostname)
    {

    $playbook = "{$this->pluginPath}/playbooks/ntp.yml";
    $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

    $output = $this->runAnsible($playbook, $hosts);

    $yamlFile = "{$this->pluginPath}/output/{$hostname}_ntp.yml";
    if (!file_exists($yamlFile)) {
        return $this->error("NTP output file not found", $output);
    }
    $data = yaml_parse_file($yamlFile);
    if (empty($data['ntp'])) {
        return $this->error("NTP data not found in YAML", $data);
    }

    return $this->success([
        "ip"   => $data['ip'] ?? $hostname,
        "ntp" => $data['ntp'],
        "raw"  => $data
    ]);
    }


    #------------------------------------------------------------
    #                       NETWORK INTERFACE CONFIG (QinQ)
    #------------------------------------------------------------

    public function network_interface_config(Request $request, $hostname)
    {
        $data = $request->validate([
            'interfaces' => 'required|array|min:1',
        ]);

        $interfaces = $data['interfaces'];

        $configJson = json_encode([
            'hostname' => $hostname,
            'interfaces' => $interfaces
        ], JSON_UNESCAPED_SLASHES);

        $playbook = "{$this->pluginPath}/playbooks/network_interface_config.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $cmd = "source {$this->venv} && ansible-playbook -i {$hosts} {$playbook} --extra-vars 'config_json={$configJson}' 2>&1";
        $output = shell_exec($cmd);

        return $this->success([
            "message" => "Network interface(s) configured successfully",
            "configured" => count($interfaces),
            "raw" => $output
        ]);
    }

    #------------------------------------------------------------
    #                     NETWORK CMD CONFIG
    #------------------------------------------------------------
    public function network_cmd_config(Request $request, $hostname)
    {
        
        $data = $request->validate([
            'config' => 'required|min:1',
        ]);

        $config = $data['config'];
        //$cliCommandsJson = json_encode($config);
        // Convert string to array line by line
        $commands = preg_split('/\r\n|\r|\n/', $config);

        // Trim each line and remove empty ones
        $commands = array_values(array_filter(array_map('trim', $commands)));

        
        
        // print_r($commands); // Debug commands
        // die;
        
        $playbook = "{$this->pluginPath}/playbooks/network_cmd_config.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";


        // $cmd = "source {$this->venv} && ansible-playbook -i {$hosts} {$playbook}  --extra-vars 'cli_commands={$cliCommandsJson}' 2>&1";
        // $output = shell_exec($cmd);

        $output = $this->runAnsibleJson($playbook, $hosts, [
            "cli_commands" => $commands
        ]);

        return $this->success([
            "message" => "Network config executed successfully",
            "raw"     => $output
        ]);
    }

    #------------------------------------------------------------
    #                     NETWORK INTERFACE SHOW
    #------------------------------------------------------------
    public function network_interface_show(Request $request, $hostname)
    {
        $new = $request->validate([
            'interface' => 'required'
        ])['interface'];

        $playbook = "{$this->pluginPath}/playbooks/network_interface_show.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        
        $output = $this->runAnsiblejs($playbook, $hosts, [
            "interface" => $new
        ]);

       

        $yamlFile = "{$this->pluginPath}/output/{$hostname}_network_interface_show.yml";

        if (!file_exists($yamlFile)) {
            return $this->error("Interface show output file not found", $output);
        }

        $data = yaml_parse_file($yamlFile);

        // ✅ FIX: Check correct key
        if (empty($data['config'])) {
            return $this->error("Interface config not found in YAML", $data);
        }

        return $this->success([
            "ip" => $data['ip'] ?? $hostname,
            "interface" => $data['interface'] ?? null,
            "config" => explode("\n", $data['config']), // 👈 split lines
        ]);
    }

    #------------------------------------------------------------
    #                     CHANGE MTU
    #------------------------------------------------------------
    public function changemtu(Request $request, $hostname)
    {
        $new = $request->validate([
            'mtu' => 'required|integer|min:1518|max:9216'
        ])['mtu'];
        $playbook = "{$this->pluginPath}/playbooks/changemtu.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $output = $this->runAnsible($playbook, $hosts, [
            "new_mtu" => $new
        ]);
        return $this->success([
            "message" => "MTU changed successfully",
            "raw"     => $output
        ]);
    }

    

    #------------------------------------------------------------
    #                       GET LLDP
    #------------------------------------------------------------
    public function getlldp($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/getlldp.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
    // Run ansible
    $output = $this->runAnsible($playbook, $hosts);
    // Expected output file
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_getlldp.yml";
    if (!file_exists($yamlFile)) {
        return $this->error("LLDP output file not found", $output);
    }
    $data = yaml_parse_file($yamlFile);
    if (empty($data['lldp'])) {
        return $this->error("LLDP data not found in YAML", $data);
    }
    return $this->success([
        "ip"   => $data['ip'] ?? $hostname,
        "lldp" => $data['lldp'],
        "raw"  => $data
    ]);
}

    #------------------------------------------------------------
    #                   GET LLDP INTERFACE
    #------------------------------------------------------------
    public function getlldpinterface($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/getlldpinterface.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
    // Run ansible
    $output = $this->runAnsible($playbook, $hosts);
    // Expected output file
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_getlldpinterface.yml";
    if (!file_exists($yamlFile)) {
        return $this->error("LLDP Interface output file not found", $output);
    }
    $data = yaml_parse_file($yamlFile);
    if (empty($data['lldp_interfaces'])) {
        return $this->error("LLDP Interface data not found in YAML", $data);
    }
    return $this->success([
        "ip"              => $data['ip'] ?? $hostname,
        "lldp"=> $data['lldp'] ?? [],
        "lldp_interfaces" => $data['lldp_interfaces'],
        "raw"             => $data
    ]);
}

    #------------------------------------------------------------
    #                     CHANGE LLDP
    #------------------------------------------------------------
    public function changelldp(Request $request, $hostname)
    {
        $data = $request->validate([
            'protocol_state' => 'required|string|in:open,close',
            'holdtime' => 'nullable|integer|max:65535',
            'timer' => 'nullable|integer|min:5|max:65534',
            'reinit' => 'nullable|integer|min:2|max:5',
        ]);
        $playbook = "{$this->pluginPath}/playbooks/changelldp.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $output = $this->runAnsible($playbook, $hosts, [
            "protocol_state" => $data['protocol_state'],
            "holdtime" => $data['holdtime'] ?? '',
            "timer" => $data['timer'] ?? '',
            "reinit" => $data['reinit'] ?? '',
        ]);
        return $this->success([
            "message" => "LLDP configuration changed successfully",
            "raw"     => $output
        ]);
    }

    #------------------------------------------------------------
    #                            get vlan
    #------------------------------------------------------------

public function getvlan($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/getvlan.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    // Run ansible
    $ansibleOutput = $this->runAnsible($playbook, $hosts);

    // YAML output file
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_getvlan.yml";

    if (!file_exists($yamlFile)) {
        return $this->error(
            "VLAN output file not found",
            $ansibleOutput
        );
    }

    if (!function_exists('yaml_parse_file')) {
        return $this->error(
            "PHP YAML extension missing",
            null
        );
    }

    $data = yaml_parse_file($yamlFile);

    if (empty($data['vlans']) || !is_array($data['vlans'])) {
        return $this->error(
            "VLAN data invalid",
            json_encode($data)
        );
    }

    return $this->success([
        "ip"    => $data['ip'] ?? $hostname,
        "vlans" => $data['vlans']
    ]);
}




    #------------------------------------------------------------
    #                            Add Vlan interface
    #------------------------------------------------------------

    public function showvlaninterface($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/getinterface.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    // Run Ansible
    $ansibleOutput = $this->runAnsible($playbook, $hosts);

    // YAML output file
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_getinterface.yml";

    if (!file_exists($yamlFile)) {
        return $this->error(
            "Interface output file not found",
            $ansibleOutput
        );
    }

    $data = yaml_parse_file($yamlFile);

    if ($data === false || !is_array($data)) {
        return $this->error(
            "Failed to parse interface YAML",
            file_get_contents($yamlFile)
        );
    }

    if (empty($data['interfaces']) || !is_array($data['interfaces'])) {
        return $this->error(
            "Interface data invalid or empty",
            $data
        );
    }

    return $this->success([
        "ip"           => $data['ip'] ?? $hostname,
        "current_time" => $data['current_time'] ?? null,
        "interfaces"   => $data['interfaces'],
        "count"        => count($data['interfaces']),
        "raw"          => $data
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
    #                            tftp upload
    #------------------------------------------------------------


    //tftp 27-01-2026
    public function tftpupload(Request $request, $hostname)
    {
        $request->validate([
            'tftp_server' => 'required|string',
            'file'        => 'required|file',
            'filename'    => 'required|string',
        ]);

        $baseTftpPath = $this->tftpPath;          // e.g. /tftpboot
        

        // ✅ 2. Build final filename
        $destinationPath=$request->filename;
        $filename = $hostname . '_' . $request->filename;

        $ext = $request->file('file')->getClientOriginalExtension();
        // if ($ext) {
        //     $filename .= '.' . $ext;
        // }

        $request->file('file')->move($baseTftpPath, $filename);
        

        // Ansible
        $playbook = "{$this->pluginPath}/playbooks/tftpupload.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

       

        $output = $this->runAnsible($playbook, $hosts, [
            "tftp_server" => $request->tftp_server,
            "filename"    => $filename,
            "destination_file"  => $destinationPath,
        ]);

        return $this->success([
            "message"  => "Config saved under {$hostname} & TFTP ready",
            "filename" => $filename,
            "path"     => $destinationPath,
            "raw"      => $output
        ]);
    }

    public function tftpexport(Request $request, $hostname)
    {
        $request->validate([
            'tftp_server' => 'required|string',
            'filename'    => 'required|string',
        ]);
        
        $device = \App\Models\Device::where('hostname', $hostname)->first();
        $deviceId = $device ? $device->device_id : null;

        $playbook = "{$this->pluginPath}/playbooks/tftpexport.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $destination_file = $hostname . '_' . date('Y-m-d_His') . '_' . $request->filename;
        $exportPath = "{$this->tftpPath}/{$destination_file}";

        $output = $this->runAnsible($playbook, $hosts, [
            "tftp_server" => $request->tftp_server,
            "filename"    => $request->filename,
            "destination_file" => $destination_file,
        ]);
        
        if (!file_exists($exportPath)) {
            // Attempt to pull the file from the remote TFTP server to the local directory
            $tftpIp = $request->tftp_server;
            $downloadCmd = "tftp -g -r " . escapeshellarg($destination_file) . " -l " . escapeshellarg($exportPath) . " " . escapeshellarg($tftpIp);
            shell_exec($downloadCmd);
        }

        if (!file_exists($exportPath)) {
            if ($deviceId) {
                try {
                    \App\Models\ConfigBackupLog::create([
                        'device_id' => $deviceId,
                        'user_id' => \Auth::id(),
                        'filename' => $destination_file,
                        'tftp_server' => $request->tftp_server,
                        'status' => 'error',
                        'message' => 'TFTP export failed, file not found. Raw output: ' . $output,
                    ]);
                } catch (\Exception $e) {
                    \Log::warning("Could not log export error: " . $e->getMessage());
                }
            }
            return $this->error("Export failed, file not found");
        }

        if ($deviceId) {
            try {
                \App\Models\ConfigBackupLog::create([
                    'device_id' => $deviceId,
                    'user_id' => \Auth::id(),
                    'filename' => $destination_file,
                    'tftp_server' => $request->tftp_server,
                    'status' => 'success',
                    'message' => 'Startup-config exported successfully.',
                ]);
            } catch (\Exception $e) {
                \Log::warning("Could not log export success: " . $e->getMessage());
            }
        }

        return $this->success([
            "message"  => "TFTP export initiated",
            "filename" => $request->filename,
            "raw"      => $output,
            "download_url" => url("/tftp/download/{$destination_file}"),
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

    public function vlandelete(Request $request, $hostname)
{
    $data = $request->validate([
        'vlan_delete' => 'required'
    ]);

    $playbook = "{$this->pluginPath}/addvlanbatch.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    $extraVars = [
        'vlan_delete' => $data['vlan_delete']
    ];

    $output = $this->runAnsible($playbook, $hosts, $extraVars);

    return $this->success([
        "message" => "VLAN deleted successfully",
        "raw"     => $output
    ]);
}


    public function voicevlandelete(Request $request, $hostname)
    {
        // ✅ Validate arrays
        $data = $request->validate([
            'mac'  => 'required|array|min:1',
            'mask' => 'required|array|min:1',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/voicevlanbatch.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $outputs = [];

        // ✅ Loop and delete ONE BY ONE
        foreach ($data['mac'] as $index => $mac) {

            $mask = $data['mask'][$index] ?? null;
            if (!$mask) {
                continue; // safety
            }

            $extraVars = [
                'mac'  => $mac,
                'mask' => $mask,
            ];

            $outputs[] = [
                'mac'    => $mac,
                'result' => $this->runAnsible($playbook, $hosts, $extraVars),
            ];
        }

        return $this->success([
            "message" => "Voice VLAN deleted successfully",
            "results" => $outputs
        ]);
    }






    public function voicevlanshow(Request $request, $hostname)
    {
        $playbook = "{$this->pluginPath}/playbooks/voicevlanshow.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

        // Run Ansible
        $ansibleOutput = $this->runAnsible($playbook, $hosts);

        // YAML output file
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_voicevlanshow.yml";

        if (!file_exists($yamlFile)) {
            return $this->error(
                "voice vlan output file not found",
                $ansibleOutput
            );
        }

        $data = yaml_parse_file($yamlFile);

        if ($data === false || !is_array($data)) {
            return $this->error(
                "Failed to parse voice vlan YAML",
                file_get_contents($yamlFile)
            );
        }

        // ✅ Ensure mac_addresses is always an array
        $macAddresses = $data['mac_addresses'] ?? [];
        if (!is_array($macAddresses)) {
            $macAddresses = [];
        }

        return $this->success([
            "ip"            => $data['ip'] ?? $hostname,
            "mac_addresses" => $macAddresses,
            "raw"           => $data
        ]);
    }






    public function saveTftpSchedule(Request $request)
    {
        $request->validate([
            'backup_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'tftp_server_ip' => 'nullable|string',
        ]);

        $tftpServerIp = $request->tftp_server_ip ?: $request->getHost();

        \DB::table('config')->updateOrInsert(
            ['config_name' => 'backup_time'],
            [
                'config_value' => $request->backup_time,
            ]
        );

        \DB::table('config')->updateOrInsert(
            ['config_name' => 'tftp_server_ip'],
            [
                'config_value' => $tftpServerIp,
            ]
        );

        return $this->success([
            "message" => "Backup schedule updated successfully",
            "backup_time" => $request->backup_time,
            "tftp_server_ip" => $tftpServerIp,
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
