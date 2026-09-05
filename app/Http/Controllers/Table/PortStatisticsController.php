<?php

namespace App\Http\Controllers\Table;

use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use LibreNMS\Util\Number;
use LibreNMS\Util\Rewrite;

/**
 * Feeds the bootgrid on the port "Statistics" tab (includes/html/pages/device/port/statistics.inc.php).
 *
 * Shows the same fields/computations as the global /ports list page
 * (Table\PortsController::formatItem()/formatExportRow()), scoped to a
 * single port, pivoted into one {field, value} row per stat.
 */
class PortStatisticsController
{
    public const COLUMNS = [
        'device' => 'Device',
        'port' => 'Port',
        'secondsIfLastChange' => 'Status Changed',
        'ifConnectorPresent' => 'Connected',
        'ifSpeed' => 'Speed',
        'ifMtu' => 'MTU',
        'ifInOctets_rate' => 'In',
        'ifOutOctets_rate' => 'Out',
        'ifInUcastPkts_rate' => 'Packets In',
        'ifOutUcastPkts_rate' => 'Packets Out',
        'ifInErrors_delta' => 'Errors In Rate',
        'ifOutErrors_delta' => 'Errors Out Rate',
        'ifInErrors' => 'Errors In',
        'ifOutErrors' => 'Errors Out',
        'ifType' => 'Media',
        'ifAlias' => 'Description',
    ];

    public function __invoke(Request $request)
    {
        $request->validate([
            'port_id' => 'required|integer',
        ]);

        $port = $this->findPort($request);

        if (! $port) {
            return response()->json(['current' => 1, 'rowCount' => 0, 'rows' => [], 'total' => 0]);
        }

        $values = $this->values($port);
        $rows = [];
        foreach (self::COLUMNS as $key => $label) {
            $rows[] = ['field' => $label, 'value' => $values[$key]];
        }

        return response()->json([
            'current' => 1,
            'rowCount' => count($rows),
            'rows' => $rows,
            'total' => count($rows),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'port_id' => 'required|integer',
        ]);

        $port = $this->findPort($request);
        $filename = 'port-' . ($port->ifName ?? $request->get('port_id')) . '-statistics-' . date('Y-m-d-His') . '.csv';

        $headers = [
            'Device ID',
            'Hostname',
            'Port',
            'ifIndex',
            'Status',
            'Admin Status',
            'Speed',
            'MTU',
            'Type',
            'In Rate (bps)',
            'Out Rate (bps)',
            'In Errors',
            'Out Errors',
            'In Error Rate',
            'Out Error Rate',
            'Description',
            'Last Change',
            'Connector Present',
        ];

        $row = $port ? [
            $port->device_id,
            $port->device ? $port->device->displayName() : '',
            $port->ifName ?: $port->ifDescr,
            $port->ifIndex,
            $port->ifOperStatus,
            $port->ifAdminStatus,
            Number::formatSi($port->ifSpeed),
            $port->ifMtu,
            Rewrite::normalizeIfType($port->ifType),
            Number::formatBi($port->ifInOctets_rate * 8) . 'bps',
            Number::formatBi($port->ifOutOctets_rate * 8) . 'bps',
            $port->ifInErrors,
            $port->ifOutErrors,
            $port->poll_period ? Number::formatSi($port->ifInErrors_delta / $port->poll_period, 2, 0, 'EPS') : '',
            $port->poll_period ? Number::formatSi($port->ifOutErrors_delta / $port->poll_period, 2, 0, 'EPS') : '',
            $port->ifAlias,
            $port->device ? ($port->device->uptime - ($port->ifLastChange / 100)) : 'N/A',
            ($port->ifConnectorPresent == 'true') ? 'yes' : 'no',
        ] : array_fill(0, count($headers), '');

        return response()->stream(function () use ($headers, $row) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, $headers);
            fputcsv($output, $row);
            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function findPort(Request $request): ?Port
    {
        return Port::hasAccess($request->user())->with('device')->find($request->get('port_id'));
    }

    private function values(Port $port): array
    {
        return [
            'device' => $port->device ? Blade::render('<x-device-link :device="$device" />', ['device' => $port->device]) : 'N/A',
            'port' => Blade::render('<x-port-link :port="$port"/>', ['port' => $port]),
            'secondsIfLastChange' => $port->device ? ceil($port->device->uptime - ($port->ifLastChange / 100)) : null,
            'ifConnectorPresent' => ($port->ifConnectorPresent == 'true') ? 'yes' : 'no',
            'ifSpeed' => Number::formatSi($port->ifSpeed, 2, 0, 'bps'),
            'ifMtu' => $port->ifMtu,
            'ifInOctets_rate' => Number::formatSi($port->ifInOctets_rate * 8, 2, 0, 'bps'),
            'ifOutOctets_rate' => Number::formatSi($port->ifOutOctets_rate * 8, 2, 0, 'bps'),
            'ifInUcastPkts_rate' => Number::formatSi($port->ifInUcastPkts_rate, 2, 0, 'pps'),
            'ifOutUcastPkts_rate' => Number::formatSi($port->ifOutUcastPkts_rate, 2, 0, 'pps'),
            'ifInErrors_delta' => $port->poll_period ? Number::formatSi($port->ifInErrors_delta / $port->poll_period, 2, 0, 'EPS') : '',
            'ifOutErrors_delta' => $port->poll_period ? Number::formatSi($port->ifOutErrors_delta / $port->poll_period, 2, 0, 'EPS') : '',
            'ifInErrors' => $port->ifInErrors,
            'ifOutErrors' => $port->ifOutErrors,
            'ifType' => Rewrite::normalizeIfType($port->ifType),
            'ifAlias' => htmlentities((string) $port->ifAlias),
        ];
    }
}
