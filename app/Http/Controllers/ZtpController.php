<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ZtpDevice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ZtpController extends Controller
{
    /**
     * ZTP Dashboard — list all registered ZTP devices.
     */
    public function index()
    {
        $this->authorize('create', \App\Models\CustomMib::class);

        $devices     = ZtpDevice::latest()->get();
        $totalCount  = $devices->count();
        $pendingCount     = $devices->where('status', 'pending')->count();
        $provisionedCount = $devices->where('status', 'provisioned')->count();
        $failedCount      = $devices->where('status', 'failed')->count();

        return view('ztp.index', compact(
            'devices',
            'totalCount',
            'pendingCount',
            'provisionedCount',
            'failedCount'
        ));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $this->authorize('create', \App\Models\CustomMib::class);
        $templates = $this->loadAvailableTemplates();
        return view('ztp.create', compact('templates'));
    }

    /**
     * Store a new ZTP device mapping.
     */
    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\CustomMib::class);

        $request->validate([
            'mac_address'  => 'required|string|max:17',
            'device_name'  => 'required|string|max:255',
            'ip_address'   => 'nullable|ip',
            'subnet_mask'  => 'nullable|string|max:45',
            'gateway'      => 'nullable|ip',
        ]);

        $mac = ZtpDevice::normalizeMac($request->mac_address);

        ZtpDevice::updateOrCreate(
            ['mac_address' => $mac],
            [
                'device_name'       => $request->device_name,
                'ip_address'        => $request->ip_address,
                'subnet_mask'       => $request->subnet_mask ?: '255.255.255.0',
                'gateway'           => $request->gateway,
                'snmp_community'    => $request->snmp_community ?: 'public',
                'template_name'     => $request->template_name ?: null,
                'template_folder'   => $request->template_folder ?: null,
                'template_commands' => $request->template_commands ?: null,
                'notes'             => $request->notes,
                'status'            => 'pending',
            ]
        );

        return redirect()->route('ztp.index')->with('status', "ZTP device '{$request->device_name}' registered successfully.");
    }

    /**
     * Show edit form.
     */
    public function edit(ZtpDevice $ztp)
    {
        $this->authorize('create', \App\Models\CustomMib::class);
        $templates = $this->loadAvailableTemplates();
        return view('ztp.edit', compact('ztp', 'templates'));
    }

    /**
     * Update ZTP device mapping.
     */
    public function update(Request $request, ZtpDevice $ztp)
    {
        $this->authorize('create', \App\Models\CustomMib::class);

        $request->validate([
            'mac_address'  => 'required|string|max:17',
            'device_name'  => 'required|string|max:255',
            'ip_address'   => 'nullable|ip',
            'subnet_mask'  => 'nullable|string|max:45',
            'gateway'      => 'nullable|ip',
        ]);

        $ztp->update([
            'mac_address'       => ZtpDevice::normalizeMac($request->mac_address),
            'device_name'       => $request->device_name,
            'ip_address'        => $request->ip_address,
            'subnet_mask'       => $request->subnet_mask ?: '255.255.255.0',
            'gateway'           => $request->gateway,
            'snmp_community'    => $request->snmp_community ?: 'public',
            'template_name'     => $request->template_name ?: null,
            'template_folder'   => $request->template_folder ?: null,
            'template_commands' => $request->template_commands ?: null,
            'notes'             => $request->notes,
            'status'            => $request->input('status', $ztp->status),
        ]);

        return redirect()->route('ztp.index')->with('status', "ZTP device '{$ztp->device_name}' updated successfully.");
    }

    /**
     * Delete a ZTP device mapping.
     */
    public function destroy(ZtpDevice $ztp)
    {
        $this->authorize('create', \App\Models\CustomMib::class);
        $name = $ztp->device_name;
        $ztp->delete();
        return redirect()->route('ztp.index')->with('status', "ZTP device '{$name}' deleted successfully.");
    }

    /**
     * PUBLIC endpoint — called by the switch during boot.
     * Returns a plain text CLI configuration file.
     * No authentication required.
     */
    public function serveConfig(string $mac)
    {
        $normalizedMac = ZtpDevice::normalizeMac($mac);

        $device = ZtpDevice::where('mac_address', $normalizedMac)->first();

        if (!$device) {
            Log::info("ZTP: Unknown device requested config. MAC: {$normalizedMac}");
            abort(404, 'Device not registered for ZTP provisioning.');
        }

        Log::info("ZTP: Serving config for MAC {$normalizedMac} (device: {$device->device_name})");

        // Build the CLI config
        $config = $this->buildConfig($device);

        // Mark as provisioned
        $device->update([
            'status'         => 'provisioned',
            'last_seen_at'   => now(),
            'provisioned_at' => now(),
        ]);

        // Auto-add device to LibreNMS after a delay (60s for switch to apply config and come online)
        if ($device->ip_address) {
            $this->scheduleLibrenmsAdd($device);
        }

        return response($config, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Schedule adding the device to LibreNMS after a delay.
     * Runs a background shell command that waits 60 seconds
     * before calling `php artisan device:add`.
     */
    private function scheduleLibrenmsAdd(ZtpDevice $device): void
    {
        $ip        = escapeshellarg($device->ip_address);
        $community = escapeshellarg($device->snmp_community ?: 'public');
        $logFile   = '/tmp/ztp_add_' . str_replace('.', '_', $device->ip_address) . '.log';
        $artisan   = base_path('artisan');

        // Build the artisan command with SNMP v2c community
        $addCmd = "php {$artisan} device:add --v2c --community={$community} --ping-fallback {$ip}";

        // Run in background: wait 60 seconds then add the device
        $bgCmd = "nohup bash -c 'sleep 60 && {$addCmd}' > {$logFile} 2>&1 &";

        exec($bgCmd);

        Log::info("ZTP: Scheduled LibreNMS auto-add for {$device->ip_address} in 60 seconds. Log: {$logFile}");
    }

    /**
     * Reset a device back to pending so it can be re-provisioned.
     */
    public function resetStatus(ZtpDevice $ztp)
    {
        $this->authorize('create', \App\Models\CustomMib::class);
        $ztp->update(['status' => 'pending', 'provisioned_at' => null]);
        return redirect()->route('ztp.index')->with('status', "Device '{$ztp->device_name}' reset to pending.");
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Build CLI configuration text for a ZTP device.
     */
    private function buildConfig(ZtpDevice $device): string
    {
        $lines = [];

        // If a template is selected, load its commands
        if ($device->template_name) {
            $commands = $this->loadTemplateCommands($device->template_name, $device->template_folder);
        } elseif ($device->template_commands) {
            $commands = array_filter(
                array_map('trim', explode("\n", $device->template_commands)),
                fn($l) => $l !== '' && !str_starts_with($l, '#')
            );
        } else {
            $commands = [];
        }

        // Always set hostname and IP if provided (prepended before template commands)
        $lines[] = 'enable';
        $lines[] = 'config';

        if ($device->device_name) {
            $lines[] = 'hostname ' . $device->device_name;
        }

        if ($device->ip_address) {
            $lines[] = 'interface vlan 1';
            $lines[] = '  ip address ' . $device->ip_address . ' ' . ($device->subnet_mask ?: '255.255.255.0');
            $lines[] = '  no shutdown';
        }

        if ($device->gateway) {
            $lines[] = 'ip default-gateway ' . $device->gateway;
        }

        // Append template/custom commands
        foreach ($commands as $cmd) {
            $lines[] = $cmd;
        }

        $lines[] = 'end';
        $lines[] = 'write';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Load commands from a JSON template file.
     */
    private function loadTemplateCommands(string $templateName, ?string $folder): array
    {
        $slug  = Str::slug($templateName);
        $paths = [];

        if ($folder) {
            $paths[] = 'templates/' . Str::slug($folder) . '/' . $slug . '.json';
        }
        $paths[] = 'templates/' . $slug . '.json';
        $paths[] = 'templates/general/' . $slug . '.json';

        foreach ($paths as $path) {
            if (Storage::disk('local')->exists($path)) {
                $data = json_decode(Storage::disk('local')->get($path), true);
                return $data['commands'] ?? [];
            }
        }

        // Fallback: search all template files
        $files = Storage::disk('local')->allFiles('templates');
        foreach ($files as $file) {
            if (basename($file) === $slug . '.json') {
                $data = json_decode(Storage::disk('local')->get($file), true);
                return $data['commands'] ?? [];
            }
        }

        return [];
    }

    /**
     * Load list of available templates from storage for form dropdowns.
     */
    private function loadAvailableTemplates(): array
    {
        $templates = [];
        try {
            if (Storage::disk('local')->exists('templates')) {
                $files = Storage::disk('local')->allFiles('templates');
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                        $content = Storage::disk('local')->get($file);
                        $data    = json_decode($content, true);
                        if ($data && isset($data['name'])) {
                            $parts = explode('/', $file);
                            $data['template_folder'] = count($parts) > 2 ? $parts[count($parts) - 2] : '';
                            $templates[] = $data;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('ZTP: Failed to load templates: ' . $e->getMessage());
        }
        return $templates;
    }
}
