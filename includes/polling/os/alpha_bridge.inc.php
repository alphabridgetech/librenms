<?php
/**
 * LibreNMS poller for Alpha Bridge AS200/12/XTS
 * Fetches CPU, Memory, and Uptime
 */

// -------------------------
// 1. CPU Polling
// Replace these OIDs with actual values from snmpwalk
$cpu_oid = '.1.3.6.1.4.1.58158.1.2.1.0'; // Vendor CPU usage %

$cpu_usage = snmp_get($device, $cpu_oid, '-Oqv', 'ALPHA-BRIDGE-MIB');

$poll_device['processor'] = [];
$poll_device['processor'][] = [
    'processor_oid'   => $cpu_oid,
    'processor_usage' => $cpu_usage,
    'processor_descr' => 'Alpha Bridge CPU',
];

// -------------------------
// 2. Memory Polling
$mem_used_oid  = '.1.3.6.1.4.1.58158.1.3.1.0'; // Memory used
$mem_total_oid = '.1.3.6.1.4.1.58158.1.3.2.0'; // Memory total

$mem_used  = snmp_get($device, $mem_used_oid, '-Oqv', 'ALPHA-BRIDGE-MIB');
$mem_total = snmp_get($device, $mem_total_oid, '-Oqv', 'ALPHA-BRIDGE-MIB');

$poll_device['mempool'] = [];
$poll_device['mempool'][] = [
    'mempool_oid'    => $mem_used_oid,
    'mempool_total'  => $mem_total,
    'mempool_used'   => $mem_used,
    'mempool_descr'  => 'Alpha Bridge Memory',
    'mempool_type'   => 'Alpha Bridge',
];

// -------------------------
// 3. System Uptime
$uptime_oid = '.1.3.6.1.2.1.1.3.0'; // sysUpTime

$sys_uptime = snmp_get($device, $uptime_oid, '-Oqv');

$poll_device['uptime'] = $sys_uptime;

// -------------------------
// End of polling script
?>
