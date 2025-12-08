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
    function loadSystemInfo() {

        let ip = "{{ $device->hostname }}";
        let apiToken = "{{ $data['api_token'] }}";

        let apiUrl = `/api/v0/system_info/${ip}`;

        fetch(apiUrl, {
            method: "GET",
            headers: {
                "Authorization": "Bearer " + apiToken,   // ✔ Send token
                "Accept": "application/json"
            }
        })
        .then(res => res.json())
        .then(res => {
            if (res.status !== "success") {
                alert("Error loading system info");
                return;
            }

            const d = res.data;

            document.getElementById("device_type").innerText = d.device_type ?? "N/A";
            document.getElementById("bios_version").innerText = d.bios_version ?? "N/A";
            document.getElementById("firmware").innerText = d.firmware ?? "N/A";
            document.getElementById("serial").innerText = d.serial ?? "N/A";
            document.getElementById("mac").innerText = d.mac ?? "N/A";
            document.getElementById("current_time").innerText = d.current_time ?? "N/A";
            document.getElementById("uptime").innerText = d.uptime ?? "N/A";
        })
        .catch(err => {
            alert("API Error: " + err);
        });
    }

    // Auto-load API on page open
    loadSystemInfo();
</script>

