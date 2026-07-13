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
use Illuminate\Support\Facades\Session;
use App\Models\Port;
use App\Http\Controllers\Traits\HandlesPushConfiguration;

class SystemBulkUploadController extends Controller
{
    use HandlesPushConfiguration;

    protected $tftpPath;

    public function __construct()
    {
        $this->tftpPath = '/tftpboot';

        // Create TFTP directory if it doesn't exist
        if (!is_dir($this->tftpPath)) {
            try {
                mkdir($this->tftpPath, 0755, true);
            } catch (\Exception $e) {
                Log::error('Failed to create TFTP directory: ' . $e->getMessage());
            }
        }
    }

    public function addHostIp(Request $request)
    {
        $this->authorize('create', CustomMib::class);
        $devices = Device::orderBy('hostname')->get(['device_id', 'hostname', 'overwrite_ip']);
        
        $templates = [];
        try {
            if (Storage::disk('local')->exists('templates')) {
                $files = Storage::disk('local')->allFiles('templates');
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                        $content = Storage::disk('local')->get($file);
                        $data = json_decode($content, true);
                        if ($data) {
                            if (isset($data['type']) && $data['type'] === 'form') {
                                continue;
                            }
                            $parts = explode('/', $file);
                            if (count($parts) > 2) {
                                $data['template_folder'] = $parts[count($parts) - 2];
                            } else {
                                $data['template_folder'] = '';
                            }
                            $templates[] = $data;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch templates: ' . $e->getMessage());
        }

        $uploadedFiles = [];
        try {
            if (Storage::disk('local')->exists('temp/configs')) {
                $files = Storage::disk('local')->allFiles('temp/configs');
                foreach ($files as $file) {
                    $uploadedFiles[] = [
                        'name' => basename($file),
                        'path' => $file,
                        'display_name' => str_replace('temp/configs/', '', $file),
                        'size' => Storage::disk('local')->size($file),
                        'time' => Storage::disk('local')->lastModified($file)
                    ];
                }
                // Sort by time descending
                usort($uploadedFiles, function($a, $b) {
                    return $b['time'] - $a['time'];
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch uploaded files: ' . $e->getMessage());
        }

        return view('addhostip.index', compact('devices', 'templates', 'uploadedFiles'));
    }

    public function addHostIpsave(Request $request)
    {
        // Integrated version: Supports both manual file upload and loaded templates
        $request->validate([
            'hostname' => 'required|string',
            'config_file' => 'required_without:use_template_commands|file|mimes:conf,txt,cfg,bin|max:10240',
        ]);

        return $this->processPush($request);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', CustomMib::class);

        // Get dropdown list (unique hardware)
        $deviceFilter = Device::whereNotNull('hardware')
            ->where('hardware', '!=', '')
            ->distinct()
            ->orderBy('hardware')
            ->pluck('hardware');

        // Get selected model_names (multiple selection)
        $selectedModels = $request->input('model_names', []);

        if (!is_array($selectedModels)) {
            $selectedModels = [$selectedModels];
        }

        // Filter out empty values
        $selectedModels = array_filter($selectedModels);

        // Remove duplicate selections
        $selectedModels = array_unique($selectedModels);

        // Devices query
        $devicesQuery = Device::orderBy('hostname')
            ->select('device_id', 'hostname', 'sysName', 'sysObjectID', 'hardware', 'status');

        // Apply filters if selected
        if (!empty($selectedModels)) {
            $devicesQuery->whereIn('hardware', $selectedModels);
        }

        $devices = $devicesQuery->get();

        // Get previously uploaded files from session (if any)
        $uploadedFiles = Session::get('system_bulk_uploads', []);

        return view('syssoftbulk.index', compact(
            'devices',
            'deviceFilter',
            'selectedModels',
            'uploadedFiles'
        ));
    }

    public function process(Request $request)
    {
        $this->authorize('create', CustomMib::class);

        $validator = Validator::make($request->all(), [
            'selected_devices' => 'required|array',
            'selected_devices.*' => 'exists:devices,device_id',
            'uploads' => 'required|array',
        ]);

        $validator->after(function ($validator) use ($request) {
            $uploads = $request->file('uploads', []);
            $selectedDevices = $request->input('selected_devices', []);
            
            // Get hardware models of selected devices
            $selectedModels = Device::whereIn('device_id', $selectedDevices)
                ->pluck('hardware')
                ->filter()
                ->unique()
                ->toArray();
                
            foreach ($selectedModels as $model) {
                if (!isset($uploads[$model]) || !$request->hasFile("uploads.{$model}")) {
                    $validator->errors()->add("uploads.{$model}", "The upload file for model {$model} is required.");
                    continue;
                }
                
                $file = $uploads[$model];
                if (is_null($file) || !$file->isValid()) {
                    $validator->errors()->add("uploads.{$model}", "The upload file for model {$model} is invalid.");
                    continue;
                }
                
                // Validate extension name instead of mime type
                $extension = strtolower($file->getClientOriginalExtension());
                if ($extension !== 'bin') {
                    $validator->errors()->add("uploads.{$model}", "The upload file for model {$model} must have a .bin extension.");
                }
                
                // Max file size: 100MB
                if ($file->getSize() > 102400 * 1024) {
                    $validator->errors()->add("uploads.{$model}", "The upload file for model {$model} must not be greater than 100MB.");
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $devices = Device::whereIn('device_id', $request->selected_devices)->get();
        // Filter out empty file uploads
        $uploads = array_filter($request->file('uploads', []));

        if (!is_dir($this->tftpPath) || !is_writable($this->tftpPath)) {
            return back()->with('error', 'TFTP directory not writable');
        }

        $modelBaseFiles = [];
        $success = 0;
        $failed = [];

        /*
         |--------------------------------------------------------------------------
         | STEP 1: Save Firmware Once Per Model
         |--------------------------------------------------------------------------
         */
        foreach ($uploads as $model => $file) {

            $safeModel = preg_replace('/[^a-zA-Z0-9\-_]/', '', $model);
            $extension = $file->getClientOriginalExtension();

            $baseName = "firmware_{$safeModel}.{$extension}";
            $basePath = $this->tftpPath . '/' . $baseName;

            if (file_exists($basePath)) {
                unlink($basePath);
            }

            $file->move($this->tftpPath, $baseName);
            chmod($basePath, 0644);

            $modelBaseFiles[$model] = $basePath;
        }

        /*
         |--------------------------------------------------------------------------
         | STEP 2: Copy Firmware Per Device (Ansible Friendly)
         |--------------------------------------------------------------------------
         */
        foreach ($devices as $device) {

            $model = $device->hardware;

            if (!isset($modelBaseFiles[$model])) {
                $failed[] = $device->hostname . ' (No firmware for model)';
                continue;
            }

            try {
                $safeHostname = preg_replace('/[^a-zA-Z0-9\-_]/', '', $device->hostname);
                $extension = pathinfo($modelBaseFiles[$model], PATHINFO_EXTENSION);

                $deviceFile = "{$safeHostname}.{$extension}";
                $devicePath = $this->tftpPath . '/' . $deviceFile;

                if (file_exists($devicePath)) {
                    unlink($devicePath);
                }

                copy($modelBaseFiles[$model], $devicePath);
                chmod($devicePath, 0644);

                $this->runAnsibleFirmwareUpload($device, $deviceFile);

                $success++;

            } catch (\Exception $e) {
                $failed[] = $device->hostname . ' (' . $e->getMessage() . ')';
            }
        }

        if ($success > 0) {
            return redirect()->route('system.bulk.upload')
                ->with('status', "$success device(s) firmware prepared & Ansible triggered.");
        }

        return back()->with('error', 'All uploads failed: ' . implode(', ', $failed));
    }


    private function runAnsibleFirmwareUpload($device, $filename)
    {
        $this->initAnsible();

        $reqUser = 'admin';
        $reqPass = 'admin';
        $reqCommunity = 'public';

        $ansibleUser = (!empty($device->ssh_user)) ? $device->ssh_user : $reqUser;
        $ansiblePassword = (!empty($device->ssh_pass)) ? $device->ssh_pass : $reqPass;
        $snmpCommunity = (!empty($device->community)) ? $device->community : $reqCommunity;

        $hostname = 'bridge_' . str_replace('.', '_', $device->hostname);
        
        $inventoryDir = $this->pluginPath . "/hosts/";
        if (!file_exists($inventoryDir)) {
            mkdir($inventoryDir, 0755, true);
        }
        $hosts = $inventoryDir . $hostname . ".yml";

        $inventoryContent = $this->generateInventoryYaml($hostname, $device->hostname, $ansibleUser, $ansiblePassword, $snmpCommunity);
        file_put_contents($hosts, $inventoryContent);

        $playbook = $this->pluginPath . "/playbooks/tftpupload.yml";

        $tftpServer = \DB::table('config')->where('config_name', 'tftp_server_ip')->value('config_value');
        if (empty($tftpServer) || $tftpServer === 'localhost' || $tftpServer === '127.0.0.1') {
            $tftpServer = parse_url(config('app.url'), PHP_URL_HOST);
        }
        if (empty($tftpServer) || $tftpServer === 'localhost' || $tftpServer === '127.0.0.1') {
            $tftpServer = '192.168.200.179'; // Fallback host IP
        }

        $destination_file = "switch.bin";    

        $extraVars = [
            'tftp_server' => $tftpServer,
            'filename' => $filename,
            'destination_file' => $destination_file,
        ];

        $ansibleResult = $this->runAnsible($playbook, $hosts, $extraVars);
        $output = $ansibleResult['output'];
        $exitCode = $ansibleResult['exit_code'];

        Log::warning("Ansible output for {$device->hostname}: " . $output);

        $isFailed = ($exitCode !== 0) || (strpos($output, 'failed=1') !== false) || (strpos($output, 'unreachable=1') !== false) || (strpos($output, 'ERROR:') !== false);
        if ($isFailed) {
            throw new \Exception("Ansible task failed with exit code {$exitCode}.");
        }
    }

    public function getUploadedFileContent(Request $request)
    {
        $path = $request->query('path');
        if (Storage::disk('local')->exists($path)) {
            return response()->json([
                'success' => true,
                'content' => Storage::disk('local')->get($path),
                'filename' => basename($path)
            ]);
        }
        return response()->json(['success' => false, 'message' => 'File not found at ' . $path], 404);
    }

    public function clearSession()
    {
        Session::forget('system_bulk_uploads');
        Session::save();

        return redirect()->route('system.bulk.upload')
            ->with('info', 'Upload session has been cleared');
    }

    public function getUploadedFiles()
    {
        $uploadedFiles = Session::get('system_bulk_uploads', []);

        return response()->json([
            'success' => true,
            'files' => $uploadedFiles
        ]);
    }
}
