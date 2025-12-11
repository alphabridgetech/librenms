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

// Add prefix based on hostname
function ck(key) {
    let host = "{{ $device->hostname }}";
    return host + "_" + key;
}

function loadFromCookies() {
    document.getElementById("device_type").innerText = getCookie(ck("device_type")) || "Loading...";
    document.getElementById("bios_version").innerText = getCookie(ck("bios_version")) || "Loading...";
    document.getElementById("firmware").innerText = getCookie(ck("firmware")) || "Loading...";
    document.getElementById("serial").innerText = getCookie(ck("serial")) || "Loading...";
    document.getElementById("mac").innerText = getCookie(ck("mac")) || "Loading...";
    document.getElementById("current_time").innerText = getCookie(ck("current_time")) || "Loading...";
    document.getElementById("uptime").innerText = getCookie(ck("uptime")) || "Loading...";
}

function loadSystemInfo() {

    let ip = getCookie(ck("device_ip")) || "{{ $device->hostname }}";
    let apiToken = getCookie(ck("api_token")) || "{{ $data['api_token'] }}";

    setCookie(ck("device_ip"), ip);
    setCookie(ck("api_token"), apiToken);

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


