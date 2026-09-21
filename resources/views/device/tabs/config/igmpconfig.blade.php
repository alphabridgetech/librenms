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

    .igmp-transfer-wrap {
        display: flex;
        align-items: stretch;
        gap: 10px;
    }

    .igmp-transfer-box {
        flex: 1 1 0;
        border: 1px solid #ddd;
        border-radius: 3px;
        overflow: hidden;
    }

    .igmp-transfer-box-header {
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        padding: 6px 10px;
        font-weight: 600;
        font-size: 12.5px;
    }

    .igmp-transfer-box select {
        border: none;
        border-radius: 0;
        box-shadow: none;
        width: 100%;
        margin: 0;
    }

    .igmp-transfer-btns {
        flex: 0 0 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
    }
</style>

<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">
<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    @php
        // Same short-form port mapping used by the Port Channel tab -
        // igmp_snooping_vlan_config_set.yml's member_port has no format
        // validation of its own (a real gap in the playbook), so this is
        // the only thing constraining input to real, known interfaces.
        $igmpAllPorts = collect($data['interfaces'] ?? [])
            ->map(function ($ifName) {
                if (preg_match('/^GigaEthernet(\d+\/\d+)$/i', $ifName, $m)) {
                    return 'g' . $m[1];
                }
                if (preg_match('/^TGigaEthernet(\d+\/\d+)$/i', $ifName, $m)) {
                    return 'tg' . $m[1];
                }
                return null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();
    @endphp
    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li class="active"><a href="#igmp_global_tab" data-toggle="tab">IGMP Snooping</a></li>
        <li><a href="#igmp_vlan_tab" data-toggle="tab">IGMP Snooping VLAN List </a></li>
        <li><a href="#igmp_filter_tab" data-toggle="tab">IGMP Snooping Filter Configuration List </a></li>
        <li><a href="#igmp_multicast_tab" data-toggle="tab">  Static Multicast Address List</a></li>
        
    </ul>

    <div class="tab-content" style="margin-top:15px;">

        <!-- Global Configuration -->
        <div class="tab-pane active" id="igmp_global_tab">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <strong>IGMP Snooping - Global Configuration</strong>
                    <span id="igmp_global_loading_note" class="text-muted" style="margin-left:10px;">
                        <i class="fa fa-spinner fa-spin"></i> reading live configuration from device...
                    </span>
                    <span id="igmp_global_error_note" class="text-danger" style="display:none; margin-left:10px;"></span>
                    <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadIgmpGlobalConfig()">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
                <div class="panel-body">
                    <form class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Destination Lookup Failure*</label>
                            <div class="col-sm-6">
                                <select id="igmp_dlf_drop" class="form-control">
                                    <option value="discard_unknown">Discard Unknown</option>
                                    <option value="transfer_unknown">Transfer Unknown</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">IGMP Snooping*</label>
                            <div class="col-sm-6">
                                <select id="igmp_snooping" class="form-control">
                                    <option value="enable">Enable</option>
                                    <option value="disable">Disable</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Auto Query*</label>
                            <div class="col-sm-6">
                                <select id="igmp_auto_query" class="form-control">
                                    <option value="enable">Enable</option>
                                    <option value="disable">Disable</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Querier Address*</label>
                            <div class="col-sm-6">
                                <input type="text" id="igmp_querier_address" class="form-control" placeholder="e.g. 10.0.0.200">
                                <div id="igmp_querier_address_error" class="text-danger" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Query Interval (s)*</label>
                            <div class="col-sm-6">
                                <input type="number" id="igmp_query_interval" class="form-control" min="10" max="2147483647">
                                <div id="igmp_query_interval_error" class="text-danger" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Querier Expiry Interval (s)*</label>
                            <div class="col-sm-6">
                                <input type="number" id="igmp_querier_expiry_interval" class="form-control" min="10" max="2147483647">
                                <div id="igmp_querier_expiry_interval_error" class="text-danger" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <div class="col-sm-offset-3 col-sm-6">
                                <button type="button" id="igmpApplyBtn" class="btn btn-primary" onclick="applyIgmpGlobalConfig()">
                                    <span class="spinner-border" style="display:none;"></span> Apply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- VLAN Configuration -->
        <div class="tab-pane" id="igmp_vlan_tab">
            <button class="btn btn-primary" id="btnAddIgmpVlan">
                <i class="glyphicon glyphicon-plus"></i> Configure VLAN
            </button>
            <span id="igmp_vlan_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="igmpVlanTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="igmpVlanTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="100">VLAN ID</th>
                            <th width="140">Immediate Leave</th>
                            <th width="200">Router Ports</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Only VLANs already known to the switch are listed here - use "Configure VLAN" to apply IGMP snooping settings to any VLAN ID (1-4094).</li>
                    <li>Member Port sets the multicast router port for the VLAN and cannot be removed once set, only replaced.</li>
                </ul>
            </div>
        </div>

        <!-- Multicast Groups -->
        <div class="tab-pane" id="igmp_multicast_tab">
            <button class="btn btn-primary" id="btnAddIgmpMulticast">
                <i class="glyphicon glyphicon-plus"></i> Add Static Entry
            </button>
            <span id="igmp_multicast_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="igmpMulticastTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="igmpMulticastTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="90">VLAN ID</th>
                            <th width="150">Group Address</th>
                            <th width="100">Type</th>
                            <th width="150">Ports</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Group Address must be a multicast IPv4 address (224.0.0.0 - 239.255.255.255).</li>
                    <li>Only entries added here (shown as type "USER") can be removed - dynamically learned entries (type "IGMP") have no Remove action.</li>
                    <li>Supported ports: g0/1 - g0/10.</li>
                </ul>
            </div>
        </div>

        <!-- VLAN Filters -->
        <div class="tab-pane" id="igmp_filter_tab">
            <button class="btn btn-primary" id="btnAddIgmpFilter">
                <i class="glyphicon glyphicon-plus"></i> Add
            </button>
            <span id="igmp_filter_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="igmpFilterTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="igmpFilterTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="100">VLAN ID</th>
                            <th width="150">Multicast Address</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Multicast Address must be a valid IPv4 address (224.0.0.0 - 239.255.255.255).</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Configure IGMP VLAN Modal -->
<div id="igmpVlanModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Configure IGMP Snooping VLAN</h4>
            </div>
            <div class="modal-body">
                <table class="table table-bordered" style="margin-bottom:20px;">
                    <tbody>
                        <tr>
                            <th width="200">VLAN ID</th>
                            <td><input type="number" id="igmp_vlan_id" class="form-control" min="1" max="4094" style="max-width:200px;"></td>
                        </tr>
                        <tr>
                            <th>Status of the IGMP Snooping VLAN</th>
                            <td>
                                <select id="igmp_vlan_status" class="form-control" style="max-width:200px;">
                                    <option value="enable">Enable</option>
                                    <option value="disable">Disable</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Immediate-leave</th>
                            <td>
                                <select id="igmp_vlan_immediate_leave" class="form-control" style="max-width:200px;">
                                    <option value="enable">Enable</option>
                                    <option value="disable">Disable</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="row text-center" style="margin-bottom:5px;">
                    <div class="col-sm-5"><strong>Configuring member ports</strong></div>
                    <div class="col-sm-2"></div>
                    <div class="col-sm-5"><strong>Available Port List</strong></div>
                </div>

                <div class="igmp-transfer-wrap">
                    <div class="igmp-transfer-box">
                        <select id="igmp_vlan_configuring_ports" multiple size="8"></select>
                    </div>
                    <div class="igmp-transfer-btns">
                        <button type="button" id="igmp_vlan_move_right" class="btn btn-default btn-sm">&gt;&gt;</button>
                        <button type="button" id="igmp_vlan_move_left" class="btn btn-default btn-sm">&lt;&lt;</button>
                    </div>
                    <div class="igmp-transfer-box">
                        <select id="igmp_vlan_available_ports" multiple size="8"></select>
                    </div>
                </div>
                <p class="text-muted" style="margin-top:8px;">Multiple ports can be moved into "Configuring member ports" - Apply applies them one at a time (the device only accepts one member port per command).</p>

                <div id="igmp_vlan_error" class="text-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" onclick="resetIgmpVlanModal()">Reset</button>
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveIgmpVlanBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    <span id="saveIgmpVlanBtnLabel">Apply</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Static Multicast Modal -->
<div id="igmpMulticastModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Static Multicast Entry</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="igmp_mc_vlan_id" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Group Address*</label>
                        <div class="col-sm-8">
                            <input type="text" id="igmp_mc_ip_address" class="form-control" placeholder="e.g. 224.1.0.1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Port*</label>
                        <div class="col-sm-8">
                            <select id="igmp_mc_port" class="form-control">
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
                        </div>
                    </div>
                    <div id="igmp_mc_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveIgmpMulticastBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add VLAN Filter Modal -->
<div id="igmpFilterModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add IGMP VLAN Filter</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="igmp_filter_vlan_id" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Multicast Address*</label>
                        <div class="col-sm-8">
                            <input type="text" id="igmp_filter_ip_address" class="form-control" placeholder="e.g. 239.1.1.1">
                        </div>
                    </div>
                    <div id="igmp_filter_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveIgmpFilterBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const IGMP_DEVICE_IP = "{{ $device->hostname }}";
    const IGMP_API_TOKEN = "{{ $data['api_token'] }}";

    /* -----------------------------------------------------
       GLOBAL CONFIGURATION
    ----------------------------------------------------- */
    function fillIgmpGlobalFields(igmp) {
        document.getElementById("igmp_snooping").value = igmp.igmp_snooping === "disable" ? "disable" : "enable";
        document.getElementById("igmp_dlf_drop").value = igmp.dlf_drop === "discard_unknown" ? "discard_unknown" : "transfer_unknown";
        document.getElementById("igmp_auto_query").value = igmp.auto_query === "disable" ? "disable" : "enable";
        document.getElementById("igmp_querier_address").value = igmp.querier_address || "";
        document.getElementById("igmp_query_interval").value = igmp.query_interval || "";
        document.getElementById("igmp_querier_expiry_interval").value = igmp.querier_expiry_interval || "";
    }

    function clearIgmpGlobalErrors() {
        ["igmp_querier_address_error", "igmp_query_interval_error", "igmp_querier_expiry_interval_error"].forEach(id => {
            document.getElementById(id).style.display = "none";
        });
    }

    function loadIgmpGlobalConfig() {
        const loadingNote = document.getElementById('igmp_global_loading_note');
        const errorNote = document.getElementById('igmp_global_error_note');
        loadingNote.style.display = "inline";
        errorNote.style.display = "none";

        fetch(`/api/v0/igmp/show/${IGMP_DEVICE_IP}`, {
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" }
        })
            .then(r => r.json())
            .then(res => {
                loadingNote.style.display = "none";
                if (res.status !== "success") {
                    errorNote.innerText = res.message || "Failed to read IGMP snooping configuration";
                    errorNote.style.display = "inline";
                    return;
                }
                if (res.igmp) {
                    fillIgmpGlobalFields(res.igmp);
                }
            })
            .catch(() => {
                loadingNote.style.display = "none";
                errorNote.innerText = "Request failed while reading IGMP snooping configuration";
                errorNote.style.display = "inline";
            });
    }

    function applyIgmpGlobalConfig() {
        clearIgmpGlobalErrors();

        const btn = document.getElementById("igmpApplyBtn");
        btn.disabled = true;
        btn.querySelector('.spinner-border').style.display = "inline-block";

        const payload = {
            igmp_snooping: document.getElementById("igmp_snooping").value,
            dlf_drop: document.getElementById("igmp_dlf_drop").value,
            auto_query: document.getElementById("igmp_auto_query").value,
            querier_address: document.getElementById("igmp_querier_address").value,
            query_interval: +document.getElementById("igmp_query_interval").value,
            querier_expiry_interval: +document.getElementById("igmp_querier_expiry_interval").value
        };

        fetch(`/api/v0/igmp/set/${IGMP_DEVICE_IP}`, {
            method: "POST",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Content-Type": "application/json", "Accept": "application/json" },
            body: JSON.stringify(payload)
        })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.querySelector('.spinner-border').style.display = "none";

                if (res.status === "success") {
                    alert("IGMP snooping configuration updated successfully");
                    loadIgmpGlobalConfig();
                } else if (res.errors) {
                    if (res.errors.querier_address) showFieldError("igmp_querier_address_error", res.errors.querier_address[0]);
                    if (res.errors.query_interval) showFieldError("igmp_query_interval_error", res.errors.query_interval[0]);
                    if (res.errors.querier_expiry_interval) showFieldError("igmp_querier_expiry_interval_error", res.errors.querier_expiry_interval[0]);
                    if (!res.errors.querier_address && !res.errors.query_interval && !res.errors.querier_expiry_interval) {
                        alert(res.message || "IGMP snooping configuration failed");
                    }
                } else {
                    alert(res.message || "IGMP snooping configuration failed");
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.querySelector('.spinner-border').style.display = "none";
                alert("Request failed");
            });
    }

    function showFieldError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = "block";
    }

    /* -----------------------------------------------------
       VLAN CONFIGURATION
    ----------------------------------------------------- */
    var igmpVlanTable = $('#igmpVlanTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/igmp/vlan/show/" + IGMP_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('igmp_vlan_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "vlan_id" },
            { data: "immediate_leave" },
            { data: "router_ports", render: data => data || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    return `<button type="button" class="btn btn-xs btn-warning btn-edit-igmp-vlan" data-vlan="${row.vlan_id}" data-leave="${row.immediate_leave}" data-port="${row.router_ports || ''}">Edit</button>`;
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No IGMP snooping VLANs found" }
    });

    const IGMP_ALL_PORTS = @json($igmpAllPorts);

    // Populate the two transfer-list <select>s: whatever is in
    // configuredValue (at most one port, since the playbook only accepts
    // a single member_port) goes in "Configuring member ports", every
    // other known port goes in "Available Port List".
    function populateIgmpPortLists(configuredValues) {
        const configuredSelect = document.getElementById('igmp_vlan_configuring_ports');
        const availableSelect = document.getElementById('igmp_vlan_available_ports');
        configuredSelect.innerHTML = '';
        availableSelect.innerHTML = '';

        const configuredSet = new Set(configuredValues);

        IGMP_ALL_PORTS.forEach(function (port) {
            const opt = document.createElement('option');
            opt.value = port;
            opt.textContent = port;
            if (configuredSet.has(port)) {
                configuredSelect.appendChild(opt);
            } else {
                availableSelect.appendChild(opt);
            }
        });
    }

    function getIgmpConfiguredPorts() {
        return Array.from(document.getElementById('igmp_vlan_configuring_ports').options).map(o => o.value);
    }

    document.getElementById('igmp_vlan_move_right').addEventListener('click', function () {
        const from = document.getElementById('igmp_vlan_available_ports');
        const to = document.getElementById('igmp_vlan_configuring_ports');
        Array.from(from.selectedOptions).forEach(function (opt) {
            opt.selected = false;
            to.appendChild(opt);
        });
    });

    document.getElementById('igmp_vlan_move_left').addEventListener('click', function () {
        const from = document.getElementById('igmp_vlan_configuring_ports');
        const to = document.getElementById('igmp_vlan_available_ports');
        Array.from(from.selectedOptions).forEach(function (opt) {
            opt.selected = false;
            to.appendChild(opt);
        });
    });

    let igmpVlanModalInitialPorts = [];

    function resetIgmpVlanModal() {
        populateIgmpPortLists(igmpVlanModalInitialPorts);
    }

    $('#btnAddIgmpVlan').on('click', function () {
        document.getElementById('igmp_vlan_id').value = '';
        document.getElementById('igmp_vlan_id').readOnly = false;
        document.getElementById('igmp_vlan_status').value = 'enable';
        document.getElementById('igmp_vlan_immediate_leave').value = 'enable';
        igmpVlanModalInitialPorts = [];
        populateIgmpPortLists([]);
        document.getElementById('igmp_vlan_error').style.display = 'none';
        $('#igmpVlanModal').modal('show');
    });

    $('#igmpVlanTable tbody').on('click', '.btn-edit-igmp-vlan', function () {
        document.getElementById('igmp_vlan_id').value = $(this).data('vlan');
        document.getElementById('igmp_vlan_id').readOnly = true;
        document.getElementById('igmp_vlan_status').value = 'enable';
        document.getElementById('igmp_vlan_immediate_leave').value = String($(this).data('leave')).toLowerCase() === 'enabled' ? 'enable' : 'disable';
        // Router Ports (from the VLAN list) is this VLAN's existing member
        // port(s) - prefill "Configuring member ports" with all of them
        // instead of leaving it empty, since the SET playbook always
        // re-applies whatever port(s) are submitted here (it has no way
        // to unset one).
        igmpVlanModalInitialPorts = String($(this).data('port') || '').split(/[\s,]+/).filter(Boolean);
        populateIgmpPortLists(igmpVlanModalInitialPorts);
        document.getElementById('igmp_vlan_error').style.display = 'none';
        $('#igmpVlanModal').modal('show');
    });

    $('#saveIgmpVlanBtn').on('click', function () {
        const btn = this;
        const btnLabel = document.getElementById('saveIgmpVlanBtnLabel');
        const errorBox = document.getElementById('igmp_vlan_error');
        errorBox.style.display = 'none';

        const memberPorts = getIgmpConfiguredPorts();
        if (!memberPorts.length) {
            errorBox.innerText = "Move at least one port into \"Configuring member ports\" before applying";
            errorBox.style.display = 'block';
            return;
        }

        const vlanId = document.getElementById('igmp_vlan_id').value;
        const statusSnoopingVlan = document.getElementById('igmp_vlan_status').value;
        const immediateLeave = document.getElementById('igmp_vlan_immediate_leave').value;

        $(btn).prop('disabled', true).find('.spinner-border').show();
        btnLabel.innerText = `Applying 0/${memberPorts.length}...`;

        // igmp_snooping_vlan_config_set.yml only accepts one member_port
        // per call, so apply each selected port with its own request, in
        // sequence, showing progress since each is a full SSH round trip.
        let done = 0;
        let failures = [];
        let chain = Promise.resolve();

        memberPorts.forEach(port => {
            chain = chain
                .then(() => $.ajax({
                    url: "/api/v0/igmp/vlan/set/" + IGMP_DEVICE_IP,
                    method: "POST",
                    headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
                    contentType: "application/json",
                    data: JSON.stringify({
                        vlan_id: vlanId,
                        status_snooping_vlan: statusSnoopingVlan,
                        immediate_leave: immediateLeave,
                        member_port: port
                    })
                }))
                .catch((xhr) => {
                    failures.push(`${port} (${xhr.responseJSON?.message || 'failed'})`);
                })
                .then(() => {
                    done++;
                    btnLabel.innerText = `Applying ${done}/${memberPorts.length}...`;
                });
        });

        chain.then(() => {
            $(btn).prop('disabled', false).find('.spinner-border').hide();
            btnLabel.innerText = 'Apply';

            if (failures.length) {
                errorBox.innerText = "Failed for: " + failures.join(', ');
                errorBox.style.display = 'block';
            } else {
                $('#igmpVlanModal').modal('hide');
                alert("IGMP snooping VLAN configuration updated for " + memberPorts.join(', '));
            }
            igmpVlanTable.ajax.reload(null, false);
        });
    });

    /* -----------------------------------------------------
       MULTICAST GROUPS
    ----------------------------------------------------- */
    var igmpMulticastTable = $('#igmpMulticastTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/igmp/multicast/show/" + IGMP_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('igmp_multicast_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "vlan_id" },
            { data: "group" },
            { data: "type", render: data => data || '-' },
            { data: "ports", render: data => Array.isArray(data) ? data.join(', ') : (data || '-') },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    // The device's own "show ip igmp-snooping group" output
                    // labels manually/statically-added entries as "USER"
                    // (added via the "static" CLI keyword) and dynamically
                    // learned ones as "IGMP" - confirmed live, not "Static"/
                    // "Dynamic" as the naming might suggest.
                    const isStatic = String(row.type || '').toUpperCase() === 'USER';
                    const port = Array.isArray(row.ports) && row.ports.length ? row.ports[0] : '';
                    if (!isStatic || !port) {
                        return '<button type="button" class="btn btn-xs btn-danger" disabled title="Only static entries with a known port can be removed">Remove</button>';
                    }
                    return `<button type="button" class="btn btn-xs btn-danger btn-remove-igmp-mc" data-vlan="${row.vlan_id}" data-ip="${row.group}" data-port="${port}">Remove</button>`;
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No IGMP multicast groups found" }
    });

    $('#btnAddIgmpMulticast').on('click', function () {
        document.getElementById('igmp_mc_vlan_id').value = '';
        document.getElementById('igmp_mc_ip_address').value = '';
        document.getElementById('igmp_mc_port').value = 'g0/1';
        document.getElementById('igmp_mc_error').style.display = 'none';
        $('#igmpMulticastModal').modal('show');
    });

    $('#saveIgmpMulticastBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('igmp_mc_error');
        errorBox.style.display = 'none';

        const payload = {
            vlan_id: document.getElementById('igmp_mc_vlan_id').value,
            ip_address: document.getElementById('igmp_mc_ip_address').value,
            port: document.getElementById('igmp_mc_port').value,
            action: 'enable'
        };

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/igmp/multicast/set/" + IGMP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#igmpMulticastModal').modal('hide');
                alert(res.message || "Static multicast entry added");
                igmpMulticastTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to add static multicast entry";
                errorBox.style.display = 'block';
            }
        });
    });

    $('#igmpMulticastTable tbody').on('click', '.btn-remove-igmp-mc', function () {
        if (!confirm(`Remove static multicast entry ${$(this).data('ip')} on VLAN ${$(this).data('vlan')}?`)) {
            return;
        }

        $.ajax({
            url: "/api/v0/igmp/multicast/set/" + IGMP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify({
                vlan_id: $(this).data('vlan'),
                ip_address: $(this).data('ip'),
                port: $(this).data('port'),
                action: 'disable'
            }),
            success: function (res) {
                alert(res.message || "Static multicast entry removed");
                igmpMulticastTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || "Failed to remove static multicast entry");
            }
        });
    });

    /* -----------------------------------------------------
       VLAN FILTERS
    ----------------------------------------------------- */
    var igmpFilterTable = $('#igmpFilterTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/igmp/filter/show/" + IGMP_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('igmp_filter_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "vlanid" },
            { data: "ip_address" },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    return `<button type="button" class="btn btn-xs btn-danger btn-delete-igmp-filter" data-vlan="${row.vlanid}" data-ip="${row.ip_address}">Delete</button>`;
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No IGMP VLAN filters found" }
    });

    $('#btnAddIgmpFilter').on('click', function () {
        document.getElementById('igmp_filter_vlan_id').value = '';
        document.getElementById('igmp_filter_ip_address').value = '';
        document.getElementById('igmp_filter_error').style.display = 'none';
        $('#igmpFilterModal').modal('show');
    });

    $('#saveIgmpFilterBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('igmp_filter_error');
        errorBox.style.display = 'none';

        const payload = {
            vlan_id: document.getElementById('igmp_filter_vlan_id').value,
            ip_address: document.getElementById('igmp_filter_ip_address').value
        };

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/igmp/filter/set/" + IGMP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#igmpFilterModal').modal('hide');
                alert(res.message || "IGMP VLAN filter added");
                igmpFilterTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to add IGMP VLAN filter";
                errorBox.style.display = 'block';
            }
        });
    });

    $('#igmpFilterTable tbody').on('click', '.btn-delete-igmp-filter', function () {
        if (!confirm(`Delete IGMP filter ${$(this).data('ip')} on VLAN ${$(this).data('vlan')}?`)) {
            return;
        }

        $.ajax({
            url: "/api/v0/igmp/filter/delete/" + IGMP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + IGMP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify({
                vlan_id: $(this).data('vlan'),
                ip_address: $(this).data('ip')
            }),
            success: function (res) {
                alert(res.message || "IGMP VLAN filter removed");
                igmpFilterTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || "Failed to delete IGMP VLAN filter");
            }
        });
    });

    /* -----------------------------------------------------
       TAB SWITCH - fix DataTable column widths when a
       tab that was initially hidden becomes active
    ----------------------------------------------------- */
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        if (target === '#igmp_vlan_tab') igmpVlanTable.columns.adjust();
        if (target === '#igmp_multicast_tab') igmpMulticastTable.columns.adjust();
        if (target === '#igmp_filter_tab') igmpFilterTable.columns.adjust();
    });

    /* AUTO LOAD */
    loadIgmpGlobalConfig();
</script>
