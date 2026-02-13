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

        // Create TFTP directory if it doesn't exist
        if (!is_dir($this->tftpPath)) {
            try {
                mkdir($this->tftpPath, 0755, true);
            } catch (\Exception $e) {
                Log::error('Failed to create TFTP directory: ' . $e->getMessage());
            }
        }
    }

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

        $request->validate([
            'selected_devices' => 'required|array',
            'selected_devices.*' => 'exists:devices,device_id',
            'uploads' => 'required|array',
            'uploads.*' => 'required|file|mimes:bin|max:102400',
        ]);

        $devices = Device::whereIn('device_id', $request->selected_devices)->get();
        $uploads = $request->file('uploads');

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

                /*
                 |--------------------------------------------------------------------------
                 | OPTIONAL: Trigger Ansible Here
                 |--------------------------------------------------------------------------
                 */

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
        
        $hosts = "{$this->pluginPath}/hosts/{$device->hostname}.yml";
        $playbook = "{$this->pluginPath}/playbooks/tftp_upload.yml";


        $tftpServer = "192.168.200.128"; 
        $destination_file="switch.bin";    

        $extraVars = [
            'tftp_server' => $tftpServer,
            'filename' => $filename,
            'destination_file' => $destination_file,
        ];

        $output = $this->runAnsible($playbook, $hosts, $extraVars);

        Log::info("Ansible output for {$device->hostname}: " . $output);

    }



    /**
     * Clear uploaded files session
     */
    public function clearSession()
    {
        Session::forget('system_bulk_uploads');
        Session::save();

        return redirect()->route('system.bulk.upload')
            ->with('info', 'Upload session has been cleared');
    }

    /**
     * Get list of uploaded files
     */
    public function getUploadedFiles()
    {
        $uploadedFiles = Session::get('system_bulk_uploads', []);

        return response()->json([
            'success' => true,
            'files' => $uploadedFiles
        ]);
    }
}