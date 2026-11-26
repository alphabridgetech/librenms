{{-- remove dd() otherwise JS will not run --}}
@php
    // Shown at once from TeleQuill's own SNMP polling, then replaced by live values from the switch.
    $polledAt = $device->last_polled;
    $polledUptime = $device->status && $device->uptime && $polledAt
        ? (int) $device->uptime + max(0, now()->getTimestamp() - $polledAt->getTimestamp())
        : null;
@endphp
{{-- @php dd($data['api_token']); @endphp --}}

<style>
    .sysinfo-wrap {
        margin-top: 20px;
    }

    .sysinfo-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        padding: 16px 20px;
        margin-bottom: 20px;
    }

    .sysinfo-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #2d3748;
    }

    .sysinfo-refresh-btn {
        background: #2d3748;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: .02em;
    }

    .sysinfo-refresh-btn:hover {
        background: #1a202c;
        color: #fff;
    }

    .sysinfo-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        margin-bottom: 20px;
    }

    .sysinfo-card {
        flex: 1 1 210px;
        min-width: 210px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border-top: 4px solid var(--accent, #4e73df);
        padding: 16px 18px;
    }

    .sysinfo-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
    }

    .sysinfo-card-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #8892a0;
        margin-bottom: 8px;
    }

    .sysinfo-card-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--accent, #4e73df);
        color: #fff;
        font-size: 13px;
        opacity: .9;
    }

    .sysinfo-card-value {
        font-size: 19px;
        font-weight: 700;
        color: #2d3748;
        word-break: break-word;
        line-height: 1.3;
        margin-top: 2px;
    }

    .sysinfo-status { margin: 8px 2px 0; font-size: 12.5px; color: #6b7280; }
    .sysinfo-status.live { color: #15803d; }
    .sysinfo-status.failed { color: #b45309; }
    .sysinfo-warn { margin-top: 6px; font-size: 12px; font-weight: 600; color: #b45309; }

    .sysinfo-card-value.loading {
        color: #b0b7c3;
        font-weight: 500;
        font-size: 14px;
    }
</style>

<div class="container-fluid sysinfo-wrap">
    <div class="sysinfo-header">
        <h3><i class="fa fa-info-circle" aria-hidden="true" style="color:#4e73df; margin-right:8px;"></i>System Information</h3>
        <button type="button" class="sysinfo-refresh-btn" onclick="loadSystemInfo()">
            <i class="fa fa-refresh" aria-hidden="true"></i> Refresh
        </button>
    </div>
    <div class="sysinfo-status" id="sysinfo_status"></div>

    <div class="sysinfo-grid">
        <div class="sysinfo-card" style="--accent:#4e73df;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Device Type</div>
                <div class="sysinfo-card-icon"><i class="fa fa-microchip" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value" id="device_type">{{ $device->hardware ?: '—' }}</div>
        </div>

        <div class="sysinfo-card" style="--accent:#1cc88a;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Firmware Version</div>
                <div class="sysinfo-card-icon"><i class="fa fa-code" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value" id="firmware">{{ $device->version ?: '—' }}</div>
        </div>

        <div class="sysinfo-card" style="--accent:#f6c23e;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">BIOS Version</div>
                <div class="sysinfo-card-icon"><i class="fa fa-cogs" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="bios_version">—</div>
        </div>

        <div class="sysinfo-card" style="--accent:#e74a3b;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Serial Num</div>
                <div class="sysinfo-card-icon"><i class="fa fa-barcode" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value" id="serial">{{ $device->serial ?: '—' }}</div>
        </div>

        <div class="sysinfo-card" style="--accent:#36b9cc;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">MAC Address</div>
                <div class="sysinfo-card-icon"><i class="fa fa-sitemap" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="mac">—</div>
        </div>

        <div class="sysinfo-card" style="--accent:#4e73df;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">IP / Hostname</div>
                <div class="sysinfo-card-icon"><i class="fa fa-globe" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value" id="ip_address">{{ $device->hostname }}</div>
        </div>

        <div class="sysinfo-card" style="--accent:#6f42c1;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Current Time</div>
                <div class="sysinfo-card-icon"><i class="fa fa-calendar" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="current_time">—</div>
            <div class="sysinfo-warn" id="clock_warn" style="display:none;"></div>
        </div>

        <div class="sysinfo-card" style="--accent:#6f42c1;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Uptime</div>
                <div class="sysinfo-card-icon"><i class="fa fa-clock-o" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value{{ $polledUptime === null ? ' loading' : '' }}" id="uptime">—</div>
        </div>
    </div>
</div>
<script>
(function () {
    const host = @json($device->hostname);
    const apiToken = @json($data['api_token']);
    const polledAt = @json($polledAt ? $polledAt->format('H:i:s') : null);
    let uptimeBase = @json($polledUptime);   // seconds, from TeleQuill's last poll
    let uptimeFrom = Date.now();
    let clockBase = null, clockFrom = 0;      // the switch's own clock, once read live
    let timer = null;

    // Values used to be cached in cookies (stale after failures, and the API token
    // sat in a readable cookie). Remove those cookies for this switch.
    ['device_type', 'bios_version', 'firmware', 'serial', 'mac', 'current_time', 'uptime', 'device_ip', 'api_token']
        .forEach(k => { document.cookie = host + '_' + k + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'; });

    const el = (id) => document.getElementById(id);
    function setCard(id, value) {
        el(id).textContent = value || '—';
        el(id).classList.toggle('loading', !value);
    }
    function status(text, kind) {
        el('sysinfo_status').textContent = text;
        el('sysinfo_status').className = 'sysinfo-status' + (kind ? ' ' + kind : '');
    }

    // "0:00:38:13" (days:hours:minutes:seconds) -> seconds
    function parseUptime(v) {
        const p = String(v || '').trim().split(':').map(Number);
        if (p.length !== 4 || p.some(isNaN)) return null;
        return ((p[0] * 24 + p[1]) * 60 + p[2]) * 60 + p[3];
    }
    function formatUptime(sec) {
        const d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
        const pad = (n) => String(n).padStart(2, '0');
        return (d ? d + (d === 1 ? ' day ' : ' days ') : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
    }
    // "2025-1-1 1:26:47" -> Date (switch local time)
    function parseClock(v) {
        const m = String(v || '').match(/(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})/);
        return m ? new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +m[6]) : null;
    }
    function formatClock(d) {
        const pad = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    // Uptime and the switch clock keep counting between refreshes.
    function tick() {
        if (uptimeBase !== null) setCard('uptime', formatUptime(uptimeBase + Math.floor((Date.now() - uptimeFrom) / 1000)));
        if (clockBase) setCard('current_time', formatClock(new Date(clockBase.getTime() + (Date.now() - clockFrom))));
    }
    function checkClock() {
        if (!clockBase) { el('clock_warn').style.display = 'none'; return; }
        const offMin = Math.round((clockBase.getTime() - clockFrom) / 60000);
        if (Math.abs(offMin) < 15) { el('clock_warn').style.display = 'none'; return; }
        const days = Math.abs(offMin) / 1440, hours = Math.abs(offMin) / 60;
        const size = days >= 1 ? Math.round(days) + ' day(s)' : hours >= 1 ? Math.round(hours) + ' hour(s)' : Math.abs(offMin) + ' min';
        el('clock_warn').textContent = '⚠ Switch clock is wrong: ' + size + (offMin < 0 ? ' behind' : ' ahead') + '. Set NTP or the time on the switch.';
        el('clock_warn').style.display = '';
    }

    window.loadSystemInfo = function () {
        status(polledAt
            ? `Showing values TeleQuill polled at ${polledAt}. Reading live values from the switch…`
            : 'Reading live values from the switch…');
        fetch(`/api/v0/systeminfo/${encodeURIComponent(host)}`, {
            headers: { 'Authorization': 'Bearer ' + apiToken, 'Accept': 'application/json' },
        })
        .then(res => res.json().catch(() => ({})).then(body => ({ ok: res.ok, body })))
        .then(({ ok, body }) => {
            if (!ok || body.status !== 'success' || !body.data) {
                const why = body.message || 'the switch did not answer';
                status(`Couldn't read live values: ${why}` + (polledAt ? ` Showing what TeleQuill polled at ${polledAt}.` : ''), 'failed');
                return;
            }
            const d = body.data;
            // A field the switch didn't report keeps the value TeleQuill already had.
            const live = (id, value) => { if (value && String(value).trim()) setCard(id, String(value).trim()); };
            live('device_type', d.device_type || d.model);
            live('firmware', d.firmware);
            live('bios_version', d.bios_version);
            live('serial', d.serial);
            live('mac', d.mac);
            const up = parseUptime(d.uptime);
            if (up !== null) { uptimeBase = up; uptimeFrom = Date.now(); } else { uptimeBase = null; setCard('uptime', d.uptime); }
            clockBase = parseClock(d.current_time); clockFrom = Date.now();
            if (!clockBase) setCard('current_time', d.current_time);
            checkClock();
            tick();
            status('Live from the switch at ' + new Date().toLocaleTimeString() + '.', 'live');
        })
        .catch(() => status("Couldn't reach the TeleQuill server." + (polledAt ? ` Showing what TeleQuill polled at ${polledAt}.` : ''), 'failed'));
    };

    tick();
    clearInterval(timer);
    timer = setInterval(tick, 1000);
    loadSystemInfo();
})();
</script>
