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
        <strong>Remote NMS / MQTT Configuration</strong>
        <span id="mqtt_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <button type="button" id="mqttRefreshBtn" class="btn btn-xs btn-default pull-right" onclick="loadMqttConfig()">
            <i class="fa fa-refresh"></i> Refresh
        </button>
    </div>

    <div class="panel-body">
        <form class="form-horizontal">

            <!-- Remote NMS Enable -->
            <div class="form-group">
                <label class="col-sm-3 control-label">Remote NMS*</label>
                <div class="col-sm-6">
                    <select id="mqtt_enable" class="form-control">
                        <option value="enable">Enable</option>
                        <option value="disable">Disable</option>
                    </select>
                </div>
            </div>

            <!-- Default MQTT Server -->
            <div class="form-group">
                <label class="col-sm-3 control-label">Default MQTT Server</label>
                <div class="col-sm-6">
                    <input type="text" id="mqtt_default_server" class="form-control" placeholder="e.g. mqtt.example.com (optional)">
                </div>
            </div>

            <!-- MQTT Server -->
            <div class="form-group">
                <label class="col-sm-3 control-label">MQTT Server*</label>
                <div class="col-sm-6">
                    <input type="text" id="mqtt_server" class="form-control" placeholder="e.g. mqtt.example.com">
                    <div id="mqtt_server_error" class="text-danger" style="display:none;"></div>
                </div>
            </div>

            <!-- DNS 1 -->
            <div class="form-group">
                <label class="col-sm-3 control-label">DNS 1</label>
                <div class="col-sm-6">
                    <input type="text" id="mqtt_dns1" class="form-control" placeholder="e.g. 8.8.8.8 (optional)">
                </div>
            </div>

            <!-- DNS 2 -->
            <div class="form-group">
                <label class="col-sm-3 control-label">DNS 2</label>
                <div class="col-sm-6">
                    <input type="text" id="mqtt_dns2" class="form-control" placeholder="e.g. 8.8.4.4 (optional)">
                    <span class="help-block">DNS 1 and DNS 2 must both be provided together, or both left blank.</span>
                    <div id="mqtt_dns_error" class="text-danger" style="display:none;"></div>
                </div>
            </div>

            <!-- Apply -->
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="button" id="mqttApplyBtn" class="btn btn-primary" onclick="applyMqttConfig()">
                        Apply
                    </button>
                </div>
            </div>

        </form>
    </div>

    <div class="panel-footer">
        Help: MQTT Server is required. Default MQTT Server and DNS are optional - DNS 1/DNS 2 must be set together or left blank.
    </div>
</div>

<script>
    const DEVICE_IP = "{{ $device->hostname }}";
    const API_TOKEN = "{{ $data['api_token'] }}";

    function showMqttError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = "block";
    }

    function clearMqttErrors() {
        ["mqtt_server_error", "mqtt_dns_error"].forEach(id => {
            document.getElementById(id).style.display = "none";
        });
    }

    function loadMqttConfig(force) {
        const btn = document.getElementById('mqttRefreshBtn');

        // A forced refresh means a real live SSH read (~10-15s), not the
        // instant cached response - show that it's actually working instead
        // of looking like the click did nothing.
        if (force && btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border"></span> Refreshing...';
        }

        fetch(`/api/v0/mqtt/show/${DEVICE_IP}` + (force ? '?force=1' : ''), {
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            }
        })
            .then(r => r.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-refresh"></i> Refresh';
                }

                document.getElementById('mqtt_cache_note').style.display = res.cached ? "inline" : "none";
                if (res.status !== "success") return;

                document.getElementById("mqtt_enable").value = (res.remote_nms_enable || '').toLowerCase() === "enable" ? "enable" : "disable";
                document.getElementById("mqtt_default_server").value = res.default_server || '';
                document.getElementById("mqtt_server").value = res.server || '';
                document.getElementById("mqtt_dns1").value = res.dns1 || '';
                document.getElementById("mqtt_dns2").value = res.dns2 || '';
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-refresh"></i> Refresh';
                }
            });
    }

    function applyMqttConfig() {
        clearMqttErrors();

        const server = document.getElementById("mqtt_server").value.trim();
        const dns1 = document.getElementById("mqtt_dns1").value.trim();
        const dns2 = document.getElementById("mqtt_dns2").value.trim();

        if (!server) {
            showMqttError("mqtt_server_error", "MQTT Server is required");
            return;
        }

        if ((dns1 !== '') !== (dns2 !== '')) {
            showMqttError("mqtt_dns_error", "DNS 1 and DNS 2 must both be provided together, or both left blank");
            return;
        }

        const btn = document.getElementById("mqttApplyBtn");
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Applying...';

        const payload = {
            remote_nms_enable: document.getElementById("mqtt_enable").value,
            default_server: document.getElementById("mqtt_default_server").value.trim(),
            server: server,
            dns1: dns1,
            dns2: dns2
        };

        fetch(`/api/v0/mqtt/set/${DEVICE_IP}`, {
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
                    alert(res.message || "Remote NMS / MQTT configuration updated successfully");
                    loadMqttConfig();
                } else if (res.errors) {
                    if (res.errors.server) showMqttError("mqtt_server_error", res.errors.server[0]);
                    if (res.errors.dns1 || res.errors.dns2) showMqttError("mqtt_dns_error", (res.errors.dns1 || res.errors.dns2)[0]);
                    if (!res.errors.server && !res.errors.dns1 && !res.errors.dns2) {
                        alert(res.message || "MQTT configuration failed");
                    }
                } else {
                    alert(res.message || "MQTT configuration failed");
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = "Apply";
                alert("Request failed");
            });
    }

    /* AUTO LOAD */
    loadMqttConfig();
</script>
