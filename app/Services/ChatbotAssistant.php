<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Port;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LibreNMS\Enum\AlertState;
use LibreNMS\Util\Number;
use LibreNMS\Util\Time;

/**
 * Built-in (offline) chatbot answers.
 *
 * Answers common NMS questions straight from the Telequill database, so the
 * chatbot works with no AI key and no internet. Every answer is limited to
 * the devices the asking user is allowed to see.
 *
 * answer() returns null when the question is not one it understands; the
 * caller can then fall back to the AI (if configured) or to help().
 */
class ChatbotAssistant
{
    private const LIST_LIMIT = 15;

    private User $user;
    private ?Collection $deviceIds = null;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function answer(string $message): ?string
    {
        $q = mb_strtolower(trim($message));

        if ($q === '' || preg_match('/^(hi|hello|hey|namaste|good (morning|afternoon|evening))\b[!. ]*$/', $q)) {
            return "Hi! I'm the TeleQuill assistant.\n\n" . $this->help();
        }
        if (preg_match('/\b(help|what can you|commands|menu|how to use)\b/', $q)) {
            return $this->help();
        }

        $device = $this->findDevice($q);
        if ($device) {
            if ($this->has($q, ['port', 'interface'])) {
                return $this->ports($q, $device);
            }
            if ($this->has($q, ['alert', 'alarm'])) {
                return $this->alerts($device);
            }

            return $this->deviceDetail($device);
        }

        if ($this->has($q, ['alert', 'alarm', 'problem', 'issue'])) {
            return $this->alerts();
        }
        if ($this->has($q, ['port', 'interface', 'link', 'traffic', 'bandwidth', 'utiliz', 'utilis'])) {
            return $this->ports($q);
        }
        if ($this->has($q, ['temperature', 'temp ', 'hot', 'sensor', 'fan', 'voltage', 'humidity', 'optic', 'dbm', 'ampere'])) {
            return $this->sensors($q);
        }
        if ($this->has($q, ['cpu', 'processor', 'memory', 'ram', 'mem '])) {
            return $this->resources();
        }
        if ($this->has($q, ['device', 'switch', 'node', 'host', 'network', 'status', 'summary', 'overview', 'health', 'down', 'offline', 'unreachable'])) {
            return $this->has($q, ['down', 'offline', 'unreachable', 'not reachable', 'dead'])
                ? $this->devicesDown()
                : $this->overview();
        }

        return null;
    }

    /**
     * NMS data handed to the AI for questions the built-in answers don't
     * cover. Same rule as everything else: only devices this user may see.
     */
    public function context(): array
    {
        $ids = $this->ids();
        $devices = $this->devices()->with('location')->get();
        $names = $devices->keyBy('device_id')->map(fn (Device $d) => $d->displayName());
        $ports = Port::query()->whereIn('device_id', $ids)->where('deleted', 0)->where('disabled', 0)->where('ignore', 0);
        $portRow = fn (Port $p) => [
            'device' => $names[$p->device_id] ?? $p->device_id,
            'port' => $p->ifName ?: $p->ifDescr,
            'alias' => $p->ifAlias,
            'oper' => $p->ifOperStatus,
            'admin' => $p->ifAdminStatus,
            'speed_bps' => $p->ifSpeed,
            'in_bps' => (int) ($p->ifInOctets_rate * 8),
            'out_bps' => (int) ($p->ifOutOctets_rate * 8),
            'in_errors_per_s' => (float) $p->ifInErrors_rate,
            'out_errors_per_s' => (float) $p->ifOutErrors_rate,
        ];

        return [
            'devices' => $devices->map(fn (Device $d) => [
                'name' => $d->displayName(),
                'hostname' => $d->hostname,
                'status' => $d->disabled ? 'disabled' : ($d->ignore ? 'ignored' : ($d->status ? 'up' : 'down')),
                'down_reason' => $d->status ? null : $d->status_reason,
                'hardware' => $d->hardware,
                'os' => $d->os,
                'version' => $d->version,
                'serial' => $d->serial,
                'location' => $d->location?->location,
                'uptime_seconds' => $d->uptime,
                'last_polled' => $d->last_polled?->toDateTimeString(),
            ])->values(),
            'ports_down' => (clone $ports)->where('ifAdminStatus', 'up')->where('ifOperStatus', '!=', 'up')->limit(300)->get()->map($portRow),
            'ports_busiest' => (clone $ports)->where('ifOperStatus', 'up')->orderByRaw('(COALESCE(ifInOctets_rate,0) + COALESCE(ifOutOctets_rate,0)) desc')->limit(25)->get()->map($portRow),
            'ports_with_errors' => (clone $ports)->where(fn ($w) => $w->where('ifInErrors_rate', '>', 0)->orWhere('ifOutErrors_rate', '>', 0))->limit(50)->get()->map($portRow),
            'active_alerts' => $this->activeAlerts()->with('rule')->orderBy('timestamp', 'desc')->limit(100)->get()->map(fn (Alert $a) => [
                'device' => $names[$a->device_id] ?? $a->device_id,
                'rule' => $a->rule?->name,
                'severity' => $a->rule?->severity,
                'since' => (string) $a->timestamp,
                'acknowledged' => $a->state == AlertState::ACKNOWLEDGED,
            ]),
            'sensors' => Sensor::query()->whereIn('device_id', $ids)->limit(500)->get()->map(fn (Sensor $s) => [
                'device' => $names[$s->device_id] ?? $s->device_id,
                'class' => $s->sensor_class,
                'name' => $s->sensor_descr,
                'value' => $s->sensor_current,
                'limit_low' => $s->sensor_limit_low,
                'limit_high' => $s->sensor_limit,
            ]),
            'cpu_percent' => DB::table('processors')->whereIn('device_id', $ids)->select('device_id', DB::raw('AVG(processor_usage) as cpu_pct'))
                ->groupBy('device_id')->get()->mapWithKeys(fn ($r) => [$names[$r->device_id] ?? $r->device_id => round($r->cpu_pct)]),
            'memory_percent' => DB::table('mempools')->whereIn('device_id', $ids)->select('device_id', DB::raw('MAX(mempool_perc) as mem_pct'))
                ->groupBy('device_id')->get()->mapWithKeys(fn ($r) => [$names[$r->device_id] ?? $r->device_id => round($r->mem_pct)]),
        ];
    }

    public function help(): string
    {
        return "I can answer these from TeleQuill's own data (works offline):\n"
            . "- **Network overview**: \"network summary\"\n"
            . "- **Devices down**: \"which devices are down?\"\n"
            . "- **One device**: \"status of 10.10.10.1\" or a device name\n"
            . "- **Alerts**: \"show active alerts\"\n"
            . "- **Ports**: \"ports down\", \"ports with errors\", \"busiest ports\"\n"
            . "- **Ports of a device**: \"ports of 10.10.10.1\"\n"
            . "- **Sensors**: \"high temperature\", \"fan sensors\", \"optic dbm\"\n"
            . "- **CPU and memory**: \"cpu usage\", \"memory usage\"";
    }

    // ---------------------------------------------------------------- answers

    private function overview(): string
    {
        $devices = $this->devices()->get();
        $active = $devices->where('ignore', 0)->where('disabled', 0);
        $down = $active->where('status', 0);
        $ports = Port::whereIn('device_id', $this->ids())->where('deleted', 0)->where('disabled', 0)->where('ignore', 0);
        $portsUp = (clone $ports)->where('ifOperStatus', 'up')->count();
        $portsDown = (clone $ports)->where('ifAdminStatus', 'up')->where('ifOperStatus', '!=', 'up')->count();
        $alerts = $this->activeAlerts()->get();

        $out = "**Network overview**\n"
            . "- Devices: {$active->count()} monitored, " . ($active->count() - $down->count()) . " up, **{$down->count()} down**\n"
            . "- Ports: {$portsUp} up, {$portsDown} down (admin up)\n"
            . '- Active alerts: ' . $alerts->count() . $this->severitySuffix($alerts);

        if ($down->isNotEmpty()) {
            $out .= "\n\nDown devices: " . $down->take(self::LIST_LIMIT)->map(fn (Device $d) => $d->displayName())->implode(', ')
                . $this->more($down->count());
        }

        return $out;
    }

    private function devicesDown(): string
    {
        $down = $this->devices()->isDown()->orderBy('last_polled', 'desc')->get();
        if ($down->isEmpty()) {
            return 'All monitored devices are **up**. ✅';
        }

        $lines = $down->take(self::LIST_LIMIT)->map(function (Device $d) {
            $reason = $d->status_reason ? " ({$d->status_reason})" : '';
            $since = $d->last_polled ? ', last polled ' . $d->last_polled->diffForHumans() : '';

            return "- **{$d->displayName()}** `{$d->hostname}`{$reason}{$since}";
        });

        return "**{$down->count()} device(s) down:**\n" . $lines->implode("\n") . $this->more($down->count());
    }

    private function deviceDetail(Device $d): string
    {
        $state = ! $d->disabled && ! $d->ignore ? ($d->status ? '🟢 Up' : '🔴 Down') : ($d->disabled ? 'Disabled' : 'Ignored');
        $ports = $d->ports()->where('deleted', 0);
        $portsUp = (clone $ports)->where('ifOperStatus', 'up')->count();
        $portsDown = (clone $ports)->where('ifAdminStatus', 'up')->where('ifOperStatus', '!=', 'up')->count();
        $alerts = $this->activeAlerts()->where('device_id', $d->device_id)->count();

        $rows = [
            'Status' => $state . ($d->status_reason && ! $d->status ? " ({$d->status_reason})" : ''),
            'IP / hostname' => "`{$d->hostname}`",
            'Hardware' => $d->hardware,
            'OS / version' => trim(($d->os ?? '') . ' ' . ($d->version ?? '')),
            'Serial' => $d->serial,
            'Location' => $d->location?->location,
            'Uptime' => $d->status && $d->uptime ? Time::formatInterval((int) $d->uptime) : null,
            'Last polled' => $d->last_polled?->diffForHumans(),
            'Ports' => "{$portsUp} up, {$portsDown} down",
            'Active alerts' => (string) $alerts,
        ];

        $out = "**{$d->displayName()}**\n";
        foreach ($rows as $label => $value) {
            if ($value !== null && $value !== '') {
                $out .= "- {$label}: {$value}\n";
            }
        }

        return rtrim($out);
    }

    private function alerts(?Device $device = null): string
    {
        $query = $this->activeAlerts()->with(['device', 'rule']);
        if ($device) {
            $query->where('device_id', $device->device_id);
        }
        $alerts = $query->orderBy('timestamp', 'desc')->get();
        $scope = $device ? " on {$device->displayName()}" : '';

        if ($alerts->isEmpty()) {
            return "No active alerts{$scope}. ✅";
        }

        $lines = $alerts->take(self::LIST_LIMIT)->map(function (Alert $a) {
            $sev = $a->rule?->severity ?? 'unknown';
            $icon = $sev === 'critical' ? '🔴' : ($sev === 'warning' ? '🟠' : '🔵');
            $ack = $a->state == AlertState::ACKNOWLEDGED ? ' (acknowledged)' : '';
            $when = $a->timestamp ? ' · ' . \Carbon\Carbon::parse($a->timestamp)->diffForHumans() : '';

            return "- {$icon} **{$sev}**: " . ($a->rule?->name ?? 'Alert') . ' on ' . ($a->device?->displayName() ?? 'unknown device') . $ack . $when;
        });

        return "**{$alerts->count()} active alert(s){$scope}**" . $this->severitySuffix($alerts) . "\n"
            . $lines->implode("\n") . $this->more($alerts->count());
    }

    private function ports(string $q, ?Device $device = null): string
    {
        $base = Port::query()->with('device')
            ->whereIn('device_id', $device ? [$device->device_id] : $this->ids())
            ->where('deleted', 0)->where('disabled', 0)->where('ignore', 0);
        $scope = $device ? " on {$device->displayName()}" : '';
        $sections = [];

        if ($this->has($q, ['error', 'crc', 'drop'])) {
            $errors = (clone $base)->where(fn ($w) => $w->where('ifInErrors_rate', '>', 0)->orWhere('ifOutErrors_rate', '>', 0))
                ->orderByRaw('(COALESCE(ifInErrors_rate,0) + COALESCE(ifOutErrors_rate,0)) desc')->get();
            $sections[] = $errors->isEmpty()
                ? "No ports with errors right now{$scope}. ✅"
                : "**{$errors->count()} port(s) with errors{$scope}:**\n" . $errors->take(self::LIST_LIMIT)->map(fn (Port $p) => '- ' . $this->portName($p, ! $device)
                    . ': in ' . round((float) $p->ifInErrors_rate, 2) . '/s, out ' . round((float) $p->ifOutErrors_rate, 2) . '/s')->implode("\n") . $this->more($errors->count());
        }

        if ($this->has($q, ['busiest', 'top', 'traffic', 'bandwidth', 'utiliz', 'utilis', 'usage'])) {
            $busy = (clone $base)->where('ifOperStatus', 'up')
                ->orderByRaw('(COALESCE(ifInOctets_rate,0) + COALESCE(ifOutOctets_rate,0)) desc')->limit(10)->get();
            $sections[] = $busy->isEmpty()
                ? "No traffic data yet{$scope}."
                : "**Busiest ports{$scope}:**\n" . $busy->map(fn (Port $p) => '- ' . $this->portName($p, ! $device)
                    . ': in ' . Number::formatSi($p->ifInOctets_rate * 8, 2, 0, 'bps') . ', out ' . Number::formatSi($p->ifOutOctets_rate * 8, 2, 0, 'bps'))->implode("\n");
        }

        if (empty($sections) || $this->has($q, ['down', 'offline', 'not up'])) {
            $down = (clone $base)->where('ifAdminStatus', 'up')->where('ifOperStatus', '!=', 'up')->orderBy('device_id')->orderBy('ifIndex')->get();
            $up = (clone $base)->where('ifOperStatus', 'up')->count();
            $sections[] = $down->isEmpty()
                ? "All enabled ports are up{$scope} ({$up} up). ✅"
                : "**Ports{$scope}: {$up} up, {$down->count()} down** (admin up, link down):\n"
                    . $down->take(self::LIST_LIMIT)->map(fn (Port $p) => '- ' . $this->portName($p, ! $device) . " ({$p->ifOperStatus})")->implode("\n")
                    . $this->more($down->count());
        }

        return implode("\n\n", $sections);
    }

    private function sensors(string $q): string
    {
        $classes = [
            'temperature' => ['temperature', 'temp ', 'hot'],
            'fanspeed' => ['fan'],
            'voltage' => ['voltage'],
            'humidity' => ['humidity'],
            'dbm' => ['optic', 'dbm', 'sfp'],
            'current' => ['ampere', 'current sensor'],
        ];
        $class = collect($classes)->search(fn ($words) => $this->has($q, $words)) ?: null;

        $query = Sensor::query()->with('device')->whereIn('device_id', $this->ids());
        if ($class) {
            $query->where('sensor_class', $class);
        }
        $sensors = $query->get();

        if ($sensors->isEmpty()) {
            return 'No ' . ($class ?? '') . ' sensors found.';
        }

        // "high/hot" -> above the upper limit only; "low" -> below the lower limit only; else either.
        $wantHigh = $this->has($q, ['high', 'hot', 'above', 'over']);
        $wantLow = $this->has($q, ['low', 'below', 'under']);
        $over = fn (Sensor $s) => $s->sensor_limit !== null && $s->sensor_current > $s->sensor_limit;
        $under = fn (Sensor $s) => $s->sensor_limit_low !== null && $s->sensor_current < $s->sensor_limit_low;
        $bad = $sensors->filter(fn (Sensor $s) => ($wantHigh && ! $wantLow) ? $over($s)
            : (($wantLow && ! $wantHigh) ? $under($s) : ($over($s) || $under($s))));
        $range = ($wantHigh && ! $wantLow) ? 'above their limit' : (($wantLow && ! $wantHigh) ? 'below their limit' : 'out of range');
        $label = $class ? ucfirst(['dbm' => 'optic (dBm)', 'fanspeed' => 'fan speed'][$class] ?? $class) . ' sensors' : 'Sensors';

        // Without a "high/critical" ask, list the top readings so the answer is never empty.
        $list = $bad->isNotEmpty() ? $bad : $sensors->sortByDesc('sensor_current');
        $title = $bad->isNotEmpty()
            ? "**{$bad->count()} of {$sensors->count()} {$label} {$range}:**"
            : "**No {$label} {$range} ✅** ({$sensors->count()} checked). Highest readings:";

        return $title . "\n" . $list->take($bad->isNotEmpty() ? self::LIST_LIMIT : 8)->map(function (Sensor $s) {
            $limits = $s->sensor_limit !== null ? ' (limit ' . $s->sensor_limit_low . ' – ' . $s->sensor_limit . ')' : '';

            return '- ' . ($s->device?->displayName() ?? '?') . ' · ' . $s->sensor_descr . ': **' . round((float) $s->sensor_current, 2) . $this->unit($s->sensor_class) . "**{$limits}";
        })->implode("\n") . ($bad->isNotEmpty() ? $this->more($bad->count()) : '');
    }

    private function resources(): string
    {
        $cpu = DB::table('processors')->whereIn('processors.device_id', $this->ids())
            ->select('device_id', DB::raw('AVG(processor_usage) as cpu_pct'))->groupBy('device_id')->orderByDesc('cpu_pct')->limit(10)->get();
        $mem = DB::table('mempools')->whereIn('mempools.device_id', $this->ids())
            ->select('device_id', DB::raw('MAX(mempool_perc) as mem_pct'))->groupBy('device_id')->orderByDesc('mem_pct')->limit(10)->get();
        $names = $this->devices()->whereIn('device_id', $cpu->pluck('device_id')->merge($mem->pluck('device_id')))->get()->keyBy('device_id');
        $name = fn ($id) => isset($names[$id]) ? $names[$id]->displayName() : "device {$id}";
        $flag = fn ($v) => $v >= 90 ? ' 🔴' : ($v >= 75 ? ' 🟠' : '');

        $out = $cpu->isEmpty() ? 'No CPU data yet.' : "**CPU usage (highest first):**\n"
            . $cpu->map(fn ($r) => '- ' . $name($r->device_id) . ': ' . round($r->cpu_pct) . '%' . $flag($r->cpu_pct))->implode("\n");
        $out .= "\n\n" . ($mem->isEmpty() ? 'No memory data yet.' : "**Memory usage (highest first):**\n"
            . $mem->map(fn ($r) => '- ' . $name($r->device_id) . ': ' . round($r->mem_pct) . '%' . $flag($r->mem_pct))->implode("\n"));

        return $out;
    }

    // ---------------------------------------------------------------- helpers

    private function devices()
    {
        return Device::query()->whereIn('device_id', $this->ids());
    }

    private function ids(): Collection
    {
        return $this->deviceIds ??= Device::hasAccess($this->user)->pluck('device_id');
    }

    private function activeAlerts()
    {
        return Alert::query()->whereIn('device_id', $this->ids())
            ->whereIn('state', [AlertState::ACTIVE, AlertState::ACKNOWLEDGED, AlertState::WORSE, AlertState::BETTER, AlertState::CHANGED]);
    }

    /** Device whose IP or name appears in the question. */
    private function findDevice(string $q): ?Device
    {
        if (preg_match('/\b(\d{1,3}(?:\.\d{1,3}){3})\b/', $q, $m)) {
            return $this->devices()->where(fn ($w) => $w->where('hostname', $m[1])->orWhere('overwrite_ip', $m[1]))->first()
                ?? $this->devices()->whereRaw('INET6_NTOA(ip) = ?', [$m[1]])->first();
        }

        // Longest names first so "core-sw-10" wins over "core-sw-1".
        return $this->devices()->get()
            ->flatMap(fn (Device $d) => collect([$d->hostname, $d->sysName, $d->display])
                ->filter(fn ($n) => is_string($n) && mb_strlen($n) >= 3)
                ->map(fn ($n) => ['name' => mb_strtolower($n), 'device' => $d]))
            ->sortByDesc(fn ($e) => mb_strlen($e['name']))
            ->first(fn ($e) => preg_match('/(^|[^a-z0-9._-])' . preg_quote($e['name'], '/') . '($|[^a-z0-9._-])/', $q))['device'] ?? null;
    }

    private function has(string $q, array $words): bool
    {
        foreach ($words as $w) {
            if (str_contains($q . ' ', $w)) {
                return true;
            }
        }

        return false;
    }

    private function portName(Port $p, bool $withDevice): string
    {
        $name = $p->ifName ?: $p->ifDescr ?: "ifIndex {$p->ifIndex}";

        return $withDevice ? ($p->device?->displayName() ?? '?') . " · {$name}" : $name;
    }

    private function severitySuffix(Collection $alerts): string
    {
        if ($alerts->isEmpty()) {
            return '';
        }
        $alerts->loadMissing('rule');
        $counts = $alerts->groupBy(fn (Alert $a) => $a->rule?->severity ?? 'unknown')->map->count();

        return ' (' . $counts->map(fn ($n, $sev) => "{$n} {$sev}")->implode(', ') . ')';
    }

    private function unit(string $class): string
    {
        return match ($class) {
            'temperature' => '°C',
            'voltage' => ' V',
            'current' => ' A',
            'fanspeed' => ' RPM',
            'humidity' => '%',
            'dbm' => ' dBm',
            default => '',
        };
    }

    private function more(int $total): string
    {
        return $total > self::LIST_LIMIT ? "\n…and " . ($total - self::LIST_LIMIT) . ' more' : '';
    }
}
