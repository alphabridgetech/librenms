<style>
    .ntp-box {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 6px;
        border: 1px solid #ddd;
    }

    .ntp-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #eee;
    }

    .ntp-row:last-child {
        border-bottom: none;
    }

    .ntp-label {
        font-weight: 600;
        color: #333;
    }

    .ntp-value {
        color: #555;
    }

    .status-ok {
        color: green;
        font-weight: bold;
    }

    .status-bad {
        color: red;
        font-weight: bold;
    }
</style>

<div class="container" style="margin-top:30px;">
    <div class="panel panel-info">
        <div class="panel-heading">
            <strong>NTP Status</strong>
        </div>

        <div class="panel-body">
            <div class="ntp-box" id="ntpData">
                <!-- Data will load here -->
            </div>

            <br>
            <button id="refreshBtn" class="btn btn-primary" onclick="loadNTP()">
                Refresh NTP
            </button>
        </div>
    </div>
</div>
<script>
function loadNTP() {

    const btn = document.getElementById("refreshBtn");
    const box = document.getElementById("ntpData");

    const ip = "192.168.200.245";
    const apiToken = "{{ $data['api_token'] }}";

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border"></span> Loading...';

    fetch(`/api/v0/ntp/${ip}`, {
        method: "GET",
        headers: {
            "Authorization": "Bearer " + apiToken,
            "Accept": "application/json"
        }
    })
    .then(res => res.json())
    .then(res => {

        btn.disabled = false;
        btn.innerHTML = 'Refresh NTP';

        if (res.status !== "success") {
            box.innerHTML = "<p style='color:red;'>Failed to load NTP data</p>";
            return;
        }

        const ntp = res.ntp;

        box.innerHTML = `
            <div class="ntp-row"><span class="ntp-label">Status</span><span class="ntp-value ${ntp.status === 'synchronized' ? 'status-ok' : 'status-bad'}">${ntp.status}</span></div>
            <div class="ntp-row"><span class="ntp-label">Current Time</span><span class="ntp-value">${ntp.current_time}</span></div>
            <div class="ntp-row"><span class="ntp-label">Timezone</span><span class="ntp-value">${ntp.timezone}</span></div>
            <div class="ntp-row"><span class="ntp-label">Stratum</span><span class="ntp-value">${ntp.stratum}</span></div>
            <div class="ntp-row"><span class="ntp-label">Reference IP</span><span class="ntp-value">${ntp.reference_id}</span></div>
            <div class="ntp-row"><span class="ntp-label">Offset</span><span class="ntp-value">${ntp.offset}</span></div>
            <div class="ntp-row"><span class="ntp-label">Jitter</span><span class="ntp-value">${ntp.jitter}</span></div>
            <div class="ntp-row"><span class="ntp-label">Root Delay</span><span class="ntp-value">${ntp.root_delay}</span></div>
            <div class="ntp-row"><span class="ntp-label">Root Dispersion</span><span class="ntp-value">${ntp.root_dispersion}</span></div>
            <div class="ntp-row"><span class="ntp-label">Packets Sent</span><span class="ntp-value">${ntp.packets_sent}</span></div>
            <div class="ntp-row"><span class="ntp-label">Packets Received</span><span class="ntp-value">${ntp.packets_received}</span></div>
            <div class="ntp-row"><span class="ntp-label">Last Update</span><span class="ntp-value">${ntp.last_update}</span></div>
        `;
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = 'Refresh NTP';
        box.innerHTML = "<p style='color:red;'>Error loading data</p>";
    });
}

// Auto load on page open
window.onload = loadNTP;
</script>