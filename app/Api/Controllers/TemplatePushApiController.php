<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Traits\HandlesPushConfiguration;
use App\Models\Device;
use App\Services\PushTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * API for the Template Push page (/addhost/template)
 */
class TemplatePushApiController
{
    use HandlesPushConfiguration;

    /**
     * GET /api/v0/templates
     */
    public function index(Request $request): JsonResponse
    {
        $templates = collect(PushTemplates::all())->map(fn ($t) => [
            'name' => $t['name'] ?? '',
            'folder' => $t['template_folder'],
            'type' => $t['type'] ?? 'custom',
            'hardware_models' => $t['hardware_models'] ?? [],
        ]);

        if ($request->filled('device')) {
            $device = $this->findDevice($request->input('device'));
            if (! $device) {
                return $this->error('Device not found', 404);
            }
            $templates = $templates->filter(fn ($t) => PushTemplates::matchesHardware($t, $device->hardware))->values();
        }

        return response()->json(['status' => 'ok', 'count' => $templates->count(), 'templates' => $templates]);
    }

    /**
     * GET /api/v0/templates/{name}?folder=
     */
    public function show(Request $request, string $name): JsonResponse
    {
        $template = PushTemplates::find($name, $request->input('folder'));
        if (! $template) {
            return $this->error('Template not found', 404);
        }

        $result = [
            'name' => $template['name'],
            'folder' => $template['template_folder'],
            'type' => $template['type'] ?? 'custom',
            'hardware_models' => $template['hardware_models'] ?? [],
        ];

        if (($template['type'] ?? '') === 'form') {
            $result['fields'] = PushTemplates::describeFields($template);
        } else {
            $result['pvid'] = $template['pvid'] ?? null;
            $result['commands'] = $template['commands'] ?? [];
        }

        return response()->json(['status' => 'ok', 'template' => $result]);
    }

    /**
     * POST /api/v0/templates/push
     *
     * {"device": "192.168.200.239", "template": "...", "interfaces": ["GigaEthernet0/1"], "values": {...}, "dry_run": true}
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'device' => 'required_without:devices',
            'devices' => 'array',
            'template' => 'required_without:commands|string',
            'folder' => 'nullable|string',
            'commands' => 'array',
            'interfaces' => 'array',
            'values' => 'array',
            'port_mode' => 'nullable|in:access,trunk,custom',
            'pvid' => 'nullable|integer|min:1|max:4094',
            'dry_run' => 'boolean',
        ]);

        $devices = [];
        foreach ((array) ($request->input('devices') ?? [$request->input('device')]) as $search) {
            $device = $this->findDevice($search);
            if (! $device) {
                return $this->error("Device not found: $search", 404);
            }
            $devices[] = $device;
        }

        $interfaces = $request->input('interfaces', []);
        $template = null;

        if ($request->filled('template')) {
            $template = PushTemplates::find($request->input('template'), $request->input('folder'));
            if (! $template) {
                return $this->error('Template not found', 404);
            }

            foreach ($devices as $device) {
                if (! PushTemplates::matchesHardware($template, $device->hardware)) {
                    return $this->error("Template \"{$template['name']}\" is for " . implode(', ', $template['hardware_models']) . ", device {$device->hostname} is {$device->hardware}", 422);
                }
            }

            if (in_array($request->input('port_mode', $template['type'] ?? ''), ['access', 'trunk']) && empty($interfaces)) {
                return $this->error('interfaces are required for access/trunk templates', 422);
            }

            try {
                $commands = PushTemplates::buildCommands($template, $request->input('values', []), $interfaces, $request->only(['port_mode', 'pvid', 'commands']));
            } catch (InvalidArgumentException $e) {
                return $this->error($e->getMessage(), 422);
            }
        } else {
            $commands = array_values(array_filter(array_map('trim', $request->input('commands', [])), fn ($c) => $c !== ''));
        }

        if (empty($commands)) {
            return $this->error('No commands to push', 422);
        }

        $ips = array_map(fn (Device $d) => $d->overwrite_ip ?: $d->hostname, $devices);

        if ($request->boolean('dry_run')) {
            return response()->json(['status' => 'ok', 'dry_run' => true, 'devices' => $ips, 'commands' => $commands]);
        }

        // the push logs and remembered form values use the logged in user
        Auth::setUser($request->user());

        $request->merge([
            'valid_ips' => json_encode($ips),
            'direct_commands' => implode("\n", $commands),
            'loaded_template_name' => $template['name'] ?? null,
            'template_folder' => $template['template_folder'] ?? '',
            'field_values' => $template ? json_encode($request->input('values', [])) : null,
            'selected_interfaces' => $interfaces ?: null,
        ]);

        $response = $this->processPushNetworkCommand($request);
        $data = $response->getData(true);
        $data['status'] = ($data['success'] ?? false) ? 'ok' : 'error';
        $data['commands'] = $commands;

        return response()->json($data, $response->getStatusCode());
    }

    private function findDevice($search): ?Device
    {
        if (is_numeric($search)) {
            return Device::find((int) $search);
        }

        return Device::where('hostname', $search)->orWhere('overwrite_ip', $search)->orWhere('sysName', $search)->first();
    }

    private function error(string $message, int $code): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $code);
    }
}
