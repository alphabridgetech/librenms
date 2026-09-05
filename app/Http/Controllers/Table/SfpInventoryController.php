<?php

namespace App\Http\Controllers\Table;

use App\Models\Device;
use App\Models\Transceiver;
use App\Models\EntPhysical;

class SfpInventoryController extends TableController
{
    protected $model = Transceiver::class;

    public function rules()
    {
        return [
            'device' => 'nullable|int',
            'vendor' => 'nullable|string',
            'type' => 'nullable|string',
            'model' => 'nullable|string',
            'serial' => 'nullable|string',
        ];
    }

    protected function filterFields($request)
    {
        return [
            'device_id' => 'device',
        ];
    }

    protected function searchFields($request)
    {
        return [
            'vendor',
            'type',
            'model',
            'serial',
            'entPhysicalName',
            'entPhysicalDescr',
            'entPhysicalModelName',
            'entPhysicalSerialNum',
            'entPhysicalMfgName',
        ];
    }

    protected function sortFields($request)
    {
        return [
            'device' => 'device_id',
            'vendor' => 'vendor',
            'type' => 'type',
            'model' => 'model',
            'serial' => 'serial',
        ];
    }

    protected function baseQuery($request)
    {
        $user = $request->user();
        $accessibleDeviceIds = Device::hasAccess($user)->pluck('device_id')->toArray();

        $hasTransceivers = Transceiver::whereIn('device_id', $accessibleDeviceIds)->exists();

        if ($hasTransceivers) {
            $query = Transceiver::whereIn('device_id', $accessibleDeviceIds)
                ->with(['device', 'port']);

            if ($request->filled('vendor')) {
                $query->where('vendor', 'LIKE', '%' . $request->get('vendor') . '%');
            }

            if ($request->filled('type')) {
                $query->where('type', 'LIKE', '%' . $request->get('type') . '%');
            }

            if ($request->filled('model')) {
                $query->where('model', 'LIKE', '%' . $request->get('model') . '%');
            }

            if ($request->filled('serial')) {
                $query->where('serial', 'LIKE', '%' . $request->get('serial') . '%');
            }

            return $query;
        } else {
            $query = EntPhysical::whereIn('device_id', $accessibleDeviceIds)
                ->with('device')
                ->where(function ($q) {
                    $q->where('entPhysicalDescr', 'LIKE', '%SFP%')
                      ->orWhere('entPhysicalName', 'LIKE', '%SFP%')
                      ->orWhere('entPhysicalModelName', 'LIKE', '%SFP%')
                      ->orWhere('entPhysicalDescr', 'LIKE', '%transceiver%')
                      ->orWhere('entPhysicalName', 'LIKE', '%TGigaEthernet%')
                      ->orWhere('entPhysicalClass', 'container');
                });

            if ($request->filled('vendor')) {
                $query->where('entPhysicalMfgName', 'LIKE', '%' . $request->get('vendor') . '%');
            }

            if ($request->filled('model')) {
                $query->where('entPhysicalModelName', 'LIKE', '%' . $request->get('model') . '%');
            }

            if ($request->filled('serial')) {
                $query->where('entPhysicalSerialNum', 'LIKE', '%' . $request->get('serial') . '%');
            }

            return $query;
        }
    }

    protected function getExportHeaders()
    {
        return [
            'Device',
            'Port',
            'Vendor',
            'Type',
            'Model',
            'Serial Number',
            'Wavelength',
            'Distance',
            'Connector',
            'DDM',
        ];
    }

    public function formatItem($item)
    {
        if ($item instanceof Transceiver) {
            $deviceLink = $item->device
                ? '<a href="' . route('device', ['device' => $item->device->device_id]) . '">' . htmlspecialchars((string) $item->device->displayName()) . '</a>'
                : 'N/A';

            $portName = $item->port ? $item->port->ifName : ("Index: " . ($item->index ?? 'N/A'));
            $wavelengthStr = $item->wavelength ? "{$item->wavelength} nm" : 'N/A';
            $distanceStr = $item->distance ? "{$item->distance} m" : 'N/A';

            return [
                'device' => $deviceLink,
                'port' => htmlspecialchars((string) $portName),
                'vendor' => htmlspecialchars((string) ($item->vendor ?: 'N/A')),
                'type' => '<span class="label label-info">' . htmlspecialchars((string) ($item->type ?: 'SFP')) . '</span>',
                'model' => htmlspecialchars((string) ($item->model ?: 'N/A')),
                'serial' => htmlspecialchars((string) ($item->serial ?: 'N/A')),
                'wavelength' => htmlspecialchars((string) $wavelengthStr),
                'distance' => htmlspecialchars((string) $distanceStr),
                'connector' => htmlspecialchars((string) ($item->connector ?: 'LC')),
                'ddm' => $item->ddm ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>',
            ];
        } else {
            // EntPhysical model fallback
            /** @var EntPhysical $item */
            $deviceLink = $item->device
                ? '<a href="' . route('device', ['device' => $item->device->device_id]) . '">' . htmlspecialchars((string) $item->device->displayName()) . '</a>'
                : 'N/A';

            $isSfpPlus = str_contains((string) $item->entPhysicalName, 'TGiga') || str_contains((string) $item->entPhysicalDescr, '10G');
            $typeLabel = $isSfpPlus ? '<span class="label label-primary">SFP+ (10G)</span>' : '<span class="label label-info">SFP</span>';

            return [
                'device' => $deviceLink,
                'port' => htmlspecialchars((string) ($item->entPhysicalName ?: 'N/A')),
                'vendor' => htmlspecialchars((string) ($item->entPhysicalMfgName ?: 'Alpha Bridge')),
                'type' => $typeLabel,
                'model' => htmlspecialchars((string) ($item->entPhysicalModelName ?: ($item->entPhysicalDescr ?: 'SFP Transceiver'))),
                'serial' => htmlspecialchars((string) ($item->serial ?: 'N/A')),
                'wavelength' => 'N/A',
                'distance' => 'N/A',
                'connector' => 'LC',
                'ddm' => $item->entPhysicalIsFRU ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">Yes</span>',
            ];
        }
    }
}
