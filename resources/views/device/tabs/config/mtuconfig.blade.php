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
        100% {
            transform: rotate(360deg);
        }
    }
</style>

<div class="container" style="margin-top:30px;">
    <div class="panel panel-info">
        <div class="panel-heading">
            <strong>MTU Configuration</strong>
        </div>

        <div class="panel-body">
            <form class="form-horizontal" id="mtuForm" style="margin-top:20px;">

                <div class="form-group">
                    <label class="col-sm-2 control-label">MTU*</label>
                    <div class="col-sm-6">
                        <input type="number" id="mtu_input" class="form-control" min="1518" max="9216"
                            placeholder="Loading...">
                        <span class="help-block">
                            Valid range: 1518 – 9216
                        </span>

                        <!-- ERROR MESSAGE -->
                        <div id="mtu_error" class="text-danger" style="display:none;"></div>
                    </div>
                </div>


                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-6">
                        <button type="button" id="applyMtuBtn" class="btn btn-primary" onclick="changeMtu()">
                            Apply
                        </button>
                    </div>
                </div>

            </form>

        </div>

        <div class="panel-footer">
            Help: Configure the size of the system MTU, whose default value is 9216
        </div>
    </div>
</div>

<script>
    const mtuKey = "{{ $device->hostname }}_mtu";

     function setCookie(name, value, days = 7) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie =
            name + "=" + encodeURIComponent(value) +
            ";expires=" + date.toUTCString() + ";path=/";
    }

    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let c of ca) {
            c = c.trim();
            if (c.indexOf(nameEQ) === 0)
                return decodeURIComponent(c.substring(nameEQ.length));
        }
        return null;
    }
    
    function showError(msg) {
        const el = document.getElementById("mtu_error");
        el.innerText = msg;
        el.style.display = "block";
    }

    function clearError() {
        const el = document.getElementById("mtu_error");
        el.innerText = "";
        el.style.display = "none";
    }

    function loadMtu() {
        clearError();

        const saved = getCookie(mtuKey);
        if (saved) {
            document.getElementById("mtu_input").value = saved;
        }

        const ip = "{{ $device->hostname }}";
        const apiToken = "{{ $data['api_token'] }}";

        fetch(`/api/v0/getmtu/${ip}`, {
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Accept": "application/json"
            }
        })
        .then(r => r.json())
        .then(res => {
            if (res.status !== "success") {
                showError("Unable to load MTU from device");
                return;
            }
            document.getElementById("mtu_input").value = res.mtu;
            setCookie(mtuKey, res.mtu);
        })
        .catch(() => showError("API communication error"));
    }

    function changeMtu() {
        clearError();

        const btn = document.getElementById("applyMtuBtn");
        const mtu = parseInt(document.getElementById("mtu_input").value, 10);
        const ip = "{{ $device->hostname }}";
        const apiToken = "{{ $data['api_token'] }}";

        // Frontend validation (MATCH BACKEND)
        if (!mtu || mtu < 1518 || mtu > 9216) {
            showError("MTU must be between 1518 and 9216");
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Applying...';

        fetch(`/api/v0/changemtu/${ip}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ mtu: mtu })
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = 'Apply';

            if (res.status === "success") {
                clearError();
                setCookie(mtuKey, mtu);
                alert("MTU updated successfully!");
            } else if (res.errors && res.errors.mtu) {
                showError(res.errors.mtu[0]);   // 👈 backend message shown
            } else {
                showError("Failed to update MTU");
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = 'Apply';
            showError("Request failed");
        });
    }

    loadMtu();
</script>

