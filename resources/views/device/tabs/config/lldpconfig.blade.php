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

    .dataTables_wrapper {
        max-width: 100%;
    }

    .dataTables_filter input {
        max-width: 100%;
    }

    @media (max-width: 600px) {
        .dataTables_length,
        .dataTables_filter {
            text-align: left !important;
            margin-bottom: 8px;
        }
    }
</style>

<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">

<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>


<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    <ul class="nav nav-tabs">
        <li class="active"><a href="#lldp_global_config" data-toggle="tab">LLDP Global Configuration</a></li>
        <li><a href="#lldp_interface_config" data-toggle="tab">LLDP Interface Configuration</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">
        <div class="tab-pane active" id="lldp_global_config">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <strong>Basic configuration of LLDP Protocol</strong>
                    <span id="lldp_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                        <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                    </span>
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
                                <button type="button" id="applyBtn" class="btn btn-primary"
                                    onclick="applyLldpConfig()">
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

        <div class="tab-pane" id="lldp_interface_config">
            <div id="lldp_protocol_closed_note" class="alert alert-warning" style="display:none;">
                LLDP is currently <strong>closed</strong> globally - interface settings shown below won't take effect until it's enabled on the LLDP Global Configuration tab. Editing is disabled until then.
            </div>
            <span id="lldp_interface_cache_note" class="text-muted" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="lldpinterfaceTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="lldpinterfaceTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="120">Port</th>
                            <th width="150">Receive LLDP Packet</th>
                            <th width="150">Send LLDP Packet</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Only GigaEthernet0/1-10 interfaces are supported.</li>
                    <li>The device's own interface list comes from LibreNMS's port table for this device.</li>
                    <li>Interface-level settings only take effect while LLDP is enabled globally.</li>
                </ul>
            </div>

        </div>
    </div>
</div>

<!-- Edit LLDP Interface Modal -->
<div id="editLldpInterfaceModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">LLDP Interface - <span id="lldp_if_edit_port"></span></h4>
            </div>
            <div class="modal-body">
                <form id="editLldpInterfaceForm" class="form-horizontal">
                    <input type="hidden" id="lldp_if_interface">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Receive LLDP Packet</label>
                        <div class="col-sm-8">
                            <select id="lldp_if_admin_status" class="form-control">
                                <option value="enable">Enable</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Send LLDP Packet</label>
                        <div class="col-sm-8">
                            <select id="lldp_if_tlv" class="form-control">
                                <option value="enable">Enable</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div id="lldp_if_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveLldpInterfaceBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
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
    // Interface-level LLDP settings don't take effect (and can't be
    // verified) while LLDP is globally closed - default to treating it as
    // closed/unknown until the global config actually confirms it's open,
    // so the Edit buttons never briefly appear enabled before we know.
    let lldpProtocolState = null;

    function applyLldpProtocolStateToInterfaceTab() {
        const closed = lldpProtocolState !== 'open';
        document.getElementById('lldp_protocol_closed_note').style.display = closed ? 'block' : 'none';

        if (typeof lldpinterfaceTable !== 'undefined') {
            lldpinterfaceTable.rows().invalidate().draw(false);
        }
    }

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
                document.getElementById('lldp_cache_note').style.display = res.cached ? "inline" : "none";
                if (res.status !== "success") return;

                const protocolState = res.lldp.protocol;
                document.getElementById("protocol_state").value = protocolState === "close" ? "close" : "open";

                document.getElementById("holdtime").value = res.lldp.holdtime;
                document.getElementById("reinit").value = res.lldp.reinit;
                document.getElementById("timer").value = res.lldp.timer;

                setCookie(COOKIE_PREFIX + "lldp", JSON.stringify(res.lldp));

                lldpProtocolState = protocolState === "close" ? "close" : "open";
                applyLldpProtocolStateToInterfaceTab();
            });
    }


    function lldpFormatStatus(status) {
        return status === 'enabled'
            ? '<span class="label label-success">Enabled</span>'
            : '<span class="label label-default">Disabled</span>';
    }

    var lldpinterfaceTable = $('#lldpinterfaceTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/getlldpinterface/" + DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('lldp_interface_cache_note').style.display = json.cached ? "inline" : "none";
                return json.lldp_interfaces || [];
            }
        },
        columns: [
            { data: "interface" },
            { data: null, render: row => lldpFormatStatus(row.rx) },
            { data: null, render: row => lldpFormatStatus(row.tx) },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    if (lldpProtocolState !== 'open') {
                        return '<button type="button" class="btn btn-xs btn-warning" disabled title="Enable LLDP globally first">Edit</button>';
                    }
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-lldp-if">Edit</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No LLDP interfaces found"
        }
    });

    $('#lldpinterfaceTable tbody').on('click', '.btn-edit-lldp-if', function () {
        const row = lldpinterfaceTable.row($(this).closest('tr')).data();
        document.getElementById('lldp_if_edit_port').innerText = row.interface;
        document.getElementById('lldp_if_interface').value = row.interface;
        document.getElementById('lldp_if_admin_status').value = row.rx === 'enabled' ? 'enable' : 'disable';
        document.getElementById('lldp_if_tlv').value = row.tx === 'enabled' ? 'enable' : 'disable';
        document.getElementById('lldp_if_edit_error').style.display = 'none';
        $('#editLldpInterfaceModal').modal('show');
    });

    $('#saveLldpInterfaceBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('lldp_if_edit_error');
        errorBox.style.display = 'none';

        const payload = {
            interface: document.getElementById('lldp_if_interface').value,
            admin_status: document.getElementById('lldp_if_admin_status').value,
            tlv: document.getElementById('lldp_if_tlv').value
        };

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/lldp/interface/set/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#editLldpInterfaceModal').modal('hide');
                alert(res.message || "LLDP interface configuration updated");
                lldpinterfaceTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to update LLDP interface configuration";
                errorBox.style.display = 'block';
            }
        });
    });



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
                    loadLldpConfig();
                    lldpinterfaceTable.ajax.reload(null, false);
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
