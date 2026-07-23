<?php

/**
 * EventlogController.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Table;

use App\Facades\LibrenmsConfig;
use App\Models\Eventlog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use LibreNMS\Enum\Severity;
use LibreNMS\Util\Url;

class EventlogController extends TableController
{
    protected $model = Eventlog::class;

    public function rules()
    {
        return [
            'device' => 'nullable|int',
            'device_group' => 'nullable|int',
            'eventtype' => 'nullable|string',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function searchFields($request)
    {
        return ['message'];
    }

    protected function filterFields($request)
    {
        return [
            'device_id' => 'device',
            'type' => 'eventtype',
        ];
    }

    protected function sortFields($request)
    {
        return ['datetime', 'type', 'device_id', 'message', 'username'];
    }

    /**
     * Defines the base query for this resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function baseQuery($request)
    {
        return Eventlog::hasAccess($request->user())
            ->with('device')
            ->when($request->device_group, function ($query) use ($request) {
                $query->inDeviceGroup($request->device_group);
            })
            ->when($request->start_date, function ($query) use ($request) {
                $query->where('datetime', '>=', $request->start_date . ' 00:00:00');
            })
            ->when($request->end_date, function ($query) use ($request) {
                $query->where('datetime', '<=', $request->end_date . ' 23:59:59');
            });
    }

    protected function getExportHeaders()
    {
        return [
            'Timestamp',
            'Type',
            'Device',
            'Message',
            'User',
        ];
    }

    protected function formatExportRow($eventlog)
    {
        return [
            (new Carbon($eventlog->datetime))->setTimezone(session('preferences.timezone') ?? config('app.timezone'))->format(LibrenmsConfig::get('dateformat.compact')),
            $eventlog->type,
            $eventlog->device ? $eventlog->device->displayName() : 'unknown',
            $eventlog->message,
            $eventlog->username ?: 'System',
        ];
    }

    /**
     * @param  Eventlog  $eventlog
     */
    public function formatItem($eventlog)
    {
        return [
            'datetime' => $this->formatDatetime($eventlog),
            'device_id' => Blade::render('<x-device-link :device="$device"/>', ['device' => $eventlog->device]),
            'type' => $this->formatType($eventlog),
            'message' => htmlspecialchars($eventlog->message),
            'username' => $eventlog->username ?: 'System',
        ];
    }

    private function formatType($eventlog)
    {
        if ($eventlog->type == 'interface') {
            if (is_numeric($eventlog->reference)) {
                $port = $eventlog->related;
                if (isset($port)) {
                    return Blade::render('<b><x-port-link :port="$port">{{ $port->getShortLabel() }}</x-port-link></b>', ['port' => $port]);
                }
            }
        } elseif ($eventlog->type == 'stp') {
            return Blade::render('<x-device-link :device="$device" tab="stp">stp</x-device-link>', ['device' => $eventlog->device]);
        } elseif (in_array($eventlog->type, \LibreNMS\Enum\Sensor::values())) {
            if (is_numeric($eventlog->reference)) {
                $sensor = $eventlog->related;
                if (isset($sensor)) {
                    return '<b>' . Url::sensorLink($sensor, $sensor->sensor_descr) . '</b>';
                }
            }
        }

        return htmlspecialchars($eventlog->type);
    }

    private function formatDatetime($eventlog)
    {
        $output = "<span class='alert-status ";
        $output .= $this->severityLabel($eventlog->severity);
        $output .= " eventlog-status'></span>";
        $output .= (new Carbon($eventlog->datetime))->setTimezone(session('preferences.timezone')?? config('app.timezone'))->format(LibrenmsConfig::get('dateformat.compact'));

        return $output;
    }

    /**
     * @param  Severity  $eventlog_severity
     * @return string $eventlog_severity_icon
     */
    private function severityLabel($eventlog_severity)
    {
        return match ($eventlog_severity) {
            Severity::Ok => 'label-success',
            Severity::Info => 'label-info',
            Severity::Notice => 'label-primary',
            Severity::Warning => 'label-warning',
            Severity::Error => 'label-danger',
            default => 'label-default', // Unknown
        };
    }

    public function forward(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'syslog_ip' => 'required|string',
            'syslog_port' => 'required|integer|between:1,65535',
        ]);

        $ip = $request->input('syslog_ip');
        $port = (int) $request->input('syslog_port');

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            // Check if it is a valid hostname
            if (! preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $ip) && ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/i', $ip)) {
                return response()->json(['success' => false, 'message' => 'Invalid IP address or hostname format.'], 422);
            }
            $resolved_ip = gethostbyname($ip);
            if ($resolved_ip === $ip) {
                return response()->json(['success' => false, 'message' => 'Hostname found but does not resolve to an IP.'], 422);
            }
        }

        // Persist settings to database config table
        LibrenmsConfig::persist('eventlog_forward_syslog_host', $request->input('syslog_ip'));
        LibrenmsConfig::persist('eventlog_forward_syslog_port', $port);

        return response()->json([
            'success' => true,
            'message' => "Syslog server configuration saved successfully."
        ]);
    }

    public function testForward(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'syslog_ip' => 'required|string',
            'syslog_port' => 'required|integer|between:1,65535',
        ]);

        $ip = $request->input('syslog_ip');
        $port = (int) $request->input('syslog_port');

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            // Check if it is a valid hostname
            if (! preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $ip) && ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/i', $ip)) {
                return response()->json(['success' => false, 'message' => 'Invalid IP address or hostname format.'], 422);
            }
            $resolved_ip = gethostbyname($ip);
            if ($resolved_ip === $ip) {
                return response()->json(['success' => false, 'message' => 'Hostname found but does not resolve to an IP.'], 422);
            }
            $ip = $resolved_ip;
        }

        if (($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) === false) {
            $errorCode = socket_last_error();
            $errorMsg = socket_strerror($errorCode);
            return response()->json(['success' => false, 'message' => "Socket creation failed: $errorMsg"], 500);
        }

        $priority = 24 + 6; // facility: daemon (3), severity: info (6) => 3 * 8 + 6 = 30
        $timestamp = Carbon::now()->format('M d H:i:s');
        $syslog_msg = "<{$priority}>{$timestamp} localhost telequill_eventlog[test]: This is a test syslog message from Telequill.";

        if (socket_sendto($socket, $syslog_msg, strlen($syslog_msg), 0, $ip, $port) === false) {
            $errorCode = socket_last_error($socket);
            $errorMsg = socket_strerror($errorCode);
            socket_close($socket);
            return response()->json(['success' => false, 'message' => "Failed to send test message: $errorMsg"], 500);
        }

        socket_close($socket);

        return response()->json([
            'success' => true,
            'message' => "Test syslog packet sent successfully to {$request->input('syslog_ip')}:{$port}."
        ]);
    }
}
