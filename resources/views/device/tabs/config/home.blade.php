{{-- remove dd() otherwise JS will not run --}}
@php
    
@endphp
{{-- @php dd($data['api_token']); @endphp --}}

<div class="container-fluid" style="margin-top: 20px;">
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">System Information</h3>
        </div>

        <div class="panel-body">
            <table class="table table-striped table-bordered" style="margin:0;">
                <tbody id="system-info-body">

                    <tr>
                        <th width="200">Device Type</th>
                        <td id="device_type">Loading...</td>
                    </tr>

                    <tr>
                        <th>BIOS Version</th>
                        <td id="bios_version">Loading...</td>
                    </tr>

                    <tr>
                        <th>Firmware Version</th>
                        <td id="firmware">Loading...</td>
                    </tr>

                    <tr>
                        <th>Serial Num</th>
                        <td id="serial">Loading...</td>
                    </tr>

                    <tr>
                        <th>MAC Address</th>
                        <td id="mac">Loading...</td>
                    </tr>

                    <tr>
                        <th>IP / Hostname</th>
                        <td id="ip_address">{{ $device->hostname }}</td>
                    </tr>

                    <tr>
                        <th>Current Time</th>
                        <td id="current_time">Loading...</td>
                    </tr>

                    <tr>
                        <th>Uptime</th>
                        <td id="uptime">Loading...</td>
                    </tr>

                </tbody>
            </table>
        </div>

        <div class="panel-footer text-right">
            <button class="btn btn-default" onclick="loadSystemInfo()">Refresh</button>
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

// Show saved data IMMEDIATELY
function loadFromCookies() {
    document.getElementById("device_type").innerText = getCookie("device_type") || "Loading...";
    document.getElementById("bios_version").innerText = getCookie("bios_version") || "Loading...";
    document.getElementById("firmware").innerText = getCookie("firmware") || "Loading...";
    document.getElementById("serial").innerText = getCookie("serial") || "Loading...";
    document.getElementById("mac").innerText = getCookie("mac") || "Loading...";
    document.getElementById("current_time").innerText = getCookie("current_time") || "Loading...";
    document.getElementById("uptime").innerText = getCookie("uptime") || "Loading...";
}

// Background API update
function loadSystemInfo() {

    let ip = getCookie("device_ip") || "{{ $device->hostname }}";
    let apiToken = getCookie("api_token") || "{{ $data['api_token'] }}";

    setCookie("device_ip", ip);
    setCookie("api_token", apiToken);

    fetch(`/api/v0/system_info/${ip}`, {
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

        // Save to cookies for next fast load
        setCookie("device_type", d.device_type);
        setCookie("bios_version", d.bios_version);
        setCookie("firmware", d.firmware);
        setCookie("serial", d.serial);
        setCookie("mac", d.mac);
        setCookie("current_time", d.current_time);
        setCookie("uptime", d.uptime);

        // Update UI instantly
        loadFromCookies();
    });
}

// 1️⃣ show instantly
loadFromCookies();

// 2️⃣ update from API (slow but background)
loadSystemInfo();

</script>


