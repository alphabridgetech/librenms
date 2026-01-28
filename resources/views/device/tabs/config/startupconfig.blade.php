<div class="container" style="margin-top:30px;">

    <!-- Export the current startup-config -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <strong>Export the current startup-config</strong>
        </div>

        <div class="panel-body">



            <div class="form-group">
                <label class="col-sm-3 control-label">Export the current startup-config</label>
                <div class="col-sm-6">
                    <input type="text" id="tftpServer_ex" class="form-control" value="{{ $data['tftpServer'] }}"
                        placeholder="192.168.1.10" hidden>
                    <input type="text" class="form-control" value="startup-config">
                </div>
            </div>

            <div class="clearfix"></div><br>


            <button type="button" id="exportBtn" class="btn btn-primary" onclick="exportStartupConfig()">
                Export the current startup-config
            </button>

        </div>
    </div>

    <!-- Import config file -->
    <div class="panel panel-success">
        <div class="panel-heading">
            <strong>Import config file</strong>
        </div>

        <div class="panel-body">

            <p class="text-danger" style="font-weight:bold;">
                Reboot is required after importing config file!
            </p>

            <!-- TFTP Server -->
            <div class="form-group">
                <label class="col-sm-3 control-label">TFTP Server</label>
                <div class="col-sm-12">
                    <input type="text" id="tftpServer" class="form-control" value="{{ $data['tftpServer'] }}"
                        placeholder="192.168.1.10">
                </div>
            </div>

            <!-- File name on server -->
            <div class="form-group">
                <label class="col-sm-3 control-label">File name on the server</label>
                <div class="col-sm-12">
                    <select id="configFileName" class="form-control">
                        <option value="startup-config">startup-config</option>
                        <option value="bvss-config">bvss-config</option>
                    </select>
                </div>
            </div>



            <!-- Upload BIN file (optional) -->
            <div class="form-group">
                <label class="col-sm-3 control-label">Upload BIN File</label>
                <div class="col-sm-12">
                    <input type="file" id="uploadfile" class="form-control">
                </div>
            </div>

            <div class="clearfix"></div><br>

            <!-- Action button -->
            <div class="form-group">
                <div class="col-sm-12 text-center">
                    <button type="button" id="importBtn" class="btn btn-success" onclick="importStartupConfig()">
                        Upgrade
                    </button>
                </div>
            </div>

        </div>
    </div>


</div>

<script>
    function importStartupConfig() {

   
    const btn = document.getElementById("importBtn");
    const ip = "{{ $device->hostname }}";
    const apiToken = "{{ $data['api_token'] }}";

    const tftpServer = document.getElementById("tftpServer").value;
    const fileName   = document.getElementById("configFileName").value;
    const fileInput  = document.getElementById("uploadfile");
    const file       = fileInput.files[0];

    if (!tftpServer) {
        alert("TFTP server is required!");
        return;
    }

    // ✅ Use FormData
    const formData = new FormData();
    formData.append("tftp_server", tftpServer);
    formData.append("file", file);
    formData.append("filename", fileName);

  

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border"></span> Importing...';

    fetch(`/api/v0/tftpupload/${ip}`, {
        method: "POST",
        headers: {
            "Authorization": "Bearer " + apiToken,
            "Accept": "application/json"
        },
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === "success") {
            alert("Config imported successfully. Device will reboot now.");
            btn.disabled = false;
            btn.innerHTML = 'Upgrade';

            // devicereboot();
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Upgrade';
            alert(res.message || "Import failed");
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = 'Upgrade';
        alert("Error: " + err);
    });
}

    function exportStartupConfig() {

        const btn = document.getElementById("exportBtn");
        const ip = "{{ $device->hostname }}";
        const apiToken = "{{ $data['api_token'] }}";
        const filename = "startup-config";
        const tftpServer = document.getElementById("tftpServer_ex").value;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Exporting...';

        fetch(`/api/v0/tftpexport/${ip}`, {
                method: "POST",
                headers: {
                    "Authorization": "Bearer " + apiToken,
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    filename: filename,
                    tftp_server: tftpServer               
                })
            })
            .then(res => res.json())
            .then(res => {
                console.log('====================================');
                console.log(res);
                console.log('====================================');
                btn.disabled = false;
                btn.innerHTML = 'Export the current startup-config';

                if (res.status === "success") {
                    alert("Startup-config exported successfully!");
                    window.location.href = res.download_url;
                } else {
                    alert("Export failed!");
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = 'Export the current startup-config';
                alert("Error: " + err);
            });
    }
</script>
