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

<div class="panel panel-info">
    <div class="panel-heading">
        <strong>Basic configuration of PDP Protocol</strong>
    </div>

    <div class="panel-body">
        <div class="alert alert-info" id="pdp_no_data_note" style="display:none;">
            No PDP configuration has been applied through this page yet for this device. The fields below show the defaults - set them and click Apply.
        </div>

        <form class="form-horizontal">

            <!-- Protocol -->
            <div class="form-group">
                <label class="col-sm-3 control-label">Protocol State*</label>
                <div class="col-sm-6">
                    <select id="pdp_state" class="form-control">
                        <option value="open">Open the PDP protocol</option>
                        <option value="close">Close the PDP protocol</option>
                    </select>
                </div>
            </div>

            <!-- Holdtime -->
            <div class="form-group">
                <label class="col-sm-3 control-label">Holdtime</label>
                <div class="col-sm-6">
                    <input type="number" id="pdp_holdtime" class="form-control" min="10" max="255">
                    <span class="help-block">10 &ndash; 255 seconds</span>
                    <div id="pdp_holdtime_error" class="text-danger" style="display:none;"></div>
                </div>
            </div>

            <!-- Transmission Cycle -->
            <div class="form-group">
                <label class="col-sm-3 control-label">Setting the packet transmission cycle</label>
                <div class="col-sm-6">
                    <input type="number" id="pdp_tx_interval" class="form-control" min="5" max="254">
                    <span class="help-block">5 &ndash; 254 seconds</span>
                    <div id="pdp_tx_interval_error" class="text-danger" style="display:none;"></div>
                </div>
            </div>

            <!-- Protocol Version -->
            <div class="form-group">
                <label class="col-sm-3 control-label">Protocol Version</label>
                <div class="col-sm-6">
                    <select id="pdp_version" class="form-control">
                        <option value="Version1">Version1</option>
                        <option value="Version2">Version2</option>
                    </select>
                    <div id="pdp_version_error" class="text-danger" style="display:none;"></div>
                </div>
            </div>

            <!-- Apply -->
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="button" id="pdpApplyBtn" class="btn btn-primary" onclick="applyPdpConfig()">
                        Apply
                    </button>
                </div>
            </div>

        </form>
    </div>

    <div class="panel-footer">
        Help: HoldTime is the TTL (Time to live) of sending PDP packets - default 120s.
        <br>
        Transmission Cycle is the delay between consecutive PDP packets - default 30s.
        <br>
        <span class="text-muted">Note: the fields above show the last configuration successfully applied through this page. This device has no way to report its current PDP state back, so this is not a live read from the switch.</span>
    </div>
</div>

<script>
    const DEVICE_IP = "{{ $device->hostname }}";
    const API_TOKEN = "{{ $data['api_token'] }}";

    function showPdpError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = "block";
    }

    function clearPdpErrors() {
        ["pdp_holdtime_error", "pdp_tx_interval_error", "pdp_version_error"].forEach(id => {
            document.getElementById(id).style.display = "none";
        });
    }

    function loadPdpConfig() {
        fetch(`/api/v0/pdp/show/${DEVICE_IP}`, {
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            }
        })
            .then(r => r.json())
            .then(res => {
                if (res.status !== "success") return;

                document.getElementById('pdp_no_data_note').style.display = res.found ? "none" : "inline";

                if (res.found && res.pdp) {
                    document.getElementById("pdp_state").value = res.pdp.pdp_state === "close" ? "close" : "open";
                    if (res.pdp.holdtime) document.getElementById("pdp_holdtime").value = res.pdp.holdtime;
                    if (res.pdp.tx_interval) document.getElementById("pdp_tx_interval").value = res.pdp.tx_interval;
                    if (res.pdp.version) document.getElementById("pdp_version").value = res.pdp.version;
                }
            });
    }

    function applyPdpConfig() {
        clearPdpErrors();

        const btn = document.getElementById("pdpApplyBtn");
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Applying...';

        const state = document.getElementById("pdp_state").value;
        const payload = { pdp_state: state };

        if (state === "open") {
            payload.holdtime = +document.getElementById("pdp_holdtime").value;
            payload.tx_interval = +document.getElementById("pdp_tx_interval").value;
            payload.version = document.getElementById("pdp_version").value;
        }

        fetch(`/api/v0/pdp/set/${DEVICE_IP}`, {
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
                    alert("PDP configuration updated successfully");
                    document.getElementById('pdp_no_data_note').style.display = "none";
                } else if (res.errors) {
                    if (res.errors.holdtime) showPdpError("pdp_holdtime_error", res.errors.holdtime[0]);
                    if (res.errors.tx_interval) showPdpError("pdp_tx_interval_error", res.errors.tx_interval[0]);
                    if (res.errors.version) showPdpError("pdp_version_error", res.errors.version[0]);
                    if (!res.errors.holdtime && !res.errors.tx_interval && !res.errors.version) {
                        alert(res.message || "PDP configuration failed");
                    }
                } else {
                    alert(res.message || "PDP configuration failed");
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = "Apply";
                alert("Request failed");
            });
    }

    /* AUTO LOAD */
    loadPdpConfig();
</script>
