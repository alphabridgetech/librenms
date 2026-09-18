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

    .tab-pane .dataTables_wrapper thead th {
        white-space: nowrap;
    }

    .tab-pane .table-responsive {
        margin-top: 15px;
        border: none;
    }

    .tab-pane .dataTables_wrapper {
        margin-bottom: 10px;
    }
</style>

<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">
<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

<!-- Tabs -->
<ul class="nav nav-tabs">
    <li class="active"><a href="#pdp_global_tab" data-toggle="tab">Global PDP Settings</a></li>
    <li><a href="#pdp_interface_tab" data-toggle="tab">Interface PDP Configuration</a></li>
</ul>

<div class="tab-content" style="margin-top: 15px;">
    <!-- Global PDP Settings -->
    <div class="tab-pane active" id="pdp_global_tab">
        <div class="panel panel-info">
            <div class="panel-heading">
                <strong>Basic configuration of PDP Protocol</strong>
                <span id="pdp_global_loading_note" class="text-muted" style="margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> reading live configuration from device...
                </span>
                <span id="pdp_global_error_note" class="text-danger" style="display:none; margin-left:10px;"></span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPdpConfig()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>

            <div class="panel-body">
                <div class="alert alert-info" id="pdp_no_data_note" style="display:none;">
                    No PDP configuration could be read from this device yet. The fields below show the defaults - set them and click Apply.
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
                Help: HoldTime is the TTL (Time to live) of sending PDP packets - default 180s.
                <br>
                Transmission Cycle is the delay between consecutive PDP packets - default 60s.
                <br>
                <span class="text-muted">The fields above reflect the device's current running-config, read live over SSH.</span>
            </div>
        </div>
    </div>

    <!-- Per-Interface PDP -->
    <div class="tab-pane" id="pdp_interface_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Interface PDP Status</strong>
                <span id="pdp_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> reading live configuration from device...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPdpInterfaceTable()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table id="pdpInterfaceTable" class="table table-striped table-bordered table-condensed" style="width:auto;">
                        <thead>
                            <tr>
                                <th>Interface</th>
                                <th>PDP Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                Help: interfaces are auto-discovered from the device's own running-config (every GigaEthernet/TGigaEthernet interface found). A port with no explicit "no pdp enable" defaults to Enable.
            </div>
        </div>
    </div>
</div>

<!-- Edit PDP Interface Modal -->
<div id="editPdpInterfaceModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit PDP - <span id="pdp_edit_interface_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="pdp_edit_interface" value="">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">PDP Status</label>
                        <div class="col-sm-8">
                            <select id="pdp_edit_status" class="form-control">
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div id="pdp_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="savePdpInterfaceBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const PDP_DEVICE_IP = "{{ $device->hostname }}";
    const PDP_API_TOKEN = "{{ $data['api_token'] }}";
    const PDP_COOKIE_KEY = PDP_DEVICE_IP + "_pdp";

    function setPdpCookie(value, days = 7) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = PDP_COOKIE_KEY + "=" + encodeURIComponent(JSON.stringify(value)) +
            ";expires=" + date.toUTCString() + ";path=/";
    }

    function getPdpCookie() {
        const nameEQ = PDP_COOKIE_KEY + "=";
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

    function fillPdpFields(pdp) {
        document.getElementById("pdp_state").value = pdp.pdp_state === "close" ? "close" : "open";
        document.getElementById("pdp_holdtime").value = pdp.holdtime ?? 180;
        document.getElementById("pdp_tx_interval").value = pdp.tx_interval ?? 60;
        document.getElementById("pdp_version").value = pdp.version || 'Version2';
    }

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

    // How long to wait before silently re-reading the output file after a
    // background refresh was triggered - measured live playbook runtime is
    // ~14s, so give it a little headroom.
    const PDP_SYNC_DELAY_MS = 16000;

    function loadPdpConfig() {
        const loadingNote = document.getElementById('pdp_global_loading_note');
        const errorNote = document.getElementById('pdp_global_error_note');

        // Instantly show the last known values (same trick as the MTU tab)
        // while the live device read happens in the background, so the
        // form is never blank/empty while waiting on the SSH round trip.
        const cachedCookie = getPdpCookie();
        if (cachedCookie) {
            fillPdpFields(cachedCookie);
        }

        loadingNote.style.display = "inline";
        loadingNote.innerHTML = '<i class="fa fa-spinner fa-spin"></i> reading live configuration from device...';
        errorNote.style.display = "none";

        fetchPdpConfig(false);
    }

    function fetchPdpConfig(peek) {
        const loadingNote = document.getElementById('pdp_global_loading_note');
        const errorNote = document.getElementById('pdp_global_error_note');
        const url = `/api/v0/pdp/show/${PDP_DEVICE_IP}` + (peek ? "?peek=1" : "");

        fetch(url, {
            headers: {
                "Authorization": "Bearer " + PDP_API_TOKEN,
                "Accept": "application/json"
            }
        })
            .then(r => r.json())
            .then(res => {
                if (res.status !== "success") {
                    loadingNote.style.display = "none";
                    errorNote.innerText = res.message || "Failed to read PDP configuration from device";
                    errorNote.style.display = "inline";
                    return;
                }

                document.getElementById('pdp_no_data_note').style.display = res.found ? "none" : "inline";

                if (res.found && res.pdp) {
                    // Always set every field from what was actually just
                    // read, including blank/null for "not explicitly
                    // configured on the device" - a falsy-value check here
                    // (e.g. `if (res.pdp.holdtime)`) would leave whatever
                    // was on screen before untouched instead of reflecting
                    // that there is genuinely nothing configured.
                    // Nothing explicitly configured (holdtime/tx_interval
                    // null) means the device is running on its own
                    // defaults, which setpdp.yml's own vars document as
                    // 180 and 60 - fillPdpFields() falls back to those.
                    fillPdpFields(res.pdp);
                    setPdpCookie(res.pdp);
                }

                if (res.cached) {
                    // Data shown is from disk, already kicked off a live
                    // refresh on the server - come back and silently pick
                    // it up once it's had time to finish.
                    loadingNote.innerHTML = '<i class="fa fa-spinner fa-spin"></i> showing last known data, syncing with device...';
                    loadingNote.style.display = "inline";
                    setTimeout(() => fetchPdpConfig(true), PDP_SYNC_DELAY_MS);
                } else {
                    loadingNote.style.display = "none";
                }
            })
            .catch(() => {
                loadingNote.style.display = "none";
                errorNote.innerText = "Request failed while reading PDP configuration";
                errorNote.style.display = "inline";
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

        fetch(`/api/v0/pdp/set/${PDP_DEVICE_IP}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + PDP_API_TOKEN,
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
                    // Re-fetch directly, skipping loadPdpConfig()'s cookie
                    // prefill step - that cookie still holds the PRE-change
                    // value at this point, so calling loadPdpConfig() here
                    // would briefly flash the form back to the old values
                    // right after a successful save (same bug found and
                    // fixed on the DDM tab).
                    document.getElementById('pdp_global_loading_note').innerHTML = '<i class="fa fa-spinner fa-spin"></i> reading live configuration from device...';
                    document.getElementById('pdp_global_loading_note').style.display = "inline";
                    fetchPdpConfig(false);
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

    function pdpFormatStatus(status) {
        return status === 'Enable'
            ? '<span class="label label-success">Enable</span>'
            : '<span class="label label-default">Disable</span>';
    }

    // Plain string sorting would put "GigaEthernet0/10" before
    // "GigaEthernet0/2" - split into text/number chunks and compare
    // numerically so ordering reads 0/1, 0/2, ... 0/10.
    function pdpNaturalCompare(a, b) {
        const ax = [], bx = [];
        String(a).replace(/(\d+)|(\D+)/g, function (_, num, str) {
            ax.push([num ? parseInt(num, 10) : Infinity, str || '']);
        });
        String(b).replace(/(\d+)|(\D+)/g, function (_, num, str) {
            bx.push([num ? parseInt(num, 10) : Infinity, str || '']);
        });
        while (ax.length && bx.length) {
            const an = ax.shift();
            const bn = bx.shift();
            const diff = (an[0] - bn[0]) || an[1].localeCompare(bn[1]);
            if (diff) return diff;
        }
        return ax.length - bx.length;
    }

    $.fn.dataTable.ext.type.order['pdp-interface-natural-asc'] = function (a, b) {
        return pdpNaturalCompare(a, b);
    };
    $.fn.dataTable.ext.type.order['pdp-interface-natural-desc'] = function (a, b) {
        return pdpNaturalCompare(b, a);
    };

    var pdpInterfaceTable = $('#pdpInterfaceTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/pdp/interface/show/" + PDP_DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + PDP_API_TOKEN,
                "Accept": "application/json"
            },
            beforeSend: function () {
                document.getElementById('pdp_cache_note').style.display = "inline";
            },
            complete: function () {
                document.getElementById('pdp_cache_note').style.display = "none";
            },
            dataSrc: function (json) {
                if (json.status !== "success") {
                    return [];
                }

                const entries = json.entries || {};

                // Unlike Rate Limit/GVRP, this GET auto-discovers its own
                // interface list from the device - whatever it reported is
                // the row set, no separate LibreNMS port list to merge in.
                return Object.keys(entries).map(function (iface) {
                    return {
                        interface: iface,
                        status: entries[iface] || 'Enable'
                    };
                });
            }
        },
        columns: [
            { data: "interface", type: "pdp-interface-natural" },
            { data: null, render: row => pdpFormatStatus(row.status) },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-pdp-interface">Edit</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No GigaEthernet/TGigaEthernet interfaces found for this device"
        }
    });

    function loadPdpInterfaceTable() {
        pdpInterfaceTable.ajax.reload(null, false);
    }

    // The Interface tab starts hidden (Global is the active tab), and
    // DataTables miscalculates column widths when initialized inside a
    // display:none element - recalculate once the tab is actually shown.
    $('a[href="#pdp_interface_tab"]').on('shown.bs.tab', function () {
        pdpInterfaceTable.columns.adjust();
    });

    $('#pdpInterfaceTable tbody').on('click', '.btn-edit-pdp-interface', function () {
        clearPdpInterfaceError();
        const row = pdpInterfaceTable.row($(this).closest('tr')).data();

        $('#pdp_edit_interface').val(row.interface);
        $('#pdp_edit_interface_label').text(row.interface);
        $('#pdp_edit_status').val(row.status || 'Enable');

        $('#editPdpInterfaceModal').modal('show');
    });

    function clearPdpInterfaceError() {
        const el = document.getElementById('pdp_edit_error');
        el.innerText = "";
        el.style.display = "none";
    }

    $('#savePdpInterfaceBtn').on('click', function () {
        clearPdpInterfaceError();

        const payload = {
            interface: $('#pdp_edit_interface').val(),
            status: $('#pdp_edit_status').val()
        };

        const $btn = $('#savePdpInterfaceBtn');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/pdp/interface/set/" + PDP_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + PDP_API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();

                if (response.status === "success") {
                    $('#editPdpInterfaceModal').modal('hide');
                    loadPdpInterfaceTable();
                    alert(response.message || 'PDP updated successfully!');
                } else if (response.errors) {
                    showPdpError('pdp_edit_error', Object.values(response.errors).flat().join(' '));
                } else {
                    showPdpError('pdp_edit_error', response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();
                showPdpError('pdp_edit_error', xhr.responseJSON?.message || 'API communication error');
            }
        });
    });
</script>
