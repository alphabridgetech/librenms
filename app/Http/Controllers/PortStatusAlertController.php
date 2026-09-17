<?php

namespace App\Http\Controllers;

use App\Models\PortStatusAlert;

class PortStatusAlertController extends Controller
{
    /**
     * Currently-open per-port alerts, for the toast/sound notification
     * poller in layouts/librenmsv1.blade.php - each row here is its own
     * distinct down event (unlike /ajax/alerts-api, which is keyed by the
     * bundled per-device alert_rules alert and never re-fires when a
     * sibling port on an already-alerting device also goes down).
     */
    public function apiOpen()
    {
        $alerts = PortStatusAlert::with('device:device_id,hostname,sysName')
            ->where('open', true)
            ->orderByDesc('down_at')
            ->limit(10)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'hostname' => $alert->device?->sysName ?: ($alert->device?->hostname ?: "device #{$alert->device_id}"),
                    'ifname' => $alert->ifname ?: $alert->ifdescr,
                    'down_at' => $alert->down_at,
                ];
            });

        return response()->json([
            'total' => $alerts->count(),
            'alerts' => $alerts,
        ]);
    }
}
