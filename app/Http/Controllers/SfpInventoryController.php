<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Transceiver;
use App\Models\EntPhysical;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class SfpInventoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->validate($request, [
            'device' => 'nullable|int',
            'vendor' => 'nullable|string',
            'type' => 'nullable|string',
            'model' => 'nullable|string',
            'serial' => 'nullable|string',
        ]);

        $user = $request->user();
        $accessibleDeviceIds = Device::hasAccess($user)->pluck('device_id')->toArray();

        // 1. Devices list for filter dropdown
        $devices = Device::hasAccess($user)
            ->select(['device_id', 'hostname', 'ip', 'sysName', 'display'])
            ->orderBy('hostname')
            ->get();

        $selectedDevice = null;
        $deviceSelectedData = '';
        if ($request->filled('device')) {
            $selectedDevice = $devices->firstWhere('device_id', (int) $request->get('device'));
            if ($selectedDevice) {
                $deviceSelectedData = ['id' => $selectedDevice->device_id, 'text' => $selectedDevice->displayName()];
            }
        }

        // 2. Compute KPI Metrics
        $transceiverQuery = Transceiver::whereIn('device_id', $accessibleDeviceIds);
        $totalTransceivers = (clone $transceiverQuery)->count();

        $totalSfpPlus = (clone $transceiverQuery)
            ->where(function ($q) {
                $q->where('type', 'LIKE', '%SFP+%')
                  ->orWhere('type', 'LIKE', '%10G%')
                  ->orWhere('model', 'LIKE', '%10G%');
            })->count();

        $totalVendors = (clone $transceiverQuery)
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->distinct('vendor')
            ->count('vendor');

        $deviceCountWithSfp = (clone $transceiverQuery)
            ->distinct('device_id')
            ->count('device_id');

        // Fallback KPI calculation from entPhysical if transceivers table is empty
        if ($totalTransceivers === 0) {
            $entSfpQuery = EntPhysical::whereIn('device_id', $accessibleDeviceIds)
                ->where(function ($q) {
                    $q->where('entPhysicalDescr', 'LIKE', '%SFP%')
                      ->orWhere('entPhysicalName', 'LIKE', '%SFP%')
                      ->orWhere('entPhysicalModelName', 'LIKE', '%SFP%')
                      ->orWhere('entPhysicalDescr', 'LIKE', '%transceiver%')
                      ->orWhere('entPhysicalName', 'LIKE', '%TGigaEthernet%')
                      ->orWhere('entPhysicalClass', 'container');
                });

            $totalTransceivers = (clone $entSfpQuery)->count();
            $totalSfpPlus = (clone $entSfpQuery)->where('entPhysicalName', 'LIKE', '%TGigaEthernet%')->count();
            $totalVendors = (clone $entSfpQuery)->whereNotNull('entPhysicalMfgName')->where('entPhysicalMfgName', '!=', '')->distinct('entPhysicalMfgName')->count('entPhysicalMfgName');
            $deviceCountWithSfp = (clone $entSfpQuery)->distinct('device_id')->count('device_id');
        }

        // 3. Unique Vendors & Types list for filters
        $vendors = Transceiver::whereIn('device_id', $accessibleDeviceIds)
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->distinct()
            ->pluck('vendor')
            ->sort()
            ->values()
            ->toArray();

        $types = Transceiver::whereIn('device_id', $accessibleDeviceIds)
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->pluck('type')
            ->sort()
            ->values()
            ->toArray();

        return view('sfp-inventory', [
            'devices' => $devices,
            'device_selected' => $deviceSelectedData,
            'vendors' => $vendors,
            'types' => $types,
            'stats' => [
                'total_sfps' => $totalTransceivers,
                'sfp_plus_count' => $totalSfpPlus,
                'vendors_count' => $totalVendors,
                'devices_count' => $deviceCountWithSfp,
            ],
            'filter' => [
                'device' => $request->get('device'),
                'vendor' => $request->get('vendor'),
                'type' => $request->get('type'),
                'model' => $request->get('model'),
                'serial' => $request->get('serial'),
            ],
        ]);
    }
}
