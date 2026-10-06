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
        $sysName = $device ? ($device->sysName ?: $device->hostname) : ($alert_data['hostname'] ?? 'unknown');
        $hostname = $device ? $device->hostname : ($alert_data['hostname'] ?? 'unknown');
        $ruleName = $alert_data['name'] ?? 'Alert Rule';

        $stateVal = match ((int) ($alert_data['state'] ?? 0)) {
            AlertState::ACTIVE, AlertState::WORSE, AlertState::CHANGED => '1',
            AlertState::RECOVERED, AlertState::BETTER => '0',
            AlertState::ACKNOWLEDGED => '2',
            default => '1',
        };

        $stateText = match ((int) ($alert_data['state'] ?? 0)) {
            AlertState::ACTIVE, AlertState::WORSE, AlertState::CHANGED => 'ACTIVE',
            AlertState::RECOVERED, AlertState::BETTER => 'RECOVERED',
            AlertState::ACKNOWLEDGED => 'ACKNOWLEDGED',
            default => 'ACTIVE',
        };

        // Determine Severity String (Critical/Major/Minor/Warning/Info/Clear)
        if (in_array((int) ($alert_data['state'] ?? 0), [AlertState::RECOVERED, AlertState::BETTER], true)) {
            $severityText = 'Clear';
            $severityVal = '0';
        } else {
            $sevInput = strtolower($alert_data['severity'] ?? 'critical');
            $severityText = match ($sevInput) {
                'ok', 'clear' => 'Clear',
                'info' => 'Info',
                'warning' => 'Warning',
                'minor' => 'Minor',
                'major' => 'Major',
                'critical' => 'Critical',
                default => 'Critical',
            };
            $severityVal = match ($sevInput) {
                'ok', 'clear' => '0',
                'warning' => '2',
                'minor' => '3',
                'major' => '4',
                'info' => '5',
                default => '1', // critical
            };
        }

        // Determine Object Type (PTP/CTP/Manage Element/Port/Link)
        $ruleLower = strtolower($ruleName . ' ' . ($alert_data['type'] ?? ''));
        if (str_contains($ruleLower, 'ptp') || str_contains($ruleLower, 'clock') || str_contains($ruleLower, 'timing')) {
            $objectType = 'PTP';
        } elseif (str_contains($ruleLower, 'ctp') || str_contains($ruleLower, 'channel')) {
            $objectType = 'CTP';
        } elseif (str_contains($ruleLower, 'port') || str_contains($ruleLower, 'interface') || str_contains($ruleLower, 'ifoperstatus')) {
            $objectType = 'Port';
        } elseif (str_contains($ruleLower, 'link')) {
            $objectType = 'Link';
        } else {
            // Default for device down / equipment alarms
            $objectType = 'Manage Element';
        }

        // Collect the faulted ports, one trap is sent per port so every port gets its own alarm/clear
        $ports = [];
        $otherFaults = [];
        foreach ((array) ($alert_data['faults'] ?? []) as $fault) {
            if (! is_array($fault)) {
                continue;
            }

            $port = $this->portFromFault($fault, (int) $device_id);
            if ($port !== null) {
                $ports[$port['ifIndex']] = $port;
            } elseif (! empty($fault['string'])) {
                $otherFaults[] = $fault['string'];
            }
        }

        $uptimeTicks = ($device && $device->uptime > 0) ? (int) ($device->uptime * 100) : 0;
        $timestamp = Carbon::now()->format('Y M j H:i:s ');

        $traps = [];
        foreach ($ports as $port) {
            $traps[] = [$port, $ruleName . ' [' . ($port['ifDescr'] ?? 'Interface ' . $port['ifIndex']) . '] (State: ' . $stateText . ')'];
        }
        if (empty($traps)) {
            $faultSummary = empty($otherFaults) ? '' : ' [' . implode(', ', array_unique($otherFaults)) . ']';
            $traps[] = [null, $ruleName . $faultSummary . ' (State: ' . $stateText . ')'];
        }

        $anySuccess = false;
        foreach ($traps as [$port, $fullRuleName]) {
            // Base varbinds payload with IF-MIB attributes inserted directly after sysName (.188.2)
            $varbinds = [
                'SNMPv2-SMI::enterprises.58158.9.188.1' => (string) $deviceIp,
                'SNMPv2-SMI::enterprises.58158.9.188.2' => (string) $sysName,
            ];

            if ($port !== null) {
                $idx = $port['ifIndex'];
                foreach (['ifIndex', 'ifDescr', 'ifType', 'ifAdminStatus', 'ifOperStatus'] as $field) {
                    if (isset($port[$field])) {
                        $varbinds["IF-MIB::{$field}.{$idx}"] = (string) $port[$field];
                    }
                }
            }

            $varbinds['SNMPv2-SMI::enterprises.58158.9.188.3'] = (string) $stateText;
            $varbinds['SNMPv2-SMI::enterprises.58158.9.188.4'] = (string) $objectType;
            $varbinds['SNMPv2-SMI::enterprises.58158.9.188.5'] = (string) $severityText;
            $varbinds['SNMPv2-SMI::enterprises.58158.9.188.6'] = (string) $timestamp;
            $varbinds['SNMPv2-SMI::enterprises.58158.9.188.7'] = (string) $fullRuleName;

            if ($this->sendTrap($targetHosts, $port, $uptimeTicks, $varbinds, $fullRuleName)) {
                $anySuccess = true;
                $this->logTrap($device, (int) $device_id, $stateVal, $varbinds);
            }
        }

        if ($anySuccess) {
            // Execute poller immediately for the device IP
            $targetIp = ! empty($deviceIp) ? $deviceIp : $device_id;
            if (! empty($targetIp)) {
                $pollerPath = base_path('poller.php');
                // snmptrapd runs as root, poll as librenms so the rrd files stay writable for the dispatcher
                $asUser = function_exists('posix_geteuid') && posix_geteuid() === 0 ? 's6-setuidgid librenms ' : '';
                $cmd = sprintf('%sphp %s -h %s > /dev/null 2>&1 &', $asUser, escapeshellarg($pollerPath), escapeshellarg($targetIp));
                exec($cmd);
            }

            return true;
        }

        return false;
    }

    /**
     * Build the IF-MIB attributes of a faulted port. Current values from the ports table win over the
     * values stored in the alert, those can be stale (e.g. recovery uses the details of the original alert).
     * Unknown attributes are left out instead of guessed.
     */
    private function portFromFault(array $fault, int $device_id): ?array
    {
        $values = [];
        foreach ($fault as $k => $v) {
            if ($v !== null && $v !== '') {
                $values[strtolower(str_replace(['ports.', 'ports_'], '', (string) $k))] = $v;
            }
        }

        $dbRow = null;
        if (isset($values['port_id']) && is_numeric($values['port_id'])) {
            $dbRow = \dbFetchRow('SELECT ifIndex, ifDescr, ifName, ifAlias, ifType, ifAdminStatus, ifOperStatus FROM ports WHERE port_id = ?', [(int) $values['port_id']]);
        } elseif (isset($values['ifindex']) && is_numeric($values['ifindex']) && $device_id) {
            $dbRow = \dbFetchRow('SELECT ifIndex, ifDescr, ifName, ifAlias, ifType, ifAdminStatus, ifOperStatus FROM ports WHERE device_id = ? AND ifIndex = ?', [$device_id, $values['ifindex']]);
        }
        $dbRow = array_filter((array) $dbRow, fn ($v) => $v !== null && $v !== '');

        $ifIndex = $dbRow['ifIndex'] ?? (isset($values['ifindex']) && is_numeric($values['ifindex']) ? $values['ifindex'] : null);
        if ($ifIndex === null) {
            return null;
        }

        return array_filter([
            'ifIndex' => (string) $ifIndex,
            'ifDescr' => $dbRow['ifDescr'] ?? $dbRow['ifName'] ?? $dbRow['ifAlias'] ?? $values['ifdescr'] ?? $values['ifname'] ?? $values['ifalias'] ?? null,
            'ifType' => $dbRow['ifType'] ?? $values['iftype'] ?? null,
            'ifAdminStatus' => $dbRow['ifAdminStatus'] ?? $values['ifadminstatus'] ?? null,
            'ifOperStatus' => $dbRow['ifOperStatus'] ?? $values['ifoperstatus'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function sendTrap(array $targetHosts, ?array $port, int $uptimeTicks, array $varbinds, string $fullRuleName): bool
    {
        $anySuccess = false;
        foreach ($targetHosts as $host) {
            $targetIp = $host;
            if (! filter_var($targetIp, FILTER_VALIDATE_IP)) {
                $resolved_ip = gethostbyname($targetIp);
                if ($resolved_ip !== $targetIp) {
                    $targetIp = $resolved_ip;
                }
            }

            $cmdArgs = [
                '/usr/bin/snmptrap',
                '-v', '2c',
                '-c', 'public',
                sprintf('udp:%s:%d', $targetIp, $this->port()),
                (string) $uptimeTicks,
                'SNMPv2-SMI::enterprises.58158.9.188.6.1.0.6',
            ];

            foreach ($varbinds as $oid => $val) {
                $cmdArgs[] = $oid;
                $type = 's';
                if (preg_match('/IF-MIB::(ifIndex|ifType|ifAdminStatus|ifOperStatus)\./i', $oid)) {
                    $type = 'i';
                }
                $cmdArgs[] = $type;
                $cmdArgs[] = $val;
            }

            $cmd = implode(' ', array_map('escapeshellarg', $cmdArgs)) . ' 2>&1';

            $output = [];
            $retval = 0;
            exec($cmd, $output, $retval);

            if ($retval !== 0) {
                \Log::error("Failed to execute snmptrap for alert rule '$fullRuleName' to host '$host'. Exit code: $retval. Output: " . implode("\n", $output));
            } else {
                $anySuccess = true;
            }
        }

        return $anySuccess;
    }

    private function port(): int
    {
        return (int) LibrenmsConfig::get('snmptrap_forward_port', 162);
    }

    /**
     * Log to Eventlog in exact trap JSON representation format
     */
    private function logTrap(?Device $device, int $device_id, string $stateVal, array $varbinds): void
    {
        $uptimeSec = ($device && $device->uptime > 0) ? (int) $device->uptime : 0;
        $days = (int) floor($uptimeSec / 86400);
        $hours = (int) floor(($uptimeSec % 86400) / 3600);
        $minutes = (int) floor(($uptimeSec % 3600) / 60);
        $secs = $uptimeSec % 60;
        $uptimeFormatted = sprintf('%d:%02d:%02d:%02d.00', $days, $hours, $minutes, $secs);

        $jsonArray = array_merge([
            'DISMAN-EVENT-MIB::sysUpTimeInstance' => $uptimeFormatted,
        ], $varbinds);

        $jsonPayload = json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $eventlogMessage = 'SNMPv2-SMI::enterprises.58158.9.188.6.1.0.6 ' . $jsonPayload;

        $logSeverity = ($stateVal === '0') ? Severity::Ok : Severity::Error;
        Eventlog::log($eventlogMessage, $device_id, 'trap', $logSeverity);
    }

    public static function configTemplate(): array
    {
        return [
            'config' => [],
            'validation' => [],
        ];
    }
}
