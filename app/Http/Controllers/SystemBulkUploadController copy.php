<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Facades\LibrenmsConfig;
use App\Models\CustomMib;
use App\Models\AuthLog;
use App\Models\Dashboard;
use App\Models\User;
use App\Models\Device;
use App\Models\UserPref;
use Auth;
use LibreNMS\Config;
use Illuminate\Support\Str;
use LibreNMS\Authentication\LegacyAuth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;
use URL;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SystemBulkUploadController extends Controller
{
    protected $tftpPath;
    protected $pluginPath;
    protected $venv;

    public function __construct()
    {
        $this->venv = base_path('librenms-ansible-inventory-plugin/bin/activate');
        $this->pluginPath = base_path('librenms-ansible-inventory-plugin');
        $this->tftpPath = '/tftpboot';
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', CustomMib::class);

        // dropdown list (unique hardware)
        $deviceFilter = Device::whereNotNull('hardware')
            ->distinct()
            ->orderBy('hardware')
            ->pluck('hardware');

        // selected model_names (multiple selection)
        $selectedModels = $request->input('model_names', []);

        if (!is_array($selectedModels)) {
            $selectedModels = [$selectedModels];
        }

        // Filter out empty values
        $selectedModels = array_filter($selectedModels);

        // devices query
        $devicesQuery = Device::orderBy('hostname')
            ->select('device_id', 'hostname', 'sysName', 'sysObjectID', 'hardware');

        // APPLY FILTERS IF SELECTED
        if (!empty($selectedModels)) {
            $devicesQuery->whereIn('hardware', $selectedModels);
        }

        $devices = $devicesQuery->get();

        return view('syssoftbulk.index', compact(
            'devices',
            'deviceFilter',
            'selectedModels'
        ));
    }

    public function store(Request $request)
{
    Log::info('Bulk upload request received', [
        'all_files' => $request->allFiles(),
        'has_files' => $request->hasFile('sysfiles'),
        'files_keys' => array_keys($request->allFiles()),
        'device_ids' => $request->device_ids
    ]);

    $this->authorize('create', CustomMib::class);

    // Validate request - FIX: Remove the strict validation for sysfiles if not present
    $validator = Validator::make($request->all(), [
        'device_ids' => 'required|array',
        'device_ids.*' => 'exists:devices,device_id',
        // Make sysfiles optional first, then check if it exists
    ]);

    if ($validator->fails()) {
        Log::error('Validation failed', ['errors' => $validator->errors()]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    // Check if sysfiles exists
    if (!$request->hasFile('sysfiles')) {
        Log::error('No sysfiles uploaded');
        return response()->json([
            'message' => 'Validation failed',
            'errors' => ['sysfiles' => ['No files were uploaded']]
        ], 422);
    }

    $results = [
        'success' => [],
        'failed' => []
    ];

    // Get all selected devices
    $devices = Device::whereIn('device_id', $request->device_ids)->get();
    
    // Group devices by hardware model
    $devicesByModel = $devices->groupBy('hardware');

    // Process each uploaded file for its specific model
    foreach ($request->file('sysfiles') as $model => $file) {
        Log::info('Processing file for model', [
            'model' => $model, 
            'file_name' => $file->getClientOriginalName()
        ]);

        // Get devices of this model from the selected devices
        $devicesForModel = $devicesByModel->get($model, collect());
        
        if ($devicesForModel->isEmpty()) {
            Log::warning('No selected devices found for model', ['model' => $model]);
            
            $results['failed'][] = [
                'device_id' => null,
                'hostname' => 'N/A',
                'hardware' => $model,
                'error' => 'No devices selected for this model'
            ];
            continue;
        }

        // For each device in this model, upload the file
        foreach ($devicesForModel as $device) {
            try {
                // Use the file from the request
                $uploadedFile = $request->file("sysfiles.{$model}");
                
                if (!$uploadedFile) {
                    throw new \Exception('File not found for model: ' . $model);
                }

                $result = $this->processDeviceUpload($device, $uploadedFile, $model);
                
                if ($result['success']) {
                    $results['success'][] = [
                        'device_id' => $device->device_id,
                        'hostname' => $device->hostname,
                        'hardware' => $device->hardware,
                        'filename' => $result['filename'],
                        'message' => $result['message']
                    ];
                } else {
                    $results['failed'][] = [
                        'device_id' => $device->device_id,
                        'hostname' => $device->hostname,
                        'hardware' => $device->hardware,
                        'error' => $result['error']
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Bulk upload failed for device ' . $device->hostname . ': ' . $e->getMessage());
                
                $results['failed'][] = [
                    'device_id' => $device->device_id,
                    'hostname' => $device->hostname,
                    'hardware' => $device->hardware,
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    $message = count($results['success']) . ' devices successful, ' . 
               count($results['failed']) . ' devices failed';

    if (count($results['failed']) > 0) {
        return response()->json([
            'message' => $message,
            'results' => $results
        ], 207);
    }

    return response()->json([
        'message' => $message,
        'results' => $results
    ]);
}

    private function processDeviceUpload($device, $file, $model)
    {
        try {
            // Ensure TFTP directory exists
            if (!is_dir($this->tftpPath)) {
                mkdir($this->tftpPath, 0755, true);
            }

            // Generate unique filename for each device to avoid conflicts
            $originalName = $file->getClientOriginalName();
            $timestamp = now()->format('Ymd_His');
            $uniqueId = uniqid();
            $filename = $device->hostname . '_' . $model . '_' . $timestamp . '_' . $uniqueId . '_' . $originalName;

            // Store file in TFTP directory - create a copy for this device
            $filePath = $this->tftpPath . '/' . $filename;

            // Since we can't move the same file multiple times, we need to copy it
            if (!copy($file->getRealPath(), $filePath)) {
                throw new \Exception('Failed to copy file to TFTP directory');
            }

            // Set TFTP server (you might want to make this configurable)
            $tftpServer = config('app.tftp_server', '10.1.1.1');

            // Create host file for Ansible if it doesn't exist
            $hostFile = $this->ensureHostFile($device->hostname);

            // Run Ansible playbook
            $playbook = "{$this->pluginPath}/playbooks/tftpupload.yml";
            $hosts = "{$this->pluginPath}/hosts/{$device->hostname}.yml";

            $output = $this->runAnsible($playbook, $hosts, [
                "tftp_server" => $tftpServer,
                "filename" => $filename,
                "destination_file" => $originalName,
            ]);

            // Log the upload
            Log::info('Bulk upload successful for device ' . $device->hostname, [
                'device_id' => $device->device_id,
                'filename' => $filename,
                'model' => $model
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'message' => 'Uploaded and processed successfully',
                'output' => $output
            ];

        } catch (\Exception $e) {
            Log::error('Device upload failed: ' . $e->getMessage(), [
                'device' => $device->hostname,
                'model' => $model,
                'file' => $file->getClientOriginalName()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function ensureHostFile($hostname)
    {
        $hostsDir = "{$this->pluginPath}/hosts";

        if (!is_dir($hostsDir)) {
            mkdir($hostsDir, 0755, true);
        }

        $hostFile = "{$hostsDir}/{$hostname}.yml";

        if (!file_exists($hostFile)) {
            $content = "[devices]\n{$hostname} ansible_host={$hostname}\n\n[devices:vars]\nansible_connection=network_cli\nansible_network_os=ios\nansible_user=librenms\nansible_password=your_password\nansible_become=yes\nansible_become_method=enable";
            file_put_contents($hostFile, $content);
        }

        return $hostFile;
    }

    private function runAnsible(string $playbook, string $hosts, array $extraVars = []): string
    {
        $extraVarsString = "";

        if (!empty($extraVars)) {
            foreach ($extraVars as $key => $value) {
                // Escape special characters
                $escapedValue = escapeshellarg($value);
                $extraVarsString .= " --extra-vars \"{$key}={$value}\"";
            }
        }

        // Check if venv exists
        if (file_exists($this->venv)) {
            $cmd = "source {$this->venv} && ansible-playbook -i {$hosts} {$playbook}{$extraVarsString} 2>&1";
        } else {
            $cmd = "ansible-playbook -i {$hosts} {$playbook}{$extraVarsString} 2>&1";
        }

        Log::debug('Running Ansible command: ' . $cmd);

        $output = shell_exec($cmd);

        if ($output === null) {
            throw new \Exception('Failed to execute Ansible playbook');
        }

        return $output;
    }

    public function getUploadStatus(Request $request)
    {
        $deviceIds = $request->input('device_ids', []);

        if (empty($deviceIds)) {
            return response()->json(['status' => 'no_devices']);
        }

        return response()->json([
            'status' => 'ready',
            'message' => 'Ready to upload'
        ]);
    }
}