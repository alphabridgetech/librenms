<?php

/**
 * Forwarder.php
 *
 * Re-sends received traps as real SNMPv2c notifications to the receivers
 * configured with snmptrap_forward_host / snmptrap_forward_port.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @link       https://www.librenms.org
 */

namespace LibreNMS\Snmptrap;

use App\Facades\LibrenmsConfig;

class Forwarder
{
    /**
     * Varbinds that snmptrapd adds itself, they are rebuilt for the forwarded trap
     */
    private const SKIP_OIDS = [
        'DISMAN-EVENT-MIB::sysUpTimeInstance',
        'SNMPv2-MIB::snmpTrapOID.0',
        'SNMP-COMMUNITY-MIB::snmpTrapAddress.0',
        'SNMP-COMMUNITY-MIB::snmpTrapCommunity.0',
    ];

    public static function forward(Trap $trap): void
    {
        $hosts = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) LibrenmsConfig::get('snmptrap_forward_host', ''))));
        $trapOid = $trap->getTrapOid();
        if (empty($hosts) || $trapOid === '') {
            return;
        }

        $port = (int) LibrenmsConfig::get('snmptrap_forward_port', 162);
        $mibDir = LibrenmsConfig::get('mib_dir', base_path('mibs'));

        $args = [(string) self::ticks($trap->getOidData('DISMAN-EVENT-MIB::sysUpTimeInstance')), $trapOid];
        foreach ($trap->getOidValues() as $oid => $value) {
            if (! in_array($oid, self::SKIP_OIDS, true)) {
                array_push($args, $oid, ...self::typedValue($oid, (string) $value));
            }
        }

        // tell the receiver which device originally sent the trap
        if (filter_var($trap->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            array_push($args, 'SNMP-COMMUNITY-MIB::snmpTrapAddress.0', 'a', $trap->ip);
        }

        $commands = [];
        foreach ($hosts as $host) {
            $base = ['/usr/bin/snmptrap', '-v', '2c', '-c', 'public', sprintf('udp:%s:%d', $host, $port)];
            // the default MIBs are enough for most traps and load fast, vendor OIDs need all MIBs (slow)
            $fast = self::command([...$base, ...$args]);
            $full = self::command([...$base, '-m', 'ALL', '-M', "$mibDir:$mibDir/cisco", ...$args]);
            $failed = self::command(['logger', '-t', 'snmptrap-forward', "failed to forward trap $trapOid to $host"]);
            $commands[] = "{ $fast || $full || $failed; }";
        }

        // run in the background so trap processing never waits for receivers or MIB loading
        exec('(' . implode('; ', $commands) . ') > /dev/null 2>&1 &');
    }

    /**
     * Type and value arguments for one varbind
     */
    private static function typedValue(string $oid, string $value): array
    {
        if (preg_match('/^\d+:\d+:\d+:\d+(\.\d+)?$/', $value)) {
            return ['t', (string) self::ticks($value)];
        }

        // OIDs the MIBs couldn't resolve have no type information, guess it from the value
        if (preg_match('/^(SNMPv2-SMI::|iso\.|\.?\d)/', $oid)) {
            if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return ['a', $value];
            }
            if (preg_match('/^-?\d+$/', $value) && abs((int) $value) <= 2147483647) {
                return ['i', $value];
            }

            return ['s', $value];
        }

        // known object, let snmptrap take the type from the MIB
        return ['=', $value];
    }

    /**
     * Convert snmptrapd's d:h:m:s.cc uptime to timeticks
     */
    private static function ticks(string $uptime): int
    {
        if (! preg_match('/^(\d+):(\d+):(\d+):(\d+)(?:\.(\d+))?$/', $uptime, $m)) {
            return 0;
        }

        return ((((int) $m[1] * 24 + (int) $m[2]) * 60 + (int) $m[3]) * 60 + (int) $m[4]) * 100 + (int) str_pad($m[5] ?? '0', 2, '0');
    }

    private static function command(array $args): string
    {
        return implode(' ', array_map('escapeshellarg', $args));
    }
}
