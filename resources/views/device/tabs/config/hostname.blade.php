<style>
    .spinner-border {
        width: 16px;
        height: 16px;
        border: 2px solid #fff;
        border-right-color: transparent;
        border-radius: 50%;
        display: inline-block;
        animation: spin 0.75s linear infinite;
    }

    @keyframes spin {
        100% { transform: rotate(360deg); }
    }
</style>

<div class="container" style="margin-top:30px;">
    <div class="panel panel-info">
        <div class="panel-heading">
            <strong>Hostname</strong>
        </div>

        <div class="panel-body">
            <p>Configure the hostname.</p>

            <form class="form-horizontal" id="hostnameForm" style="margin-top:20px;">

                <div class="form-group">
                    <label class="col-sm-2 control-label">Hostname*</label>
                    <div class="col-sm-6">
                        <input type="text" id="hostname_input" class="form-control" value="Loading...">
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-6">
                        <button type="button" id="applyBtn" class="btn btn-primary" onclick="changeHostname()">
                            Apply
                        </button>
                    </div>
                </div>

            </form>

        </div>

        <div class="panel-footer">
            Help: Configure the host name of the switch.
        </div>
    </div>
</div>

<script>
    const hostnameKey = "{{ $device->hostname }}_hostname"; // cookie key per device

    function setCookie(name, value, days = 7) {
        const date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        document.cookie = name + "=" + encodeURIComponent(value) + ";expires=" + date.toUTCString() + ";path=/";
    }

    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let c of ca) {
            c = c.trim();
            if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length));
        }
        return null;
    }

    function loadHostname() {
        // 1️⃣ Show from cookie immediately if available
        const saved = getCookie(hostnameKey);
        if (saved) {
            document.getElementById("hostname_input").value = saved;
        }

        // 2️⃣ Fetch latest from API
        let ip = "{{ $device->hostname }}"; 
        let apiToken = "{{ $data['api_token'] }}";
        let apiUrl = `/api/v0/gethostname/${ip}`;

        fetch(apiUrl, {
            method: "GET",
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Accept": "application/json"
            }
        })
        .then(res => res.json())
        .then(res => {
            if (res.status !== "success") {
                if (!saved) document.getElementById("hostname_input").value = "Error";
                return;
            }

            document.getElementById("hostname_input").value = res.hostname;
            setCookie(hostnameKey, res.hostname); // save to cookie
        })
        .catch(err => {
            console.error(err);
            if (!saved) document.getElementById("hostname_input").value = "API Error";
        });
    }

    function changeHostname() {
        const btn = document.getElementById("applyBtn");
        const newHostname = document.getElementById("hostname_input").value;
        const ip = "{{ $device->hostname }}";
        const apiToken = "{{ $data['api_token'] }}";

        if (!newHostname) {
            alert("Hostname cannot be empty");
            return;
        }

        // Show spinner
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Applying...';

        fetch(`/api/v0/changehostname/${ip}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ hostname: newHostname })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = 'Apply';

            if (res.status === "success") {
                alert("Hostname changed successfully!");
                setCookie(hostnameKey, newHostname); // save to cookie
            } else {
                alert("Failed to change hostname");
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = 'Apply';
            alert("Error: " + err);
        });
    }

    // Auto-load
    loadHostname();
</script>
