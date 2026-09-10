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
    
    $playbook = "{$this->pluginPath}/playbooks/device/devicedetails.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/interface/interfacereset.yml";
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

    $playbook = "{$this->pluginPath}/playbooks/interface/cngportstatus.yml";
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
    $playbook = "{$this->pluginPath}/playbooks/hostname/gethostname.yml";
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
    $playbook = "{$this->pluginPath}/playbooks/mtu/getmtu.yml";
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
        $playbook = "{$this->pluginPath}/playbooks/device/rebootdevice.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/hostname/changehostname.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/vlan/vlanconfigure.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/vlan/vlanconfiguretrunk.yml";
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

    $playbook = "{$this->pluginPath}/playbooks/ntp/ntp.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/interface/network_interface_config.yml";
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
        
        $playbook = "{$this->pluginPath}/playbooks/push/network_cmd_config.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/interface/network_interface_show.yml";
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
        $playbook = "{$this->pluginPath}/playbooks/mtu/changemtu.yml";
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
    $playbook = "{$this->pluginPath}/playbooks/lldp/getlldp.yml";
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
    $playbook = "{$this->pluginPath}/playbooks/lldp/getlldpinterface.yml";
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
        $playbook = "{$this->pluginPath}/playbooks/lldp/changelldp.yml";
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
    $playbook = "{$this->pluginPath}/playbooks/vlan/getvlan.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_getvlan.yml";

    if (!function_exists('yaml_parse_file')) {
        return $this->error(
            "PHP YAML extension missing",
            null
        );
    }

    // If we already have a result from a previous run, serve it immediately
    // and kick off a fresh SSH fetch in the background for next time -
    // avoids making every page load wait on a live SSH round-trip.
    if (file_exists($yamlFile)) {
        $cached = yaml_parse_file($yamlFile);
        $vlans = is_array($cached['vlans'] ?? null) ? $cached['vlans'] : [];

        $this->runAnsibleAsync($playbook, $hosts);

        return $this->success([
            "ip"     => $cached['ip'] ?? $hostname,
            "vlans"  => $vlans,
            "cached" => true,
        ]);
    }

    // No cached result yet (first load) - fetch synchronously.
    $ansibleOutput = $this->runAnsible($playbook, $hosts);

    if (!file_exists($yamlFile)) {
        return $this->error(
            "VLAN output file not found",
            $ansibleOutput
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
        "ip"     => $data['ip'] ?? $hostname,
        "vlans"  => $data['vlans'],
        "cached" => false,
    ]);
}




    #------------------------------------------------------------
    #                            Add Vlan interface
    #------------------------------------------------------------

    public function showvlaninterface($hostname)
{
    $playbook = "{$this->pluginPath}/playbooks/interface/getinterface.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
    $yamlFile = "{$this->pluginPath}/output/{$hostname}_getinterface.yml";

    // If we already have a result from a previous run, serve it immediately
    // and kick off a fresh SSH fetch in the background for next time -
    // avoids making every page load wait on a live SSH round-trip.
    if (file_exists($yamlFile)) {
        $cached = yaml_parse_file($yamlFile);
        $interfaces = is_array($cached['interfaces'] ?? null) ? $cached['interfaces'] : [];

        $this->runAnsibleAsync($playbook, $hosts);

        return $this->success([
            "ip"           => $cached['ip'] ?? $hostname,
            "current_time" => $cached['current_time'] ?? null,
            "interfaces"   => $interfaces,
            "count"        => count($interfaces),
            "raw"          => $cached,
            "cached"       => true,
        ]);
    }

    // No cached result yet (first load) - fetch synchronously.
    $ansibleOutput = $this->runAnsible($playbook, $hosts);

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
        "raw"          => $data,
        "cached"       => false,
    ]);
}


    public function addvlan(Request $request, $hostname)
    {
        $data = $request->validate([
            'vlan_id' => 'required|integer',
            'vlan_name' => 'required|string',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/vlan/addvlan.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $output = $this->runAnsible($playbook, $hosts, [
            "vlan_id"   => $data['vlan_id'],
            "vlan_name" => $data['vlan_name'],
        ]);

        // Refresh the getvlan cache synchronously so the table the frontend
        // reloads right after this call already reflects the new VLAN,
        // instead of showing stale data until the next background refresh
        // (see getvlan()'s stale-while-revalidate caching).
        $this->runAnsible("{$this->pluginPath}/playbooks/vlan/getvlan.yml", $hosts);

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
        $playbook = "{$this->pluginPath}/playbooks/tftp/tftpupload.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/tftp/tftpexport.yml";
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

        $playbook = "{$this->pluginPath}/playbooks/vlan/addvlanbatch.yml";
        $hosts = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $extraVars = [];
        if (!empty($data['vlan_add'])) {
            $extraVars['vlan_add'] = $data['vlan_add'];
        }
        if (!empty($data['vlan_delete'])) {
            $extraVars['vlan_delete'] = $data['vlan_delete'];
        }

        $output = $this->runAnsible($playbook, $hosts, $extraVars);

        // See addvlan() - refresh the getvlan cache synchronously so the
        // table reflects add/delete results as soon as the frontend reloads.
        $this->runAnsible("{$this->pluginPath}/playbooks/vlan/getvlan.yml", $hosts);

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

    $playbook = "{$this->pluginPath}/playbooks/vlan/addvlanbatch.yml";
    $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

    $extraVars = [
        'vlan_delete' => $data['vlan_delete']
    ];

    $output = $this->runAnsible($playbook, $hosts, $extraVars);

    // See addvlan() - refresh the getvlan cache synchronously so a following
    // table reload reflects the deletion immediately.
    $this->runAnsible("{$this->pluginPath}/playbooks/vlan/getvlan.yml", $hosts);

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

        $playbook = "{$this->pluginPath}/playbooks/voicevlan/voicevlanbatch.yml";
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
        $playbook = "{$this->pluginPath}/playbooks/voicevlan/voicevlanshow.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_voicevlanshow.yml";

        // If we already have a result from a previous run, serve it immediately
        // and kick off a fresh SSH fetch in the background for next time -
        // avoids making every page load wait on a live SSH round-trip.
        if (file_exists($yamlFile)) {
            $cached = yaml_parse_file($yamlFile);
            $macAddresses = is_array($cached['mac_addresses'] ?? null) ? $cached['mac_addresses'] : [];

            $this->runAnsibleAsync($playbook, $hosts);

            return $this->success([
                "ip"            => $cached['ip'] ?? $hostname,
                "mac_addresses" => $macAddresses,
                "raw"           => $cached,
                "cached"        => true,
            ]);
        }

        // No cached result yet (first load) - fetch synchronously.
        $ansibleOutput = $this->runAnsible($playbook, $hosts);

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
            "raw"           => $data,
            "cached"        => false,
        ]);
    }

    #------------------------------------------------------------
    #                    SHOW STATIC ARP TABLE
    #------------------------------------------------------------
    public function showbasicarp($hostname)
    {
        $playbook = "{$this->pluginPath}/playbooks/arp/show_basic_arp.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_show_basic_arp.yml";

        // If we already have a result from a previous run, serve it immediately
        // and kick off a fresh SSH fetch in the background for next time -
        // avoids making every page load wait on a live SSH round-trip.
        if (file_exists($yamlFile)) {
            $cached = yaml_parse_file($yamlFile);
            $entries = $cached['arp_entries'] ?? [];
            if (!is_array($entries)) {
                $entries = [];
            }

            $this->runAnsibleAsync($playbook, $hosts);

            return $this->success([
                "ip"           => $cached['ip'] ?? $hostname,
                "arp_entries"  => $entries,
                "cached"       => true,
            ]);
        }

        // No cached result yet (first load) - fetch synchronously.
        $ansibleOutput = $this->runAnsible($playbook, $hosts);

        if (!file_exists($yamlFile)) {
            return $this->error("ARP show output file not found", $ansibleOutput);
        }

        $data = yaml_parse_file($yamlFile);
        if (($data['status'] ?? null) !== 'success') {
            return $this->error($data['error'] ?? 'Failed to read ARP table', $data);
        }

        $entries = $data['arp_entries'] ?? [];
        if (!is_array($entries)) {
            $entries = [];
        }

        return $this->success([
            "ip"           => $data['ip'] ?? $hostname,
            "arp_entries"  => $entries,
            "cached"       => false,
        ]);
    }

    /**
     * Fire the given playbook in the background and return immediately,
     * without waiting for it to finish. Used to silently refresh a cached
     * output file after already serving its previous contents to the user.
     */
    private function runAnsibleAsync(string $playbook, string $hosts): void
    {
        $cmd = "source {$this->venv} && ansible-playbook -i {$hosts} {$playbook} > /dev/null 2>&1 &";
        shell_exec($cmd);
    }

    #------------------------------------------------------------
    #                    ADD STATIC ARP ENTRY
    #------------------------------------------------------------
    public function addbasicarp(Request $request, $hostname)
    {
        $data = $request->validate([
            'ip_address' => 'required|ipv4',
            'mac_address' => ['required', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'interface_vlan' => 'required|integer|min:1|max:4094',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/arp/add_basic_arp.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $ansibleOutput = $this->runAnsible($playbook, $hosts, [
            'ip_address' => $data['ip_address'],
            'mac_address' => $data['mac_address'],
            'interface_vlan' => $data['interface_vlan'],
        ]);

        $yamlFile = "{$this->pluginPath}/output/{$hostname}_add_basic_arp.yml";
        if (!file_exists($yamlFile)) {
            return $this->error("ARP add output file not found", $ansibleOutput);
        }

        $result = yaml_parse_file($yamlFile);
        if (($result['status'] ?? null) !== 'success') {
            return $this->error($result['error'] ?? 'Failed to add ARP entry', $result);
        }

        return $this->success([
            "message" => "Static ARP entry {$data['ip_address']} -> {$data['mac_address']} added on VLAN {$data['interface_vlan']}",
            "raw"     => $result,
        ]);
    }

    #------------------------------------------------------------
    #                    EDIT STATIC ARP ENTRY
    #------------------------------------------------------------
    public function editbasicarp(Request $request, $hostname)
    {
        $data = $request->validate([
            'ip_address' => 'required|ipv4',
            'mac_address' => ['required', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'interface_vlan' => 'required|integer|min:1|max:4094',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/arp/edit_basic_arp.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $ansibleOutput = $this->runAnsible($playbook, $hosts, [
            'ip_address' => $data['ip_address'],
            'mac_address' => $data['mac_address'],
            'interface_vlan' => $data['interface_vlan'],
        ]);

        $yamlFile = "{$this->pluginPath}/output/{$hostname}_edit_basic_arp.yml";
        if (!file_exists($yamlFile)) {
            return $this->error("ARP edit output file not found", $ansibleOutput);
        }

        $result = yaml_parse_file($yamlFile);
        if (($result['status'] ?? null) !== 'success') {
            return $this->error($result['error'] ?? 'Failed to edit ARP entry', $result);
        }

        return $this->success([
            "message" => "Static ARP entry for {$data['ip_address']} updated to {$data['mac_address']} on VLAN {$data['interface_vlan']}",
            "raw"     => $result,
        ]);
    }

    #------------------------------------------------------------
    #                    DELETE STATIC ARP ENTRY
    #------------------------------------------------------------
    public function deletebasicarp(Request $request, $hostname)
    {
        $data = $request->validate([
            'ip_address' => 'required|ipv4',
            'interface_vlan' => 'required|integer|min:1|max:4094',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/arp/delete_basic_arp.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $ansibleOutput = $this->runAnsible($playbook, $hosts, [
            'ip_address' => $data['ip_address'],
            'interface_vlan' => $data['interface_vlan'],
        ]);

        $yamlFile = "{$this->pluginPath}/output/{$hostname}_delete_basic_arp.yml";
        if (!file_exists($yamlFile)) {
            return $this->error("ARP delete output file not found", $ansibleOutput);
        }

        $result = yaml_parse_file($yamlFile);
        if (($result['status'] ?? null) !== 'success') {
            return $this->error($result['error'] ?? 'Failed to delete ARP entry', $result);
        }

        return $this->success([
            "message" => "Static ARP entry {$data['ip_address']} removed from VLAN {$data['interface_vlan']}",
            "raw"     => $result,
        ]);
    }

    #------------------------------------------------------------
    #          BACKUPLINK PROTOCOL GLOBAL CONFIGURATION - SHOW
    #------------------------------------------------------------
    public function getbackuplink($hostname)
    {
        $playbook = "{$this->pluginPath}/playbooks/backup_link_configuration/getbackuplink.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_backuplink.yml";

        // If we already have a result from a previous run, serve it immediately
        // and kick off a fresh SSH fetch in the background for next time.
        if (file_exists($yamlFile)) {
            $cached = yaml_parse_file($yamlFile);
            $groups = is_array($cached['backuplink_global_configuration'] ?? null) ? $cached['backuplink_global_configuration'] : [];

            $this->runAnsibleAsync($playbook, $hosts);

            return $this->success([
                "ip"     => $cached['ip'] ?? $hostname,
                "groups" => $groups,
                "cached" => true,
            ]);
        }

        // No cached result yet (first load) - fetch synchronously.
        $ansibleOutput = $this->runAnsible($playbook, $hosts);

        if (!file_exists($yamlFile)) {
            return $this->error("BackupLink output file not found", $ansibleOutput);
        }

        $data = yaml_parse_file($yamlFile);
        $groups = is_array($data['backuplink_global_configuration'] ?? null) ? $data['backuplink_global_configuration'] : [];

        return $this->success([
            "ip"     => $data['ip'] ?? $hostname,
            "groups" => $groups,
            "cached" => false,
        ]);
    }

    #------------------------------------------------------------
    #          BACKUPLINK PROTOCOL GLOBAL CONFIGURATION - ADD/DELETE
    #------------------------------------------------------------
    public function setbackuplink(Request $request, $hostname)
    {
        $data = $request->validate([
            'operation'         => 'required|in:add,delete',
            'group_id'          => 'required|integer|min:1|max:8',
            'preemption_mode'   => 'required_if:operation,add|in:none,forced,bandwidth',
            'preemption_delay'  => 'nullable|integer|min:0',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/backup_link_configuration/setbackuplink.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";

        $extraVars = [
            'operation' => $data['operation'],
            'group_id'  => $data['group_id'],
        ];

        if ($data['operation'] === 'add') {
            $extraVars['preemption_mode'] = $data['preemption_mode'];
            $extraVars['preemption_delay'] = $data['preemption_delay'] ?? 0;
        }

        $ansibleOutput = $this->runAnsible($playbook, $hosts, $extraVars);

        if (!str_contains($ansibleOutput, 'Status: Success')) {
            return $this->error('BackupLink configuration failed', $ansibleOutput);
        }

        return $this->success([
            "message" => $data['operation'] === 'add'
                ? "BackupLink group {$data['group_id']} configured"
                : "BackupLink group {$data['group_id']} deleted",
            "raw" => $ansibleOutput,
        ]);
    }

    #------------------------------------------------------------
    #          PORT CHANNEL (AGGREGATE GROUP) - SHOW
    #------------------------------------------------------------
    public function getportaggregate($hostname)
    {
        $playbook = "{$this->pluginPath}/playbooks/port_channel/port_aggregate_details.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_port_aggregate_details.yml";

        // If we already have a result from a previous run, serve it immediately
        // and kick off a fresh SSH fetch in the background for next time.
        if (file_exists($yamlFile)) {
            $cached = yaml_parse_file($yamlFile);
            $groups = is_array($cached['groups'] ?? null) ? $cached['groups'] : [];

            $this->runAnsibleAsync($playbook, $hosts);

            return $this->success([
                "ip"     => $cached['ip'] ?? $hostname,
                "groups" => $groups,
                "cached" => true,
            ]);
        }

        // No cached result yet (first load) - fetch synchronously.
        $ansibleOutput = $this->runAnsible($playbook, $hosts);

        if (!file_exists($yamlFile)) {
            return $this->error("Port aggregate output file not found", $ansibleOutput);
        }

        $data = yaml_parse_file($yamlFile);
        if (($data['status'] ?? null) !== 'success') {
            return $this->error($data['error'] ?? 'Failed to read port aggregate details', $data);
        }

        $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];

        return $this->success([
            "ip"     => $data['ip'] ?? $hostname,
            "groups" => $groups,
            "cached" => false,
        ]);
    }

    #------------------------------------------------------------
    #          PORT CHANNEL (AGGREGATE GROUP) - ADD/CONFIGURE
    #------------------------------------------------------------
    public function addportaggregate(Request $request, $hostname)
    {
        $data = $request->validate([
            'aggregate_group' => 'required|in:P1,P2,P3,P4,P5,P6,P7,P8',
            'mode'            => 'required|in:static,lacp active,lacp passive',
            'ports'           => 'required|string',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/port_channel/port_aggregate_config.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_port_aggregate_config.yml";

        // mode may contain a space ("lacp active"/"lacp passive") - use the
        // JSON extra-vars helper so ansible doesn't mis-split it on whitespace.
        $ansibleOutput = $this->runAnsiblejs($playbook, $hosts, [
            'aggregate_group' => $data['aggregate_group'],
            'mode'            => $data['mode'],
            'ports'           => $data['ports'],
        ]);

        if (!file_exists($yamlFile)) {
            return $this->error("Port aggregate config output file not found", $ansibleOutput);
        }

        $result = yaml_parse_file($yamlFile);
        if (($result['status'] ?? null) !== 'success') {
            return $this->error($result['error'] ?? 'Failed to configure port aggregate group', $result);
        }

        return $this->success([
            "message" => "Aggregate group {$data['aggregate_group']} configured ({$data['mode']}) with ports {$data['ports']}",
            "raw"     => $result,
        ]);
    }

    #------------------------------------------------------------
    #          PORT CHANNEL (AGGREGATE GROUP) - EDIT
    #------------------------------------------------------------
    public function editportaggregate(Request $request, $hostname)
    {
        $data = $request->validate([
            'aggregate_group' => 'required|in:p1,p2,p3,p4,p5,p6,p7,p8',
            'mode'            => 'required|in:static,lacp active,lacp passive',
            'add_ports'       => 'nullable|string',
            'remove_ports'    => 'nullable|string',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/port_channel/port_aggregate_edit.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_port_aggregation_edit.yml";

        $extraVars = [
            'aggregate_group' => $data['aggregate_group'],
            'mode'            => $data['mode'],
        ];
        if (!empty($data['add_ports'])) {
            $extraVars['add_ports'] = $data['add_ports'];
        }
        if (!empty($data['remove_ports'])) {
            $extraVars['remove_ports'] = $data['remove_ports'];
        }

        $ansibleOutput = $this->runAnsiblejs($playbook, $hosts, $extraVars);

        if (!file_exists($yamlFile)) {
            return $this->error("Port aggregate edit output file not found", $ansibleOutput);
        }

        $result = yaml_parse_file($yamlFile);
        if (($result['status'] ?? null) !== 'success') {
            return $this->error($result['error'] ?? 'Failed to edit port aggregate group', $result);
        }

        return $this->success([
            "message" => "Aggregate group {$data['aggregate_group']} updated",
            "raw"     => $result,
        ]);
    }

    #------------------------------------------------------------
    #          PORT CHANNEL (AGGREGATE GROUP) - DELETE
    #------------------------------------------------------------
    public function deleteportaggregate(Request $request, $hostname)
    {
        $data = $request->validate([
            'port_id' => 'required|in:p1,p2,p3,p4,p5,p6,p7,p8',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/port_channel/port_aggregate_delete.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_port_aggregation_config_delete.yml";

        $ansibleOutput = $this->runAnsible($playbook, $hosts, [
            'port_id' => $data['port_id'],
        ]);

        if (!file_exists($yamlFile)) {
            return $this->error("Port aggregate delete output file not found", $ansibleOutput);
        }

        $result = yaml_parse_file($yamlFile);
        if (($result['status'] ?? null) !== 'success') {
            return $this->error($result['error'] ?? 'Failed to delete port channel interface', $result);
        }

        return $this->success([
            "message" => "Port channel interface {$data['port_id']} deleted",
            "raw"     => $result,
        ]);
    }

    #------------------------------------------------------------
    #          PORT CHANNEL GROUP LOAD BALANCING - SET
    #------------------------------------------------------------
    public function setportchannelloadbalance(Request $request, $hostname)
    {
        $data = $request->validate([
            'port' => 'required|in:p1,p2,p3,p4,p5,p6,p7,p8',
            'mode' => 'required|in:SRC MAC,DST MAC,BOTH MAC,SRC IP,DST IP,BOTH IP',
        ]);

        $playbook = "{$this->pluginPath}/playbooks/port_channel/port_channel_load_balancing.yml";
        $hosts    = "{$this->pluginPath}/hosts/{$hostname}.yml";
        $yamlFile = "{$this->pluginPath}/output/{$hostname}_port_channel_load_balancing.yml";

        // mode contains a space ("SRC MAC", etc.) - use the JSON extra-vars
        // helper so ansible doesn't mis-split it on whitespace.
        $ansibleOutput = $this->runAnsiblejs($playbook, $hosts, [
            'port' => $data['port'],
            'mode' => $data['mode'],
        ]);

        if (!file_exists($yamlFile)) {
            return $this->error("Port channel load balancing output file not found", $ansibleOutput);
        }

        $result = yaml_parse_file($yamlFile);
        if (($result['status'] ?? null) !== 'success') {
            return $this->error($result['error'] ?? 'Failed to configure load balancing', $result);
        }

        return $this->success([
            "message" => "Load balancing mode \"{$data['mode']}\" applied to {$data['port']}",
            "raw"     => $result,
        ]);
    }





    public function saveTftpSchedule(Request $request)
    {
        $request->validate([
            'backup_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'tftp_server_ip' => 'nullable|string',
            'backup_retention_days' => 'required|integer|min:1',
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

        \DB::table('config')->updateOrInsert(
            ['config_name' => 'backup_retention_days'],
            [
                'config_value' => $request->backup_retention_days,
            ]
        );

        return $this->success([
            "message" => "Backup schedule updated successfully",
            "backup_time" => $request->backup_time,
            "tftp_server_ip" => $tftpServerIp,
            "backup_retention_days" => $request->backup_retention_days,
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
