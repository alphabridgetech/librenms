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

    .tab-pane .table-responsive {
        margin-top: 15px;
        border: none;
    }

    .stp-summary td:first-child {
        width: 45%;
        color: #777;
    }

    .stp-port-row .form-control {
        margin-bottom: 6px;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">

    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li class="active"><a href="#stp_global_tab" data-toggle="tab">Global Configuration</a></li>
        <li><a href="#stp_mst_tab" data-toggle="tab">MST Region &amp; Instances</a></li>
        <li><a href="#stp_port_tab" data-toggle="tab">Port Configuration</a></li>
        <li><a href="#stp_guard_tab" data-toggle="tab">Port Guard</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">

        <!-- Global Configuration -->
        <div class="tab-pane active" id="stp_global_tab">
            <div class="row">
                <div class="col-md-5">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>Current Status</strong>
                            <span id="stp_global_loading" class="text-muted" style="margin-left:10px;">
                                <i class="fa fa-spinner fa-spin"></i> reading from device...
                            </span>
                            <span id="stp_global_cache_note" class="text-muted" style="display:none; margin-left:10px;">last known data, refreshing...</span>
                            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadStpGlobal()">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                        </div>
                        <table class="table table-condensed stp-summary" style="margin-bottom:0;">
                            <tr><td>Protocol</td><td id="stp_cur_protocol">-</td></tr>
                            <tr><td>Bridge MAC</td><td id="stp_cur_bridge_mac">-</td></tr>
                            <tr><td>Root Bridge Priority</td><td id="stp_cur_root_priority">-</td></tr>
                            <tr><td>Root Bridge MAC</td><td id="stp_cur_root_mac">-</td></tr>
                            <tr><td>Hello Time / Max Age / Forward Delay</td><td id="stp_cur_timers">-</td></tr>
                            <tr><td>BPDU Terminal</td><td id="stp_cur_bpdu_terminal">-</td></tr>
                            <tr><td>Loop Guard</td><td id="stp_cur_loop_guard">-</td></tr>
                        </table>
                        <div id="stp_global_error" class="text-danger" style="display:none; padding:8px 15px;"></div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="panel panel-info">
                        <div class="panel-heading"><strong>Configure Spanning Tree</strong></div>
                        <div class="panel-body">
                            <form class="form-horizontal">
                                <div class="form-group">
                                    <label class="col-sm-5 control-label">Protocol*</label>
                                    <div class="col-sm-6">
                                        <select id="stp_protocol" class="form-control">
                                            <option value="SSTP">SSTP</option>
                                            <option value="RSTP">RSTP</option>
                                            <option value="MSTP">MSTP</option>
                                            <option value="PVST">PVST</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group stp-field" data-modes="SSTP RSTP">
                                    <label class="col-sm-5 control-label">Bridge Priority*</label>
                                    <div class="col-sm-6">
                                        <select id="stp_priority" class="form-control">
                                            @for ($priority = 0; $priority <= 61440; $priority += 4096)
                                                <option value="{{ $priority }}" @if ($priority === 32768) selected @endif>{{ $priority }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group stp-field" data-modes="SSTP RSTP MSTP">
                                    <label class="col-sm-5 control-label">Hello Time (s)*</label>
                                    <div class="col-sm-6">
                                        <input type="number" id="stp_hello_time" class="form-control" min="1" max="10" value="2">
                                    </div>
                                </div>
                                <div class="form-group stp-field" data-modes="SSTP RSTP MSTP">
                                    <label class="col-sm-5 control-label">Max Age (s)*</label>
                                    <div class="col-sm-6">
                                        <input type="number" id="stp_max_age" class="form-control" min="6" max="40" value="20">
                                    </div>
                                </div>
                                <div class="form-group stp-field" data-modes="SSTP RSTP MSTP">
                                    <label class="col-sm-5 control-label">Forward Delay (s)*</label>
                                    <div class="col-sm-6">
                                        <input type="number" id="stp_forward_delay" class="form-control" min="4" max="30" value="15">
                                    </div>
                                </div>
                                <div class="form-group stp-field" data-modes="MSTP">
                                    <label class="col-sm-5 control-label">Network Diameter*</label>
                                    <div class="col-sm-6">
                                        <input type="number" id="stp_network_diameter" class="form-control" min="2" max="7" value="7">
                                    </div>
                                </div>
                                <div class="form-group stp-field" data-modes="MSTP">
                                    <label class="col-sm-5 control-label">Max Hops*</label>
                                    <div class="col-sm-6">
                                        <input type="number" id="stp_max_hops" class="form-control" min="6" max="40" value="20">
                                    </div>
                                </div>
                                <div class="form-group stp-field" data-modes="MSTP">
                                    <label class="col-sm-5 control-label">MST Compatibility*</label>
                                    <div class="col-sm-6">
                                        <select id="stp_mst_compatibility" class="form-control">
                                            <option value="Disable">Disable</option>
                                            <option value="Enable">Enable</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-5 control-label">BPDU Terminal*</label>
                                    <div class="col-sm-6">
                                        <select id="stp_bpdu_terminal" class="form-control">
                                            <option value="Disable">Disable</option>
                                            <option value="Enable">Enable</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-5 control-label">Loop Guard*</label>
                                    <div class="col-sm-6">
                                        <select id="stp_loop_guard" class="form-control">
                                            <option value="Disable">Disable</option>
                                            <option value="Enable">Enable</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <div class="col-sm-offset-5 col-sm-6">
                                        <button type="button" id="stpGlobalApplyBtn" class="btn btn-primary" onclick="applyStpGlobal()">
                                            <span class="spinner-border" style="display:none;"></span> Apply
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <h4>Port State</h4>
            <div class="table-responsive">
                <table id="stpPortStateTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="90">Interface</th>
                            <th width="90">Role</th>
                            <th width="90">Status</th>
                            <th width="90">Cost</th>
                            <th width="90">Priority.Nbr</th>
                            <th width="90">Type</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Changing the protocol or timers can briefly stop traffic on ports while spanning tree re-converges, and can take a minute or more to apply.</li>
                    <li>Timers must satisfy 2 &times; (Forward Delay &minus; 1) &ge; Max Age &ge; 2 &times; (Hello Time + 1), or the switch may reject them.</li>
                    <li>Timer fields start from the current root bridge values. MSTP's Network Diameter, Max Hops and MST Compatibility are not reported by the switch, so they start at their defaults.</li>
                </ul>
            </div>
        </div>

        <!-- MST Region & Instances -->
        <div class="tab-pane" id="stp_mst_tab">
            <div class="panel panel-default">
                <div class="panel-body">
                    <strong>Region Name:</strong> <span id="stp_mst_region_name">-</span>
                    &nbsp;&nbsp; <strong>Revision:</strong> <span id="stp_mst_region_revision">-</span>
                    <span id="stp_mst_cache_note" class="text-muted" style="display:none; margin-left:10px;">last known data, refreshing...</span>
                </div>
            </div>
            <button class="btn btn-primary" id="btnAddStpMst">
                <i class="glyphicon glyphicon-plus"></i> Configure Instance
            </button>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="stpMstTable && stpMstTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="stpMstTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="100">Instance ID</th>
                            <th>VLANs</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>MST instances only take effect when the protocol is MSTP.</li>
                    <li>The switch does not report each instance's bridge priority or port costs, so Configure starts them at their defaults - set every value you want.</li>
                    <li>Every switch in the same MST region needs the same region name, revision and VLAN-to-instance mapping.</li>
                </ul>
            </div>
        </div>

        <!-- Port Configuration -->
        <div class="tab-pane" id="stp_port_tab">
            <span id="stp_port_cache_note" class="text-muted" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="stpPortTable && stpPortTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="stpPortTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="90">Port</th>
                            <th width="110">Protocol Status</th>
                            <th width="80">Priority</th>
                            <th width="90">Path Cost</th>
                            <th width="90">Edge Port</th>
                            <th width="90">RSTP Ring</th>
                            <th width="100">BPDU Filter</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Path Cost 0 means the switch calculates it automatically. Leave Path Cost blank to keep the current value.</li>
                    <li>Reset removes all STP port settings from the interface.</li>
                </ul>
            </div>
        </div>

        <!-- Port Guard -->
        <div class="tab-pane" id="stp_guard_tab">
            <span id="stp_guard_cache_note" class="text-muted" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="stpGuardTable && stpGuardTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="stpGuardTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="90">Port</th>
                            <th width="120">BPDU Guard</th>
                            <th width="120">Guard</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Port guard can only be set on GigaEthernet and TGigaEthernet ports.</li>
                    <li>Loop guard and root guard are mutually exclusive on a port.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Configure MST Instance Modal -->
<div id="stpMstModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="stp_mst_modal_title">Configure MST Instance</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Region Name*</label>
                        <div class="col-sm-5">
                            <input type="text" id="stp_mst_name" class="form-control" maxlength="32">
                            <span class="help-block">Letters, digits, <code>_ . -</code> only.</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Revision*</label>
                        <div class="col-sm-5">
                            <input type="number" id="stp_mst_revision" class="form-control" min="0" max="65535">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Instance ID*</label>
                        <div class="col-sm-5">
                            <input type="number" id="stp_mst_instance" class="form-control" min="0" max="15">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">VLANs*</label>
                        <div class="col-sm-5">
                            <input type="text" id="stp_mst_vlan" class="form-control" placeholder="e.g. 10,20-30">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Bridge Priority*</label>
                        <div class="col-sm-5">
                            <select id="stp_mst_priority" class="form-control">
                                @for ($priority = 0; $priority <= 61440; $priority += 4096)
                                    <option value="{{ $priority }}" @if ($priority === 32768) selected @endif>{{ $priority }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:5px;">
                        <label class="col-sm-3 control-label">Port Settings</label>
                        <div class="col-sm-9">
                            <button type="button" class="btn btn-xs btn-default" id="btnAddStpMstPort">
                                <i class="glyphicon glyphicon-plus"></i> Add Port
                            </button>
                            <span class="help-block" style="display:inline; margin-left:8px;">Optional per-port path cost and priority for this instance.</span>
                        </div>
                    </div>
                    <div id="stp_mst_ports"></div>

                    <div id="stp_mst_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveStpMstBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Port Configuration Modal -->
<div id="stpPortModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="stp_port_modal_title">Edit STP Port</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="stp_port_interface">
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Protocol Status*</label>
                        <div class="col-sm-7">
                            <select id="stp_port_protocol_status" class="form-control">
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Priority*</label>
                        <div class="col-sm-7">
                            <select id="stp_port_priority" class="form-control">
                                @for ($priority = 0; $priority <= 240; $priority += 16)
                                    <option value="{{ $priority }}" @if ($priority === 128) selected @endif>{{ $priority }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Path Cost</label>
                        <div class="col-sm-7">
                            <input type="number" id="stp_port_path_cost" class="form-control" min="1" max="65535" placeholder="blank = keep current">
                        </div>
                    </div>
                    @foreach (['edge_port' => 'Edge Port', 'rstp_ring' => 'RSTP Ring', 'bpdu_filter' => 'BPDU Filter'] as $field => $label)
                        <div class="form-group">
                            <label class="col-sm-5 control-label">{{ $label }}*</label>
                            <div class="col-sm-7">
                                <select id="stp_port_{{ $field }}" class="form-control">
                                    <option value="Disable">Disable</option>
                                    <option value="Enable">Enable</option>
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div id="stp_port_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveStpPortBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Port Guard Modal -->
<div id="stpGuardModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="stp_guard_modal_title">Edit Port Guard</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="stp_guard_interface">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">BPDU Guard*</label>
                        <div class="col-sm-8">
                            <select id="stp_guard_bpdu_guard" class="form-control">
                                <option value="Disable">Disable</option>
                                <option value="Enable">Enable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Guard*</label>
                        <div class="col-sm-8">
                            <select id="stp_guard_guard" class="form-control">
                                <option value="none">None</option>
                                <option value="loop">Loop Guard</option>
                                <option value="root">Root Guard</option>
                            </select>
                        </div>
                    </div>
                    <div id="stp_guard_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveStpGuardBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const STP_DEVICE_IP = "{{ $device->hostname }}";
    const STP_API_TOKEN = "{{ $data['api_token'] }}";
    const STP_API = "/api/v0/stp/";
    const STP_HEADERS = { "Authorization": "Bearer " + STP_API_TOKEN, "Accept": "application/json" };

    // Laravel returns 422 { message, errors: {field: [msg]} } on validation
    // failure - show the first field error rather than the generic message.
    function stpErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    function stpBusy(btn, busy) {
        $(btn).find('.spinner-border').toggle(busy);
        $(btn).prop('disabled', busy);
    }

    // POST to stp/<path>/set. onError receives the message; on success the
    // server's message is alerted and onDone runs.
    function stpPost(path, payload, btn, onDone, onError) {
        if (btn) stpBusy(btn, true);

        $.ajax({
            url: STP_API + path + "/" + STP_DEVICE_IP,
            method: "POST",
            headers: STP_HEADERS,
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                if (btn) stpBusy(btn, false);
                alert(res.message || "Saved");
                if (onDone) onDone(res);
            },
            error: function (xhr) {
                if (btn) stpBusy(btn, false);
                const message = stpErrorMessage(xhr, "Request failed");
                if (onError) onError(message); else alert(message);
            }
        });
    }

    function stpShowError(id, message) {
        const box = document.getElementById(id);
        box.textContent = message;
        box.style.display = 'block';
    }

    function stpText(id, value) {
        document.getElementById(id).textContent = (value === null || value === undefined || value === '') ? '-' : value;
    }

    const stpToggleLabel = data => data === 'Enable'
        ? '<span class="label label-success">Enable</span>'
        : (data ? '<span class="label label-default">' + data + '</span>' : '-');

    /* -----------------------------------------------------
       GLOBAL CONFIGURATION
    ----------------------------------------------------- */
    function showStpFields() {
        const mode = document.getElementById('stp_protocol').value;
        $('.stp-field').each(function () {
            $(this).toggle(String($(this).data('modes')).split(' ').indexOf(mode) !== -1);
        });
    }

    $('#stp_protocol').on('change', showStpFields);

    var stpPortStateTable = $('#stpPortStateTable').DataTable({
        data: [],
        columns: [
            { data: "interface" },
            { data: "role", render: data => data || '-' },
            { data: "status", render: data => data || '-' },
            { data: "cost", render: data => data ?? '-' },
            { data: "priority", render: data => data ?? '-' },
            { data: "type", render: data => data || '-' }
        ],
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No ports are taking part in spanning tree" }
    });

    function loadStpGlobal() {
        const loading = document.getElementById('stp_global_loading');
        const error = document.getElementById('stp_global_error');
        loading.style.display = 'inline';
        error.style.display = 'none';

        $.ajax({
            url: STP_API + "global/show/" + STP_DEVICE_IP,
            headers: STP_HEADERS,
            success: function (res) {
                loading.style.display = 'none';
                document.getElementById('stp_global_cache_note').style.display = res.cached ? 'inline' : 'none';

                const d = res.data || {};
                const root = d.root || {};

                stpText('stp_cur_protocol', d.protocol);
                stpText('stp_cur_bridge_mac', d.bridge_mac);
                stpText('stp_cur_root_priority', root.priority);
                stpText('stp_cur_root_mac', root.mac_address);
                stpText('stp_cur_timers', (root.hello_time ?? '-') + ' / ' + (root.max_age ?? '-') + ' / ' + (root.forward_delay ?? '-') + ' s');
                stpText('stp_cur_bpdu_terminal', d.bpdu_terminal);
                stpText('stp_cur_loop_guard', d.loop_guard);

                if (d.mode) document.getElementById('stp_protocol').value = d.mode;
                document.getElementById('stp_bpdu_terminal').value = d.bpdu_terminal === 'Enable' ? 'Enable' : 'Disable';
                document.getElementById('stp_loop_guard').value = d.loop_guard === 'Enable' ? 'Enable' : 'Disable';
                if (root.hello_time) document.getElementById('stp_hello_time').value = root.hello_time;
                if (root.max_age) document.getElementById('stp_max_age').value = root.max_age;
                if (root.forward_delay) document.getElementById('stp_forward_delay').value = root.forward_delay;
                // PVST adds the VLAN ID to the priority (e.g. 32868 = 32768 + 100),
                // so round down to the nearest selectable step of 4096.
                if (root.priority !== null && root.priority !== undefined && root.priority !== '') {
                    document.getElementById('stp_priority').value = Math.floor(Number(root.priority) / 4096) * 4096;
                }
                showStpFields();

                stpPortStateTable.options.data = d.ports || [];
                stpPortStateTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                loading.style.display = 'none';
                error.textContent = stpErrorMessage(xhr, "Failed to read spanning tree configuration from device");
                error.style.display = 'block';
            }
        });
    }

    function applyStpGlobal() {
        const mode = document.getElementById('stp_protocol').value;
        if (!confirm(`Apply ${mode}? Ports may briefly stop forwarding while spanning tree re-converges.`)) {
            return;
        }

        const payload = {
            protocol: mode,
            bpdu_terminal: document.getElementById('stp_bpdu_terminal').value,
            loop_guard: document.getElementById('stp_loop_guard').value
        };
        if (mode !== 'PVST') {
            payload.hello_time = document.getElementById('stp_hello_time').value;
            payload.max_age = document.getElementById('stp_max_age').value;
            payload.forward_delay = document.getElementById('stp_forward_delay').value;
        }
        if (mode === 'SSTP' || mode === 'RSTP') {
            payload.priority = document.getElementById('stp_priority').value;
        }
        if (mode === 'MSTP') {
            payload.network_diameter = document.getElementById('stp_network_diameter').value;
            payload.max_hops = document.getElementById('stp_max_hops').value;
            payload.mst_compatibility = document.getElementById('stp_mst_compatibility').value;
        }

        stpPost('global/set', payload, document.getElementById('stpGlobalApplyBtn'), loadStpGlobal);
    }

    /* -----------------------------------------------------
       MST REGION & INSTANCES / PORT CONFIGURATION / PORT GUARD
       Each table is created - and first loaded - when its tab is opened.
       Bootgrid stops row clicks from bubbling past <tbody>, and <tbody>
       only exists once the table is created, so row handlers are bound
       right after each table is created.
    ----------------------------------------------------- */
    var stpMstTable = null;
    var stpPortTable = null;
    var stpGuardTable = null;
    var stpMstRegion = { name: '', revision: '' };

    function initStpMstTable() {
        if (stpMstTable) return;

        stpMstTable = $('#stpMstTable').DataTable({
            ajax: {
                url: STP_API + "mst/show/" + STP_DEVICE_IP,
                type: "GET",
                headers: STP_HEADERS,
                dataSrc: function (json) {
                    const d = json.data || {};
                    stpMstRegion = { name: d.name || '', revision: d.revision ?? '' };
                    stpText('stp_mst_region_name', d.name);
                    stpText('stp_mst_region_revision', d.revision);
                    document.getElementById('stp_mst_cache_note').style.display = json.cached ? 'inline' : 'none';
                    return d.instances || [];
                }
            },
            columns: [
                { data: "instance_id" },
                { data: "vlan_ids", render: data => data || '-' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (row, type, full, meta) => `<button type="button" class="btn btn-xs btn-warning btn-edit-stp-mst" data-row="${meta.row}">Configure</button>`
                }
            ],
            order: [[0, "asc"]],
            lengthMenu: [10, 25, 50, 100],
            language: { emptyTable: "No MST instances found" }
        });

        $('#stpMstTable tbody').on('click', '.btn-edit-stp-mst', function () {
            openStpMstModal(stpMstTable.row($(this).data('row')).data());
        });
    }

    function addStpMstPortRow(port) {
        port = port || { interface_type: 'GigaEthernet', interface_id: '', cost: '', port_priority: 128 };
        const priorities = Array.from({ length: 16 }, (_, i) => i * 16)
            .map(p => `<option value="${p}" ${p === Number(port.port_priority) ? 'selected' : ''}>${p}</option>`).join('');
        const types = ['GigaEthernet', 'TGigaEthernet', 'Port-aggregator']
            .map(t => `<option value="${t}" ${t === port.interface_type ? 'selected' : ''}>${t}</option>`).join('');

        $('#stp_mst_ports').append(`
            <div class="form-group stp-port-row" style="margin-bottom:0;">
                <div class="col-sm-offset-3 col-sm-3"><select class="form-control stp-mst-port-type">${types}</select></div>
                <div class="col-sm-2"><input type="text" class="form-control stp-mst-port-id" placeholder="0/1" value="${port.interface_id}"></div>
                <div class="col-sm-2"><input type="number" class="form-control stp-mst-port-cost" placeholder="cost" min="1" max="200000000" value="${port.cost}"></div>
                <div class="col-sm-1" style="padding-left:0; padding-right:0;"><select class="form-control stp-mst-port-priority" title="port priority">${priorities}</select></div>
                <div class="col-sm-1"><button type="button" class="btn btn-sm btn-link text-danger btn-remove-stp-mst-port" title="Remove">&times;</button></div>
            </div>`);
    }

    $('#btnAddStpMstPort').on('click', () => addStpMstPortRow());
    $('#stp_mst_ports').on('click', '.btn-remove-stp-mst-port', function () {
        $(this).closest('.stp-port-row').remove();
    });

    function openStpMstModal(row) {
        document.getElementById('stp_mst_modal_title').textContent = row
            ? 'Configure MST Instance ' + row.instance_id
            : 'Configure MST Instance';
        document.getElementById('stp_mst_name').value = stpMstRegion.name;
        document.getElementById('stp_mst_revision').value = stpMstRegion.revision;
        document.getElementById('stp_mst_instance').value = row ? row.instance_id : '';
        document.getElementById('stp_mst_instance').readOnly = !!row;
        document.getElementById('stp_mst_vlan').value = row ? row.vlan_ids : '';
        document.getElementById('stp_mst_priority').value = 32768;
        $('#stp_mst_ports').empty();
        document.getElementById('stp_mst_error').style.display = 'none';
        $('#stpMstModal').modal('show');
    }

    $('#btnAddStpMst').on('click', () => openStpMstModal(null));

    $('#saveStpMstBtn').on('click', function () {
        document.getElementById('stp_mst_error').style.display = 'none';

        const ports = $('#stp_mst_ports .stp-port-row').map(function () {
            return {
                interface_type: $(this).find('.stp-mst-port-type').val(),
                interface_id: $(this).find('.stp-mst-port-id').val().trim(),
                cost: $(this).find('.stp-mst-port-cost').val(),
                port_priority: $(this).find('.stp-mst-port-priority').val()
            };
        }).get();

        stpPost('mst/set', {
            mst_name: document.getElementById('stp_mst_name').value.trim(),
            mst_revision: document.getElementById('stp_mst_revision').value,
            mst_instance: document.getElementById('stp_mst_instance').value,
            mst_vlan: document.getElementById('stp_mst_vlan').value.trim(),
            mst_priority: document.getElementById('stp_mst_priority').value,
            mst_ports: ports
        }, this, function () {
            $('#stpMstModal').modal('hide');
            stpMstTable.ajax.reload(null, false);
        }, message => stpShowError('stp_mst_error', message));
    });

    function initStpPortTable() {
        if (stpPortTable) return;

        stpPortTable = $('#stpPortTable').DataTable({
            ajax: {
                url: STP_API + "port/show/" + STP_DEVICE_IP,
                type: "GET",
                headers: STP_HEADERS,
                dataSrc: function (json) {
                    document.getElementById('stp_port_cache_note').style.display = json.cached ? "inline" : "none";
                    return json.data || [];
                }
            },
            columns: [
                { data: "port" },
                { data: "protocol_status", render: stpToggleLabel },
                { data: "priority", render: data => data ?? '-' },
                { data: "path_cost", render: data => String(data) === '0' ? '0 (auto)' : (data ?? '-') },
                { data: "edge_port", render: stpToggleLabel },
                { data: "rstp_ring", render: stpToggleLabel },
                { data: "bpdu_filter", render: stpToggleLabel },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (row, type, full, meta) => `
                        <button type="button" class="btn btn-xs btn-warning btn-edit-stp-port" data-row="${meta.row}">Edit</button>
                        <button type="button" class="btn btn-xs btn-danger btn-reset-stp-port" data-row="${meta.row}">Reset</button>`
                }
            ],
            order: [],
            lengthMenu: [10, 25, 50, 100],
            language: { emptyTable: "No interfaces found" }
        });

        $('#stpPortTable tbody').on('click', '.btn-edit-stp-port', function () {
            const row = stpPortTable.row($(this).data('row')).data();

            document.getElementById('stp_port_modal_title').textContent = 'Edit STP Port ' + row.port;
            document.getElementById('stp_port_interface').value = row.interface;
            document.getElementById('stp_port_protocol_status').value = row.protocol_status === 'Disable' ? 'Disable' : 'Enable';
            const priority = document.getElementById('stp_port_priority');
            priority.value = $(priority).find('option[value="' + row.priority + '"]').length ? row.priority : 128;
            document.getElementById('stp_port_path_cost').value = String(row.path_cost) === '0' ? '' : (row.path_cost ?? '');
            ['edge_port', 'rstp_ring', 'bpdu_filter'].forEach(function (field) {
                document.getElementById('stp_port_' + field).value = row[field] === 'Enable' ? 'Enable' : 'Disable';
            });
            document.getElementById('stp_port_error').style.display = 'none';
            $('#stpPortModal').modal('show');
        });

        $('#stpPortTable tbody').on('click', '.btn-reset-stp-port', function () {
            const row = stpPortTable.row($(this).data('row')).data();
            if (!confirm(`Reset all STP port settings on ${row.port}?`)) {
                return;
            }

            stpPost('port/set', { operation: 'delete', interface: row.interface }, null, function () {
                stpPortTable.ajax.reload(null, false);
            });
        });
    }

    $('#saveStpPortBtn').on('click', function () {
        document.getElementById('stp_port_error').style.display = 'none';

        stpPost('port/set', {
            operation: 'set',
            interface: document.getElementById('stp_port_interface').value,
            protocol_status: document.getElementById('stp_port_protocol_status').value,
            priority: document.getElementById('stp_port_priority').value,
            path_cost: document.getElementById('stp_port_path_cost').value,
            edge_port: document.getElementById('stp_port_edge_port').value,
            rstp_ring: document.getElementById('stp_port_rstp_ring').value,
            bpdu_filter: document.getElementById('stp_port_bpdu_filter').value
        }, this, function () {
            $('#stpPortModal').modal('hide');
            stpPortTable.ajax.reload(null, false);
        }, message => stpShowError('stp_port_error', message));
    });

    const STP_GUARD_PORT = /^(g|GigaEthernet|tg|TGigaEthernet)[0-9]+\/[0-9]+$/;

    function initStpGuardTable() {
        if (stpGuardTable) return;

        stpGuardTable = $('#stpGuardTable').DataTable({
            ajax: {
                url: STP_API + "portguard/show/" + STP_DEVICE_IP,
                type: "GET",
                headers: STP_HEADERS,
                dataSrc: function (json) {
                    document.getElementById('stp_guard_cache_note').style.display = json.cached ? "inline" : "none";
                    return json.data || [];
                }
            },
            columns: [
                { data: "port" },
                { data: "bpdu_guard", render: stpToggleLabel },
                { data: "guard", render: data => ({ loop: 'Loop Guard', root: 'Root Guard' })[data] || 'None' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (row, type, full, meta) => STP_GUARD_PORT.test(row.interface || '')
                        ? `<button type="button" class="btn btn-xs btn-warning btn-edit-stp-guard" data-row="${meta.row}">Edit</button>`
                        : '<span class="text-muted">not supported</span>'
                }
            ],
            order: [],
            lengthMenu: [10, 25, 50, 100],
            language: { emptyTable: "No interfaces found" }
        });

        $('#stpGuardTable tbody').on('click', '.btn-edit-stp-guard', function () {
            const row = stpGuardTable.row($(this).data('row')).data();

            document.getElementById('stp_guard_modal_title').textContent = 'Edit Port Guard ' + row.port;
            document.getElementById('stp_guard_interface').value = row.interface;
            document.getElementById('stp_guard_bpdu_guard').value = row.bpdu_guard === 'Enable' ? 'Enable' : 'Disable';
            document.getElementById('stp_guard_guard').value = ['loop', 'root'].indexOf(row.guard) !== -1 ? row.guard : 'none';
            document.getElementById('stp_guard_error').style.display = 'none';
            $('#stpGuardModal').modal('show');
        });
    }

    $('#saveStpGuardBtn').on('click', function () {
        document.getElementById('stp_guard_error').style.display = 'none';

        stpPost('portguard/set', {
            interface: document.getElementById('stp_guard_interface').value,
            bpdu_guard: document.getElementById('stp_guard_bpdu_guard').value,
            guard: document.getElementById('stp_guard_guard').value
        }, this, function () {
            $('#stpGuardModal').modal('hide');
            stpGuardTable.ajax.reload(null, false);
        }, message => stpShowError('stp_guard_error', message));
    });

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        if (target === '#stp_mst_tab') initStpMstTable();
        if (target === '#stp_port_tab') initStpPortTable();
        if (target === '#stp_guard_tab') initStpGuardTable();
    });

    showStpFields();
    loadStpGlobal();
</script>
