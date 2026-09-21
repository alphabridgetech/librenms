<?php

namespace App\Console\Commands;

use App\Models\Eventlog;
use App\Models\Port;
use App\Models\PortStatusAlert;
use Illuminate\Console\Command;
use LibreNMS\Enum\Severity;

class CheckPortStatusAlerts extends Command
{
    /**
     * One open row per (device_id, port_id) instead of the single bundled
     * per-device alert the generic alert_rules engine produces - so a port
     * going down while other ports on the same device are already down
     * shows up (and notifies) as its own event, not merged into theirs.
     * Mirrors the "Interface Down (Physical or Admin)" alert rule's own
     * matching condition, so this covers the same ports that rule does.
     *
     * @var string
     */
    protected $signature = 'port:check-status-alerts';

    /**
     * @var string
     */
    protected $description = 'Open or close per-port down alerts, one row per port instead of one bundled alert per device.';

    public function handle()
    {
        $downPorts = Port::query()
            ->join('devices', 'devices.device_id', '=', 'ports.device_id')
            ->where('devices.status', 1)
            ->where('ports.ifOperStatus', 'down')
            ->where('ports.deleted', 0)
            ->where('ports.disabled', 0)
            ->where('ports.ignore', 0)
            ->get(['ports.port_id', 'ports.device_id', 'ports.ifName', 'ports.ifDescr', 'ports.ifAlias']);

        $downPortIds = $downPorts->pluck('port_id');

        $openAlertsByPort = PortStatusAlert::where('open', true)->get()->keyBy('port_id');

        $opened = 0;
        foreach ($downPorts as $port) {
            if ($openAlertsByPort->has($port->port_id)) {
                continue;
            }

            PortStatusAlert::create([
                'device_id' => $port->device_id,
                'port_id' => $port->port_id,
                'ifname' => $port->ifName,
                'ifdescr' => $port->ifDescr,
                'ifalias' => $port->ifAlias,
                'open' => true,
                'down_at' => now(),
            ]);

            $portName = $port->ifName ?: ($port->ifDescr ?: "port #{$port->port_id}");
            Eventlog::log("Port down: {$portName}", $port->device_id, 'interface', Severity::Error, $port->port_id);
            $this->info("Opened port-down alert: device #{$port->device_id} {$portName}");
            $opened++;
        }

        $closed = 0;
        foreach ($openAlertsByPort as $portId => $alert) {
            if ($downPortIds->contains($portId)) {
                continue;
            }

            $alert->update(['open' => false, 'recovered_at' => now()]);

            $portName = $alert->ifname ?: ($alert->ifdescr ?: "port #{$portId}");
            Eventlog::log("Port recovered: {$portName}", $alert->device_id, 'interface', Severity::Ok, $portId);
            $this->info("Closed port-down alert: device #{$alert->device_id} {$portName}");
            $closed++;
        }

        $this->info("Port status check complete: {$opened} opened, {$closed} recovered, " . $downPorts->count() . ' currently down.');

        return 0;
    }
}
