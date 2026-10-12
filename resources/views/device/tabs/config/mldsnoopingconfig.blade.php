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

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    <div class="panel panel-info">
        <div class="panel-heading">
            <strong>IPv6 MLD Snooping - Basic Configuration</strong>
            <span id="mld_loading_note" class="text-muted" style="margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> reading live configuration from device...
            </span>
            <span id="mld_error_note" class="text-danger" style="display:none; margin-left:10px;"></span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadMldConfig()">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div class="panel-body">
            <form class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-3 control-label">MLD Snooping*</label>
                    <div class="col-sm-6">
                        <select id="mld_snooping" class="form-control">
                            <option value="enable">Enable</option>
                            <option value="disable">Disable</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Router Age Timer (s)*</label>
                    <div class="col-sm-6">
                        <input type="number" id="mld_router_age_timer" class="form-control" min="10" max="2147483647">
                        <div id="mld_router_age_timer_error" class="text-danger" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Response Age Timer (s)*</label>
                    <div class="col-sm-6">
                        <input type="number" id="mld_response_age_timer" class="form-control" min="10" max="2147483647">
                        <div id="mld_response_age_timer_error" class="text-danger" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Querier*</label>
                    <div class="col-sm-6">
                        <select id="mld_querier" class="form-control">
                            <option value="enable">Enable</option>
                            <option value="disable">Disable</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Querier Address</label>
                    <div class="col-sm-6">
                        <input type="text" id="mld_querier_address" class="form-control" placeholder="e.g. fe80::30c0:657b:cfb5:cdfe">
                        <div id="mld_querier_address_error" class="text-danger" style="display:none;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Solicitation*</label>
                    <div class="col-sm-6">
                        <select id="mld_solicitation" class="form-control">
                            <option value="enable">Enable</option>
                            <option value="disable">Disable</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <div class="col-sm-offset-3 col-sm-6">
                        <button type="button" id="mldApplyBtn" class="btn btn-primary" onclick="applyMldConfig()">
                            <span class="spinner-border" style="display:none;"></span> Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-info">
        <ul>
            <li>Router Age and Response Age Timer range: 10 - 2147483647 seconds.</li>
            <li>When MLD Snooping is Disable, only "no ipv6 mld-snooping" is sent - the other fields are not applied.</li>
            <li>Querier Address is optional and is applied even while Querier is Disable.</li>
        </ul>
    </div>
</div>

<script>
    const MLD_DEVICE_IP = "{{ $device->hostname }}";
    const MLD_API_TOKEN = "{{ $data['api_token'] }}";
    const MLD_FIELD_ERRORS = ['router_age_timer', 'response_age_timer', 'querier_address'];

    function fillMldFields(mld) {
        document.getElementById("mld_snooping").value = mld.mld_snooping === "disable" ? "disable" : "enable";
        document.getElementById("mld_router_age_timer").value = mld.router_age_timer || "";
        document.getElementById("mld_response_age_timer").value = mld.response_age_timer || "";
        document.getElementById("mld_querier").value = mld.querier === "disable" ? "disable" : "enable";
        document.getElementById("mld_querier_address").value = mld.querier_address || "";
        document.getElementById("mld_solicitation").value = mld.solicitation === "disable" ? "disable" : "enable";
    }

    function clearMldErrors() {
        MLD_FIELD_ERRORS.forEach(field => {
            document.getElementById(`mld_${field}_error`).style.display = "none";
        });
    }

    function showMldFieldError(field, msg) {
        const el = document.getElementById(`mld_${field}_error`);
        el.innerText = msg;
        el.style.display = "block";
    }

    function loadMldConfig() {
        const loadingNote = document.getElementById('mld_loading_note');
        const errorNote = document.getElementById('mld_error_note');
        loadingNote.style.display = "inline";
        errorNote.style.display = "none";

        fetch(`/api/v0/mld/show/${MLD_DEVICE_IP}`, {
            headers: { "Authorization": "Bearer " + MLD_API_TOKEN, "Accept": "application/json" }
        })
            .then(r => r.json())
            .then(res => {
                loadingNote.style.display = "none";
                if (res.status !== "success") {
                    errorNote.innerText = res.message || "Failed to read IPv6 MLD snooping configuration";
                    errorNote.style.display = "inline";
                    return;
                }
                if (res.mld) {
                    fillMldFields(res.mld);
                }
            })
            .catch(() => {
                loadingNote.style.display = "none";
                errorNote.innerText = "Request failed while reading IPv6 MLD snooping configuration";
                errorNote.style.display = "inline";
            });
    }

    function applyMldConfig() {
        clearMldErrors();

        const btn = document.getElementById("mldApplyBtn");
        btn.disabled = true;
        btn.querySelector('.spinner-border').style.display = "inline-block";

        const payload = {
            mld_snooping: document.getElementById("mld_snooping").value,
            router_age_timer: +document.getElementById("mld_router_age_timer").value,
            response_age_timer: +document.getElementById("mld_response_age_timer").value,
            querier: document.getElementById("mld_querier").value,
            querier_address: document.getElementById("mld_querier_address").value.trim(),
            solicitation: document.getElementById("mld_solicitation").value
        };

        fetch(`/api/v0/mld/set/${MLD_DEVICE_IP}`, {
            method: "POST",
            headers: { "Authorization": "Bearer " + MLD_API_TOKEN, "Content-Type": "application/json", "Accept": "application/json" },
            body: JSON.stringify(payload)
        })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.querySelector('.spinner-border').style.display = "none";

                if (res.status === "success") {
                    alert("IPv6 MLD snooping configuration updated successfully");
                    loadMldConfig();
                } else if (res.errors) {
                    const shown = MLD_FIELD_ERRORS.filter(field => res.errors[field]);
                    shown.forEach(field => showMldFieldError(field, res.errors[field][0]));
                    if (!shown.length) {
                        alert(res.message || "IPv6 MLD snooping configuration failed");
                    }
                } else {
                    alert(res.message || "IPv6 MLD snooping configuration failed");
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.querySelector('.spinner-border').style.display = "none";
                alert("Request failed");
            });
    }

    loadMldConfig();
</script>
