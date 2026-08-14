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

        // Determine Object Type (PTP/CTP/Equipment/Port/Link)
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
            $objectType = 'Equipment';
        }

        // Extract faulted entity details & IF-MIB interface attributes
        $faultDetails = [];
        $portData = []; // Array of ['ifIndex' => ..., 'ifDescr' => ..., 'ifType' => ..., 'ifAdminStatus' => ..., 'ifOperStatus' => ...]

        if (! empty($alert_data['faults']) && is_array($alert_data['faults'])) {
            foreach ($alert_data['faults'] as $fault) {
                if (is_array($fault)) {
                    $portName = null;
                    $portId = null;
                    $ifIndex = null;
                    $ifDescr = null;
                    $ifType = null;
                    $ifAdminStatus = null;
                    $ifOperStatus = null;

                    // 1. Direct or prefixed key search in fault array
                    foreach ($fault as $k => $v) {
                        if ($v === null || $v === '') {
                            continue;
                        }
                        $cleanKey = strtolower(str_replace(['ports.', 'ports_'], '', (string) $k));
                        if ($cleanKey === 'port_id' && is_numeric($v)) {
                            $portId = (int) $v;
                        } elseif ($cleanKey === 'ifindex' && is_numeric($v)) {
                            $ifIndex = (string) $v;
                        } elseif (in_array($cleanKey, ['ifdescr', 'ifname', 'ifalias'])) {
                            if (! $ifDescr) {
                                $ifDescr = (string) $v;
                            }
                        } elseif ($cleanKey === 'iftype') {
                            $ifType = (string) $v;
                        } elseif ($cleanKey === 'ifadminstatus') {
                            $ifAdminStatus = (string) $v;
                        } elseif ($cleanKey === 'ifoperstatus') {
                            $ifOperStatus = (string) $v;
                        }
                    }

                    // 2. Query DB if port_id exists or if attributes are missing
                    if ($portId || ($ifIndex && (! $ifDescr || ! $ifType || ! $ifAdminStatus || ! $ifOperStatus))) {
                        $dbRow = null;
                        if ($portId) {
                            $dbRow = \dbFetchRow('SELECT ifIndex, ifDescr, ifName, ifAlias, ifType, ifAdminStatus, ifOperStatus FROM ports WHERE port_id = ?', [$portId]);
                        } elseif ($ifIndex && $device_id) {
                            $dbRow = \dbFetchRow('SELECT ifIndex, ifDescr, ifName, ifAlias, ifType, ifAdminStatus, ifOperStatus FROM ports WHERE device_id = ? AND ifIndex = ?', [$device_id, $ifIndex]);
                        }

                        if ($dbRow) {
                            if (! $ifIndex && ! empty($dbRow['ifIndex'])) {
                                $ifIndex = (string) $dbRow['ifIndex'];
                            }
                            if (! $ifDescr) {
                                $ifDescr = $dbRow['ifDescr'] ?: ($dbRow['ifName'] ?: $dbRow['ifAlias']);
                            }
                            if (! $ifType && ! empty($dbRow['ifType'])) {
                                $ifType = (string) $dbRow['ifType'];
                            }
                            if (! $ifAdminStatus && ! empty($dbRow['ifAdminStatus'])) {
                                $ifAdminStatus = (string) $dbRow['ifAdminStatus'];
                            }
                            if (! $ifOperStatus && ! empty($dbRow['ifOperStatus'])) {
                                $ifOperStatus = (string) $dbRow['ifOperStatus'];
                            }
                        }
                    }

                    $portName = $ifDescr ?: ($ifIndex ? "Interface {$ifIndex}" : null);

                    if ($portName) {
                        $faultDetails[] = $portName;
                    } elseif (! empty($fault['string'])) {
                        $faultDetails[] = $fault['string'];
                    }

                    if ($ifIndex !== null && $ifIndex !== '') {
                        $portData[] = [
                            'ifIndex' => (string) $ifIndex,
                            'ifDescr' => (string) ($ifDescr ?: ('GigaEthernet0/' . $ifIndex)),
                            'ifType' => (string) ($ifType ?: 'gigabitEthernet'),
                            'ifAdminStatus' => (string) ($ifAdminStatus ?: 'up'),
                            'ifOperStatus' => (string) ($ifOperStatus ?: 'up'),
                        ];
                    }
                }
            }
        }

        // Fallback for Port / Link rules ONLY if no portData was collected from faults
        if (empty($portData) && in_array($objectType, ['Port', 'Link'], true)) {
            $dbRow = null;
            if ($device_id) {
                $dbRow = \dbFetchRow('SELECT ifIndex, ifDescr, ifName, ifAlias, ifType, ifAdminStatus, ifOperStatus FROM ports WHERE device_id = ? LIMIT 1', [$device_id]);
            }
            if ($dbRow && ! empty($dbRow['ifIndex'])) {
                $idx = (string) $dbRow['ifIndex'];
                $descr = $dbRow['ifDescr'] ?: ($dbRow['ifName'] ?: ($dbRow['ifAlias'] ?: ('GigaEthernet0/' . $idx)));
                $portData[] = [
                    'ifIndex' => $idx,
                    'ifDescr' => (string) $descr,
                    'ifType' => (string) ($dbRow['ifType'] ?: 'gigabitEthernet'),
                    'ifAdminStatus' => (string) ($dbRow['ifAdminStatus'] ?: 'up'),
                    'ifOperStatus' => (string) ($dbRow['ifOperStatus'] ?: 'up'),
                ];
                if (empty($faultDetails) && ($objectType === 'Port' || str_contains(strtolower($ruleName), 'interface'))) {
                    $faultDetails[] = $descr;
                }
            } else {
                // Default fallback for test interface alerts
                $portData[] = [
                    'ifIndex' => '10',
                    'ifDescr' => 'GigaEthernet0/10',
                    'ifType' => 'gigabitEthernet',
                    'ifAdminStatus' => 'up',
                    'ifOperStatus' => 'up',
                ];
                if (empty($faultDetails) && ($objectType === 'Port' || str_contains(strtolower($ruleName), 'interface'))) {
                    $faultDetails[] = 'GigaEthernet0/10';
                }
            }
        }

        // Prioritize physical ports (GigaEthernet, etc.) over virtual/VLAN interfaces
        if (count($portData) > 1) {
            usort($portData, function ($a, $b) {
                $isVirtA = str_contains(strtolower($a['ifDescr']), 'vlan') || in_array($a['ifType'], ['propVirtual', 'softwareLoopback', 'tunnel', 'l2vlan'], true);
                $isVirtB = str_contains(strtolower($b['ifDescr']), 'vlan') || in_array($b['ifType'], ['propVirtual', 'softwareLoopback', 'tunnel', 'l2vlan'], true);
                if ($isVirtA && ! $isVirtB) {
                    return 1;
                }
                if (! $isVirtA && $isVirtB) {
                    return -1;
                }
                return 0;
            });
            $portData = array_slice($portData, 0, 1);
        }

        $uniqueFaults = array_values(array_unique($faultDetails));
        if (count($uniqueFaults) > 1) {
            usort($uniqueFaults, function ($a, $b) {
                $isVirtA = str_contains(strtolower($a), 'vlan');
                $isVirtB = str_contains(strtolower($b), 'vlan');
                if ($isVirtA && ! $isVirtB) {
                    return 1;
                }
                if (! $isVirtA && $isVirtB) {
                    return -1;
                }
                return 0;
            });
            $uniqueFaults = array_slice($uniqueFaults, 0, 1);
        }

        $faultSummary = ! empty($uniqueFaults) ? (' [' . implode(', ', $uniqueFaults) . ']') : '';
        $fullRuleName = $ruleName . $faultSummary . ' (State: ' . $stateText . ')';

        $uptimeTicks = ($device && $device->uptime > 0) ? (int) ($device->uptime * 100) : 0;
        $timestamp = Carbon::now()->format('Y M j H:i:s ');

        // Base varbinds payload with IF-MIB attributes inserted directly after sysName (.188.2)
        $varbinds = [
            'SNMPv2-SMI::enterprises.58158.9.188.1' => (string) $deviceIp,
            'SNMPv2-SMI::enterprises.58158.9.188.2' => (string) $sysName,
        ];

        foreach ($portData as $p) {
            $idx = $p['ifIndex'];
            $varbinds["IF-MIB::ifIndex.{$idx}"] = (string) $idx;
            $varbinds["IF-MIB::ifDescr.{$idx}"] = (string) $p['ifDescr'];
            
            $varbinds["IF-MIB::ifType.{$idx}"] = (string) $p['ifType'];
            $varbinds["IF-MIB::ifAdminStatus.{$idx}"] = (string) $p['ifAdminStatus'];
            $varbinds["IF-MIB::ifOperStatus.{$idx}"] = (string) $p['ifOperStatus'];
        }

        $varbinds['SNMPv2-SMI::enterprises.58158.9.188.3'] = (string) $stateText;
        $varbinds['SNMPv2-SMI::enterprises.58158.9.188.4'] = (string) $objectType;
        $varbinds['SNMPv2-SMI::enterprises.58158.9.188.5'] = (string) $severityText;
        $varbinds['SNMPv2-SMI::enterprises.58158.9.188.6'] = (string) $timestamp;
        $varbinds['SNMPv2-SMI::enterprises.58158.9.188.7'] = (string) $fullRuleName;

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
                sprintf('udp:%s:%d', $targetIp, $port),
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

        if ($anySuccess) {
            // Log to Eventlog in exact trap JSON representation format
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
