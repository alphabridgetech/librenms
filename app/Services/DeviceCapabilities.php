<?php

namespace App\Services;

use App\Models\Device;
use LibreNMS\Config;

/**
 * What a switch is, worked out from data discovery already stored for it -
 * no per-model or per-OID lists:
 *
 *  - family:  the detected OS (os_detection/*.yaml), e.g. switchv1alphabridge
 *  - version: firmware version from discovery
 *  - speed:   fastest physical port (ifSpeed of Ethernet ports; a down port
 *             reports its nominal speed, so a 10G port linked at 1G still
 *             counts as 10G through the others)
 *  - ports:   the physical port names, as the switch CLI uses them
 *
 * A new model is classified as soon as it is discovered.
 */
class DeviceCapabilities
{
    /** IANA ifTypes of physical Ethernet ports (VLANs, LAGs, Null0 ... are not). */
    private const PHYSICAL_IF_TYPES = ['ethernetCsmacd', 'gigabitEthernet', 'fastEther', 'fastEtherFX', 'iso88023Csmacd'];

    private static array $cache = [];

    public static function for(Device $device): array
    {
        return self::$cache[$device->device_id] ??= self::build($device);
    }

    public static function forHostname(string $hostname): ?array
    {
        $device = Device::where('hostname', $hostname)->first();

        return $device ? self::for($device) : null;
    }

    private static function build(Device $device): array
    {
        $ports = $device->ports()
            ->whereIn('ifType', self::PHYSICAL_IF_TYPES)
            ->where('deleted', 0)
            ->get(['ifName', 'ifSpeed']);

        $maxSpeed = (int) $ports->max('ifSpeed');
        $speed    = $maxSpeed > 0 ? self::speedLabel($maxSpeed) : null;

        return [
            'family'       => $device->os,
            'family_text'  => Config::get("os.{$device->os}.text", $device->os),
            'hardware'     => $device->hardware,
            'version'      => $device->version,
            'speed'        => $speed,                                   // "1G", "10G", "100G" ...
            'speed_bps'    => $maxSpeed ?: null,
            'speed_slug'   => $speed ? strtolower($speed) : null,       // playbook sub-folder name
            'port_count'   => $ports->count(),
            'ports'        => $ports->pluck('ifName')->filter()->values()->all(),
        ];
    }

    /**
     * Whether the Configuration tab $tab (its url, e.g. "stpconfig") is
     * shown for $device, by the rules in
     * resources/definitions/config_module_support.yaml.
     */
    public static function supports(Device $device, string $tab): bool
    {
        $rule = self::supportRules()[$tab] ?? null;
        if (!is_array($rule)) {
            return true;
        }

        $caps = self::for($device);

        // A value discovery hasn't found yet never hides a tab.
        if (!empty($rule['only']) && !self::anyMatch($rule['only'], $caps, true)) {
            return false;
        }

        return !(!empty($rule['hide']) && self::anyMatch($rule['hide'], $caps, false));
    }

    private static ?array $supportRules = null;

    private static function supportRules(): array
    {
        if (self::$supportRules === null) {
            $file = resource_path('definitions/config_module_support.yaml');
            try {
                self::$supportRules = is_file($file) ? (array) \Symfony\Component\Yaml\Yaml::parseFile($file) : [];
            } catch (\Throwable $e) {
                report($e);
                self::$supportRules = [];
            }
        }

        return self::$supportRules;
    }

    /** Does any of the match lines in $lines fit $caps? $unknown = result for a field the switch has no value for. */
    private static function anyMatch(array $lines, array $caps, bool $unknown): bool
    {
        $fields = ['family' => $caps['family'], 'speed' => $caps['speed_slug'], 'hardware' => $caps['hardware']];

        foreach ($lines as $line) {
            if (!is_array($line) || !$line) {
                continue;
            }

            $all = true;
            foreach ($line as $field => $want) {
                $have = $fields[$field] ?? null;
                if ($have === null || $have === '') {
                    $ok = $unknown;
                } else {
                    $ok = in_array(strtolower((string) $have), array_map(fn ($v) => strtolower((string) $v), (array) $want), true);
                }
                if (!$ok) {
                    $all = false;
                    break;
                }
            }

            if ($all) {
                return true;
            }
        }

        return false;
    }

    /** 1000000000 -> "1G", 10000000000 -> "10G", 100000000 -> "100M". */
    private static function speedLabel(int $bps): string
    {
        foreach ([1_000_000_000_000 => 'T', 1_000_000_000 => 'G', 1_000_000 => 'M'] as $unit => $suffix) {
            if ($bps >= $unit) {
                return rtrim(rtrim(number_format($bps / $unit, 1, '.', ''), '0'), '.') . $suffix;
            }
        }

        return $bps . 'bps';
    }
}
