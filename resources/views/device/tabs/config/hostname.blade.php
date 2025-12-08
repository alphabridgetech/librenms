
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
            <button type="button" class="btn btn-primary" onclick="changeHostname()">Apply</button>
            <button type="reset" class="btn btn-default">Reset</button>
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
    function loadHostname() {

        let ip = "{{ $device->hostname }}";     // device IP or hostname
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
                document.getElementById("hostname_input").value = "Error";
                return;
            }

            // Set hostname in input
            document.getElementById("hostname_input").value = res.hostname;
        })
        .catch(err => {
            console.error(err);
            document.getElementById("hostname_input").value = "API Error";
        });
    }

    function changeHostname() {

    let newHostname = document.getElementById("hostname_input").value;
    let ip = "{{ $device->hostname }}";
    let apiToken = "{{ $data['api_token'] }}";

    if (!newHostname) {
        alert("Hostname cannot be empty");
        return;
    }

    fetch(`/api/v0/changehostname/${ip}`, {
        method: "POST",
        headers: {
            "Authorization": "Bearer " + apiToken,
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        body: JSON.stringify({
            hostname: newHostname
        })
    })
    .then(res => res.json())
    .then(res => {

        if (res.status === "success") {
            alert("Hostname changed successfully!");
        } else {
            alert("Failed to change hostname");
        }

    })
    .catch(err => {
        alert("Error: " + err);
    });

}

    // Auto-load hostname on page load
    loadHostname();
</script>
