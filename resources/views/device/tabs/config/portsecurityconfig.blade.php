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

    .ps-toolbar {
        margin-bottom: 10px;
    }

    .ps-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 30px;
        margin-bottom: 15px;
    }

    .ps-filters label {
        font-weight: normal;
        margin: 0 5px 0 0;
    }

    .ps-filters select,
    .ps-filters input {
        display: inline-block;
        width: auto;
    }

    .ps-add-binding {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .ps-add-binding input {
        width: 160px;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')

<div id="ps_load_error" class="alert alert-danger" style="display:none;"></div>

{{-- Port Type / Slot Num / Name(s) filter, as on the switch's own pages --}}
@php
    $psFilters = function (string $tab) {
        return '<div class="ps-filters" data-tab="' . $tab . '">'
            . '<strong>Filters</strong>'
            . '<span><label>Port Type:</label><select class="form-control input-sm ps-filter-type">'
            . '<option value="">All</option><option value="g">g (GigaEthernet)</option><option value="tg">tg (TGigaEthernet)</option>'
            . '</select></span>'
            . '<span><label>Slot Num:</label><select class="form-control input-sm ps-filter-slot"><option value="">All</option></select></span>'
            . '<span><label>Name(s):</label><input type="text" class="form-control input-sm ps-filter-name" style="width:180px;" placeholder="e.g. g0/1, tg0/2"></span>'
            . '</div>';
    };
@endphp

<ul class="nav nav-tabs">
    <li class="active"><a href="#ps_ipmac_tab" data-toggle="tab">IP-MAC Binding</a></li>
    <li><a href="#ps_staticmode_tab" data-toggle="tab">Static MAC Filtration Mode</a></li>
    <li><a href="#ps_staticentry_tab" data-toggle="tab">Static MAC Filtration Entry</a></li>
    <li><a href="#ps_dynamic_tab" data-toggle="tab">Dynamic MAC Filtration Mode</a></li>
</ul>

<div class="tab-content" style="margin-top: 15px;">
    <!-- IP-MAC Binding -->
    <div class="tab-pane active" id="ps_ipmac_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Binding IP-MAC Port</strong>
                <span class="ps-cache-note text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortSecurity()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                {!! $psFilters('ipmac') !!}
                <div class="table-responsive">
                    <table id="psIpMacPortTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Port</th>
                                <th>Bindings</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                Help: click Detail to see, add or delete the IP-MAC bindings of a port. MAC format: aabb.ccdd.eeff or aabbccddeeff.
            </div>
        </div>
    </div>

    <!-- Static MAC Filtration Mode -->
    <div class="tab-pane" id="ps_staticmode_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Static MAC Filtration Mode</strong>
                <span class="ps-cache-note text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortSecurity()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                {!! $psFilters('staticmode') !!}
                <div class="table-responsive">
                    <table id="psStaticModeTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Port</th>
                                <th>Static MAC Filtration Mode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                Help: Accept lets in only the MAC addresses listed under Static MAC Filtration Entry; Reject blocks them. A port has one mode at a time - setting a static mode replaces Dynamic. Trunk ports can't use port security.
            </div>
        </div>
    </div>

    <!-- Static MAC Filtration Entry -->
    <div class="tab-pane" id="ps_staticentry_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Static MAC Filtration Entry</strong>
                <span class="ps-cache-note text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortSecurity()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                <div class="ps-toolbar">
                    <button type="button" class="btn btn-sm btn-primary" id="psAddStaticBtn">
                        <i class="fa fa-plus"></i> Add
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="psStaticTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Port</th>
                                <th>MAC Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
                <div id="ps_static_error" class="text-danger" style="display:none; margin-top:8px;"></div>
            </div>
            <div class="panel-footer">
                Help: these MAC addresses are used by ports in Static MAC Filtration Mode Accept or Reject. MAC format: aabb.ccdd.eeff or aabbccddeeff.
            </div>
        </div>
    </div>

    <!-- Dynamic MAC Filtration Mode -->
    <div class="tab-pane" id="ps_dynamic_tab">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Dynamic MAC Filtration Mode</strong>
                <span class="ps-cache-note text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortSecurity()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                {!! $psFilters('dynamic') !!}
                <div class="table-responsive">
                    <table id="psDynamicTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Port</th>
                                <th>Dynamic MAC Filtration</th>
                                <th>Maximum MAC Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                Help: the port learns up to the maximum number of MAC addresses (1-2048). A port has one mode at a time - enabling Dynamic replaces a static mode. Trunk ports can't use port security.
            </div>
        </div>
    </div>
</div>

<!-- IP-MAC Binding Detail Modal -->
<div id="psIpMacDetailModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">IP-MAC Binding - <span id="ps_ipmac_port_label"></span></h4>
            </div>
            <div class="modal-body">
                <table class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>IP Address</th>
                            <th>MAC Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="psIpMacDetailBody"></tbody>
                </table>

                <h5><strong>Add Binding</strong></h5>
                <form class="ps-add-binding" onsubmit="return false;">
                    <input type="text" id="ps_ipmac_ip" class="form-control input-sm" placeholder="IP, e.g. 192.168.10.11">
                    <input type="text" id="ps_ipmac_mac" class="form-control input-sm" placeholder="MAC, e.g. aabb.ccdd.eeff">
                    <button type="button" id="psSaveIpMacBtn" class="btn btn-sm btn-primary">
                        <span class="spinner-border" style="display:none;"></span> Add
                    </button>
                </form>
                <div id="ps_ipmac_error" class="text-danger" style="display:none; margin-top:8px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Static MAC Filtration Mode Modal -->
<div id="psStaticModeModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Static MAC Filtration Mode - <span id="ps_staticmode_port_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <input type="hidden" id="ps_staticmode_port" value="">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Mode</label>
                        <div class="col-sm-8">
                            <select id="ps_staticmode_value" class="form-control">
                                <option value="Disable">Disable</option>
                                <option value="Accept">Accept</option>
                                <option value="Reject">Reject</option>
                            </select>
                            <span class="help-block" id="ps_staticmode_help"></span>
                        </div>
                    </div>
                    <div id="ps_staticmode_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="psSaveStaticModeBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Static MAC Entry Modal -->
<div id="psStaticModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Static MAC Filtration Entry</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Port</label>
                        <div class="col-sm-8"><select id="ps_static_interface" class="form-control ps-port-select"></select></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">MAC Address</label>
                        <div class="col-sm-8"><input type="text" id="ps_static_mac" class="form-control" placeholder="aabb.ccdd.eeff"></div>
                    </div>
                    <div id="ps_static_add_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="psSaveStaticBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Add
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic MAC Filtration Mode Modal -->
<div id="psDynamicModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Dynamic MAC Filtration Mode - <span id="ps_dynamic_port_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <input type="hidden" id="ps_dynamic_port" value="">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Dynamic MAC Filtration</label>
                        <div class="col-sm-8">
                            <select id="ps_dynamic_value" class="form-control">
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                            <span class="help-block" id="ps_dynamic_help"></span>
                        </div>
                    </div>
                    <div class="form-group ps-dynamic-detail">
                        <label class="col-sm-4 control-label">Maximum MAC Address</label>
                        <div class="col-sm-8">
                            <input type="number" id="ps_dynamic_max" class="form-control" min="1" max="2048">
                            <span class="help-block">1-2048</span>
                        </div>
                    </div>
                    <div id="ps_dynamic_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="psSaveDynamicBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const PS_DEVICE_IP = "{{ $device->hostname }}";
    const PS_API_TOKEN = "{{ $data['api_token'] }}";
    const PS_MAC_RE = /^([0-9a-f]{4}\.[0-9a-f]{4}\.[0-9a-f]{4}|[0-9a-f]{12})$/i;
    const PS_IP_RE = /^((25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(25[0-5]|2[0-4]\d|1?\d?\d)$/;

    let psPorts = [];     // [{interface, mode, dynamic_maximum}]
    let psIpMac = [];     // [{interface, ip_address, mac_address}]
    let psDetailPort = null;

    function psShow(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = msg ? "block" : "none";
    }

    function psRequestError(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    function psBusy($btn, busy) {
        $btn.prop('disabled', busy);
        $btn.find('.spinner-border').toggle(busy);
    }

    // The tables use the offline DataTables shim (offline-datatable), which
    // loads rows only through options.data / options.ajax - no clear()/rows.add().
    function psSetRows(table, rows) {
        table.options.data = rows;
        table.ajax.reload(null, false);
    }

    // "tg0/3" -> {type: "tg", slot: "0", num: 3}
    function psParsePort(port) {
        const m = /^([a-z]+)(\d+)\/(\d+)$/i.exec(port) || [];
        return { type: (m[1] || '').toLowerCase(), slot: m[2] || '', num: parseInt(m[3] || '0', 10) };
    }

    // g ports before tg, then by slot/number: g0/1 ... g0/8, tg0/1 ...
    function psComparePorts(a, b) {
        const order = { g: 0, ge: 0, tg: 1 };
        const pa = psParsePort(a), pb = psParsePort(b);
        return ((order[pa.type] ?? 9) - (order[pb.type] ?? 9))
            || (parseInt(pa.slot, 10) - parseInt(pb.slot, 10))
            || (pa.num - pb.num);
    }

    $.fn.dataTable.ext.type.order['ps-port-asc'] = (a, b) => psComparePorts(a, b);
    $.fn.dataTable.ext.type.order['ps-port-desc'] = (a, b) => psComparePorts(b, a);

    // "g0/1" matches only g0/1 (not tg0/1); a partial name like "tg" matches from the start
    function psNameMatches(port, name) {
        port = port.toLowerCase();
        return name.indexOf('/') !== -1 ? port === name : port.indexOf(name) === 0;
    }

    // Ports shown on a tab after its Port Type / Slot Num / Name(s) filter
    function psFilteredPorts(tab) {
        const $f = $('.ps-filters[data-tab="' + tab + '"]');
        const type = $f.find('.ps-filter-type').val();
        const slot = $f.find('.ps-filter-slot').val();
        const names = $f.find('.ps-filter-name').val().split(/[\s,]+/).map(s => s.trim().toLowerCase()).filter(Boolean);

        return psPorts.filter(function (row) {
            const p = psParsePort(row.interface);
            return (!type || p.type === type)
                && (!slot || p.slot === slot)
                && (!names.length || names.some(n => psNameMatches(row.interface, n)));
        });
    }

    function psBindingsOf(port) {
        return psIpMac.filter(b => b.interface === port);
    }

    function psModeLabel(row) {
        return row.mode === 'Accept' || row.mode === 'Reject' ? row.mode : 'Disable';
    }

    var psIpMacPortTable = $('#psIpMacPortTable').DataTable({
        data: [],
        autoWidth: false,
        columns: [
            { data: "interface", type: "ps-port" },
            { data: "interface", type: "num", render: port => psBindingsOf(port).length },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: () => '<a href="javascript:void(0)" class="ps-ipmac-detail">Detail</a>'
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [25, 50, 100],
        language: { emptyTable: "No ports found" }
    });

    var psStaticModeTable = $('#psStaticModeTable').DataTable({
        data: [],
        autoWidth: false,
        columns: [
            { data: "interface", type: "ps-port" },
            {
                data: null,
                render: function (row) {
                    const mode = psModeLabel(row);
                    const dynamicNote = row.mode === 'Dynamic' ? ' <small class="text-muted">(port is in Dynamic mode)</small>' : '';
                    return (mode === 'Disable' ? '<span class="text-muted">Disable</span>' : '<span class="label label-success">' + mode + '</span>') + dynamicNote;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: () => '<button type="button" class="btn btn-xs btn-warning btn-edit-staticmode">Edit</button>'
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [25, 50, 100],
        language: { emptyTable: "No ports found" }
    });

    var psStaticTable = $('#psStaticTable').DataTable({
        data: [],
        autoWidth: false,
        columns: [
            { data: "no", type: "num" },
            { data: "interface", type: "ps-port" },
            { data: "mac_address" },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: () => '<button type="button" class="btn btn-xs btn-danger btn-delete-static"><span class="spinner-border" style="display:none;"></span> Delete</button>'
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No static MAC addresses configured" }
    });

    var psDynamicTable = $('#psDynamicTable').DataTable({
        data: [],
        autoWidth: false,
        columns: [
            { data: "interface", type: "ps-port" },
            {
                data: "mode",
                render: mode => mode === 'Dynamic' ? '<span class="label label-success">Enable</span>' : '<span class="text-muted">Disable</span>'
            },
            { data: null, render: row => row.mode === 'Dynamic' && row.dynamic_maximum ? row.dynamic_maximum : '<span class="text-muted">-</span>' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: () => '<button type="button" class="btn btn-xs btn-warning btn-edit-dynamic">Edit</button>'
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [25, 50, 100],
        language: { emptyTable: "No ports found" }
    });

    const PS_PORT_TABLES = { ipmac: psIpMacPortTable, staticmode: psStaticModeTable, dynamic: psDynamicTable };

    function psRenderPortTables() {
        Object.keys(PS_PORT_TABLES).forEach(tab => psSetRows(PS_PORT_TABLES[tab], psFilteredPorts(tab)));
    }

    $('.ps-filters').on('change input', 'select, input', function () {
        const tab = $(this).closest('.ps-filters').attr('data-tab');
        psSetRows(PS_PORT_TABLES[tab], psFilteredPorts(tab));
    });

    function psRenderDetail() {
        if (!psDetailPort) {
            return;
        }
        const $body = $('#psIpMacDetailBody').empty();
        const bindings = psBindingsOf(psDetailPort);

        if (!bindings.length) {
            $body.append('<tr><td colspan="4" class="text-muted">No IP-MAC bindings on this port</td></tr>');
            return;
        }

        bindings.forEach(function (b, i) {
            $body.append($('<tr>').append(
                $('<td>').text(i + 1),
                $('<td>').text(b.ip_address),
                $('<td>').text(b.mac_address),
                $('<td>').append(
                    $('<button type="button" class="btn btn-xs btn-danger btn-delete-ipmac">')
                        .attr({ 'data-ip': b.ip_address, 'data-mac': b.mac_address })
                        .append('<span class="spinner-border" style="display:none;"></span> Delete')
                )
            ));
        });
    }

    function loadPortSecurity() {
        psShow('ps_load_error', '');

        $.ajax({
            url: "/api/v0/portsecurity/show/" + PS_DEVICE_IP,
            method: "GET",
            headers: {
                "Authorization": "Bearer " + PS_API_TOKEN,
                "Accept": "application/json"
            },
            success: function (json) {
                $('.ps-cache-note').toggle(!!json.cached);

                if (json.status !== "success") {
                    psShow('ps_load_error', json.message || 'Failed to load Port Security configuration');
                    return;
                }

                psPorts = (json.ports || []).slice().sort((a, b) => psComparePorts(a.interface, b.interface));
                psIpMac = json.ip_mac || [];

                const portNames = psPorts.map(p => p.interface);
                $('.ps-port-select').each(function () {
                    const $select = $(this).empty();
                    portNames.forEach(port => $select.append($('<option>').val(port).text(port)));
                });

                const slots = [...new Set(portNames.map(p => psParsePort(p).slot))].sort();
                $('.ps-filter-slot').each(function () {
                    const current = $(this).val();
                    $(this).empty().append($('<option>').val('').text('All'));
                    slots.forEach(s => $(this).append($('<option>').val(s).text(s)));
                    $(this).val(slots.indexOf(current) !== -1 ? current : '');
                });

                psRenderPortTables();
                psSetRows(psStaticTable, (json.static_macs || []).map((row, i) => Object.assign({ no: i + 1 }, row)));
                psRenderDetail();
            },
            error: function (xhr) {
                psShow('ps_load_error', psRequestError(xhr));
            }
        });
    }

    // POST to one of the set endpoints; reloads the page data either way
    function psPost(path, payload, $btn, errorId, onDone) {
        psShow(errorId, '');
        psBusy($btn, true);

        $.ajax({
            url: "/api/v0/portsecurity/" + path + "/" + PS_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + PS_API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                psBusy($btn, false);
                loadPortSecurity();
                if (response.status === "success") {
                    if (onDone) onDone();
                    alert(response.message);
                } else {
                    psShow(errorId, response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                psBusy($btn, false);
                psShow(errorId, psRequestError(xhr));
            }
        });
    }

    // IP-MAC Binding
    $('#psIpMacPortTable tbody').on('click', '.ps-ipmac-detail', function () {
        psDetailPort = psIpMacPortTable.row($(this).closest('tr')).data().interface;
        $('#ps_ipmac_port_label').text(psDetailPort);
        $('#ps_ipmac_ip').val('');
        $('#ps_ipmac_mac').val('');
        psShow('ps_ipmac_error', '');
        psRenderDetail();
        $('#psIpMacDetailModal').modal('show');
    });

    $('#psIpMacDetailModal').on('hidden.bs.modal', function () {
        psDetailPort = null;
    });

    $('#psSaveIpMacBtn').on('click', function () {
        const payload = {
            operation: 'set',
            interface: psDetailPort,
            ip_address: $.trim($('#ps_ipmac_ip').val()),
            mac_address: $.trim($('#ps_ipmac_mac').val()).toLowerCase()
        };

        if (!PS_IP_RE.test(payload.ip_address)) {
            psShow('ps_ipmac_error', 'Enter a valid IPv4 address.');
            return;
        }
        if (!PS_MAC_RE.test(payload.mac_address)) {
            psShow('ps_ipmac_error', 'MAC address must look like aabb.ccdd.eeff or aabbccddeeff.');
            return;
        }

        psPost('ipmac/set', payload, $(this), 'ps_ipmac_error', function () {
            $('#ps_ipmac_ip').val('');
            $('#ps_ipmac_mac').val('');
        });
    });

    $('#psIpMacDetailBody').on('click', '.btn-delete-ipmac', function () {
        const ip = $(this).attr('data-ip');
        const mac = $(this).attr('data-mac');
        if (!confirm('Delete IP-MAC binding ' + ip + ' / ' + mac + ' from ' + psDetailPort + '?')) {
            return;
        }
        psPost('ipmac/set', { operation: 'delete', interface: psDetailPort, ip_address: ip, mac_address: mac }, $(this), 'ps_ipmac_error');
    });

    // Static MAC Filtration Mode
    $('#psStaticModeTable tbody').on('click', '.btn-edit-staticmode', function () {
        psShow('ps_staticmode_error', '');
        const row = psStaticModeTable.row($(this).closest('tr')).data();

        $('#ps_staticmode_port').val(row.interface);
        $('#ps_staticmode_port_label').text(row.interface);
        $('#ps_staticmode_value').val(psModeLabel(row));
        $('#ps_staticmode_help').text(row.mode === 'Dynamic'
            ? 'This port is in Dynamic mode. Accept or Reject replaces it; Disable turns port security off.'
            : '');

        $('#psStaticModeModal').modal('show');
    });

    $('#psSaveStaticModeBtn').on('click', function () {
        const payload = { interface: $('#ps_staticmode_port').val(), mode: $('#ps_staticmode_value').val() };
        psPost('mode/set', payload, $(this), 'ps_staticmode_error', () => $('#psStaticModeModal').modal('hide'));
    });

    // Static MAC Filtration Entry
    $('#psAddStaticBtn').on('click', function () {
        psShow('ps_static_add_error', '');
        $('#ps_static_mac').val('');
        $('#psStaticModal').modal('show');
    });

    $('#psSaveStaticBtn').on('click', function () {
        const payload = {
            operation: 'set',
            interface: $('#ps_static_interface').val(),
            mac_address: $.trim($('#ps_static_mac').val()).toLowerCase()
        };

        if (!PS_MAC_RE.test(payload.mac_address)) {
            psShow('ps_static_add_error', 'MAC address must look like aabb.ccdd.eeff or aabbccddeeff.');
            return;
        }

        psPost('staticmac/set', payload, $(this), 'ps_static_add_error', () => $('#psStaticModal').modal('hide'));
    });

    $('#psStaticTable tbody').on('click', '.btn-delete-static', function () {
        const row = psStaticTable.row($(this).closest('tr')).data();
        if (!confirm('Delete static MAC ' + row.mac_address + ' from ' + row.interface + '?')) {
            return;
        }
        psPost('staticmac/set', { operation: 'delete', interface: row.interface, mac_address: row.mac_address }, $(this), 'ps_static_error');
    });

    // Dynamic MAC Filtration Mode
    function psToggleDynamic() {
        $('.ps-dynamic-detail').toggle($('#ps_dynamic_value').val() === 'Enable');
    }

    $('#ps_dynamic_value').on('change', psToggleDynamic);

    $('#psDynamicTable tbody').on('click', '.btn-edit-dynamic', function () {
        psShow('ps_dynamic_error', '');
        const row = psDynamicTable.row($(this).closest('tr')).data();
        const enabled = row.mode === 'Dynamic';

        $('#ps_dynamic_port').val(row.interface);
        $('#ps_dynamic_port_label').text(row.interface);
        $('#ps_dynamic_value').val(enabled ? 'Enable' : 'Disable');
        $('#ps_dynamic_max').val(row.dynamic_maximum || 1);
        $('#ps_dynamic_help').text(row.mode === 'Accept' || row.mode === 'Reject'
            ? 'This port is in Static ' + row.mode + ' mode. Enabling Dynamic replaces it.'
            : '');
        psToggleDynamic();

        $('#psDynamicModal').modal('show');
    });

    $('#psSaveDynamicBtn').on('click', function () {
        const port = $('#ps_dynamic_port').val();
        const row = psPorts.find(p => p.interface === port) || {};
        let payload;

        if ($('#ps_dynamic_value').val() === 'Enable') {
            payload = { interface: port, mode: 'Dynamic', dynamic_maximum: $.trim($('#ps_dynamic_max').val()) };
            if (!/^\d+$/.test(payload.dynamic_maximum) || payload.dynamic_maximum < 1 || payload.dynamic_maximum > 2048) {
                psShow('ps_dynamic_error', 'Maximum MAC Address must be between 1 and 2048.');
                return;
            }
        } else {
            // Disabling turns port security off - don't touch a port that is in a static mode
            if (row.mode !== 'Dynamic') {
                $('#psDynamicModal').modal('hide');
                return;
            }
            payload = { interface: port, mode: 'Disable' };
        }

        psPost('mode/set', payload, $(this), 'ps_dynamic_error', () => $('#psDynamicModal').modal('hide'));
    });

    loadPortSecurity();
</script>
