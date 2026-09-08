<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">
<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

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

    .dataTables_wrapper thead th {
        white-space: nowrap;
    }
</style>

<div class="container" style="margin-top:30px;">
    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li class="active"><a href="#port_channel_main" data-toggle="tab">Port Channel</a></li>
        <li><a href="#port_channel_lb" data-toggle="tab">Port Channel Group Load Balancing</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">
        <div class="tab-pane active" id="port_channel_main">

            <div class="panel panel-info">
                <div class="panel-heading" data-toggle="collapse" data-target="#addPortChannelCollapse" style="cursor:pointer;">
                    <strong>Add / Configure Aggregate Group</strong>
                    <i class="fa fa-chevron-down pull-right"></i>
                </div>
                <div id="addPortChannelCollapse" class="collapse">
                    <div class="panel-body">
                        <form class="form-horizontal" id="portChannelAddForm">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Aggregate Group*</label>
                                <div class="col-sm-6">
                                    <select id="pc_add_group" class="form-control">
                                        <option value="P1">P1</option>
                                        <option value="P2">P2</option>
                                        <option value="P3">P3</option>
                                        <option value="P4">P4</option>
                                        <option value="P5">P5</option>
                                        <option value="P6">P6</option>
                                        <option value="P7">P7</option>
                                        <option value="P8">P8</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Mode*</label>
                                <div class="col-sm-6">
                                    <select id="pc_add_mode" class="form-control">
                                        <option value="static">Static</option>
                                        <option value="lacp active">LACP Active</option>
                                        <option value="lacp passive">LACP Passive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Member Ports*</label>
                                <div class="col-sm-6">
                                    <select id="pc_add_ports" class="form-control" multiple size="6">
                                        <option value="g0/1">g0/1</option>
                                        <option value="g0/2">g0/2</option>
                                        <option value="g0/3">g0/3</option>
                                        <option value="g0/4">g0/4</option>
                                        <option value="g0/5">g0/5</option>
                                        <option value="g0/6">g0/6</option>
                                        <option value="g0/7">g0/7</option>
                                        <option value="g0/8">g0/8</option>
                                        <option value="g0/9">g0/9</option>
                                        <option value="g0/10">g0/10</option>
                                    </select>
                                    <span class="help-block">Ctrl/Cmd-click to select multiple ports.</span>
                                </div>
                            </div>
                            <div id="pc_add_error" class="text-danger" style="display:none; margin-left:15px;"></div>
                            <div class="form-group">
                                <div class="col-sm-offset-2 col-sm-6">
                                    <button type="button" id="pcAddBtn" class="btn btn-primary" onclick="addPortChannel()">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="panel-footer">
                        CLI: <code>interface &lt;full-port&gt;</code> &rarr; <code>aggregator-group &lt;group-id&gt; mode &lt;mode&gt;</code> per port
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>Current Port Channel Groups</strong>
                    <span id="pc_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                        <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                    </span>
                    <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortChannelTable()">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table id="portChannelTable" class="table table-condensed table-hover table-striped" style="width:auto;">
                            <thead>
                                <tr>
                                    <th width="90">Group</th>
                                    <th width="140">Mode</th>
                                    <th width="200">Configured Ports</th>
                                    <th width="200">Valid Ports</th>
                                    <th width="90">Speed</th>
                                    <th width="100">State</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <div class="tab-pane" id="port_channel_lb">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <strong>Port Channel Group Load Balancing</strong>
                </div>

                <div class="panel-body">
                    <form class="form-horizontal" id="lbForm">
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Port Channel*</label>
                            <div class="col-sm-6">
                                <select id="lb_port" class="form-control">
                                    <option value="p1">p1</option>
                                    <option value="p2">p2</option>
                                    <option value="p3">p3</option>
                                    <option value="p4">p4</option>
                                    <option value="p5">p5</option>
                                    <option value="p6">p6</option>
                                    <option value="p7">p7</option>
                                    <option value="p8">p8</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Load Balancing Mode*</label>
                            <div class="col-sm-6">
                                <select id="lb_mode" class="form-control">
                                    <option value="SRC MAC">SRC MAC</option>
                                    <option value="DST MAC">DST MAC</option>
                                    <option value="BOTH MAC">BOTH MAC</option>
                                    <option value="SRC IP">SRC IP</option>
                                    <option value="DST IP">DST IP</option>
                                    <option value="BOTH IP">BOTH IP</option>
                                </select>
                            </div>
                        </div>
                        <div id="lb_error" class="text-danger" style="display:none; margin-left:15px;"></div>
                        <div class="form-group">
                            <div class="col-sm-offset-2 col-sm-6">
                                <button type="button" id="lbApplyBtn" class="btn btn-primary" onclick="applyLoadBalance()">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="panel-footer">
                    CLI: <code>interface &lt;port-channel&gt;</code> &rarr; <code>aggregator-group load-balance &lt;mode&gt;</code>
                    <br>
                    <span class="text-muted">
                        Note: the switch doesn't expose a way to read back the current load-balancing mode,
                        so there's no live status table here - this only applies a new setting.
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Port Channel Modal -->
<div id="editPortChannelModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Aggregate Group</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="portChannelEditForm">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Aggregate Group</label>
                        <div class="col-sm-8">
                            <input type="text" id="pc_edit_group" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Mode*</label>
                        <div class="col-sm-8">
                            <select id="pc_edit_mode" class="form-control">
                                <option value="static">Static</option>
                                <option value="lacp active">LACP Active</option>
                                <option value="lacp passive">LACP Passive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Add Ports</label>
                        <div class="col-sm-8">
                            <input type="text" id="pc_edit_add_ports" class="form-control" placeholder="e.g. g0/2,g0/6">
                            <span class="help-block">Comma-separated ports to move from Available to Configured.</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Remove Ports</label>
                        <div class="col-sm-8">
                            <input type="text" id="pc_edit_remove_ports" class="form-control" placeholder="e.g. g0/7">
                            <span class="help-block">Comma-separated ports to move from Configured to Available.</span>
                        </div>
                    </div>
                    <div id="pc_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="pcEditBtn" class="btn btn-warning" onclick="editPortChannel()">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const pcIp = "{{ $device->hostname }}";
    const pcApiToken = "{{ $data['api_token'] }}";

    function showFieldError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = "block";
    }

    function clearFieldError(id) {
        const el = document.getElementById(id);
        el.innerText = "";
        el.style.display = "none";
    }

    function setBusy(btnId, busyLabel, busy) {
        const btn = document.getElementById(btnId);
        btn.disabled = busy;
        btn.innerHTML = busy ? '<span class="spinner-border"></span> ' + busyLabel : btn.dataset.label;
    }

    document.getElementById('pcAddBtn').dataset.label = 'Save';
    document.getElementById('pcEditBtn').dataset.label = 'Update';

    $('#addPortChannelCollapse').on('show.bs.collapse', function () {
        $(this).prev('.panel-heading').find('.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }).on('hide.bs.collapse', function () {
        $(this).prev('.panel-heading').find('.fa-chevron-up').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });

    var portChannelTable = $('#portChannelTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: `/api/v0/portchannel/show/${pcIp}`,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + pcApiToken,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('pc_cache_note').style.display = json.cached ? "inline" : "none";
                return json.groups || [];
            }
        },
        columns: [
            { data: "aggregation_group" },
            { data: "mode" },
            { data: "configured_port_members", render: d => Array.isArray(d) ? d.join(', ') : (d || '') },
            { data: "valid_port_members", render: d => Array.isArray(d) ? d.join(', ') : (d || '') },
            { data: "speed" },
            { data: "state" },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-pc">Edit</button> ' +
                        '<button type="button" class="btn btn-xs btn-danger btn-delete-pc">Delete</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No port channel groups configured"
        }
    });

    function loadPortChannelTable() {
        portChannelTable.ajax.reload();
    }

    $('#portChannelTable tbody').on('click', '.btn-edit-pc', function () {
        const row = portChannelTable.row($(this).closest('tr')).data();
        openEditPortChannelModal(row);
    });

    $('#portChannelTable tbody').on('click', '.btn-delete-pc', function () {
        const row = portChannelTable.row($(this).closest('tr')).data();
        deletePortChannelRow(row['aggregation_group']);
    });

    function openEditPortChannelModal(row) {
        clearFieldError('pc_edit_error');
        document.getElementById('pc_edit_group').value = 'p' + row['aggregation_group'];
        document.getElementById('pc_edit_add_ports').value = '';
        document.getElementById('pc_edit_remove_ports').value = '';

        const modeVal = (row['mode'] || '').toLowerCase();
        if (modeVal.includes('lacp')) {
            document.getElementById('pc_edit_mode').value = modeVal.includes('passive') ? 'lacp passive' : 'lacp active';
        } else {
            document.getElementById('pc_edit_mode').value = 'static';
        }

        $('#editPortChannelModal').modal('show');
    }

    function callPortChannelApi(url, body, errorId, btnId, busyLabel, onSuccess) {
        clearFieldError(errorId);
        setBusy(btnId, busyLabel, true);

        fetch(url, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + pcApiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(body)
        })
        .then(r => r.json())
        .then(res => {
            setBusy(btnId, busyLabel, false);
            if (res.status === "success") {
                alert(res.message || "Success");
                if (onSuccess) onSuccess();
            } else if (res.errors) {
                showFieldError(errorId, Object.values(res.errors).flat().join(' '));
            } else {
                showFieldError(errorId, res.message || "Request failed");
            }
        })
        .catch(() => {
            setBusy(btnId, busyLabel, false);
            showFieldError(errorId, "API communication error");
        });
    }

    function addPortChannel() {
        const group = document.getElementById('pc_add_group').value;
        const mode = document.getElementById('pc_add_mode').value;
        const ports = Array.from(document.getElementById('pc_add_ports').selectedOptions).map(o => o.value);

        if (ports.length === 0) {
            showFieldError('pc_add_error', 'Select at least one member port');
            return;
        }

        callPortChannelApi(
            `/api/v0/portchannel/add/${pcIp}`,
            { aggregate_group: group, mode: mode, ports: ports.join(',') },
            'pc_add_error', 'pcAddBtn', 'Saving...',
            function () {
                document.getElementById('portChannelAddForm').reset();
                loadPortChannelTable();
            }
        );
    }

    function editPortChannel() {
        const group = document.getElementById('pc_edit_group').value;
        const mode = document.getElementById('pc_edit_mode').value;
        const addPorts = document.getElementById('pc_edit_add_ports').value.trim();
        const removePorts = document.getElementById('pc_edit_remove_ports').value.trim();

        const body = { aggregate_group: group, mode: mode };
        if (addPorts) body.add_ports = addPorts;
        if (removePorts) body.remove_ports = removePorts;

        callPortChannelApi(
            `/api/v0/portchannel/edit/${pcIp}`,
            body,
            'pc_edit_error', 'pcEditBtn', 'Updating...',
            function () {
                $('#editPortChannelModal').modal('hide');
                loadPortChannelTable();
            }
        );
    }

    function deletePortChannelRow(groupNum) {
        const portId = 'p' + groupNum;
        if (!confirm(`Delete port channel interface ${portId}? This removes the whole aggregate group.`)) {
            return;
        }

        fetch(`/api/v0/portchannel/delete/${pcIp}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + pcApiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ port_id: portId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                alert(res.message || "Deleted successfully");
                loadPortChannelTable();
            } else {
                alert(res.message || "Failed to delete port channel interface");
            }
        })
        .catch(() => alert("API communication error"));
    }

    document.getElementById('lbApplyBtn').dataset.label = 'Apply';

    function lbShowError(msg) {
        const el = document.getElementById('lb_error');
        el.innerText = msg;
        el.style.display = "block";
    }

    function lbClearError() {
        const el = document.getElementById('lb_error');
        el.innerText = "";
        el.style.display = "none";
    }

    function applyLoadBalance() {
        lbClearError();

        const port = document.getElementById('lb_port').value;
        const mode = document.getElementById('lb_mode').value;
        const btn = document.getElementById('lbApplyBtn');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Applying...';

        fetch(`/api/v0/portchannel/loadbalance/${pcIp}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + pcApiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ port: port, mode: mode })
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.label;

            if (res.status === "success") {
                alert(res.message || "Load balancing mode applied");
            } else if (res.errors) {
                lbShowError(Object.values(res.errors).flat().join(' '));
            } else {
                lbShowError(res.message || "Request failed");
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.label;
            lbShowError("API communication error");
        });
    }
</script>
