<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Device;
use Symfony\Component\Yaml\Yaml;

trait HandlesPushConfiguration
{
    protected $venv;
    protected $pluginPath;

    protected function initAnsible()
    {
        $this->venv = base_path('librenms-ansible-inventory-plugin/bin/activate');
        $this->pluginPath = base_path('librenms-ansible-inventory-plugin');
    }

    protected function runAnsible(string $playbook, string $hosts, array $extraVars = []): array
    {
        $this->initAnsible();
        $extraVarsString = "";

        if (!empty($extraVars)) {
            $json = json_encode($extraVars);
            $extraVarsString = " --extra-vars '" . $json . "'";
        }

        $cmd = "source {$this->venv} && ansible-playbook -i {$hosts} {$playbook}{$extraVarsString} 2>&1";
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        return [
            'output' => implode("\n", $output),
            'exit_code' => $returnCode
        ];
    }

    protected function generateInventoryYaml($hostname, $ip, $user, $pass, $community)
    {
        $inventory = [
            'alphabridge_devices' => [
                'hosts' => [
                    $hostname => [
                        'ansible_host' => $ip,
                        'ansible_user' => $user,
                        'ansible_password' => $pass,
                        'snmp_community' => $community
                    ]
                ]
            ]
        ];
        return Yaml::dump($inventory);
    }

    protected function processPush(Request $request)
    {
        $this->initAnsible();
        $validIPs = [];
        if ($request->filled('valid_ips')) {
            $validIPs = json_decode($request->valid_ips, true);
        } else {
            $lines = explode("\n", $request->hostname);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && !str_starts_with($line, '#') && filter_var($line, FILTER_VALIDATE_IP)) {
                    $validIPs[] = $line;
                }
            }
        }

        if (empty($validIPs)) {
            return response()->json(['success' => false, 'message' => 'No valid IP addresses found'], 400);
        }

        $commands = [];
        $selectedInterfaces = $request->input('selected_interfaces', []);
        
        if ($request->filled('direct_commands')) {
            $commands = explode("\n", trim($request->direct_commands));
            $commands = array_filter(array_map('trim', $commands), function ($cmd) {
                return !empty($cmd) && !str_starts_with($cmd, '#');
            });
            $commands = array_values($commands);
        } elseif ($request->has('use_template_commands')) {
            if ($request->filled('loaded_template_name')) {
                $templateFile = 'templates/' . Str::slug($request->input('template_folder', 'general')) . '/' . Str::slug($request->loaded_template_name) . '.json';
                if (Storage::disk('local')->exists($templateFile)) {
                    $content = Storage::disk('local')->get($templateFile);
                    $data = json_decode($content, true);
                    $commands = $data['commands'] ?? [];
                    if (empty($selectedInterfaces) && isset($data['interfaces'])) {
                        $selectedInterfaces = $data['interfaces'];
                    }
                }
            } elseif ($request->filled('loaded_filename')) {
                $filePath = $request->loaded_filename;
                if (Storage::disk('local')->exists($filePath)) {
                    $fileContent = file(storage_path('app/' . $filePath), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($fileContent as $line) {
                        $line = trim($line);
                        if ($line === '' || str_starts_with($line, '#')) continue;
                        $commands[] = $line;
                    }
                }
            }
        }

        if (empty($commands) && $request->hasFile('config_file')) {
            $configFile = $request->file('config_file');
            $dateFolder = now()->format('Y-m-d');
            $filename = time() . '_' . $configFile->getClientOriginalName();
            $storedPath = $configFile->storeAs('temp/configs/' . $dateFolder, $filename);
            $fullPath = storage_path('app/' . $storedPath);

            if (!file_exists($fullPath)) {
                return response()->json(['success' => false, 'message' => 'Config file upload failed'], 500);
            }

            $fileContent = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($fileContent as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                $commands[] = $line;
            }
        }

        if (empty($commands)) {
            return response()->json(['success' => false, 'message' => 'No valid commands found in config file or template'], 400);
        }

        $basePath = "/opt/librenms";
        $inventoryDir = $basePath . "/librenms-ansible-inventory-plugin/hosts/";
        if (!file_exists($inventoryDir)) {
            mkdir($inventoryDir, 0755, true);
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($validIPs as $ip) {
            $hostnameip = trim($ip);
            $device = Device::where('hostname', $hostnameip)->orWhere('overwrite_ip', $hostnameip)->first();
            
            $reqUser = $request->input('ansible_user', 'admin');
            $reqPass = $request->input('ansible_password', 'admin');
            $reqCommunity = $request->input('snmp_community', 'public');
            
            $ansibleUser = ($device && !empty($device->ssh_user)) ? $device->ssh_user : $reqUser;
            $ansiblePassword = ($device && !empty($device->ssh_pass)) ? $device->ssh_pass : $reqPass;
            $snmpCommunity = ($device && !empty($device->community)) ? $device->community : $reqCommunity;

            $hostname = 'bridge_' . str_replace('.', '_', $hostnameip);
            $inventoryFile = $inventoryDir . $hostname . ".yml";

            $inventoryContent = $this->generateInventoryYaml($hostname, $hostnameip, $ansibleUser, $ansiblePassword, $snmpCommunity);
            file_put_contents($inventoryFile, $inventoryContent);

            $playbook = $this->pluginPath . "/playbooks/firstconfiguploadip.yml";
            $extraVars = [
                'cli_commands' => $commands,
            ];

            try {
                $ansibleResult = $this->runAnsible($playbook, $inventoryFile, $extraVars);
                $output = $ansibleResult['output'];
                $exitCode = $ansibleResult['exit_code'];
                $isFailed = ($exitCode !== 0) || (strpos($output, 'failed=1') !== false) || (strpos($output, 'unreachable=1') !== false) || (strpos($output, 'ERROR:') !== false) || (strpos($output, 'Unknown command') !== false) || (strpos($output, 'Invalid input') !== false);

                if ($isFailed) {
                    $results[] = ['ip' => $ip, 'hostname' => $hostname, 'status' => 'failed', 'ansible_output' => $output];
                    $failedCount++;
                } else {
                    $results[] = ['ip' => $ip, 'hostname' => $hostname, 'status' => 'success', 'ansible_output' => $output];
                    $successCount++;
                }
            } catch (\Exception $e) {
                $results[] = ['ip' => $ip, 'hostname' => $hostname, 'status' => 'failed', 'error' => $e->getMessage()];
                $failedCount++;
            }
        }

        return response()->json([
            'success' => ($failedCount === 0),
            'message' => ($failedCount === 0) ? "Successfully processed {$successCount} device(s)" : "Processed with {$failedCount} failure(s). Success: {$successCount}, Failed: {$failedCount}",
            'results' => $results,
            'summary' => ['total' => count($validIPs), 'success' => $successCount, 'failed' => $failedCount]
        ]);
    }

    protected function processPushNetworkCommand(Request $request)
    {
        $this->initAnsible();
        $validIPs = [];
        if ($request->filled('valid_ips')) {
            $validIPs = json_decode($request->valid_ips, true);
        } else {
            $lines = explode("\n", $request->hostname);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && !str_starts_with($line, '#') && filter_var($line, FILTER_VALIDATE_IP)) {
                    $validIPs[] = $line;
                }
            }
        }

        if (empty($validIPs)) {
            return response()->json(['success' => false, 'message' => 'No valid IP addresses found'], 400);
        }

        $commands = [];
        $selectedInterfaces = $request->input('selected_interfaces', []);
        
        if ($request->filled('direct_commands')) {
            $commands = explode("\n", trim($request->direct_commands));
            $commands = array_filter(array_map('trim', $commands), function ($cmd) {
                return !empty($cmd) && !str_starts_with($cmd, '#');
            });
            $commands = array_values($commands);
        } elseif ($request->has('use_template_commands')) {
            if ($request->filled('loaded_template_name')) {
                $templateFile = 'templates/' . Str::slug($request->input('template_folder', 'general')) . '/' . Str::slug($request->loaded_template_name) . '.json';
                if (Storage::disk('local')->exists($templateFile)) {
                    $content = Storage::disk('local')->get($templateFile);
                    $data = json_decode($content, true);
                    $commands = $data['commands'] ?? [];
                    if (empty($selectedInterfaces) && isset($data['interfaces'])) {
                        $selectedInterfaces = $data['interfaces'];
                    }
                }
            } elseif ($request->filled('loaded_filename')) {
                $filePath = $request->loaded_filename;
                if (Storage::disk('local')->exists($filePath)) {
                    $fileContent = file(storage_path('app/' . $filePath), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($fileContent as $line) {
                        $line = trim($line);
                        if ($line === '' || str_starts_with($line, '#')) continue;
                        $commands[] = $line;
                    }
                }
            }
        }

        if (empty($commands) && $request->hasFile('config_file')) {
            $configFile = $request->file('config_file');
            $dateFolder = now()->format('Y-m-d');
            $filename = time() . '_' . $configFile->getClientOriginalName();
            $storedPath = $configFile->storeAs('temp/configs/' . $dateFolder, $filename);
            $fullPath = storage_path('app/' . $storedPath);

            if (!file_exists($fullPath)) {
                return response()->json(['success' => false, 'message' => 'Config file upload failed'], 500);
            }

            $fileContent = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($fileContent as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                $commands[] = $line;
            }
        }

        if (empty($commands)) {
            return response()->json(['success' => false, 'message' => 'No valid commands found in config file or template'], 400);
        }

        $basePath = "/opt/librenms";
        $inventoryDir = $basePath . "/librenms-ansible-inventory-plugin/hosts/";
        if (!file_exists($inventoryDir)) {
            mkdir($inventoryDir, 0755, true);
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($validIPs as $ip) {
            $hostnameip = trim($ip);
            $device = Device::where('hostname', $hostnameip)->orWhere('overwrite_ip', $hostnameip)->first();
            
            $reqUser = $request->input('ansible_user', 'admin');
            $reqPass = $request->input('ansible_password', 'admin');
            $reqCommunity = $request->input('snmp_community', 'public');
            
            $ansibleUser = ($device && !empty($device->ssh_user)) ? $device->ssh_user : $reqUser;
            $ansiblePassword = ($device && !empty($device->ssh_pass)) ? $device->ssh_pass : $reqPass;
            $snmpCommunity = ($device && !empty($device->community)) ? $device->community : $reqCommunity;

            $hostname = 'bridge_' . str_replace('.', '_', $hostnameip);
            $inventoryFile = $inventoryDir . $hostname . ".yml";

            $inventoryContent = $this->generateInventoryYaml($hostname, $hostnameip, $ansibleUser, $ansiblePassword, $snmpCommunity);
            file_put_contents($inventoryFile, $inventoryContent);

            $playbook = $this->pluginPath . "/playbooks/pushconfigtempuploadip.yml";
            $extraVars = [
                'cli_commands' => $commands,
            ];

            try {
                $ansibleResult = $this->runAnsible($playbook, $inventoryFile, $extraVars);
                $output = $ansibleResult['output'];
                $exitCode = $ansibleResult['exit_code'];
                $isFailed = ($exitCode !== 0) || (strpos($output, 'failed=1') !== false) || (strpos($output, 'unreachable=1') !== false) || (strpos($output, 'ERROR:') !== false) || (strpos($output, 'Unknown command') !== false) || (strpos($output, 'Invalid input') !== false);

                if ($isFailed) {
                    $results[] = ['ip' => $ip, 'hostname' => $hostname, 'status' => 'failed', 'ansible_output' => $output];
                    $failedCount++;
                } else {
                    $results[] = ['ip' => $ip, 'hostname' => $hostname, 'status' => 'success', 'ansible_output' => $output];
                    $successCount++;
                }
            } catch (\Exception $e) {
                $results[] = ['ip' => $ip, 'hostname' => $hostname, 'status' => 'failed', 'error' => $e->getMessage()];
                $failedCount++;
            }
        }

        return response()->json([
            'success' => ($failedCount === 0),
            'message' => ($failedCount === 0) ? "Successfully processed {$successCount} device(s)" : "Processed with {$failedCount} failure(s). Success: {$successCount}, Failed: {$failedCount}",
            'results' => $results,
            'summary' => ['total' => count($validIPs), 'success' => $successCount, 'failed' => $failedCount]
        ]);
    }
}
