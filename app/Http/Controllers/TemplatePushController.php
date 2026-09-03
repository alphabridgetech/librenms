<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Controllers\Traits\HandlesPushConfiguration;

use App\Models\ApiToken;
use Illuminate\Support\Facades\Auth;

class TemplatePushController extends Controller
{
    use HandlesPushConfiguration;

    private function getUserLibreNMSToken()
    {
        try {
            $apiToken = ApiToken::select('token_hash')
                ->where('user_id', Auth::user()->user_id)
                ->firstOrFail();
            return $apiToken->token_hash;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function addHostTemplate(Request $request)
    {
        $this->authorize('create', \App\Models\CustomMib::class);
        
        $devices = Device::orderBy('hostname')->get(['device_id', 'hostname', 'overwrite_ip', 'sysName', 'display', 'ip']);
        
        $templates = [];
        try {
            $templatesDir = resource_path('templates');
            if (is_dir($templatesDir)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templatesDir));
                foreach ($files as $file) {
                    if ($file->isFile() && $file->getExtension() === 'json') {
                        $content = file_get_contents($file->getPathname());
                        $data = json_decode($content, true);
                        if ($data) {
                            if (isset($data['type']) && $data['type'] !== 'form') {
                                continue;
                            }
                            // Calculate relative path to detect if it's in a subfolder
                            $relativePath = str_replace($templatesDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                            $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
                            if (count($parts) > 1) {
                                $data['template_folder'] = $parts[0];
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
                usort($uploadedFiles, function($a, $b) {
                    return $b['time'] - $a['time'];
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch uploaded files: ' . $e->getMessage());
        }

        $api_token = $this->getUserLibreNMSToken();

        return view('addhostip.template', compact('templates', 'uploadedFiles', 'devices', 'api_token'));
    }

    public function addHostTemplateSave(Request $request)
    {
        $request->validate([
            'hostname' => 'required|string',
            'direct_commands' => 'required_without_all:config_file,loaded_template_name,loaded_filename|string',
            'template_name' => 'nullable|string|max:255',
        ]);

        return $this->processPushNetworkCommand($request);
    }

    public function storeTemplate(Request $request)
    {
        $name = $request->template_name;
        $slug = Str::slug($name);
        $mode = $request->port_mode;

        if ($mode === 'form') {
            $fields = $request->input('fields', []);
            if (empty($name) || empty($fields)) {
                return response()->json(['success' => false, 'message' => 'Name and fields are required'], 400);
            }

            $data = [
                'name' => $name,
                'type' => 'form',
                'hostname' => '',
                'interfaces' => [],
                'fields' => $fields,
                'created_at' => Carbon::now()->toDateTimeString(),
            ];
        } else {
            $request->validate([
                'template_name' => 'required|string|max:255',
                'direct_commands' => 'required|string',
                'port_mode' => 'required|in:access,trunk,custom',
                'pvid' => 'nullable|integer|min:1|max:4094',
            ]);

            $data = [
                'name' => $name,
                'type' => $mode,
                'hostname' => '',
                'interfaces' => [],
                'commands' => explode("\n", trim($request->direct_commands)),
                'pvid' => ($mode !== 'custom') ? $request->pvid : null,
                'created_at' => Carbon::now()->toDateTimeString(),
            ];
        }

        $folder = $request->input('template_folder');
        $templatesDir = resource_path('templates');
        
        if (!empty($folder)) {
            $folderSlug = Str::slug($folder);
            $targetDir = $templatesDir . DIRECTORY_SEPARATOR . $folderSlug;
            $filePath = $targetDir . DIRECTORY_SEPARATOR . $slug . '.json';
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        } else {
            $filePath = $templatesDir . DIRECTORY_SEPARATOR . $slug . '.json';
            if (!is_dir($templatesDir)) {
                mkdir($templatesDir, 0755, true);
            }
        }

        try {
            file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
            return response()->json(['success' => true, 'message' => 'Template saved successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to save template: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save template'], 500);
        }
    }

    public function destroyTemplate(Request $request)
    {
        $name = $request->input('template_name');
        if (empty($name)) {
            return response()->json(['success' => false, 'message' => 'Template name is required'], 400);
        }

        $slug = Str::slug($name);
        $folder = $request->input('template_folder');
        $templatesDir = resource_path('templates');
        
        $filePath = null;
        if (!empty($folder)) {
            $path = $templatesDir . DIRECTORY_SEPARATOR . Str::slug($folder) . DIRECTORY_SEPARATOR . $slug . '.json';
            if (file_exists($path)) {
                $filePath = $path;
            }
        }
        
        if (!$filePath) {
            $path = $templatesDir . DIRECTORY_SEPARATOR . $slug . '.json';
            if (file_exists($path)) {
                $filePath = $path;
            }
        }

        if (!$filePath) {
            $path = $templatesDir . DIRECTORY_SEPARATOR . 'general' . DIRECTORY_SEPARATOR . $slug . '.json';
            if (file_exists($path)) {
                $filePath = $path;
            }
        }

        // Fallback: search all files in templates directory
        if (!$filePath && is_dir($templatesDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templatesDir));
            foreach ($files as $file) {
                if ($file->isFile() && basename($file->getPathname()) === $slug . '.json') {
                    $filePath = $file->getPathname();
                    break;
                }
            }
        }

        if (!$filePath) {
            return response()->json(['success' => false, 'message' => 'Template not found'], 404);
        }

        try {
            unlink($filePath);
            return response()->json(['success' => true, 'message' => 'Template deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete template: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete template'], 500);
        }
    }

    public function getDevicePorts(Request $request)
    {
        $deviceIds = $request->input('device_ids', []);
        
        if (empty($deviceIds)) {
            return response()->json(['success' => true, 'ports' => []]);
        }

        $ports = Port::whereIn('device_id', $deviceIds)
            ->select('port_id', 'device_id', 'ifName', 'ifDescr')
            ->orderBy('ifName')
            ->get()
            ->groupBy('device_id');

        $devices = Device::whereIn('device_id', $deviceIds)->pluck('hostname', 'device_id');

        $formattedPorts = [];
        foreach ($ports as $deviceId => $devicePorts) {
            $hostname = $devices[$deviceId] ?? 'Unknown Device';
            $formattedPorts[] = [
                'device_id' => $deviceId,
                'text' => $hostname,
                'children' => $devicePorts->map(function ($port) {
                    return [
                        'id' => $port->ifName,
                        'text' => $port->ifName . ($port->ifDescr && $port->ifName !== $port->ifDescr ? " ({$port->ifDescr})" : '')
                    ];
                })->toArray()
            ];
        }

        return response()->json([
            'success' => true,
            'ports' => $formattedPorts
        ]);
    }
}
