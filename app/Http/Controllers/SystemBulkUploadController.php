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
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'selected_devices' => 'required|array',
            'selected_devices.*' => 'exists:devices,device_id',
            'uploads' => 'required|array',
            'uploads.*' => 'required|file|mimes:bin,img,tar,tar.gz,zip,txt|max:102400', // 100MB max
        ], [
            'selected_devices.required' => 'Please select at least one device',
            'selected_devices.*.exists' => 'One or more selected devices do not exist',
            'uploads.required' => 'Please upload at least one file',
            'uploads.*.required' => 'All selected hardware models require a file upload',
            'uploads.*.mimes' => 'Invalid file format. Allowed formats: .bin, .img, .tar, .tar.gz, .zip, .txt',
            'uploads.*.max' => 'File size cannot exceed 100MB',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Get selected devices and uploaded files
        $selectedDeviceIds = $request->input('selected_devices');
        $selectedDevices = Device::whereIn('device_id', $selectedDeviceIds)->get();
        $uploads = $request->file('uploads');
        
        $successCount = 0;
        $failedCount = 0;
        $failedDevices = [];
        $uploadedFiles = Session::get('system_bulk_uploads', []);
        
        // Check if TFTP directory exists and is writable
        if (!is_dir($this->tftpPath) || !is_writable($this->tftpPath)) {
            Log::error('TFTP directory is not writable or does not exist', ['path' => $this->tftpPath]);
            
            return redirect()->back()
                ->with('error', 'System configuration error: TFTP directory is not accessible. Please contact administrator.')
                ->withInput();
        }

        // Process each device
        foreach ($selectedDevices as $device) {
            $hardware = $device->hardware;
            
            // Check if file exists for this hardware model
            if (!isset($uploads[$hardware])) {
                $failedCount++;
                $failedDevices[] = $device->hostname . ' (no file uploaded for ' . $hardware . ')';
                continue;
            }
            
            $file = $uploads[$hardware];
            
            try {
                // Sanitize filename to prevent directory traversal
                $safeHostname = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $device->hostname);
                $safeOriginalName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $file->getClientOriginalName());
                
                // Create filename: hostname_timestamp_filename
                $filename = $safeHostname . '_' . $safeOriginalName;
                
                // Full path for file
                $filePath = $this->tftpPath . '/' . $filename;
                
                // Check if file already exists
                if (file_exists($filePath)) {
                    // Add timestamp to make unique
                    $filename = $safeHostname . '_' . time() . '_' . $safeOriginalName;
                    $filePath = $this->tftpPath . '/' . $filename;
                }
                
                // Move the file to TFTP directory
                $file->move($this->tftpPath, $filename);
                
                // Verify file was moved successfully
                if (file_exists($filePath)) {
                    // Set proper permissions
                    chmod($filePath, 0644);
                    
                    // Log successful upload
                    Log::info('System software bulk upload successful', [
                        'device_id' => $device->device_id,
                        'hostname' => $device->hostname,
                        'hardware' => $hardware,
                        'filename' => $filename,
                        'file_size' => filesize($filePath),
                        'user_id' => Auth::id(),
                        'user' => Auth::user()->username ?? 'unknown'
                    ]);
                    
                    // Store in session for this session
                    $uploadedFiles[$hardware] = $filename;
                    $successCount++;
                } else {
                    throw new \Exception('File could not be saved to TFTP directory');
                }
                
            } catch (\Exception $e) {
                $failedCount++;
                $failedDevices[] = $device->hostname . ' (' . $e->getMessage() . ')';
                
                // Log error
                Log::error('System software bulk upload failed', [
                    'device_id' => $device->device_id,
                    'hostname' => $device->hostname,
                    'hardware' => $hardware,
                    'error' => $e->getMessage(),
                    'user_id' => Auth::id()
                ]);
            }
        }

        // Store uploaded files info in session
        Session::put('system_bulk_uploads', $uploadedFiles);
        Session::save();

        // Prepare response message
        if ($successCount > 0) {
            $message = 'Successfully uploaded files for ' . $successCount . ' device(s)';
            
            if ($failedCount > 0) {
                return redirect()->route('system.bulk.upload')
                    ->with('warning', $message . '. Failed for ' . $failedCount . ' device(s): ' . implode(', ', $failedDevices))
                    ->with('status', $successCount . ' file(s) uploaded successfully');
            } else {
                return redirect()->route('system.bulk.upload')
                    ->with('status', $message . '. All files uploaded successfully!');
            }
        } else {
            return redirect()->back()
                ->with('error', 'Upload failed for all ' . $failedCount . ' device(s): ' . implode(', ', $failedDevices))
                ->withInput();
        }
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