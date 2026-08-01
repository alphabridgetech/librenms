<?php

namespace LibreNMS\Alert\Transport;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\Eventlog;
use Carbon\Carbon;
use LibreNMS\Alert\Transport;
use LibreNMS\Enum\AlertState;
use LibreNMS\Enum\Severity;

class Snmpforwarding extends Transport
{
    public function deliverAlert(array $alert_data): bool
    {
        $rawHosts = LibrenmsConfig::get('snmptrap_forward_host', '');
        $port = (int) LibrenmsConfig::get('snmptrap_forward_port', 162);

        if (empty($rawHosts)) {
            return false;
        }

        $targetHosts = array_filter(array_map('trim', preg_split('/[\s,]+/', $rawHosts)));
        if (empty($targetHosts)) {
            return false;
        }

        // Check if per-rule SNMP forwarding is enabled (default disabled / false)
        if (isset($alert_data['snmp_forward'])) {
            if (! (bool) $alert_data['snmp_forward']) {
                return false;
            }
        } elseif (! empty($alert_data['rule_id'])) {
            $extra_str = \dbFetchCell('SELECT extra FROM alert_rules WHERE id = ?', [$alert_data['rule_id']]);
            if (! empty($extra_str)) {
                $extra_arr = json_decode($extra_str, true);
                if (! (bool) ($extra_arr['snmp_forward'] ?? false)) {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        $device_id = $alert_data['device_id'] ?? 0;
        $device = Device::find($device_id);
        $deviceIp = $device ? ($device->ip ?: $device->hostname) : ($alert_data['hostname'] ?? 'unknown');
        $hostname = $device ? $device->hostname : ($alert_data['hostname'] ?? 'unknown');
        $ruleName = $alert_data['name'] ?? 'Alert Rule';

        $stateVal = match ((int) ($alert_data['state'] ?? 0)) {
            AlertState::ACTIVE => '1',
            AlertState::RECOVERED => '0',
            AlertState::ACKNOWLEDGED => '2',
            default => '1',
        };

        $stateText = match ((int) ($alert_data['state'] ?? 0)) {
            AlertState::ACTIVE => 'ACTIVE',
            AlertState::RECOVERED => 'RECOVERED',
            AlertState::ACKNOWLEDGED => 'ACKNOWLEDGED',
            default => 'ACTIVE',
        };

        $severityVal = match (strtolower($alert_data['severity'] ?? 'critical')) {
            'ok', 'clear' => '0',
            'warning' => '2',
            default => '1', // critical
        };

        if ((int) ($alert_data['state'] ?? 0) === AlertState::RECOVERED) {
            $severityVal = '0';
        }

        $fullRuleName = $ruleName . ' (State: ' . $stateText . ')';

        $uptimeTicks = ($device && $device->uptime > 0) ? (int) ($device->uptime * 100) : 0;
        $timestamp = Carbon::now()->format('Y M j H:i:s ');

        $anySuccess = false;
        foreach ($targetHosts as $host) {
            $targetIp = $host;
            if (! filter_var($targetIp, FILTER_VALIDATE_IP)) {
                $resolved_ip = gethostbyname($targetIp);
                if ($resolved_ip !== $targetIp) {
                    $targetIp = $resolved_ip;
                }
            }

            $cmd = sprintf(
                '/usr/bin/snmptrap -v 2c -c %s udp:%s:%d %d %s %s s %s %s s %s %s s %s %s s %s %s s %s %s s %s 2>&1',
                escapeshellarg('public'),
                escapeshellarg($targetIp),
                $port,
                $uptimeTicks,
                escapeshellarg('SNMPv2-SMI::enterprises.58158.9.188.6.1.0.6'),
                escapeshellarg('SNMPv2-SMI::enterprises.58158.9.188.1'), escapeshellarg($deviceIp),
                escapeshellarg('SNMPv2-SMI::enterprises.58158.9.188.2'), escapeshellarg($stateVal),
                escapeshellarg('SNMPv2-SMI::enterprises.58158.9.188.3'), escapeshellarg('admin'),
                escapeshellarg('SNMPv2-SMI::enterprises.58158.9.188.4'), escapeshellarg($timestamp),
                escapeshellarg('SNMPv2-SMI::enterprises.58158.9.188.5'), escapeshellarg($severityVal),
                escapeshellarg('SNMPv2-SMI::enterprises.58158.9.188.6'), escapeshellarg($fullRuleName)
            );

            $output = [];
            $retval = 0;
            exec($cmd, $output, $retval);

            if ($retval !== 0) {
                \Log::error("Failed to execute snmptrap for alert rule '$fullRuleName' to host '$host'. Exit code: $retval. Output: " . implode("\n", $output));
            } else {
                $anySuccess = true;
            }
        }

        if ($anySuccess) {
            // Log to Eventlog in exact trap JSON representation format
            $uptimeSec = ($device && $device->uptime > 0) ? (int) $device->uptime : 0;
            $days = (int) floor($uptimeSec / 86400);
            $hours = (int) floor(($uptimeSec % 86400) / 3600);
            $minutes = (int) floor(($uptimeSec % 3600) / 60);
            $secs = $uptimeSec % 60;
            $uptimeFormatted = sprintf('%d:%02d:%02d:%02d.00', $days, $hours, $minutes, $secs);

            $jsonPayload = json_encode([
                'DISMAN-EVENT-MIB::sysUpTimeInstance' => $uptimeFormatted,
                'SNMPv2-SMI::enterprises.58158.9.188.1' => (string) $deviceIp,
                'SNMPv2-SMI::enterprises.58158.9.188.2' => (string) $stateVal,
                'SNMPv2-SMI::enterprises.58158.9.188.3' => 'admin',
                'SNMPv2-SMI::enterprises.58158.9.188.4' => (string) $timestamp,
                'SNMPv2-SMI::enterprises.58158.9.188.5' => (string) $severityVal,
                'SNMPv2-SMI::enterprises.58158.9.188.6' => (string) $fullRuleName,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $eventlogMessage = 'SNMPv2-SMI::enterprises.58158.9.188.6.1.0.6 ' . $jsonPayload;

            $logSeverity = ($stateVal === '0') ? Severity::Ok : Severity::Error;
            Eventlog::log($eventlogMessage, $device_id, 'trap', $logSeverity);

            // Execute poller immediately for the device IP
            $targetIp = ! empty($deviceIp) ? $deviceIp : $device_id;
            if (! empty($targetIp)) {
                $pollerPath = base_path('poller.php');
                $cmd = sprintf('php %s -h %s > /dev/null 2>&1 &', escapeshellarg($pollerPath), escapeshellarg($targetIp));
                exec($cmd);
            }

            return true;
        }

        return false;
    }

    public static function configTemplate(): array
    {
        return [
            'config' => [],
            'validation' => [],
        ];
    }
}
