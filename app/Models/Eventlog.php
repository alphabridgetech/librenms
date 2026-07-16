<?php

/**
 * Eventlog.php
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

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use LibreNMS\Enum\Severity;

class Eventlog extends DeviceRelatedModel
{
    protected $table = 'eventlog';
    protected $primaryKey = 'event_id';
    public $timestamps = false;
    protected $fillable = ['datetime', 'device_id', 'message', 'type', 'reference', 'username', 'severity'];

    /**
     * @return array{severity: 'LibreNMS\Enum\Severity'}
     */
    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
        ];
    }

    // ---- Helper Functions ----
    /**
     * This is used to be able to mock _log()
     *
     * @see _log()
     *
     * @param  string  $text  message describing the event
     * @param  Device|int|null  $device  related device
     * @param  string  $type  brief category for this event. Examples: sensor, state, stp, system, temperature, interface
     * @param  Severity  $severity  1: ok, 2: info, 3: notice, 4: warning, 5: critical, 0: unknown
     * @param  int|string|null  $reference  the id of the referenced entity.  Supported types: interface
     */
    public static function log(string $text, Device|int|null $device = null, ?string $type = null, Severity $severity = Severity::Info, int|string|null $reference = null): void
    {
        $model = app()->make(Eventlog::class);
        $model->_log($text, $device, $type, $severity, $reference);
    }

    /**
     * Log events to the event table
     */
    public function _log(string $text, Device|int|null $device = null, ?string $type = null, Severity $severity = Severity::Info, int|string|null $reference = null): void
    {
        $log = new static([
            'reference' => $reference,
            'type' => $type,
            'datetime' => Carbon::now(),
            'severity' => $severity,
            'message' => $text,
            'username' => (class_exists('\Auth') && Auth::check()) ? Auth::user()->username : '',
        ]);

        if (is_numeric($device)) {
            $log->device_id = $device;
        }

        if ($device instanceof Device) {
            $device->eventlogs()->save($log);
        } else {
            $log->save();
        }

        // Real-time Syslog Forwarding
        if (\App\Facades\LibrenmsConfig::has('eventlog_forward_syslog_host')) {
            $this->forwardSingleToSyslog($log, $device);
        }
    }

    protected function forwardSingleToSyslog(Eventlog $log, Device|int|null $device): void
    {
        $ip = \App\Facades\LibrenmsConfig::get('eventlog_forward_syslog_host');
        $port = (int) \App\Facades\LibrenmsConfig::get('eventlog_forward_syslog_port', 514);

        if (empty($ip)) {
            return;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            // Validate hostname
            if (! preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $ip) && ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/i', $ip)) {
                return;
            }
            $resolved_ip = gethostbyname($ip);
            if ($resolved_ip === $ip) {
                return;
            }
            $ip = $resolved_ip;
        }

        if (($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) !== false) {
            $priority = 24 + match ($log->severity) {
                Severity::Ok => 6,
                Severity::Info => 6,
                Severity::Notice => 5,
                Severity::Warning => 4,
                Severity::Error => 3,
                default => 6,
            };

            $deviceModel = null;
            if ($device instanceof \App\Models\Device) {
                $deviceModel = $device;
            } elseif (is_numeric($device)) {
                $deviceModel = \App\Models\Device::find($device);
            }

            $hostname = $deviceModel ? $deviceModel->hostname : 'localhost';
            $username = $log->username ? " [user: {$log->username}]" : "";
            $timestamp = Carbon::parse($log->datetime)->format('M d H:i:s');
            $syslog_msg = "<{$priority}>{$timestamp} {$hostname} telequill_eventlog[{$log->type}]: {$log->message}{$username}";

            socket_sendto($socket, $syslog_msg, strlen($syslog_msg), 0, $ip, $port);
            socket_close($socket);
        }
    }

    // ---- Define Relationships ----

    public function related(): MorphTo
    {
        return $this->morphTo('related', 'type', 'reference');
    }
}
