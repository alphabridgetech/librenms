<?php
// ajax_csv_template.php

use LibreNMS\Enum\PortAssociationMode;

if (!Auth::user()->hasGlobalAdmin()) {
    exit('Unauthorized');
}

// Define headers
$headers = [
    'hostname',
    'snmpver',
    'community',
    'port',
    'transport',
    'authlevel',
    'authname',
    'authpass',
    'authalgo',
    'cryptopass',
    'cryptoalgo',
    'ssh_user',
    'ssh_pass',
    'os',
    'hardware',
    'sysname',
    'poller_group',
    'port_assoc_mode',
    'force_add',
    'snmp_disable'
];

// Example data rows
$examples = [
    [
        'hostname' => '192.168.200.245',
        'snmpver' => 'v2c',
        'community' => 'public',
        'port' => '161',
        'transport' => 'udp',
        'authlevel' => '',
        'authname' => '',
        'authpass' => '',
        'authalgo' => '',
        'cryptopass' => '',
        'cryptoalgo' => '',
        'ssh_user' => 'admin',
        'ssh_pass' => 'admin',
        'os' => 'ios',
        'hardware' => '',
        'sysname' => '',
        'poller_group' => '0',
        'port_assoc_mode' => 'ifIndex',
        'force_add' => 'no',
        'snmp_disable' => 'no'
    ],
    // [
    //     'hostname' => 'switch.domain.com',
    //     'snmpver' => 'v3',
    //     'community' => '',
    //     'port' => '',
    //     'transport' => 'udp',
    //     'authlevel' => 'authPriv',
    //     'authname' => 'snmpuser',
    //     'authpass' => 'authpass123',
    //     'authalgo' => 'SHA',
    //     'cryptopass' => 'cryptopass123',
    //     'cryptoalgo' => 'AES',
    //     'ssh_user' => 'admin',
    //     'ssh_pass' => 'admin123',
    //     'os' => 'procurve',
    //     'hardware' => '',
    //     'sysname' => '',
    //     'poller_group' => '1',
    //     'port_assoc_mode' => 'ifName',
    //     'force_add' => 'no',
    //     'snmp_disable' => 'no'
    // ],
    // [
    //     'hostname' => 'ping-only-device',
    //     'snmpver' => '',
    //     'community' => '',
    //     'port' => '',
    //     'transport' => '',
    //     'authlevel' => '',
    //     'authname' => '',
    //     'authpass' => '',
    //     'authalgo' => '',
    //     'cryptopass' => '',
    //     'cryptoalgo' => '',
    //     'ssh_user' => 'admin',
    //     'ssh_pass' => 'admin',
    //     'os' => 'ping',
    //     'hardware' => 'Generic',
    //     'sysname' => 'ping-device',
    //     'poller_group' => '0',
    //     'port_assoc_mode' => 'ifIndex',
    //     'force_add' => 'no',
    //     'snmp_disable' => 'yes'
    // ]
];

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=telequill_bulk_add_template.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, $headers);

// Write example rows
foreach ($examples as $row) {
    $csv_row = [];
    foreach ($headers as $header) {
        $csv_row[] = $row[$header] ?? '';
    }
    fputcsv($output, $csv_row);
}

// Add some instructions as comments
// fputcsv($output, ['# hostname is required']);
// fputcsv($output, ['# snmpver: v1, v2c, v3, or empty for non-SNMP']);
// fputcsv($output, ['# authlevel: noAuthNoPriv, authNoPriv, authPriv']);
// fputcsv($output, ['# authalgo: MD5, SHA, SHA-224, SHA-256, SHA-384, SHA-512']);
// fputcsv($output, ['# cryptoalgo: DES, 3DES, AES, AES-192, AES-256']);
// fputcsv($output, ['# port_assoc_mode: ' . implode(', ', PortAssociationMode::getModes())]);
// fputcsv($output, ['# force_add: yes/no - skip reachability checks']);
// fputcsv($output, ['# snmp_disable: yes/no - disable SNMP (ICMP only)']);

fclose($output);
exit;