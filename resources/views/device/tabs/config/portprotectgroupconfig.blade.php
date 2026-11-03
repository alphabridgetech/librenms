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

    .pp-toolbar {
        margin-bottom: 10px;
    }

    .pp-batch {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
    }

    .pp-batch label {
        font-weight: normal;
        margin: 0;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')

<div id="pp_load_error" class="alert alert-danger" style="display:none;"></div>

<ul class="nav nav-tabs">
    <li class="active"><a href="#pp_group_tab" data-toggle="tab">Port Protect Group List</a></li>
    <li><a href="#pp_interface_tab" data-toggle="tab">Port Protect Group Interface Configuration</a></li>
</ul>

<div class="tab-content" style="margin-top: 15px;">
    <!-- Port Protect Group List -->
    <div class="tab-pane active" id="pp_group_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Port Protect Group List</strong>
                <span class="pp-cache-note text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortProtect()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                <div class="pp-toolbar">
                    <button type="button" class="btn btn-sm btn-primary" id="ppOpenAddBtn">
                        <i class="fa fa-plus"></i> Add
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="ppGroupTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th>Port Protect Group</th>
                                <th>Member Ports</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <div class="pp-batch">
                    <label><input type="checkbox" id="pp_select_all"> Select All/Select None</label>
                    <button type="button" id="ppBatchDeleteBtn" class="btn btn-sm btn-danger">
                        <span class="spinner-border" style="display:none;"></span> Batch Delete
                    </button>
                </div>
                <div id="pp_group_error" class="text-danger" style="display:none; margin-top:8px;"></div>
            </div>
            <div class="panel-footer">
                Help: a Port Protect Group (1-28) isolates its member ports from each other - they can't send traffic to one another, but can still reach ports outside the group. Create the group here, then add ports to it on the Interface Configuration tab.
            </div>
        </div>
    </div>

    <!-- Port Protect Group Interface Configuration -->
    <div class="tab-pane" id="pp_interface_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Port Protect Group Interface Configuration</strong>
                <span class="pp-cache-note text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortProtect()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table id="ppPortTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Interface</th>
                                <th>Port Protect Group</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                Help: a port can be in one group at a time - choosing another group moves it. Only groups created on the Group List tab can be chosen.
            </div>
        </div>
    </div>
</div>

<!-- Add Group Modal -->
<div id="addPortProtectGroupModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Port Protect Group</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Port Protect Group</label>
                        <div class="col-sm-8">
                            <input type="number" id="pp_new_group" class="form-control" min="1" max="28" placeholder="1-28">
                            <span class="help-block">1-28</span>
                        </div>
                    </div>
                    <div id="pp_add_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="ppAddGroupBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Add
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Interface Modal -->
<div id="editPortProtectModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Port Protect Group - <span id="pp_edit_interface_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <input type="hidden" id="pp_edit_interface" value="">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Group</label>
                        <div class="col-sm-8">
                            <select id="pp_edit_group" class="form-control"></select>
                            <span class="help-block" id="pp_edit_group_help"></span>
                        </div>
                    </div>
                    <div id="pp_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="savePortProtectBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const PP_DEVICE_IP = "{{ $device->hostname }}";
    const PP_API_TOKEN = "{{ $data['api_token'] }}";

    let ppGroups = [];
    let ppInterfaces = [];
    let ppSelected = new Set();   // ticked group IDs - the table only keeps the current page on screen

    // The tables use the offline DataTables shim (offline-datatable), which
    // loads rows only through options.data / options.ajax - no clear()/rows.add().
    function ppSetRows(table, rows) {
        table.options.data = rows;
        table.ajax.reload(null, false);
    }

    function ppShow(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = msg ? "block" : "none";
    }

    function ppRequestError(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    function ppBusy($btn, busy) {
        $btn.prop('disabled', busy);
        $btn.find('.spinner-border').toggle(busy);
    }

    // g ports before tg, then by slot/number: g0/1 ... g0/8, tg0/1 ...
    function ppPortKey(port) {
        const m = /^([a-z]+)(\d+)\/(\d+)$/i.exec(port) || [];
        const order = { g: 0, ge: 0, tg: 1 };
        return [order[(m[1] || '').toLowerCase()] ?? 9, parseInt(m[2] || '0', 10), parseInt(m[3] || '0', 10)];
    }

    function ppComparePorts(a, b) {
        const ka = ppPortKey(a), kb = ppPortKey(b);
        return (ka[0] - kb[0]) || (ka[1] - kb[1]) || (ka[2] - kb[2]);
    }

    function ppMembers(group) {
        return ppInterfaces
            .filter(i => i.port_protect_group === group)
            .map(i => i.interface)
            .sort(ppComparePorts);
    }

    $.fn.dataTable.ext.type.order['pp-port-asc'] = (a, b) => ppComparePorts(a, b);
    $.fn.dataTable.ext.type.order['pp-port-desc'] = (a, b) => ppComparePorts(b, a);

    var ppGroupTable = $('#ppGroupTable').DataTable({
        data: [],
        autoWidth: false,
        columns: [
            {
                data: "group",
                orderable: false,
                searchable: false,
                render: group => '<input type="checkbox" class="pp-group-check" value="' + group + '"' + (ppSelected.has(group) ? ' checked' : '') + '>'
            },
            { data: "group", type: "num" },
            { data: "members", render: members => members.length ? members.join(', ') : '<span class="text-muted">-</span>' },
            {
                data: "group",
                orderable: false,
                searchable: false,
                render: group => '<button type="button" class="btn btn-xs btn-danger btn-delete-group" data-group="' + group + '">'
                    + '<span class="spinner-border" style="display:none;"></span> Delete</button>'
            }
        ],
        order: [[1, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No Port Protect Groups configured"
        }
    });

    var ppPortTable = $('#ppPortTable').DataTable({
        data: [],
        autoWidth: false,
        columns: [
            { data: "interface", type: "pp-port" },
            {
                data: "port_protect_group",
                render: group => group ? '<span class="label label-primary">Group ' + group + '</span>' : '<span class="text-muted">None</span>'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: () => '<button type="button" class="btn btn-xs btn-warning btn-edit-portprotect">Edit</button>'
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No ports found on this device"
        }
    });

    // The interface table is built while its tab is hidden - fix its widths when shown
    $('a[href="#pp_interface_tab"]').on('shown.bs.tab', function () {
        ppPortTable.columns.adjust();
    });

    function loadPortProtect() {
        ppShow('pp_load_error', '');
        ppShow('pp_group_error', '');

        $.ajax({
            url: "/api/v0/portprotect/show/" + PP_DEVICE_IP,
            method: "GET",
            headers: {
                "Authorization": "Bearer " + PP_API_TOKEN,
                "Accept": "application/json"
            },
            success: function (json) {
                $('.pp-cache-note').toggle(!!json.cached);

                if (json.status !== "success") {
                    ppShow('pp_load_error', json.message || 'Failed to load Port Protect Group configuration');
                    return;
                }

                ppGroups = (json.groups || []).slice().sort((a, b) => a - b);
                ppInterfaces = json.interfaces || [];

                ppSelected = new Set();
                $('#pp_select_all').prop('checked', false);
                ppSetRows(ppGroupTable, ppGroups.map(g => ({ group: g, members: ppMembers(g) })));
                ppSetRows(ppPortTable, ppInterfaces);
            },
            error: function (xhr) {
                ppShow('pp_load_error', ppRequestError(xhr));
            }
        });
    }

    // operation "set" / "delete" for one or more group IDs
    function ppSetGroups(operation, groupIds, $btn, errorId, onDone) {
        ppShow(errorId, '');
        ppBusy($btn, true);

        $.ajax({
            url: "/api/v0/portprotect/group/set/" + PP_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + PP_API_TOKEN,
                "Accept": "application/json"
            },
            data: { operation: operation, group_ids: groupIds },
            success: function (response) {
                ppBusy($btn, false);
                loadPortProtect();
                if (response.status === "success") {
                    if (onDone) onDone();
                    alert(response.message);
                } else {
                    ppShow(errorId, response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                ppBusy($btn, false);
                ppShow(errorId, ppRequestError(xhr));
            }
        });
    }

    function ppConfirmDelete(groups) {
        const used = groups.filter(g => ppMembers(g).length);
        const warning = used.length ? '\n\nGroup ' + used.join(', ') + ' still has member ports.' : '';
        return confirm('Delete Port Protect Group ' + groups.join(', ') + '?' + warning);
    }

    $('#ppOpenAddBtn').on('click', function () {
        ppShow('pp_add_error', '');
        $('#pp_new_group').val('');
        $('#addPortProtectGroupModal').modal('show');
    });

    $('#ppAddGroupBtn').on('click', function () {
        const id = $.trim($('#pp_new_group').val());
        if (!/^\d+$/.test(id) || id < 1 || id > 28) {
            ppShow('pp_add_error', 'Port Protect Group must be between 1 and 28.');
            return;
        }
        if (ppGroups.indexOf(parseInt(id, 10)) !== -1) {
            ppShow('pp_add_error', 'Group ' + id + ' already exists.');
            return;
        }
        ppSetGroups('set', [id], $(this), 'pp_add_error', () => $('#addPortProtectGroupModal').modal('hide'));
    });

    $('#ppGroupTable').on('click', '.btn-delete-group', function () {
        const group = parseInt($(this).attr('data-group'), 10);
        if (ppConfirmDelete([group])) {
            ppSetGroups('delete', [group], $(this), 'pp_group_error');
        }
    });

    // Select All/Select None works on every group, not just the visible page
    $('#ppGroupTable').on('change', '.pp-group-check', function () {
        const group = parseInt(this.value, 10);
        this.checked ? ppSelected.add(group) : ppSelected.delete(group);
        $('#pp_select_all').prop('checked', ppGroups.length > 0 && ppSelected.size === ppGroups.length);
    });

    // Select All/Select None covers every group, not just the visible page
    $('#pp_select_all').on('change', function () {
        ppSelected = new Set(this.checked ? ppGroups : []);
        ppGroupTable.rows().invalidate().draw(false);
    });

    $('#ppBatchDeleteBtn').on('click', function () {
        const groups = [...ppSelected].sort((a, b) => a - b);

        if (!groups.length) {
            ppShow('pp_group_error', 'Tick at least one group to delete.');
            return;
        }
        if (ppConfirmDelete(groups)) {
            ppSetGroups('delete', groups, $(this), 'pp_group_error');
        }
    });

    $('#ppPortTable tbody').on('click', '.btn-edit-portprotect', function () {
        ppShow('pp_edit_error', '');
        const row = ppPortTable.row($(this).closest('tr')).data();

        $('#pp_edit_interface').val(row.interface);
        $('#pp_edit_interface_label').text(row.interface);

        const $select = $('#pp_edit_group').empty();
        ppGroups.forEach(g => $select.append($('<option>').val(g).text('Group ' + g)));
        if (row.port_protect_group) {
            $select.val(row.port_protect_group);
        }

        $('#pp_edit_group_help').text(ppGroups.length
            ? (row.port_protect_group ? 'Currently in Group ' + row.port_protect_group + '.' : 'Currently not in a group.')
            : 'No groups yet - add one on the Port Protect Group List tab first.');
        $('#savePortProtectBtn').prop('disabled', !ppGroups.length);

        $('#editPortProtectModal').modal('show');
    });

    $('#savePortProtectBtn').on('click', function () {
        ppShow('pp_edit_error', '');
        const $btn = $(this);
        const payload = {
            interface: $('#pp_edit_interface').val(),
            group_id: $('#pp_edit_group').val()
        };

        if (!payload.group_id) {
            ppShow('pp_edit_error', 'Select a group.');
            return;
        }

        ppBusy($btn, true);

        $.ajax({
            url: "/api/v0/portprotect/interface/set/" + PP_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + PP_API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                ppBusy($btn, false);
                if (response.status === "success") {
                    $('#editPortProtectModal').modal('hide');
                    loadPortProtect();
                    alert(response.message);
                } else {
                    ppShow('pp_edit_error', response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                ppBusy($btn, false);
                ppShow('pp_edit_error', ppRequestError(xhr));
            }
        });
    });

    loadPortProtect();
</script>
