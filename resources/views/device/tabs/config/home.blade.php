{{-- remove dd() otherwise JS will not run --}}
@php

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

    <div class="sysinfo-grid">
        <div class="sysinfo-card" style="--accent:#4e73df;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Device Type</div>
                <div class="sysinfo-card-icon"><i class="fa fa-microchip" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="device_type">Loading...</div>
        </div>

        <div class="sysinfo-card" style="--accent:#1cc88a;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Firmware Version</div>
                <div class="sysinfo-card-icon"><i class="fa fa-code" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="firmware">Loading...</div>
        </div>

        <div class="sysinfo-card" style="--accent:#f6c23e;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">BIOS Version</div>
                <div class="sysinfo-card-icon"><i class="fa fa-cogs" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="bios_version">Loading...</div>
        </div>

        <div class="sysinfo-card" style="--accent:#e74a3b;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Serial Num</div>
                <div class="sysinfo-card-icon"><i class="fa fa-barcode" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="serial">Loading...</div>
        </div>

        <div class="sysinfo-card" style="--accent:#36b9cc;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">MAC Address</div>
                <div class="sysinfo-card-icon"><i class="fa fa-sitemap" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="mac">Loading...</div>
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
            <div class="sysinfo-card-value loading" id="current_time">Loading...</div>
        </div>

        <div class="sysinfo-card" style="--accent:#6f42c1;">
            <div class="sysinfo-card-top">
                <div class="sysinfo-card-label">Uptime</div>
                <div class="sysinfo-card-icon"><i class="fa fa-clock-o" aria-hidden="true"></i></div>
            </div>
            <div class="sysinfo-card-value loading" id="uptime">Loading...</div>
        </div>
    </div>
</div>
<script>

function setCookie(name, value, days = 7) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for (let c of ca) {
        c = c.trim();
        if (c.indexOf(nameEQ) === 0)
            return c.substring(nameEQ.length);
    }
    return null;
}

// Add prefix based on hostname
function ck(key) {
    let host = "{{ $device->hostname }}";
    return host + "_" + key;
}

function setCardValue(id, value) {
    const el = document.getElementById(id);
    el.innerText = value || "Loading...";
    el.classList.toggle('loading', !value);
}

function loadFromCookies() {
    setCardValue("device_type", getCookie(ck("device_type")));
    setCardValue("bios_version", getCookie(ck("bios_version")));
    setCardValue("firmware", getCookie(ck("firmware")));
    setCardValue("serial", getCookie(ck("serial")));
    setCardValue("mac", getCookie(ck("mac")));
    setCardValue("current_time", getCookie(ck("current_time")));
    setCardValue("uptime", getCookie(ck("uptime")));
}

function loadSystemInfo() {
    let ip = getCookie(ck("device_ip")) || "{{ $device->hostname }}";
    let apiToken = "{{ $data['api_token'] }}";
    let apiUrl = `/api/v0/systeminfo/${ip}`;


    setCookie(ck("device_ip"), ip);
    setCookie(ck("api_token"), apiToken);


    fetch(apiUrl, {
        method: "GET",
        headers: {
            "Authorization": "Bearer " + apiToken,
            "Accept": "application/json"
        }
    })
    .then(res => res.json())
    .then(res => {
        if (res.status !== "success") return;

        const d = res.data;

        setCookie(ck("device_type"), d.device_type);
        setCookie(ck("bios_version"), d.bios_version);
        setCookie(ck("firmware"), d.firmware);
        setCookie(ck("serial"), d.serial);
        setCookie(ck("mac"), d.mac);
        setCookie(ck("current_time"), d.current_time);
        setCookie(ck("uptime"), d.uptime);

        loadFromCookies();
    });
}

loadFromCookies();
loadSystemInfo();


</script>

