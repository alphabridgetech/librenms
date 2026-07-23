#!/usr/bin/env php
<?php

/**
 * LibreNMS
 *
 *   This file is part of LibreNMS.
 *
 * @copyright  (C) 2006 - 2012 Adam Armstrong
 * @copyright  (C) 2018 LibreNMS
 * Adapted from old snmptrap.php handler
 */

use LibreNMS\Util\Debug;

$init_modules = [];
require __DIR__ . '/includes/init.php';

$options = getopt('d::');

if (Debug::set(isset($options['d']))) {
    echo "DEBUG!\n";
}

$text = stream_get_contents(STDIN);

// Real-time SNMP Trap Forwarding
try {
    if (\App\Facades\LibrenmsConfig::has('snmptrap_forward_host')) {
        $ip = \App\Facades\LibrenmsConfig::get('snmptrap_forward_host');
        $port = (int) \App\Facades\LibrenmsConfig::get('snmptrap_forward_port', 162);
        if (!empty($ip)) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $resolved_ip = gethostbyname($ip);
                if ($resolved_ip !== $ip) {
                    $ip = $resolved_ip;
                }
            }
            if (($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) !== false) {
                socket_sendto($socket, $text, strlen($text), 0, $ip, $port);
                socket_close($socket);
            }
        }
    }
} catch (\Throwable $t) {
    // Silently ignore or log trap forwarding errors so we don't break main trap processing
    \Illuminate\Support\Facades\Log::error("SNMP Trap Forwarding failed: " . $t->getMessage());
}

// create handle and send it this trap
\LibreNMS\Snmptrap\Dispatcher::handle(new \LibreNMS\Snmptrap\Trap($text));
