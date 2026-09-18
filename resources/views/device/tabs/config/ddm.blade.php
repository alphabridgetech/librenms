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
            <strong>DDM (Digital Diagnostic Monitoring) Configuration</strong>
            <span id="ddm_loading_note" class="text-muted" style="margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> reading live configuration from device...
            </span>
            <span id="ddm_error_note" class="text-danger" style="display:none; margin-left:10px;"></span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadDdmConfig()">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>

        <div class="panel-body">
            <form class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-3 control-label">DDM Status*</label>
                    <div class="col-sm-6">
                        <select id="ddm_status" class="form-control">
                            <option value="enable">Enable</option>
                            <option value="disable">Disable</option>
                        </select>
                        <div id="ddm_status_error" class="text-danger" style="display:none;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-6">
                        <button type="button" id="ddmApplyBtn" class="btn btn-primary" onclick="applyDdmConfig()">
                            <span class="spinner-border" style="display:none;"></span> Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="panel-footer">
            Help: Digital Diagnostic Monitoring (DDM) reports real-time optical transceiver diagnostics (temperature, voltage, bias current, and optical power).
        </div>
    </div>
</div>

<script>
    const DDM_DEVICE_IP = "{{ $device->hostname }}";
    const DDM_API_TOKEN = "{{ $data['api_token'] }}";
    const DDM_COOKIE_KEY = DDM_DEVICE_IP + "_ddm";

    // How long to wait before silently re-reading the output file after a
    // background refresh was triggered - matches the PDP tab's timing.
    const DDM_SYNC_DELAY_MS = 16000;

    function setDdmCookie(value, days = 7) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = DDM_COOKIE_KEY + "=" + encodeURIComponent(JSON.stringify(value)) +
            ";expires=" + date.toUTCString() + ";path=/";
    }

    function getDdmCookie() {
        const nameEQ = DDM_COOKIE_KEY + "=";
        const ca = document.cookie.split(';');
        for (let c of ca) {
            c = c.trim();
            if (c.indexOf(nameEQ) === 0) {
                try {
                    return JSON.parse(decodeURIComponent(c.substring(nameEQ.length)));
                } catch (e) {
                    return null;
                }
            }
        }
        return null;
    }

    function fillDdmFields(ddm) {
        document.getElementById("ddm_status").value = ddm.ddm_status === "disable" ? "disable" : "enable";
    }

    function showDdmError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = "block";
    }

    function clearDdmErrors() {
        document.getElementById("ddm_status_error").style.display = "none";
    }

    function loadDdmConfig() {
        const loadingNote = document.getElementById('ddm_loading_note');
        const errorNote = document.getElementById('ddm_error_note');

        // Instantly show the last known value (same trick used on the
        // MTU/PDP tabs) while the live device read happens in the
        // background, so the form is never blank while waiting on SSH.
        const cachedCookie = getDdmCookie();
        if (cachedCookie) {
            fillDdmFields(cachedCookie);
        }

        loadingNote.innerHTML = '<i class="fa fa-spinner fa-spin"></i> reading live configuration from device...';
        loadingNote.style.display = "inline";
        errorNote.style.display = "none";

        fetchDdmConfig(false);
    }

    function fetchDdmConfig(peek) {
        const loadingNote = document.getElementById('ddm_loading_note');
        const errorNote = document.getElementById('ddm_error_note');
        const url = `/api/v0/ddm/show/${DDM_DEVICE_IP}` + (peek ? "?peek=1" : "");

        fetch(url, {
            headers: {
                "Authorization": "Bearer " + DDM_API_TOKEN,
                "Accept": "application/json"
            }
        })
            .then(r => r.json())
            .then(res => {
                if (res.status !== "success") {
                    loadingNote.style.display = "none";
                    errorNote.innerText = res.message || "Failed to read DDM configuration from device";
                    errorNote.style.display = "inline";
                    return;
                }

                if (res.found && res.ddm) {
                    fillDdmFields(res.ddm);
                    setDdmCookie(res.ddm);
                }

                if (res.cached) {
                    // Data shown is from disk, a live refresh already
                    // started on the server - come back and silently pick
                    // it up once it's had time to finish.
                    loadingNote.innerHTML = '<i class="fa fa-spinner fa-spin"></i> showing last known data, syncing with device...';
                    loadingNote.style.display = "inline";
                    setTimeout(() => fetchDdmConfig(true), DDM_SYNC_DELAY_MS);
                } else {
                    loadingNote.style.display = "none";
                }
            })
            .catch(() => {
                loadingNote.style.display = "none";
                errorNote.innerText = "Request failed while reading DDM configuration";
                errorNote.style.display = "inline";
            });
    }

    function applyDdmConfig() {
        clearDdmErrors();

        const btn = document.getElementById("ddmApplyBtn");
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Applying...';

        const payload = { ddm_status: document.getElementById("ddm_status").value };

        fetch(`/api/v0/ddm/set/${DDM_DEVICE_IP}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + DDM_API_TOKEN,
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
                    alert("DDM configuration updated successfully");
                    // Re-fetch directly, skipping loadDdmConfig()'s cookie
                    // prefill step - that cookie still holds the PRE-change
                    // value at this point (it's only updated once a fetch
                    // resolves), so calling loadDdmConfig() here would
                    // briefly flash the dropdown back to the old value
                    // right after a successful save.
                    document.getElementById('ddm_loading_note').innerHTML = '<i class="fa fa-spinner fa-spin"></i> reading live configuration from device...';
                    document.getElementById('ddm_loading_note').style.display = "inline";
                    fetchDdmConfig(false);
                } else if (res.errors && res.errors.ddm_status) {
                    showDdmError("ddm_status_error", res.errors.ddm_status[0]);
                } else {
                    alert(res.message || "DDM configuration failed");
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = "Apply";
                alert("Request failed");
            });
    }

    /* AUTO LOAD */
    loadDdmConfig();
</script>
