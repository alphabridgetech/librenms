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

<!-- Tabs -->
<ul class="nav nav-tabs">
    <li class="active"><a href="#gvrp_global_tab" data-toggle="tab">Global GVRP Settings</a></li>
    <li><a href="#gvrp_interface_tab" data-toggle="tab">Interface GVRP Status</a></li>
</ul>

<div class="tab-content" style="margin-top: 15px;">
    <!-- Global GVRP Settings -->
    <div class="tab-pane active" id="gvrp_global_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Global GVRP Settings</strong>
                <span id="gvrp_global_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
            </div>
            <div class="panel-body">
                <form class="form-horizontal" id="gvrpGlobalForm">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">GVRP</label>
                        <div class="col-sm-4">
                            <select id="gvrp_global_status" class="form-control">
                                <option value="enable">Enable</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Dynamic VLAN Pruning</label>
                        <div class="col-sm-4">
                            <select id="gvrp_dynamic_vlan_status" class="form-control">
                                <option value="enable">Enable</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="button" id="saveGvrpGlobalBtn" class="btn btn-primary btn-sm">
                                <span class="spinner-border" style="display:none;"></span> Save
                            </button>
                            <span id="gvrp_global_error" class="text-danger" style="display:none; margin-left: 10px;"></span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Per-Interface GVRP -->
    <div class="tab-pane" id="gvrp_interface_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Interface GVRP Status</strong>
                <span id="gvrp_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadGvrpTable()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table id="gvrpTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Interface</th>
                                <th>GVRP Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                Help: ports are taken directly from Telequill (every GigaEthernet/TGigaEthernet port on this device). A port with no explicit "no gvrp" in its running-config defaults to Enable.
            </div>
        </div>
    </div>
</div>

<!-- Edit GVRP Modal -->
<div id="editGvrpModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit GVRP - <span id="gvrp_edit_interface_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="gvrp_edit_interface" value="">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">GVRP Status</label>
                        <div class="col-sm-8">
                            <select id="gvrp_edit_status" class="form-control">
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div id="gvrp_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="saveGvrpBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const GVRP_DEVICE_IP = "{{ $device->hostname }}";
    const GVRP_API_TOKEN = "{{ $data['api_token'] }}";

    function gvrpShowError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = "block";
    }

    function gvrpClearError(id) {
        const el = document.getElementById(id);
        el.innerText = "";
        el.style.display = "none";
    }

    function gvrpFormatStatus(status) {
        return status === 'Enable'
            ? '<span class="label label-success">Enable</span>'
            : '<span class="label label-default">Disable</span>';
    }

    // Plain string sorting would put "GigaEthernet0/10" before
    // "GigaEthernet0/2" - split into text/number chunks and compare
    // numerically so ordering reads 0/1, 0/2, ... 0/10.
    function gvrpNaturalCompare(a, b) {
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

    $.fn.dataTable.ext.type.order['gvrp-interface-natural-asc'] = function (a, b) {
        return gvrpNaturalCompare(a, b);
    };
    $.fn.dataTable.ext.type.order['gvrp-interface-natural-desc'] = function (a, b) {
        return gvrpNaturalCompare(b, a);
    };

    var gvrpTable = $('#gvrpTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/gvrp/show/" + GVRP_DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + GVRP_API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('gvrp_cache_note').style.display = json.cached ? "inline" : "none";

                if (json.status !== "success") {
                    return [];
                }

                const ports = json.ports || [];
                const entries = json.entries || {};

                // Every LibreNMS-known GigaEthernet/TGigaEthernet port is
                // always shown, defaulting to Enable (the switch's own
                // default) for any port the SSH show didn't explicitly
                // report "no gvrp" for.
                return ports.map(function (port) {
                    return {
                        interface: port,
                        status: entries[port] || 'Enable'
                    };
                });
            }
        },
        columns: [
            { data: "interface", type: "gvrp-interface-natural" },
            { data: null, render: row => gvrpFormatStatus(row.status) },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-gvrp">Edit</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No GigaEthernet/TGigaEthernet ports found for this device"
        }
    });

    function loadGvrpTable() {
        gvrpTable.ajax.reload(null, false);
    }

    // The Interface tab starts hidden (Global is the active tab), and
    // DataTables miscalculates column widths when initialized inside a
    // display:none element - recalculate once the tab is actually shown.
    $('a[href="#gvrp_interface_tab"]').on('shown.bs.tab', function () {
        gvrpTable.columns.adjust();
    });

    $('#gvrpTable tbody').on('click', '.btn-edit-gvrp', function () {
        gvrpClearError('gvrp_edit_error');
        const row = gvrpTable.row($(this).closest('tr')).data();

        $('#gvrp_edit_interface').val(row.interface);
        $('#gvrp_edit_interface_label').text(row.interface);
        $('#gvrp_edit_status').val(row.status || 'Enable');

        $('#editGvrpModal').modal('show');
    });

    $('#saveGvrpBtn').on('click', function () {
        gvrpClearError('gvrp_edit_error');

        const payload = {
            interface: $('#gvrp_edit_interface').val(),
            gvrp_status: $('#gvrp_edit_status').val()
        };

        const $btn = $('#saveGvrpBtn');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/gvrp/set/" + GVRP_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + GVRP_API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();

                if (response.status === "success") {
                    $('#editGvrpModal').modal('hide');
                    loadGvrpTable();
                    alert(response.message || 'GVRP updated successfully!');
                } else if (response.errors) {
                    gvrpShowError('gvrp_edit_error', Object.values(response.errors).flat().join(' '));
                } else {
                    gvrpShowError('gvrp_edit_error', response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();
                gvrpShowError('gvrp_edit_error', xhr.responseJSON?.message || 'API communication error');
            }
        });
    });

    // Global GVRP settings: load current state, then let the user toggle
    // either field - both are always sent together on Save, since the
    // underlying playbook requires both gvrp_global and dynamic_vlan.
    function loadGvrpGlobal() {
        $.ajax({
            url: "/api/v0/gvrp/global/show/" + GVRP_DEVICE_IP,
            method: "GET",
            headers: {
                "Authorization": "Bearer " + GVRP_API_TOKEN,
                "Accept": "application/json"
            },
            success: function (response) {
                document.getElementById('gvrp_global_cache_note').style.display = response.cached ? "inline" : "none";

                if (response.status === "success") {
                    if (response.gvrp_global) {
                        $('#gvrp_global_status').val(String(response.gvrp_global).toLowerCase());
                    }
                    if (response.dynamic_vlan) {
                        $('#gvrp_dynamic_vlan_status').val(String(response.dynamic_vlan).toLowerCase());
                    }
                }
            }
        });
    }
    loadGvrpGlobal();

    $('#saveGvrpGlobalBtn').on('click', function () {
        gvrpClearError('gvrp_global_error');

        const payload = {
            gvrp_global: $('#gvrp_global_status').val(),
            dynamic_vlan: $('#gvrp_dynamic_vlan_status').val()
        };

        const $btn = $('#saveGvrpGlobalBtn');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/gvrp/global/set/" + GVRP_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + GVRP_API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();

                if (response.status === "success") {
                    alert(response.message || 'Global GVRP settings updated successfully!');
                    loadGvrpGlobal();
                } else if (response.errors) {
                    gvrpShowError('gvrp_global_error', Object.values(response.errors).flat().join(' '));
                } else {
                    gvrpShowError('gvrp_global_error', response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();
                gvrpShowError('gvrp_global_error', xhr.responseJSON?.message || 'API communication error');
            }
        });
    });
</script>
