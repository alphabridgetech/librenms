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
            <strong>Reboot</strong>
        </div>

        <div class="panel-body">

            <form class="form-horizontal" style="margin-top:20px;">

                <!-- Center Button -->
                <div class="form-group">
                    <div class="col-sm-12 text-center">
                        <button type="button" id="applyBtn" class="btn btn-primary" onclick="devicereboot()">
                            Reboot Device
                        </button>
                    </div>
                </div>

            </form>

        </div>

        <div class="panel-footer">
            Click the 'Reboot' button to restart the device.
        </div>
    </div>
</div>

<script>
    function devicereboot() {
        const btn = document.getElementById("applyBtn");

        const ip = "{{ $device->hostname }}";
        const apiToken = "{{ $data['api_token'] }}";

        // Spinner animation
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Rebooting...';

        fetch(`/api/v0/devicereboot/${ip}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            }
        })
        .then(res => res.json())
        .then(res => {

            btn.disabled = false;
            btn.innerHTML = 'Reboot Device';

            if (res.status === "success") {
                alert("Device reboot initiated successfully!");
            } else {
                alert("Failed to reboot device.");
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = 'Reboot Device';
            alert("Error: " + err);
        });
    }
</script>
