<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Quick "can TeleQuill reach this switch over SSH?" check.
 *
 * Almost every Configuration playbook logs in to the switch over SSH
 * (paramiko). When SSH is disabled on the switch they all fail with long,
 * unclear errors, so the Configuration pages and the API check the SSH port
 * first and explain the problem instead.
 */
class SwitchSsh
{
    private const CACHE_SECONDS = 30;
    private const TIMEOUT_SECONDS = 3;

    /** The switch address and SSH port from its Ansible inventory file. */
    public static function target(string $hostname): array
    {
        $file = self::inventoryFile($hostname);
        $yaml = is_file($file) ? (string) file_get_contents($file) : '';
        $host = preg_match('/^\s*ansible_host:\s*["\']?([^"\'\s#]+)/m', $yaml, $m) ? $m[1] : $hostname;
        $port = preg_match('/^\s*ansible_port:\s*["\']?(\d+)/m', $yaml, $m) ? (int) $m[1] : 22;

        return [$host, $port];
    }

    /**
     * ['ok' => bool, 'reason' => ok|refused|timeout|no-inventory, 'host', 'port', 'telnet' => bool]
     * Cached for 30 seconds per switch; $fresh skips the cache ("Check again").
     */
    public static function check(string $hostname, bool $fresh = false): array
    {
        $key = 'switch_ssh.' . $hostname;
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($hostname) {
            [$host, $port] = self::target($hostname);
            $base = ['host' => $host, 'port' => $port, 'telnet' => false];

            if (! is_file(self::inventoryFile($hostname))) {
                return $base + ['ok' => false, 'reason' => 'no-inventory'];
            }

            [$open, $errno, $errstr] = self::probe($host, $port, self::TIMEOUT_SECONDS);
            if ($open) {
                return $base + ['ok' => true, 'reason' => 'ok'];
            }

            // "Connection refused" = switch is up but SSH is off; anything else = no answer.
            $refused = $errno === 111 || stripos($errstr, 'refused') !== false;

            return ['ok' => false, 'reason' => $refused ? 'refused' : 'timeout', 'host' => $host, 'port' => $port,
                'telnet' => self::probe($host, 23, 1)[0]];
        });
    }

    /** Plain-language explanation for the user. */
    public static function message(array $check): string
    {
        $at = "{$check['host']} (port {$check['port']})";

        return match ($check['reason']) {
            'refused' => "SSH is not enabled on this switch: {$at} refused the connection."
                . ($check['telnet'] ? ' Telnet is open, but TeleQuill configures switches over SSH.' : '')
                . ' Enable SSH on the switch, then try again.',
            'timeout' => "The switch did not answer on SSH: {$at}. Check that it is powered on, reachable from the TeleQuill server, and has SSH enabled.",
            'no-inventory' => 'This switch has no TeleQuill inventory file, so it cannot be configured. Re-add the device or check its inventory.',
            default => 'SSH is available.',
        };
    }

    /** [open, errno, errstr] for a TCP connect, without PHP warnings reaching the error handler or logs. */
    private static function probe(string $host, int $port, int $timeout): array
    {
        set_error_handler(fn () => true);
        try {
            $socket = fsockopen($host, $port, $errno, $errstr, $timeout);
        } finally {
            restore_error_handler();
        }
        if ($socket) {
            fclose($socket);
        }

        return [(bool) $socket, (int) $errno, (string) $errstr];
    }

    private static function inventoryFile(string $hostname): string
    {
        return base_path('librenms-ansible-inventory-plugin/hosts/' . basename($hostname) . '.yml');
    }
}
