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
</style>

<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">
<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    @php
        // Short-form port list for ring member ports - both playbooks
        // accept GigaEthernet/TGigaEthernet (ERPS also accepts full form,
        // Ethernet Ring is restricted to its own fixed device whitelist
        // handled separately below).
        $ringPorts = collect($data['interfaces'] ?? [])
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

        // setethernet.yml only accepts this exact whitelist (device-side
        // restriction, not derived from NMS) - g0/4 and g0/5 are
        // deliberately excluded by the playbook itself.
        $etherRingPorts = [
            'None',
            'GigaEthernet0/1', 'GigaEthernet0/2', 'GigaEthernet0/3',
            'GigaEthernet0/6', 'GigaEthernet0/7', 'GigaEthernet0/8',
            'TenGigabitEthernet0/1', 'TenGigabitEthernet0/2',
            'TenGigabitEthernet0/3', 'TenGigabitEthernet0/4',
            'p1',
        ];
    @endphp

    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li class="active"><a href="#etherring_tab" data-toggle="tab">Ethernet Ring</a></li>
        <li><a href="#erps_tab" data-toggle="tab">ERPS Configuration</a></li>
        
    </ul>

    <div class="tab-content" style="margin-top:15px;">

        <!-- ERPS -->
        <div class="tab-pane active" id="erps_tab">
            <button class="btn btn-primary" id="btnAddErps">
                <i class="glyphicon glyphicon-plus"></i> Add Ring
            </button>
            <span id="erps_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="erpsTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="erpsTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="80">Ring ID</th>
                            <th width="100">Control VLAN</th>
                            <th width="100">Status</th>
                            <th width="80">WTR (s)</th>
                            <th width="90">Guard (ms)</th>
                            <th width="80">Send (s)</th>
                            <th width="150">Port 1</th>
                            <th width="150">Port 2</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Ring ID range: 1-239.</li>
                    <li>Editing a ring only changes Control VLAN/WTR/Guard/Send time - port assignment cannot be changed once the ring is added, only removed by deleting the ring.</li>
                </ul>
            </div>
        </div>

        <!-- Ethernet Ring (EAPS) -->
        <div class="tab-pane" id="etherring_tab">
            <button class="btn btn-primary" id="btnAddEtherRing">
                <i class="glyphicon glyphicon-plus"></i> Add Ring
            </button>
            <span id="etherring_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="etherRingTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="etherRingTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="80">Ring ID</th>
                            <th width="110">Node Type</th>
                            <th width="130">Description</th>
                            <th width="100">Control VLAN</th>
                            <th width="100">Status</th>
                            <th width="70">Hello</th>
                            <th width="70">Fail</th>
                            <th width="90">Preforward</th>
                            <th width="150">Primary</th>
                            <th width="150">Secondary</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Ring ID range: 1-32.</li>
                    <li>Primary/Secondary Port are limited to this device's supported ring ports.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Add/Edit ERPS Modal -->
<div id="erpsModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="erps_modal_title">Add ERPS Ring</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="erps_operation">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Ring ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="erps_ring_id" class="form-control" min="1" max="239">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Control VLAN*</label>
                        <div class="col-sm-8">
                            <input type="number" id="erps_control_vlan" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">WTR Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="erps_wtr_time" class="form-control" min="0" max="720" value="20">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Guard Time (ms)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="erps_guard_time" class="form-control" min="0" max="2000" step="10" value="500">
                            <span class="help-block">Must be a multiple of 10.</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Send Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="erps_send_time" class="form-control" min="1" max="10" value="5">
                        </div>
                    </div>
                    <div id="erps_port_fields">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Port 1*</label>
                            <div class="col-sm-4">
                                <select id="erps_port1" class="form-control">
                                    @foreach($ringPorts as $port)
                                        <option value="{{ $port }}">{{ $port }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <select id="erps_port1_role" class="form-control">
                                    <option value="ring-port">ring-port</option>
                                    <option value="rpl">rpl</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Port 2*</label>
                            <div class="col-sm-4">
                                <select id="erps_port2" class="form-control">
                                    @foreach($ringPorts as $port)
                                        <option value="{{ $port }}">{{ $port }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <select id="erps_port2_role" class="form-control">
                                    <option value="rpl">rpl</option>
                                    <option value="ring-port">ring-port</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-muted">One port must be "ring-port" and the other "rpl".</p>
                    </div>
                    <div id="erps_edit_note" class="alert alert-info" style="display:none;">
                        Port 1/Port 2 above show this ring's current assignment for reference and cannot be changed while editing - delete and re-add the ring to change ports.
                    </div>
                    <div id="erps_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveErpsBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Ethernet Ring Modal -->
<div id="etherRingModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="etherring_modal_title">Add Ethernet Ring</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="etherring_operation">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Ring ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="etherring_ring_id" class="form-control" min="1" max="32">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Node Type*</label>
                        <div class="col-sm-8">
                            <select id="etherring_node_type" class="form-control">
                                <option value="Master Node">Master Node</option>
                                <option value="Transit Node">Transit Node</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Ring Description</label>
                        <div class="col-sm-8">
                            <input type="text" id="etherring_ring_description" class="form-control" maxlength="64">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Control VLAN*</label>
                        <div class="col-sm-8">
                            <input type="number" id="etherring_control_vlan" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Hello Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="etherring_hello_time" class="form-control" min="1" max="10" value="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Fail Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="etherring_fail_time" class="form-control" min="3" max="30" value="3">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Pre-forward Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="etherring_pre_forward_time" class="form-control" min="3" max="30" value="3">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Primary Port*</label>
                        <div class="col-sm-8">
                            <select id="etherring_primary_port" class="form-control">
                                @foreach($etherRingPorts as $port)
                                    <option value="{{ $port }}">{{ $port }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Secondary Port*</label>
                        <div class="col-sm-8">
                            <select id="etherring_secondary_port" class="form-control">
                                @foreach($etherRingPorts as $port)
                                    <option value="{{ $port }}">{{ $port }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div id="etherring_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveEtherRingBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const RP_DEVICE_IP = "{{ $device->hostname }}";
    const RP_API_TOKEN = "{{ $data['api_token'] }}";

    /* -----------------------------------------------------
       ERPS
    ----------------------------------------------------- */
    var erpsTable = $('#erpsTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/erps/show/" + RP_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + RP_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('erps_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "ring_id" },
            { data: "control_vlan" },
            { data: "ring_status", render: data => data || '-' },
            { data: "wtr_time", render: data => data ?? '-' },
            { data: "guard_time", render: data => data ?? '-' },
            { data: "send_time", render: data => data ?? '-' },
            { data: null, render: row => row.port1 ? `${row.port1} (${row.port1_role || ''})` : '-' },
            { data: null, render: row => row.port2 ? `${row.port2} (${row.port2_role || ''})` : '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    return `
                        <button type="button" class="btn btn-xs btn-warning btn-edit-erps" data-ring="${row.ring_id}" data-vlan="${row.control_vlan}" data-wtr="${row.wtr_time ?? ''}" data-guard="${row.guard_time ?? ''}" data-send="${row.send_time ?? ''}" data-port1="${row.port1 || ''}" data-port1role="${row.port1_role || ''}" data-port2="${row.port2 || ''}" data-port2role="${row.port2_role || ''}">Edit</button>
                        <button type="button" class="btn btn-xs btn-danger btn-delete-erps" data-ring="${row.ring_id}">Delete</button>
                    `;
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No ERPS rings found" }
    });

    $('#btnAddErps').on('click', function () {
        document.getElementById('erps_modal_title').innerText = 'Add ERPS Ring';
        document.getElementById('erps_operation').value = 'add';
        document.getElementById('erps_ring_id').value = '';
        document.getElementById('erps_ring_id').readOnly = false;
        document.getElementById('erps_control_vlan').value = '';
        document.getElementById('erps_wtr_time').value = 20;
        document.getElementById('erps_guard_time').value = 500;
        document.getElementById('erps_send_time').value = 5;
        document.getElementById('erps_port_fields').style.display = '';
        $('#erps_port_fields select').prop('disabled', false);
        document.getElementById('erps_edit_note').style.display = 'none';
        document.getElementById('erps_error').style.display = 'none';
        $('#erpsModal').modal('show');
    });

    $('#erpsTable tbody').on('click', '.btn-edit-erps', function () {
        document.getElementById('erps_modal_title').innerText = 'Edit ERPS Ring ' + $(this).data('ring');
        document.getElementById('erps_operation').value = 'edit';
        document.getElementById('erps_ring_id').value = $(this).data('ring');
        document.getElementById('erps_ring_id').readOnly = true;
        document.getElementById('erps_control_vlan').value = $(this).data('vlan');
        document.getElementById('erps_wtr_time').value = $(this).data('wtr');
        document.getElementById('erps_guard_time').value = $(this).data('guard');
        document.getElementById('erps_send_time').value = $(this).data('send');

        // seterps.yml's EDIT operation never touches port configuration
        // (existing ports remain unchanged), so these are shown for
        // reference only, prefilled with the ring's current assignment,
        // and disabled so they're never mistaken for an editable field.
        const port1 = $(this).data('port1');
        const port1Role = String($(this).data('port1role') || '').toLowerCase();
        const port2 = $(this).data('port2');
        const port2Role = String($(this).data('port2role') || '').toLowerCase();

        if (port1 && $('#erps_port1 option[value="' + port1 + '"]').length) {
            document.getElementById('erps_port1').value = port1;
        }
        if (port1Role === 'ring-port' || port1Role === 'rpl') {
            document.getElementById('erps_port1_role').value = port1Role;
        }
        if (port2 && $('#erps_port2 option[value="' + port2 + '"]').length) {
            document.getElementById('erps_port2').value = port2;
        }
        if (port2Role === 'ring-port' || port2Role === 'rpl') {
            document.getElementById('erps_port2_role').value = port2Role;
        }

        document.getElementById('erps_port_fields').style.display = '';
        $('#erps_port_fields select').prop('disabled', true);
        document.getElementById('erps_edit_note').style.display = 'block';
        document.getElementById('erps_error').style.display = 'none';
        $('#erpsModal').modal('show');
    });

    $('#erpsTable tbody').on('click', '.btn-delete-erps', function () {
        const ringId = $(this).data('ring');
        if (!confirm(`Delete ERPS ring ${ringId}?`)) {
            return;
        }

        $.ajax({
            url: "/api/v0/erps/set/" + RP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + RP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify({ operation: 'delete', ring_id: ringId }),
            success: function (res) {
                alert(res.message || "ERPS ring deleted");
                erpsTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || "Failed to delete ERPS ring");
            }
        });
    });

    $('#saveErpsBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('erps_error');
        errorBox.style.display = 'none';

        const operation = document.getElementById('erps_operation').value;
        const payload = {
            operation: operation,
            ring_id: document.getElementById('erps_ring_id').value,
            control_vlan: document.getElementById('erps_control_vlan').value,
            wtr_time: document.getElementById('erps_wtr_time').value,
            guard_time: document.getElementById('erps_guard_time').value,
            send_time: document.getElementById('erps_send_time').value
        };

        if (operation === 'add') {
            const port1 = document.getElementById('erps_port1').value;
            const port2 = document.getElementById('erps_port2').value;
            const port1Role = document.getElementById('erps_port1_role').value;
            const port2Role = document.getElementById('erps_port2_role').value;

            if (port1 === port2) {
                errorBox.innerText = "Port 1 and Port 2 must be different";
                errorBox.style.display = 'block';
                return;
            }
            if (port1Role === port2Role) {
                errorBox.innerText = "One port must be ring-port and the other rpl";
                errorBox.style.display = 'block';
                return;
            }

            payload.port1 = port1;
            payload.port1_role = port1Role;
            payload.port2 = port2;
            payload.port2_role = port2Role;
        }

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/erps/set/" + RP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + RP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#erpsModal').modal('hide');
                alert(res.message || "ERPS ring saved");
                erpsTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to save ERPS ring";
                errorBox.style.display = 'block';
            }
        });
    });

    /* -----------------------------------------------------
       ETHERNET RING (EAPS)
    ----------------------------------------------------- */
    var etherRingTable = $('#etherRingTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/etherring/show/" + RP_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + RP_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('etherring_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "ring_id" },
            { data: "node_type", render: data => data || '-' },
            { data: "ring_description", render: data => data || '-' },
            { data: "control_vlan", render: data => data || '-' },
            { data: "status", render: data => data || '-' },
            { data: "hello", render: data => data || '-' },
            { data: "fail", render: data => data || '-' },
            { data: "preforward", render: data => data || '-' },
            { data: "primary", render: data => data || '-' },
            { data: "secondary", render: data => data || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    return `
                        <button type="button" class="btn btn-xs btn-warning btn-edit-etherring"
                            data-ring="${row.ring_id}"
                            data-node="${row.node_type || ''}"
                            data-desc="${row.ring_description || ''}"
                            data-vlan="${row.control_vlan || ''}"
                            data-hello="${row.hello || ''}"
                            data-fail="${row.fail || ''}"
                            data-preforward="${row.preforward || ''}"
                            data-primary="${row.primary || ''}"
                            data-secondary="${row.secondary || ''}">Edit</button>
                        <button type="button" class="btn btn-xs btn-danger btn-delete-etherring" data-ring="${row.ring_id}">Delete</button>
                    `;
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No Ethernet Rings found" }
    });

    // getethernet.yml's "primary"/"secondary" fields are a composite
    // string like "g0/1 / BLK / Enabled, Link-Down" (or "-" when unset) -
    // pull out just the short port name and map it back to the full
    // interface name the Primary/Secondary Port <select> options use.
    function etherRingPortNameFromRow(composite) {
        if (!composite || composite === '-') {
            return 'None';
        }

        const short = composite.split(' / ')[0].trim();

        let m = short.match(/^g(\d+\/\d+)$/i);
        if (m) return 'GigaEthernet' + m[1];

        m = short.match(/^tg(\d+\/\d+)$/i);
        if (m) return 'TenGigabitEthernet' + m[1];

        if (short.toLowerCase() === 'p1') return 'p1';

        return 'None';
    }

    $('#btnAddEtherRing').on('click', function () {
        document.getElementById('etherring_modal_title').innerText = 'Add Ethernet Ring';
        document.getElementById('etherring_operation').value = 'add';
        document.getElementById('etherring_ring_id').value = '';
        document.getElementById('etherring_ring_id').readOnly = false;
        document.getElementById('etherring_node_type').value = 'Master Node';
        document.getElementById('etherring_ring_description').value = '';
        document.getElementById('etherring_control_vlan').value = '';
        document.getElementById('etherring_hello_time').value = 1;
        document.getElementById('etherring_fail_time').value = 3;
        document.getElementById('etherring_pre_forward_time').value = 3;
        document.getElementById('etherring_primary_port').value = 'None';
        document.getElementById('etherring_secondary_port').value = 'None';
        document.getElementById('etherring_error').style.display = 'none';
        $('#etherRingModal').modal('show');
    });

    $('#etherRingTable tbody').on('click', '.btn-edit-etherring', function () {
        document.getElementById('etherring_modal_title').innerText = 'Edit Ethernet Ring ' + $(this).data('ring');
        document.getElementById('etherring_operation').value = 'edit';
        document.getElementById('etherring_ring_id').value = $(this).data('ring');
        document.getElementById('etherring_ring_id').readOnly = true;

        const nodeType = $(this).data('node');
        document.getElementById('etherring_node_type').value =
            (nodeType && nodeType.toLowerCase().indexOf('transit') !== -1) ? 'Transit Node' : 'Master Node';

        document.getElementById('etherring_ring_description').value = $(this).data('desc') || '';
        document.getElementById('etherring_control_vlan').value = $(this).data('vlan') || '';
        document.getElementById('etherring_hello_time').value = $(this).data('hello') || 1;
        document.getElementById('etherring_fail_time').value = $(this).data('fail') || 3;
        document.getElementById('etherring_pre_forward_time').value = $(this).data('preforward') || 3;

        const primaryValue = etherRingPortNameFromRow($(this).data('primary'));
        const secondaryValue = etherRingPortNameFromRow($(this).data('secondary'));
        // Fall back to 'None' if the current port isn't one of this
        // select's known options (e.g. a port outside the fixed whitelist).
        document.getElementById('etherring_primary_port').value =
            $('#etherring_primary_port option[value="' + primaryValue + '"]').length ? primaryValue : 'None';
        document.getElementById('etherring_secondary_port').value =
            $('#etherring_secondary_port option[value="' + secondaryValue + '"]').length ? secondaryValue : 'None';

        document.getElementById('etherring_error').style.display = 'none';
        $('#etherRingModal').modal('show');
    });

    $('#etherRingTable tbody').on('click', '.btn-delete-etherring', function () {
        const ringId = $(this).data('ring');
        if (!confirm(`Delete Ethernet Ring ${ringId}?`)) {
            return;
        }

        $.ajax({
            url: "/api/v0/etherring/set/" + RP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + RP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify({ operation: 'delete', ring_id: ringId }),
            success: function (res) {
                alert(res.message || "Ethernet Ring deleted");
                etherRingTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || "Failed to delete Ethernet Ring");
            }
        });
    });

    $('#saveEtherRingBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('etherring_error');
        errorBox.style.display = 'none';

        const operation = document.getElementById('etherring_operation').value;
        const payload = {
            operation: operation,
            ring_id: document.getElementById('etherring_ring_id').value,
            node_type: document.getElementById('etherring_node_type').value,
            ring_description: document.getElementById('etherring_ring_description').value,
            control_vlan: document.getElementById('etherring_control_vlan').value,
            hello_time: document.getElementById('etherring_hello_time').value,
            fail_time: document.getElementById('etherring_fail_time').value,
            pre_forward_time: document.getElementById('etherring_pre_forward_time').value
        };

        const primaryPort = document.getElementById('etherring_primary_port').value;
        const secondaryPort = document.getElementById('etherring_secondary_port').value;
        if (operation === 'add' || primaryPort !== 'None') payload.primary_port = primaryPort;
        if (operation === 'add' || secondaryPort !== 'None') payload.secondary_port = secondaryPort;

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/etherring/set/" + RP_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + RP_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#etherRingModal').modal('hide');
                alert(res.message || "Ethernet Ring saved");
                etherRingTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to save Ethernet Ring";
                errorBox.style.display = 'block';
            }
        });
    });

    /* -----------------------------------------------------
       TAB SWITCH - fix DataTable column widths
    ----------------------------------------------------- */
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        if (target === '#etherring_tab') etherRingTable.columns.adjust();
        if (target === '#erps_tab') erpsTable.columns.adjust();
    });
</script>
