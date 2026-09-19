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
</style>

<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">

<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    @php
        // Short-form port list for the VLAN Edit modal's Interface select -
        // editvlanconfiguration.yml accepts GigaEthernet/TGigaEthernet
        // (short or full form) and Port-aggregatorN.
        $vlanEditPorts = collect($data['interfaces'] ?? [])
            ->map(function ($ifName) {
                if (preg_match('/^GigaEthernet(\d+\/\d+)$/i', $ifName, $m)) {
                    return 'g' . $m[1];
                }
                if (preg_match('/^TGigaEthernet(\d+\/\d+)$/i', $ifName, $m)) {
                    return 'tg' . $m[1];
                }
                if (preg_match('/^Port-?[Aa]ggregator(\d+)$/i', $ifName, $m)) {
                    return 'Port-aggregator' . $m[1];
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
        <li class="active"><a href="#vlan_config" data-toggle="tab">VLAN Configuration</a></li>
        <li><a href="#vlan_batch" data-toggle="tab">VLAN Batch Configuration</a></li>
        <li><a href="#interface_vlan" data-toggle="tab">Interface VLAN Attribute</a></li>
        <li><a href="#voice_vlan" data-toggle="tab">Voice VLAN</a></li>
        <li><a href="#interface_voice_vlan" data-toggle="tab">Interface Voice VLAN</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">
        <div class="tab-pane active" id="vlan_config">
            <button class="btn btn-primary" id="btnAddVlan">
                <i class="glyphicon glyphicon-plus"></i> Add
            </button>
            <span id="vlan_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <div class="table-responsive">
                <table id="vlanTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th width="100">VLAN ID</th>
                            <th width="200">VLAN Name</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>


            <div class="row" style="margin-top:10px;">
                <div class="col-sm-6">

                </div>
                <div class="col-sm-6 text-right">
                    <button id="batchDeleteBtn" class="btn btn-danger btn-sm">Batch Delete</button>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>The default VLAN cannot be deleted.</li>
                    <li>Click Edit to browse or reset VLAN.</li>
                    <li>For 100+ VLANs use <code>show vlan</code> in CLI.</li>
                </ul>
            </div>
        </div>

        <!-- Other Tabs -->
        <div class="tab-pane" id="vlan_batch">

            <div class="container-fluid" style="margin-top: 20px;">
                <div class="panel panel-primary">

                    <div class="panel-heading">
                        <h3 class="panel-title">Batch VLAN Configuration</h3>
                    </div>

                    <div class="panel-body">
                        <table class="table table-striped table-bordered" style="margin:0;">
                            <tbody>

                                <tr>
                                    <th width="200">VLAN Configured</th>
                                    <td id="vlan_configured">1,200</td>
                                </tr>

                                <tr>
                                    <th>VLAN Add</th>
                                    <td>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="vlan_add">
                                            </div>
                                            <div class="col-sm-6 text-muted" style="line-height:34px;">
                                                &lt;2-4094&gt;
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>VLAN Delete</th>
                                    <td>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="vlan_delete">
                                            </div>
                                            <div class="col-sm-6 text-muted" style="line-height:34px;">
                                                &lt;2-4094&gt;
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>

                    <div class="panel-footer text-right">

                        <button class="btn btn-primary" onclick="applyVlanBatch()"><span class="spinner-border"
                                style="display:none;"></span>Apply</button>
                        <button class="btn btn-default" onclick="resetVlanBatch()">Reset</button>
                    </div>

                </div>

                <!-- Help Panel -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Help</h3>
                    </div>
                    <div class="panel-body">
                        <ul style="margin-bottom:0;">
                            <li>VLAN ID (2-4094), such as (2,3,5,7) or (2-3,5-7) or (2-7) or (2 3,5 7-9)</li>
                            <li>VLAN Operate: First add; Second delete.</li>
                        </ul>
                    </div>
                </div>

            </div>

        </div>






        <div class="tab-pane" id="interface_vlan">
            <span id="interface_vlan_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="interfaceVlanTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>
            <div class="table-responsive">
                <table id="interfaceVlanTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="100">Port</th>
                            <th width="80">PVID</th>
                            <th width="90">Mode</th>
                            <th width="150">VLAN Allowed</th>
                            <th width="150">VLAN Untagged</th>
                            <th width="90">MAC VLAN</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>VLAN-allowed and VLAN-untagged: (1-4094), such as (1,3,5,7) Or (1,3-5,7) Or (1-7) Or (1 3,5 7-9)
                    </li>
                    <li>For Access mode, PVID and VLAN-allowed must be the same.</li>
                </ul>
            </div>
        </div>




        <div class="tab-pane" id="voice_vlan">
            <button class="btn btn-primary" id="btnAddvoiceVlan">
                <i class="glyphicon glyphicon-plus"></i> Add
            </button>
            <span id="voice_vlan_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="voicevlanTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>
            <div class="table-responsive">
                <table id="voicevlanTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAllVoiceVlan">
                            </th>
                            <th width="160">MAC Address</th>
                            <th width="160">MAC MASK</th>
                        </tr>
                    </thead>
                </table>
            </div>


            <div class="row" style="margin-top:10px;">
                <div class="col-sm-6">

                </div>
                <div class="col-sm-6 text-right">
                    <button id="batchVoicevlanDeleteBtn" class="btn btn-danger btn-sm">
                        <span class="spinner-border" style="display:none;"></span>
                        <span id="batchVoicevlanDeleteBtnLabel">Batch Delete</span>
                    </button>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>MAC Address and MAC Mask use the xxxx.xxxx.xxxx format.</li>
                </ul>
            </div>

        </div>
        <div class="tab-pane" id="interface_voice_vlan">
            <span id="interface_voice_vlan_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="interfaceVoiceVlanTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>
            <div class="table-responsive">
                <table id="interfaceVoiceVlanTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="100">Port</th>
                            <th width="100">VLAN ID</th>
                            <th width="120">Priority Mode</th>
                            <th width="90">Priority</th>
                            <th width="120">Mode</th>
                            <th width="140">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Priority: 0-7 for COS, 0-63 for DSCP.</li>
                    <li>Mode: vlan or mac-address.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add VLAN Modal -->
<div id="addVlanModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add VLAN</h4>
            </div>
            <div class="modal-body">
                <form id="addVlanForm">
                    <div class="form-group">
                        <label for="vlan_id">VLAN ID</label>
                        <input type="number" class="form-control" id="vlan_id" name="vlan_id"
                            placeholder="Enter VLAN ID" required>
                    </div>
                    <div class="form-group">
                        <label for="vlan_name">VLAN Name</label>
                        <input type="text" class="form-control" id="vlan_name" name="vlan_name"
                            placeholder="Enter VLAN Name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveVlanBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save VLAN
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit VLAN Configuration Modal -->
<div id="editVlanConfigModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="width:800px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit VLAN</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="evc_vlan_id">
                <table class="table table-bordered" style="margin-bottom:20px;">
                    <tbody>
                        <tr>
                            <th width="200" class="bg-gray" style="background:#f5f5f5;">VLAN ID</th>
                            <td><input type="text" id="evc_vlan_id_display" class="form-control" style="max-width:200px;" readonly></td>
                        </tr>
                        <tr>
                            <th class="bg-gray" style="background:#f5f5f5;">VLAN Name</th>
                            <td><input type="text" id="evc_vlan_name" class="form-control" style="max-width:200px;"></td>
                        </tr>
                    </tbody>
                </table>

                <span id="evc_loading_note" class="text-muted" style="display:none;">
                    <i class="fa fa-spinner fa-spin"></i> reading live port configuration from device...
                </span>
                <span id="evc_error" class="text-danger" style="display:none;"></span>

                <div class="table-responsive">
                    <table class="table table-condensed" id="evc_ports_table">
                        <thead>
                            <tr>
                                <th>Port</th>
                                <th>Default VLAN</th>
                                <th>Mode</th>
                                <th>Untag or not</th>
                                <th>Allow or not</th>
                            </tr>
                        </thead>
                        <tbody id="evc_ports_tbody">
                            @foreach($vlanEditPorts as $port)
                                <tr data-port="{{ $port }}">
                                    <td><strong>{{ $port }}</strong></td>
                                    <td>
                                        <input type="number" class="form-control evc-row-pvid" min="1" max="4094" style="width:90px;">
                                    </td>
                                    <td>
                                        <select class="form-control evc-row-mode" style="width:100px;">
                                            <option value="Access">Access</option>
                                            <option value="Trunk">Trunk</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control evc-row-untagged" style="width:90px;">
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control evc-row-allowed" style="width:90px;">
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-muted">Untag/Allow only apply to Trunk ports and are disabled for Access. Only rows you change are saved.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveEditVlanConfigBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    <span id="saveEditVlanConfigBtnLabel">Apply</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Interface VLAN Attribute Modal -->
<div id="editInterfaceVlanModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Interface VLAN Attribute - <span id="ivlan_edit_port"></span></h4>
            </div>
            <div class="modal-body">
                <form id="editInterfaceVlanForm" class="form-horizontal">
                    <input type="hidden" id="ivlan_interface">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Mode</label>
                        <div class="col-sm-8">
                            <select id="ivlan_mode" class="form-control">
                                <option value="Access">Access</option>
                                <option value="Trunk">Trunk</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">PVID</label>
                        <div class="col-sm-8">
                            <input type="number" class="form-control" id="ivlan_pvid" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN Allowed</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="ivlan_allowed" placeholder="e.g. 1,3-5,7">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN Untagged</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="ivlan_untagged" placeholder="e.g. 1,3-5,7">
                        </div>
                    </div>
                    <div id="ivlan_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveInterfaceVlanBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Voice VLAN Modal -->
<div id="addVoiceVlanModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Voice VLAN</h4>
            </div>
            <div class="modal-body">
                <form id="addVoiceVlanForm" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">MAC Address</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="voice_mac_address" placeholder="e.g. 001a.2b3c.4d5e">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">MAC Mask</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="voice_mac_mask" placeholder="e.g. ffff.ffff.ffff">
                        </div>
                    </div>
                    <div id="voice_vlan_add_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveVoiceVlanBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Interface Voice VLAN Modal -->
<div id="editInterfaceVoiceVlanModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Interface Voice VLAN - <span id="ivvlan_edit_port"></span></h4>
            </div>
            <div class="modal-body">
                <form id="editInterfaceVoiceVlanForm" class="form-horizontal">
                    <input type="hidden" id="ivvlan_interface">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN ID</label>
                        <div class="col-sm-8">
                            <input type="number" class="form-control" id="ivvlan_vlan_id" min="2" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Priority Mode</label>
                        <div class="col-sm-8">
                            <select id="ivvlan_priority_mode" class="form-control">
                                <option value="cos">COS</option>
                                <option value="dscp">DSCP</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Priority</label>
                        <div class="col-sm-8">
                            <input type="number" class="form-control" id="ivvlan_priority" min="0" max="63">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Mode</label>
                        <div class="col-sm-8">
                            <select id="ivvlan_mode" class="form-control">
                                <option value="vlan">vlan</option>
                                <option value="mac-address">mac-address</option>
                            </select>
                        </div>
                    </div>
                    <div id="ivvlan_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="deleteInterfaceVoiceVlanBtn" class="btn btn-danger">Delete</button>
                <button id="saveInterfaceVoiceVlanBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    /* -----------------------
   COOKIE FUNCTIONS (per hostname)
----------------------- */
    function setCookie(name, value, days = 7) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + expires + "; path=/";
    }

    function getCookie(name) {
        const cookies = document.cookie.split("; ").reduce((acc, cookie) => {
            const [key, val] = cookie.split("=");
            acc[key] = decodeURIComponent(val);
            return acc;
        }, {});
        return cookies[name] || null;
    }

    /* -----------------------
       DEVICE VARIABLES
    ----------------------- */
    const HOSTNAME = "{{ $device->hostname }}";
    const COOKIE_PREFIX = HOSTNAME + "_"; // cookie key prefix per device
    let DEVICE_IP = getCookie(COOKIE_PREFIX + "device_ip") || HOSTNAME;
    let API_TOKEN = getCookie(COOKIE_PREFIX + "api_token") || "{{ $data['api_token'] }}";
    setCookie(COOKIE_PREFIX + "device_ip", DEVICE_IP);
    setCookie(COOKIE_PREFIX + "api_token", API_TOKEN);

    /* -----------------------
       BOOTGRID INITIALIZATION
    ----------------------- */
    var vlanTable = $('#vlanTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/getvlan/" + DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function(json) {
                document.getElementById('vlan_cache_note').style.display = json.cached ? "inline" : "none";
                if (json.vlans) return json.vlans;
                return json;
            }
        },
        columns: [{
                data: "id",
                orderable: false,
                render: function(data) {
                    return '<input type="checkbox" class="row-check" value="' + data + '">';
                }
            },
            {
                data: "id"
            },
            {
                data: "name"
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(row) {
                    return `<button type="button" class="btn btn-xs btn-info btn-edit-vlan" data-id="${row.id}" data-name="${row.name}">Edit</button>`;
                }
            }
        ],
        order: [
            [1, "asc"]
        ],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No VLANs found"
        }
    });

    /* ---------------------
    voice vlan show
    -----------------------*/

    var voicevlanTable = $('#voicevlanTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/vlan/voice/show/" + DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function(json) {
                document.getElementById('voice_vlan_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries ?? [];
            }
        },
        columns: [{
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `<input type="checkbox" class="row-check voice" data-mac="${row.mac_address}"
                    data-mask="${row.mask}">`;
                }
            },
            {
                data: "mac_address"
            },
            {
                data: "mask"
            }
        ],
        order: [
            [1, "asc"]
        ],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No Voice Vlan found"
        }
    });

    /* -----------------------
       INTERFACE VLAN ATTRIBUTE
    ----------------------- */
    var interfaceVlanTable = $('#interfaceVlanTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/vlan/interface/attribute/show/" + DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function(json) {
                document.getElementById('interface_vlan_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries ?? [];
            }
        },
        columns: [
            { data: "port_name" },
            { data: "pvid" },
            { data: "mode" },
            { data: "vlan_allowed_range" },
            { data: "vlan_untagged_range" },
            { data: "mac_vlan" },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(row) {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-ivlan">Edit</button>';
                }
            }
        ],
        order: [
            [0, "asc"]
        ],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No interfaces found"
        }
    });

    $('#interfaceVlanTable tbody').on('click', '.btn-edit-ivlan', function() {
        const row = interfaceVlanTable.row($(this).closest('tr')).data();
        document.getElementById('ivlan_edit_port').innerText = row.port_name;
        document.getElementById('ivlan_interface').value = row.interface;
        document.getElementById('ivlan_mode').value = row.mode;
        document.getElementById('ivlan_pvid').value = row.pvid;
        document.getElementById('ivlan_allowed').value = row.vlan_allowed_range;
        document.getElementById('ivlan_untagged').value = row.vlan_untagged_range;
        document.getElementById('ivlan_edit_error').style.display = 'none';
        $('#editInterfaceVlanModal').modal('show');
    });

    $('#saveInterfaceVlanBtn').on('click', function() {
        const btn = this;
        const errorBox = document.getElementById('ivlan_edit_error');
        errorBox.style.display = 'none';

        const payload = {
            interface: document.getElementById('ivlan_interface').value,
            mode: document.getElementById('ivlan_mode').value,
            pvid: document.getElementById('ivlan_pvid').value,
            vlan_allowed_range: document.getElementById('ivlan_allowed').value,
            vlan_untagged_range: document.getElementById('ivlan_untagged').value
        };

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/vlan/interface/attribute/set/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function(res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#editInterfaceVlanModal').modal('hide');
                alert(res.message || "Interface VLAN attribute updated");
                interfaceVlanTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to update interface VLAN attribute";
                errorBox.style.display = 'block';
            }
        });
    });

    /* -----------------------
       INTERFACE VOICE VLAN
    ----------------------- */
    var interfaceVoiceVlanTable = $('#interfaceVoiceVlanTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/vlan/interface/voice/show/" + DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function(json) {
                document.getElementById('interface_voice_vlan_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries ?? [];
            }
        },
        columns: [
            { data: "port_name" },
            { data: "vlan_id", render: data => data || '-' },
            { data: "priority_mode", render: data => data || '-' },
            { data: "priority", render: data => (data === '' || data === null || data === undefined) ? '-' : data },
            { data: "mode", render: data => data || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(row) {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-ivvlan">Edit</button>';
                }
            }
        ],
        order: [
            [0, "asc"]
        ],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No interfaces found"
        }
    });

    $('#interfaceVoiceVlanTable tbody').on('click', '.btn-edit-ivvlan', function() {
        const row = interfaceVoiceVlanTable.row($(this).closest('tr')).data();
        document.getElementById('ivvlan_edit_port').innerText = row.port_name;
        document.getElementById('ivvlan_interface').value = row.port_name;
        document.getElementById('ivvlan_vlan_id').value = row.vlan_id || 90;
        document.getElementById('ivvlan_priority_mode').value = row.priority_mode || 'dscp';
        document.getElementById('ivvlan_priority').value = (row.priority === '' || row.priority === undefined) ? 5 : row.priority;
        document.getElementById('ivvlan_mode').value = row.mode || 'vlan';
        document.getElementById('ivvlan_edit_error').style.display = 'none';
        $('#editInterfaceVoiceVlanModal').modal('show');
    });

    $('#saveInterfaceVoiceVlanBtn').on('click', function() {
        const btn = this;
        const errorBox = document.getElementById('ivvlan_edit_error');
        errorBox.style.display = 'none';

        const payload = {
            operation: 'set',
            interface: document.getElementById('ivvlan_interface').value,
            vlan_id: document.getElementById('ivvlan_vlan_id').value,
            priority_mode: document.getElementById('ivvlan_priority_mode').value,
            priority: document.getElementById('ivvlan_priority').value,
            mode: document.getElementById('ivvlan_mode').value
        };

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/vlan/interface/voice/set/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function(res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#editInterfaceVoiceVlanModal').modal('hide');
                alert(res.message || "Interface Voice VLAN updated");
                interfaceVoiceVlanTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to update interface voice VLAN";
                errorBox.style.display = 'block';
            }
        });
    });

    $('#deleteInterfaceVoiceVlanBtn').on('click', function() {
        if (!confirm("Remove voice VLAN configuration from this interface?")) {
            return;
        }

        $.ajax({
            url: "/api/v0/vlan/interface/voice/set/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",
            data: JSON.stringify({
                operation: 'delete',
                interface: document.getElementById('ivvlan_interface').value
            }),
            success: function(res) {
                $('#editInterfaceVoiceVlanModal').modal('hide');
                alert(res.message || "Interface Voice VLAN removed");
                interfaceVoiceVlanTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || "Failed to remove interface voice VLAN");
            }
        });
    });

    /* -----------------------
       ADD VOICE VLAN
    ----------------------- */
    $('#btnAddvoiceVlan').on('click', function() {
        $('#addVoiceVlanForm')[0].reset();
        document.getElementById('voice_vlan_add_error').style.display = 'none';
        $('#addVoiceVlanModal').modal('show');
    });

    $('#saveVoiceVlanBtn').on('click', function() {
        const btn = this;
        const errorBox = document.getElementById('voice_vlan_add_error');
        errorBox.style.display = 'none';

        const macAddress = document.getElementById('voice_mac_address').value.trim();
        const macMask = document.getElementById('voice_mac_mask').value.trim();

        if (!macAddress || !macMask) {
            errorBox.innerText = "Please enter both MAC Address and MAC Mask";
            errorBox.style.display = 'block';
            return;
        }

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/vlan/voice/set/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",
            data: JSON.stringify({
                operation: 'set',
                mac_address: macAddress,
                mac_mask: macMask
            }),
            success: function(res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#addVoiceVlanModal').modal('hide');
                alert(res.message || "Voice VLAN added successfully");
                voicevlanTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to add voice VLAN";
                errorBox.style.display = 'block';
            }
        });
    });

    /* -----------------------
       ADD VLAN MODAL
    ----------------------- */
    $("#btnAddVlan").on("click", function() {
        $("#addVlanForm")[0].reset();
        $("#addVlanModal").modal("show");
    });

    $('#selectAll').on('change', function() {
        $('.row-check').prop('checked', this.checked);
    });

    $('#selectAllVoiceVlan').on('change', function() {
        $('.row-check.voice').prop('checked', this.checked);
    });

    $('#batchDeleteBtn').on('click', function() {
        let ids = [];

        $('.row-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert("Please select VLANs");
            return;
        }

        $.ajax({
            url: "/api/v0/vlan/batch/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",
            data: JSON.stringify({
                vlan_delete: ids.join(',') // ✅ IMPORTANT
            }),
            success: function(response) {
                alert(response.message || "VLAN Deleted successfully");
                vlanTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || "VLAN delete failed");
            }
        });
    });


    $('#batchVoicevlanDeleteBtn').on('click', function() {

        let entries = [];

        $('.row-check.voice:checked').each(function() {
            entries.push({
                mac_address: $(this).data('mac'),
                mac_mask: $(this).data('mask')
            });
        });

        if (!entries.length) {
            alert("Please select Voice VLAN");
            return;
        }

        if (!confirm(`Delete ${entries.length} Voice VLAN entr${entries.length === 1 ? 'y' : 'ies'}?`)) {
            return;
        }

        const btn = document.getElementById('batchVoicevlanDeleteBtn');
        const btnLabel = document.getElementById('batchVoicevlanDeleteBtnLabel');
        let done = 0;

        $(btn).prop('disabled', true).find('.spinner-border').show();
        btnLabel.innerText = `Processing 0/${entries.length}...`;

        // setvoicevlan.yml only accepts one MAC/mask pair per call, so
        // delete each selected entry with its own request, in sequence -
        // each can take a while (a full SSH round trip), so the button
        // shows live progress instead of sitting there with no feedback.
        let chain = Promise.resolve();
        let failures = [];

        entries.forEach(entry => {
            chain = chain
                .then(() => $.ajax({
                    url: "/api/v0/vlan/voice/set/" + DEVICE_IP,
                    type: "POST",
                    headers: {
                        "Authorization": "Bearer " + API_TOKEN,
                        "Accept": "application/json"
                    },
                    contentType: "application/json",
                    data: JSON.stringify({
                        operation: 'delete',
                        mac_address: entry.mac_address,
                        mac_mask: entry.mac_mask
                    })
                }))
                .catch(() => {
                    failures.push(entry.mac_address);
                })
                .then(() => {
                    done++;
                    btnLabel.innerText = `Processing ${done}/${entries.length}...`;
                });
        });

        chain.then(() => {
            $(btn).prop('disabled', false).find('.spinner-border').hide();
            btnLabel.innerText = 'Batch Delete';

            if (failures.length) {
                alert("Failed to delete: " + failures.join(', '));
            } else {
                alert("Voice VLAN entries deleted successfully");
            }
            voicevlanTable.ajax.reload(null, false);
        });
    });







    $("#saveVlanBtn").on("click", function() {
        let vlan_id = $("#vlan_id").val();
        let vlan_name = $("#vlan_name").val();

        if (!vlan_id || !vlan_name) {
            alert("Please enter both VLAN ID and VLAN Name.");
            return;
        }

        $(".spinner-border").show();
        $("#saveVlanBtn").prop("disabled", true);

        $.ajax({
            url: "/api/v0/addvlan/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN
            },
            data: {
                vlan_id: vlan_id,
                vlan_name: vlan_name
            },
            success: function(response) {
                $(".spinner-border").hide();
                $("#saveVlanBtn").prop("disabled", false);

                if (response.status === "success") {
                    $("#addVlanModal").modal("hide");
                    vlanTable.ajax.reload(null, false);
                    alert("VLAN added successfully!");
                } else {
                    alert("Error: " + JSON.stringify(response));
                }
            },
            error: function(xhr) {
                $(".spinner-border").hide();
                $("#saveVlanBtn").prop("disabled", false);
                alert("Request Failed: " + xhr.responseText);
            }
        });
    });

    /* -----------------------
       EDIT VLAN CONFIGURATION (editvlanconfiguration.yml)
       Shows every port at once, prefilled from the Interface VLAN
       Attribute data, and only submits rows the user actually changed -
       editvlanconfiguration.yml only edits one interface per call, so
       Apply loops sequentially over the changed rows.
    ----------------------- */
    function vlanRangeContains(rangeStr, vlanId) {
        if (!rangeStr) return false;
        const id = parseInt(vlanId, 10);
        return String(rangeStr).split(',').some(function (part) {
            part = part.trim();
            if (!part) return false;
            if (part.indexOf('-') !== -1) {
                const bounds = part.split('-').map(function (n) { return parseInt(n, 10); });
                return id >= bounds[0] && id <= bounds[1];
            }
            return parseInt(part, 10) === id;
        });
    }

    function toggleEvcRowTrunkFields(row) {
        const isTrunk = row.find('.evc-row-mode').val() === 'Trunk';
        row.find('.evc-row-untagged, .evc-row-allowed').prop('disabled', !isTrunk);
    }

    $('#evc_ports_tbody').on('change', '.evc-row-mode', function () {
        toggleEvcRowTrunkFields($(this).closest('tr'));
    });

    let evcInitialState = {};

    $('#vlanTable tbody').on('click', '.btn-edit-vlan', function () {
        const vlanId = $(this).data('id');
        const vlanName = $(this).data('name');

        document.getElementById('evc_vlan_id').value = vlanId;
        document.getElementById('evc_vlan_id_display').value = vlanId;
        document.getElementById('evc_vlan_name').value = vlanName;
        document.getElementById('evc_error').style.display = 'none';
        document.getElementById('evc_loading_note').style.display = 'inline';
        $('#evc_ports_tbody tr').hide();

        $('#editVlanConfigModal').modal('show');

        fetch(`/api/v0/vlan/interface/attribute/show/${DEVICE_IP}`, {
            headers: { "Authorization": "Bearer " + API_TOKEN, "Accept": "application/json" }
        })
            .then(r => r.json())
            .then(res => {
                document.getElementById('evc_loading_note').style.display = 'none';
                $('#evc_ports_tbody tr').show();

                const byPort = {};
                (res.entries || []).forEach(function (e) { byPort[e.port_name] = e; });

                evcInitialState = {};

                $('#evc_ports_tbody tr').each(function () {
                    const port = $(this).data('port');
                    const entry = byPort[port];

                    const pvid = entry ? entry.pvid : 1;
                    const mode = entry && entry.mode === 'Trunk' ? 'Trunk' : 'Access';
                    const untagged = entry && vlanRangeContains(entry.vlan_untagged_range, vlanId) ? 'Yes' : 'No';
                    const allowed = entry && vlanRangeContains(entry.vlan_allowed_range, vlanId) ? 'Yes' : 'No';

                    $(this).find('.evc-row-pvid').val(pvid);
                    $(this).find('.evc-row-mode').val(mode);
                    $(this).find('.evc-row-untagged').val(untagged);
                    $(this).find('.evc-row-allowed').val(allowed);
                    toggleEvcRowTrunkFields($(this));

                    evcInitialState[port] = { pvid: String(pvid), mode: mode, untagged: untagged, allowed: allowed };
                });
            })
            .catch(() => {
                document.getElementById('evc_loading_note').style.display = 'none';
                $('#evc_ports_tbody tr').show();
                document.getElementById('evc_error').innerText = "Failed to read current port configuration - showing defaults";
                document.getElementById('evc_error').style.display = 'block';
            });
    });

    $('#saveEditVlanConfigBtn').on('click', function () {
        const btn = this;
        const btnLabel = document.getElementById('saveEditVlanConfigBtnLabel');
        const errorBox = document.getElementById('evc_error');
        errorBox.style.display = 'none';

        const vlanId = document.getElementById('evc_vlan_id').value;
        const vlanName = document.getElementById('evc_vlan_name').value;

        const changedRows = [];

        $('#evc_ports_tbody tr').each(function () {
            const port = $(this).data('port');
            const current = {
                pvid: $(this).find('.evc-row-pvid').val(),
                mode: $(this).find('.evc-row-mode').val(),
                untagged: $(this).find('.evc-row-untagged').val(),
                allowed: $(this).find('.evc-row-allowed').val()
            };
            const initial = evcInitialState[port];

            const changed = !initial ||
                initial.pvid !== current.pvid ||
                initial.mode !== current.mode ||
                (current.mode === 'Trunk' && (initial.untagged !== current.untagged || initial.allowed !== current.allowed));

            if (changed) {
                changedRows.push({ port: port, ...current });
            }
        });

        if (!changedRows.length) {
            errorBox.innerText = "No changes to apply";
            errorBox.style.display = 'block';
            return;
        }

        $(btn).prop('disabled', true).find('.spinner-border').show();
        btnLabel.innerText = `Applying 0/${changedRows.length}...`;

        let done = 0;
        let failures = [];
        let chain = Promise.resolve();

        changedRows.forEach(row => {
            const payload = {
                vlan_id: vlanId,
                vlan_name: vlanName,
                interface: row.port,
                pvid: row.pvid,
                mode: row.mode
            };
            if (row.mode === 'Trunk') {
                payload.untagged = row.untagged;
                payload.allowed = row.allowed;
            }

            chain = chain
                .then(() => $.ajax({
                    url: "/api/v0/vlan/edit/" + DEVICE_IP,
                    method: "POST",
                    headers: { "Authorization": "Bearer " + API_TOKEN, "Accept": "application/json" },
                    contentType: "application/json",
                    data: JSON.stringify(payload)
                }))
                .catch((xhr) => {
                    failures.push(`${row.port} (${xhr.responseJSON?.message || 'failed'})`);
                })
                .then(() => {
                    done++;
                    btnLabel.innerText = `Applying ${done}/${changedRows.length}...`;
                });
        });

        chain.then(() => {
            $(btn).prop('disabled', false).find('.spinner-border').hide();
            btnLabel.innerText = 'Apply';

            if (failures.length) {
                errorBox.innerText = "Failed for: " + failures.join(', ');
                errorBox.style.display = 'block';
            } else {
                $('#editVlanConfigModal').modal('hide');
                alert(`VLAN ${vlanId} configuration updated for ${changedRows.length} port(s)`);
            }

            vlanTable.ajax.reload(null, false);
            if (typeof interfaceVlanTable !== 'undefined') {
                interfaceVlanTable.ajax.reload(null, false);
            }
        });
    });

    function applyVlanBatch() {

        var vlanAdd = $("#vlan_add").val().trim();
        var vlanDelete = $("#vlan_delete").val().trim();
        $(".spinner-border").show();

        if (!vlanAdd && !vlanDelete) {
            alert("Please enter VLAN Add or VLAN Delete value");
            return;
        }

        $.ajax({
            url: "/api/v0/vlan/batch/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",

            data: JSON.stringify({
                device: DEVICE_IP,
                vlan_add: vlanAdd,
                vlan_delete: vlanDelete
            }),

            success: function(response) {

                alert(response.message || "VLAN batch configuration applied successfully");
                $(".spinner-border").hide();

                // Clear inputs
                resetVlanBatch();

                // Reload VLAN grid
                vlanTable.ajax.reload(null, false);
            },

            error: function(xhr) {
                $(".spinner-border").hide();

                var msg = "Failed to apply VLAN configuration";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    }

    function resetVlanBatch() {
        $("#vlan_add").val("");
        $("#vlan_delete").val("");
    }

    function isValidVlanInput(value) {
        var regex = /^[0-9,\-\s]+$/;
        return regex.test(value);
    }
</script>
