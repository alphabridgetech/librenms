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

@include('device.tabs.datatablepath.offline-datatable')

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li style="display:none;"><a href="#pvlan_tab" data-toggle="tab">Private VLAN</a></li>
        <li class="active"><a href="#pvlan_port_tab" data-toggle="tab">Private VLAN Port</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">

        <!-- Private VLAN -->
        <div class="tab-pane" id="pvlan_tab" style="display:none;">
            <button class="btn btn-primary" id="btnAddPvlan">
                <i class="glyphicon glyphicon-plus"></i> Add Private VLAN
            </button>
            <span id="pvlan_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="pvlanTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="pvlanTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="100">VLAN ID</th>
                            <th width="160">VLAN Type</th>
                            <th width="180">Associated Primary VLAN</th>
                            <th width="90">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>VLAN ID range: 1-4094.</li>
                    <li>Community and Isolated VLANs must be associated with a Primary VLAN.</li>
                    <li>Delete removes the whole VLAN from the switch ("no vlan &lt;id&gt;"), not just its Private VLAN type.</li>
                </ul>
            </div>
        </div>

        <!-- Private VLAN Port -->
        <div class="tab-pane active" id="pvlan_port_tab">
            <span id="pvlan_port_cache_note" class="text-muted" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="pvlanPortTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="pvlanPortTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="80">Port</th>
                            <th width="70">VLAN</th>
                            <th width="140">Port Mode</th>
                            <th width="100">Primary VLAN</th>
                            <th width="110">Secondary VLAN</th>
                            <th width="140">Outgoing Segment Tag</th>
                            <th width="80">Tag PVID</th>
                            <th width="90">Tag Priority</th>
                            <th width="70">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Host and Promiscuous mode require both a Primary and a Secondary VLAN.</li>
                    <li>Tag PVID range: 1-4094, Tag Priority range: 0-7.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Add Private VLAN Modal -->
<div id="pvlanModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Private VLAN</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="pvlan_vlan_id" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN Type*</label>
                        <div class="col-sm-8">
                            <select id="pvlan_vlan_type" class="form-control">
                                <option value="primary">Primary VLAN</option>
                                <option value="community">Community VLAN</option>
                                <option value="isolated">Isolated VLAN</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="pvlan_assoc_group" style="display:none;">
                        <label class="col-sm-4 control-label">Associated Primary VLAN*</label>
                        <div class="col-sm-8">
                            <input type="number" id="pvlan_associated_primary_vlan" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div id="pvlan_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="savePvlanBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Private VLAN Port Modal -->
<div id="pvlanPortModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="pvlan_port_modal_title">Edit Private VLAN Port</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="pvlan_port_name">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN (PVID)*</label>
                        <div class="col-sm-8">
                            <input type="number" id="pvlan_port_vlan" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Port Mode*</label>
                        <div class="col-sm-8">
                            <select id="pvlan_port_mode" class="form-control">
                                <option value="regular">Regular Port</option>
                                <option value="host">Host Port</option>
                                <option value="promiscuous">Promiscuous Port</option>
                            </select>
                        </div>
                    </div>
                    <div id="pvlan_port_assoc_fields" style="display:none;">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Primary VLAN*</label>
                            <div class="col-sm-8">
                                <input type="number" id="pvlan_port_primary_vlan" class="form-control" min="1" max="4094">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Secondary VLAN*</label>
                            <div class="col-sm-8">
                                <input type="number" id="pvlan_port_secondary_vlan" class="form-control" min="1" max="4094">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Outgoing Segment Tag*</label>
                        <div class="col-sm-8">
                            <select id="pvlan_port_outgoing_tag" class="form-control">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Tag PVID</label>
                        <div class="col-sm-8">
                            <input type="number" id="pvlan_port_tag_pvid" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Tag Priority</label>
                        <div class="col-sm-8">
                            <input type="number" id="pvlan_port_tag_priority" class="form-control" min="0" max="7">
                        </div>
                    </div>
                    <div id="pvlan_port_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="savePvlanPortBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const PV_DEVICE_IP = "{{ $device->hostname }}";
    const PV_API_TOKEN = "{{ $data['api_token'] }}";

    // Laravel returns 422 { message, errors: {field: [msg]} } on validation
    // failure - show the first field error rather than the generic message.
    function pvlanErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    /* -----------------------------------------------------
       PRIVATE VLAN
    ----------------------------------------------------- */
    var pvlanTable = $('#pvlanTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/privatevlan/show/" + PV_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + PV_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('pvlan_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "vlan_id" },
            { data: "vlan_type", render: data => data || '-' },
            { data: "associated_primary_vlan", render: data => data || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    return `<button type="button" class="btn btn-xs btn-danger btn-delete-pvlan" data-vlan="${row.vlan_id}">Delete</button>`;
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No Private VLANs found" }
    });

    function togglePvlanAssoc() {
        document.getElementById('pvlan_assoc_group').style.display =
            document.getElementById('pvlan_vlan_type').value === 'primary' ? 'none' : '';
    }

    $('#pvlan_vlan_type').on('change', togglePvlanAssoc);

    $('#btnAddPvlan').on('click', function () {
        document.getElementById('pvlan_vlan_id').value = '';
        document.getElementById('pvlan_vlan_type').value = 'primary';
        document.getElementById('pvlan_associated_primary_vlan').value = '';
        togglePvlanAssoc();
        document.getElementById('pvlan_error').style.display = 'none';
        $('#pvlanModal').modal('show');
    });

    $('#pvlanTable tbody').on('click', '.btn-delete-pvlan', function () {
        const vlanId = $(this).data('vlan');
        if (!confirm(`Delete VLAN ${vlanId}? This removes the whole VLAN from the switch.`)) {
            return;
        }

        $.ajax({
            url: "/api/v0/privatevlan/set/" + PV_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + PV_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify({ operation: 'delete', vlan_id: vlanId }),
            success: function (res) {
                alert(res.message || "Private VLAN deleted");
                pvlanTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                alert(pvlanErrorMessage(xhr, "Failed to delete Private VLAN"));
                pvlanTable.ajax.reload(null, false);
            }
        });
    });

    $('#savePvlanBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('pvlan_error');
        errorBox.style.display = 'none';

        const payload = {
            operation: 'add',
            vlan_id: document.getElementById('pvlan_vlan_id').value,
            vlan_type: document.getElementById('pvlan_vlan_type').value
        };
        if (payload.vlan_type !== 'primary') {
            payload.associated_primary_vlan = document.getElementById('pvlan_associated_primary_vlan').value;
        }

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/privatevlan/set/" + PV_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + PV_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#pvlanModal').modal('hide');
                alert(res.message || "Private VLAN saved");
                pvlanTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = pvlanErrorMessage(xhr, "Failed to save Private VLAN");
                errorBox.style.display = 'block';
            }
        });
    });

    /* -----------------------------------------------------
       PRIVATE VLAN PORT
    ----------------------------------------------------- */
    var pvlanPortTable = $('#pvlanPortTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/privatevlan/port/show/" + PV_DEVICE_IP,
            type: "GET",
            headers: { "Authorization": "Bearer " + PV_API_TOKEN, "Accept": "application/json" },
            dataSrc: function (json) {
                document.getElementById('pvlan_port_cache_note').style.display = json.cached ? "inline" : "none";
                return json.entries || [];
            }
        },
        columns: [
            { data: "port" },
            { data: "vlan", render: data => data ?? '-' },
            { data: "private_vlan_port_mode", render: data => data || '-' },
            { data: "primary_vlan", render: data => data || '-' },
            { data: "secondary_vlan", render: data => data || '-' },
            { data: "outgoing_segment_tag", render: data => data || '-' },
            { data: "tag_pvid", render: data => data ?? '-' },
            { data: "tag_priority", render: data => data ?? '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row, type, full, meta) {
                    return `<button type="button" class="btn btn-xs btn-warning btn-edit-pvlan-port" data-row="${meta.row}">Edit</button>`;
                }
            }
        ],
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No ports found" }
    });

    function togglePvlanPortAssoc() {
        document.getElementById('pvlan_port_assoc_fields').style.display =
            document.getElementById('pvlan_port_mode').value === 'regular' ? 'none' : '';
    }

    $('#pvlan_port_mode').on('change', togglePvlanPortAssoc);

    // "Host Port" -> host, "Promiscuous Port" -> promiscuous, else regular
    function pvlanPortModeValue(mode) {
        const value = String(mode || '').toLowerCase();
        if (value.indexOf('host') !== -1) return 'host';
        if (value.indexOf('promiscuous') !== -1) return 'promiscuous';
        return 'regular';
    }

    $('#pvlanPortTable tbody').on('click', '.btn-edit-pvlan-port', function () {
        const row = pvlanPortTable.row($(this).data('row')).data();

        document.getElementById('pvlan_port_modal_title').innerText = 'Edit Private VLAN Port ' + row.port;
        document.getElementById('pvlan_port_name').value = row.port;
        document.getElementById('pvlan_port_vlan').value = row.vlan ?? '';
        document.getElementById('pvlan_port_mode').value = pvlanPortModeValue(row.private_vlan_port_mode);
        document.getElementById('pvlan_port_primary_vlan').value = row.primary_vlan || '';
        document.getElementById('pvlan_port_secondary_vlan').value = row.secondary_vlan || '';
        document.getElementById('pvlan_port_outgoing_tag').value = row.outgoing_segment_tag === 'Yes' ? 'Yes' : 'No';
        document.getElementById('pvlan_port_tag_pvid').value = row.tag_pvid ?? '';
        document.getElementById('pvlan_port_tag_priority').value = row.tag_priority ?? '';
        togglePvlanPortAssoc();
        document.getElementById('pvlan_port_error').style.display = 'none';
        $('#pvlanPortModal').modal('show');
    });

    $('#savePvlanPortBtn').on('click', function () {
        const btn = this;
        const errorBox = document.getElementById('pvlan_port_error');
        errorBox.style.display = 'none';

        const payload = {
            port: document.getElementById('pvlan_port_name').value,
            port_vlan: document.getElementById('pvlan_port_vlan').value,
            private_vlan_port_mode: document.getElementById('pvlan_port_mode').value,
            outgoing_segment_tag: document.getElementById('pvlan_port_outgoing_tag').value,
            tag_pvid: document.getElementById('pvlan_port_tag_pvid').value,
            tag_priority: document.getElementById('pvlan_port_tag_priority').value
        };
        if (payload.private_vlan_port_mode !== 'regular') {
            payload.primary_vlan = document.getElementById('pvlan_port_primary_vlan').value;
            payload.secondary_vlan = document.getElementById('pvlan_port_secondary_vlan').value;
        }

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        $.ajax({
            url: "/api/v0/privatevlan/port/set/" + PV_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + PV_API_TOKEN, "Accept": "application/json" },
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                $('#pvlanPortModal').modal('hide');
                alert(res.message || "Private VLAN port saved");
                pvlanPortTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
                errorBox.innerText = pvlanErrorMessage(xhr, "Failed to save Private VLAN port");
                errorBox.style.display = 'block';
            }
        });
    });

    /* -----------------------------------------------------
       TAB SWITCH - fix DataTable column widths
    ----------------------------------------------------- */
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        if (target === '#pvlan_tab') pvlanTable.columns.adjust();
        if (target === '#pvlan_port_tab') pvlanPortTable.columns.adjust();
    });
</script>
