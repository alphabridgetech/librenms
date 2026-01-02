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
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<div class="container" style="margin-top:30px;">
    <ul class="nav nav-tabs">
        <li class="active"><a href="#lldp_global_config" data-toggle="tab">LLDP Global Configuration</a></li>
        <li><a href="#lldp_interface_config" data-toggle="tab">LLDP Interface Configuration</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">
        <div class="tab-pane active" id="lldp_global_config">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <strong>Basic configuration of LLDP Protocol</strong>
                </div>

                <div class="panel-body">
                    <form class="form-horizontal">

                        <!-- Protocol -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Protocol State*</label>
                            <div class="col-sm-6">
                                <select id="protocol_state" class="form-control">
                                    <option value="open">Open the LLDP protocol</option>
                                    <option value="close">Close the LLDP protocol</option>
                                </select>
                            </div>
                        </div>

                        <!-- Holdtime -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Holdtime</label>
                            <div class="col-sm-6">
                                <input type="number" id="holdtime" class="form-control" min="0" max="65535">
                                <span class="help-block">0 – 65535 seconds</span>
                                <div id="holdtime_error" class="text-danger" style="display:none;"></div>
                            </div>
                        </div>

                        <!-- Reinit -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Reinit</label>
                            <div class="col-sm-6">
                                <input type="number" id="reinit" class="form-control" min="2" max="5">
                                <span class="help-block">2 – 5 seconds</span>
                                <div id="reinit_error" class="text-danger" style="display:none;"></div>
                            </div>
                        </div>

                        <!-- Timer -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Setting the packet transmission cycle</label>
                            <div class="col-sm-6">
                                <input type="number" id="timer" class="form-control" min="5" max="65534">
                                <span class="help-block">5 – 65534 seconds</span>
                                <div id="timer_error" class="text-danger" style="display:none;"></div>
                            </div>
                        </div>

                        <!-- Apply -->
                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-6">
                                <button type="button" id="applyBtn" class="btn btn-primary" onclick="applyLldpConfig()">
                                    Apply
                                </button>
                            </div>
                        </div>

                    </form>
                </div>

                <div class="panel-footer">
                    Help: Means the TTL(Time to live) of sending LLDP packets. Its default value is 120s.
                    </br>
                    Reinit: LLDP Indicates the delay for sending consecutive packets. The default value is 2s
                </div>
            </div>
        </div>

        <div class="tab-pane" id="lldp_interface_config"></div>
    </div>
</div>

<script>
/* -----------------------
   COOKIE HELPERS
----------------------- */
function setCookie(name, value, days = 7) {
    const d = new Date();
    d.setTime(d.getTime() + (days * 86400000));
    document.cookie = name + "=" + encodeURIComponent(value) + ";expires=" + d.toUTCString() + ";path=/";
}

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

/* -----------------------
   ERROR HANDLING
----------------------- */
function showError(id, msg) {
    const el = document.getElementById(id);
    el.innerText = msg;
    el.style.display = "block";
}
function clearErrors() {
    ["holdtime_error", "reinit_error", "timer_error"].forEach(id => {
        document.getElementById(id).style.display = "none";
    });
}

/* -----------------------
   CONSTANTS
----------------------- */
const DEVICE_IP = "{{ $device->hostname }}";
const API_TOKEN = "{{ $data['api_token'] }}";
const COOKIE_PREFIX = DEVICE_IP + "_";

/* -----------------------
   LOAD LLDP CONFIG
----------------------- */
function loadLldpConfig() {
    fetch(`/api/v0/getlldp/${DEVICE_IP}`, {
        headers: {
            "Authorization": "Bearer " + API_TOKEN,
            "Accept": "application/json"
        }
    })
    .then(r => r.json())
    .then(res => {
        console.log(res);
        if (res.status !== "success") return;

        const protocolState = res.lldp.protocol;
        document.getElementById("protocol_state").value = protocolState === "close" ? "close" : "open";
        
        document.getElementById("holdtime").value = res.lldp.holdtime;
        document.getElementById("reinit").value = res.lldp.reinit;
        document.getElementById("timer").value = res.lldp.timer;

        setCookie(COOKIE_PREFIX + "lldp", JSON.stringify(res.lldp));
    });
}

/* -----------------------
   APPLY LLDP CONFIG
----------------------- */
function applyLldpConfig() {
    clearErrors();

    const btn = document.getElementById("applyBtn");
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border"></span> Applying...';

    const payload = {
        protocol_state: document.getElementById("protocol_state").value,
        holdtime: +document.getElementById("holdtime").value,
        reinit: +document.getElementById("reinit").value,
        timer: +document.getElementById("timer").value
    };

    fetch(`/api/v0/changelldp/${DEVICE_IP}`, {
        method: "POST",
        headers: {
            "Authorization": "Bearer " + API_TOKEN,
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = "Apply";

        if (res.status === "success") {
            alert("LLDP configuration updated successfully");
            setCookie(COOKIE_PREFIX + "lldp", JSON.stringify(payload));
        } else if (res.errors) {
            if (res.errors.holdtime) showError("holdtime_error", res.errors.holdtime[0]);
            if (res.errors.reinit) showError("reinit_error", res.errors.reinit[0]);
            if (res.errors.timer) showError("timer_error", res.errors.timer[0]);
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = "Apply";
        alert("Request failed");
    });
}

/* AUTO LOAD */
loadLldpConfig();
</script>
