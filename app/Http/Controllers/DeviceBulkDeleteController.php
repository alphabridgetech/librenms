<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /delhost/bulk - deletes a few devices per call. The Delete Device page
 * sends the selection in small batches so it can show progress and never
 * runs into a request timeout, whatever the number of devices.
 */
class DeviceBulkDeleteController extends Controller
{
    public const BATCH = 5;

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->user()->isDemo()) {
            return response()->json(['message' => 'Not allowed for the demo account'], 403);
        }

        $ids = $request->validate([
            'ids'   => 'required|array|min:1|max:' . self::BATCH,
            'ids.*' => 'integer',
        ])['ids'];

        set_time_limit(0);
        $found   = Device::whereIn('device_id', $ids)->get()->keyBy('device_id');
        $results = [];

        foreach (array_unique($ids) as $id) {
            $device = $found->get((int) $id);

            if (! $device) {
                // Already gone (deleted elsewhere or a retried batch) - nothing left to do.
                $results[] = ['id' => (int) $id, 'hostname' => null, 'ok' => true, 'message' => 'Already removed'];
                continue;
            }

            try {
                $ok = (bool) $device->delete();
            } catch (\Throwable $e) {
                report($e);
                $ok = false;
            }

            $results[] = [
                'id'       => $device->device_id,
                'hostname' => $device->hostname,
                'ok'       => $ok,
                'message'  => $ok ? 'Removed' : 'Failed to remove',
            ];
        }

        return response()->json(['results' => $results]);
    }
}
