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

    .pc-transfer-wrap {
        display: flex;
        align-items: stretch;
        gap: 10px;
    }

    .pc-transfer-box {
        flex: 1 1 0;
        border: 1px solid #ddd;
        border-radius: 3px;
        overflow: hidden;
    }

    .pc-transfer-box-header {
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        padding: 6px 10px;
        font-weight: 600;
        font-size: 12.5px;
    }

    .pc-transfer-box select {
        border: none;
        border-radius: 0;
        box-shadow: none;
        width: 100%;
        margin: 0;
    }

    .pc-transfer-btns {
        flex: 0 0 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
    }
</style>

<div class="container" style="margin-top:30px;">
    @php
        // Show every real interface on this device. Backend
        // (port_aggregate_config.yml) currently only accepts the short
        // "g0/N" form for GigaEthernet ports (max g0/1-g0/10), so map those
        // to that short form; everything else is listed as-is (selecting
        // one will surface the backend's own validation error if it isn't
        // supported).
        $allPorts = collect($data['interfaces'] ?? [])
            ->map(function ($ifName) {
                if (preg_match('/^GigaEthernet(\d+\/(?:[1-9]|10))$/', $ifName, $m)) {
                    return ['value' => 'g' . $m[1], 'label' => $ifName . ' (g' . $m[1] . ')'];
                }

                return ['value' => $ifName, 'label' => $ifName];
            })
            ->sortBy('label')
            ->values();
    @endphp
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
                                    <input type="text" id="pc_add_group" class="form-control" placeholder="e.g. P1" maxlength="2">
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
                                <div class="col-sm-10">
                                    <div class="pc-transfer-wrap">
                                        <div class="pc-transfer-box">
                                            <div class="pc-transfer-box-header">Configured Port List</div>
                                            <select id="pc_add_configured_ports" multiple size="8"></select>
                                        </div>
                                        <div class="pc-transfer-btns">
                                            <button type="button" id="pc_add_move_left" class="btn btn-default btn-sm">&gt;&gt;</button>
                                            <button type="button" id="pc_add_move_right" class="btn btn-default btn-sm">&lt;&lt;</button>
                                        </div>
                                        <div class="pc-transfer-box">
                                            <div class="pc-transfer-box-header">Available Port List</div>
                                            <select id="pc_add_available_ports" multiple size="8">
                                                @forelse($allPorts as $port)
                                                    <option value="{{ $port['value'] }}">{{ $port['label'] }}</option>
                                                @empty
                                                    <option value="" disabled>No ports found on this device</option>
                                                @endforelse
                                            </select>
                                        </div>
                                    </div>
                                    <span class="help-block">Select port(s) then click &gt;&gt; to add to the group, or &lt;&lt; to remove. Only GigaEthernet ports (g0/1-g0/10) are currently supported for aggregation.</span>
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
    <div class="modal-dialog modal-lg">
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
                        <label class="col-sm-4 control-label">Member Ports</label>
                        <div class="col-sm-8">
                            <div class="pc-transfer-wrap">
                                <div class="pc-transfer-box">
                                    <div class="pc-transfer-box-header">Configured Port List</div>
                                    <select id="pc_edit_configured_ports" multiple size="8"></select>
                                </div>
                                <div class="pc-transfer-btns">
                                    <button type="button" id="pc_edit_move_right" class="btn btn-default btn-sm">&gt;&gt;</button>
                                    <button type="button" id="pc_edit_move_left" class="btn btn-default btn-sm">&lt;&lt;</button>
                                </div>
                                <div class="pc-transfer-box">
                                    <div class="pc-transfer-box-header">Available Port List</div>
                                    <select id="pc_edit_available_ports" multiple size="8"></select>
                                </div>
                            </div>
                            <span class="help-block">Select port(s) then click &gt;&gt; to add, or &lt;&lt; to remove.</span>
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
    const PC_ALL_PORTS = @json($allPorts);

    // Rebuild a pair of Configured/Available <select> lists from scratch -
    // configuredValues (already in the group) go in the left list,
    // everything else in PC_ALL_PORTS goes in the right list.
    function populatePortLists(configuredId, availableId, configuredValues) {
        const configuredSelect = document.getElementById(configuredId);
        const availableSelect = document.getElementById(availableId);
        configuredSelect.innerHTML = '';
        availableSelect.innerHTML = '';

        const configuredSet = new Set(configuredValues);

        PC_ALL_PORTS.forEach(function (port) {
            const opt = document.createElement('option');
            opt.value = port.value;
            opt.textContent = port.label;
            if (configuredSet.has(port.value)) {
                configuredSelect.appendChild(opt);
            } else {
                availableSelect.appendChild(opt);
            }
        });
    }

    // Move whichever options are currently selected from one <select> to
    // the other (the standard ">>"/"<<" transfer-list pattern).
    function moveSelectedOptions(fromId, toId) {
        const from = document.getElementById(fromId);
        const to = document.getElementById(toId);
        Array.from(from.selectedOptions).forEach(function (opt) {
            opt.selected = false;
            to.appendChild(opt);
        });
    }

    document.getElementById('pc_add_move_right').addEventListener('click', function () {
        moveSelectedOptions('pc_add_available_ports', 'pc_add_configured_ports');
    });
    document.getElementById('pc_add_move_left').addEventListener('click', function () {
        moveSelectedOptions('pc_add_configured_ports', 'pc_add_available_ports');
    });
    document.getElementById('pc_edit_move_right').addEventListener('click', function () {
        moveSelectedOptions('pc_edit_available_ports', 'pc_edit_configured_ports');
    });
    document.getElementById('pc_edit_move_left').addEventListener('click', function () {
        moveSelectedOptions('pc_edit_configured_ports', 'pc_edit_available_ports');
    });

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

    // Tracks what was configured when the Edit modal was opened, so
    // editPortChannel() can diff the current Configured list against it
    // and send only the actual add_ports/remove_ports delta.
    let pcEditOriginalPorts = [];

    function openEditPortChannelModal(row) {
        clearFieldError('pc_edit_error');
        document.getElementById('pc_edit_group').value = 'p' + row['aggregation_group'];

        const configured = Array.isArray(row['configured_port_members']) ? row['configured_port_members'] : [];
        pcEditOriginalPorts = configured.slice();
        populatePortLists('pc_edit_configured_ports', 'pc_edit_available_ports', configured);

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
        const ports = Array.from(document.getElementById('pc_add_configured_ports').options).map(o => o.value);

        if (ports.length === 0) {
            showFieldError('pc_add_error', 'Move at least one port to the Configured Port List');
            return;
        }

        callPortChannelApi(
            `/api/v0/portchannel/add/${pcIp}`,
            { aggregate_group: group, mode: mode, ports: ports.join(',') },
            'pc_add_error', 'pcAddBtn', 'Saving...',
            function () {
                document.getElementById('portChannelAddForm').reset();
                populatePortLists('pc_add_configured_ports', 'pc_add_available_ports', []);
                loadPortChannelTable();
            }
        );
    }

    function editPortChannel() {
        const group = document.getElementById('pc_edit_group').value;
        const mode = document.getElementById('pc_edit_mode').value;

        const currentPorts = Array.from(document.getElementById('pc_edit_configured_ports').options).map(o => o.value);
        const addPorts = currentPorts.filter(p => !pcEditOriginalPorts.includes(p));
        const removePorts = pcEditOriginalPorts.filter(p => !currentPorts.includes(p));

        const body = { aggregate_group: group, mode: mode };
        if (addPorts.length) body.add_ports = addPorts.join(',');
        if (removePorts.length) body.remove_ports = removePorts.join(',');

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
