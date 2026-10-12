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

    .dataTables_wrapper thead th {
        white-space: nowrap;
    }

    .table-responsive {
        margin-top: 15px;
        border: none;
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
        // This device's GigaEthernet ports in the short form the playbooks
        // take (GigaEthernet0/1 -> g0/1). multiple_ring_protection_set/edit.yml
        // only accept g0/1 - g0/10, so anything outside that is left out.
        $meapsAllowed = array_map(fn ($n) => "g0/{$n}", range(1, 10));
        $meapsPorts = collect($data['interfaces'] ?? [])
            ->map(fn ($ifName) => preg_match('/^GigaEthernet(\d+\/\d+)$/i', $ifName, $m) ? 'g' . $m[1] : null)
            ->filter(fn ($port) => in_array($port, $meapsAllowed, true))
            ->unique()
            ->sortBy(fn ($port) => (int) substr($port, 3))
            ->values()
            ->prepend('none');
    @endphp

    <button class="btn btn-primary" id="btnAddMRing">
        <i class="glyphicon glyphicon-plus"></i> Add Ring
    </button>
    <span id="mring_cache_note" class="text-muted" style="display:none; margin-left:10px;">
        <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
    </span>
    <button type="button" class="btn btn-xs btn-default pull-right" onclick="mRingTable.ajax.reload(null, false)">
        <i class="fa fa-refresh"></i> Refresh
    </button>

    <div class="table-responsive">
        <table id="mRingTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
            <thead>
                <tr>
                    <th width="80">Domain ID</th>
                    <th width="70">Ring ID</th>
                    <th width="100">Ring Type</th>
                    <th width="110">Node Type</th>
                    <th width="100">Control VLAN</th>
                    <th width="70">Hello (s)</th>
                    <th width="70">Fail (s)</th>
                    <th width="100">Preforward (s)</th>
                    <th width="110">Primary Port</th>
                    <th width="110">Secondary Port</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
        </table>
    </div>

    <div class="alert alert-info" style="margin-top:20px;">
        <ul>
            <li>Domain ID range: 0-3, Ring ID range: 0-7.</li>
            <li>Ring Type, Node Type and Control VLAN cannot be changed once the ring is added - delete and re-add the ring to change them.</li>
            <li>Primary/Secondary Port list this device's ports supported for MEAPS (g0/1 - g0/10), or none.</li>
        </ul>
    </div>
</div>

<!-- Add/Edit Multiple Ring Modal -->
<div id="mRingModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="mring_modal_title">Add Multiple Ring</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="mring_operation">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Domain ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="mring_domain_id" class="form-control" min="0" max="3">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Ring ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="mring_ring_id" class="form-control" min="0" max="7">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Ring Type*</label>
                        <div class="col-sm-8">
                            <select id="mring_ring_type" class="form-control">
                                <option value="major ring">Major Ring</option>
                                <option value="sub ring">Sub Ring</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Node Type*</label>
                        <div class="col-sm-8">
                            <select id="mring_node_type" class="form-control">
                                <option value="master node">Master Node</option>
                                <option value="transit node">Transit Node</option>
                                <option value="edge node">Edge Node</option>
                                <option value="assistant node">Assistant Node</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Control VLAN*</label>
                        <div class="col-sm-8">
                            <input type="number" id="mring_control_vlan" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Hello Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="mring_hello_time" class="form-control" min="1" max="10" value="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Fail Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="mring_fail_time" class="form-control" min="3" max="30" value="3">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Pre-forward Time (s)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="mring_pre_forward_time" class="form-control" min="3" max="30" value="3">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Primary Port*</label>
                        <div class="col-sm-8">
                            <select id="mring_primary_port" class="form-control">
                                @foreach($meapsPorts as $port)
                                    <option value="{{ $port }}">{{ $port }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Secondary Port*</label>
                        <div class="col-sm-8">
                            <select id="mring_secondary_port" class="form-control">
                                @foreach($meapsPorts as $port)
                                    <option value="{{ $port }}">{{ $port }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div id="mring_edit_note" class="alert alert-info" style="display:none;">
                        Domain ID, Ring ID, Ring Type, Node Type and Control VLAN cannot be changed while editing.
                    </div>
                    <div id="mring_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveMRingBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const MR_DEVICE_IP = "{{ $device->hostname }}";
    const MR_API_TOKEN = "{{ $data['api_token'] }}";
    const MR_LOCKED_FIELDS = ['mring_domain_id', 'mring_ring_id', 'mring_ring_type', 'mring_node_type', 'mring_control_vlan'];

    var mRingTable = $('#mRingTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/multiplering/show/" + MR_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + MR_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('mring_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "domain_id", render: data => data ?? '-' },
            { data: "ring_id", render: data => data ?? '-' },
            { data: "ring_type", render: data => data || '-' },
            { data: "node_type", render: data => data || '-' },
            { data: "control_vlan", render: data => data ?? '-' },
            { data: "hello_time", render: data => data ?? '-' },
            { data: "fail_time", render: data => data ?? '-' },
            { data: "pre_forward_time", render: data => data ?? '-' },
            { data: "primary_port", render: data => data || '-' },
            { data: "secondary_port", render: data => data || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row, type, full, meta) {
                    return `
                        <button type="button" class="btn btn-xs btn-warning btn-edit-mring" data-row="${meta.row}">Edit</button>
                        <button type="button" class="btn btn-xs btn-danger btn-delete-mring" data-domain="${row.domain_id}" data-ring="${row.ring_id}">Delete</button>
                    `;
                }
            }
        ],
        order: [[0, "asc"], [1, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No Multiple Ring Protection rings found" }
    });

    // The switch may report ports as "None", "g0/1" or "GigaEthernet0/1" -
    // map them to the short form the Primary/Secondary <select> options use.
    function mRingPortValue(port) {
        if (!port) return 'none';

        const value = String(port).trim();
        const m = value.match(/^(?:g|GigaEthernet)(\d+\/\d+)$/i);
        const short = m ? 'g' + m[1] : value.toLowerCase();

        return $('#mring_primary_port option[value="' + short + '"]').length ? short : 'none';
    }

    // "Major Ring" -> "major ring", "Master-node" -> "master node"
    function mRingTypeValue(value) {
        return String(value || '').replace(/-/g, ' ').trim().toLowerCase();
    }

    function setMRingLocked(locked) {
        MR_LOCKED_FIELDS.forEach(id => {
            const el = document.getElementById(id);
            if (el.tagName === 'SELECT') el.disabled = locked;
            else el.readOnly = locked;
        });
        document.getElementById('mring_edit_note').style.display = locked ? 'block' : 'none';
    }

    $('#btnAddMRing').on('click', function () {
        document.getElementById('mring_modal_title').innerText = 'Add Multiple Ring';
        document.getElementById('mring_operation').value = 'add';
        document.getElementById('mring_domain_id').value = '';
        document.getElementById('mring_ring_id').value = '';
        document.getElementById('mring_ring_type').value = 'major ring';
        document.getElementById('mring_node_type').value = 'master node';
        document.getElementById('mring_control_vlan').value = '';
        document.getElementById('mring_hello_time').value = 1;
        document.getElementById('mring_fail_time').value = 3;
        document.getElementById('mring_pre_forward_time').value = 3;
        document.getElementById('mring_primary_port').value = 'none';
        document.getElementById('mring_secondary_port').value = 'none';
        setMRingLocked(false);
        document.getElementById('mring_error').style.display = 'none';
        $('#mRingModal').modal('show');
    });

    $('#mRingTable tbody').on('click', '.btn-edit-mring', function () {
        const row = mRingTable.row($(this).data('row')).data();

        document.getElementById('mring_modal_title').innerText =
            `Edit Multiple Ring (Domain ${row.domain_id}, Ring ${row.ring_id})`;
        document.getElementById('mring_operation').value = 'edit';
        document.getElementById('mring_domain_id').value = row.domain_id;
        document.getElementById('mring_ring_id').value = row.ring_id;

        const ringType = mRingTypeValue(row.ring_type);
        const nodeType = mRingTypeValue(row.node_type);
        if ($('#mring_ring_type option[value="' + ringType + '"]').length) {
            document.getElementById('mring_ring_type').value = ringType;
        }
        if ($('#mring_node_type option[value="' + nodeType + '"]').length) {
            document.getElementById('mring_node_type').value = nodeType;
        }

        document.getElementById('mring_control_vlan').value = row.control_vlan ?? '';
        document.getElementById('mring_hello_time').value = row.hello_time ?? 1;
        document.getElementById('mring_fail_time').value = row.fail_time ?? 3;
        document.getElementById('mring_pre_forward_time').value = row.pre_forward_time ?? 3;
        document.getElementById('mring_primary_port').value = mRingPortValue(row.primary_port);
        document.getElementById('mring_secondary_port').value = mRingPortValue(row.secondary_port);

        setMRingLocked(true);
        document.getElementById('mring_error').style.display = 'none';
        $('#mRingModal').modal('show');
    });

    $('#mRingTable tbody').on('click', '.btn-delete-mring', function () {
        const domainId = $(this).data('domain');
        const ringId = $(this).data('ring');
        if (!confirm(`Delete ring ${ringId} in domain ${domainId}?`)) {
            return;
        }

        $.ajax({
            url: "/api/v0/multiplering/set/" + MR_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + MR_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify({ operation: 'delete', domain_id: domainId, ring_id: ringId }),
            success: function (res) {
                alert(res.message || "Ring deleted");
                mRingTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || "Failed to delete ring");
            }
        });
    });

    $('#saveMRingBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('mring_error');
        errorBox.style.display = 'none';

        const payload = {
            operation: document.getElementById('mring_operation').value,
            domain_id: document.getElementById('mring_domain_id').value,
            ring_id: document.getElementById('mring_ring_id').value,
            ring_type: document.getElementById('mring_ring_type').value,
            node_type: document.getElementById('mring_node_type').value,
            control_vlan: document.getElementById('mring_control_vlan').value,
            hello_time: document.getElementById('mring_hello_time').value,
            fail_time: document.getElementById('mring_fail_time').value,
            pre_forward_time: document.getElementById('mring_pre_forward_time').value,
            primary_port: document.getElementById('mring_primary_port').value,
            secondary_port: document.getElementById('mring_secondary_port').value
        };

        if (payload.primary_port !== 'none' && payload.primary_port === payload.secondary_port) {
            errorBox.innerText = "Primary and Secondary Port must be different";
            errorBox.style.display = 'block';
            return;
        }

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/multiplering/set/" + MR_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + MR_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#mRingModal').modal('hide');
                alert(res.message || "Ring saved");
                mRingTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = xhr.responseJSON?.message || "Failed to save ring";
                errorBox.style.display = 'block';
            }
        });
    });
</script>
