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

    .l3-page .table-responsive {
        margin-top: 15px;
        border: none;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')
@include('device.tabs.datatablepath.l3-batch-delete')

<div class="container-fluid l3-page" style="margin-top:30px; padding-left:0; padding-right:0;">

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Static Routes</strong>
            <span id="sr_loading" class="text-muted" style="margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> reading from device...
            </span>
            <span id="sr_cache_note" class="text-muted" style="display:none; margin-left:10px;">last known data, refreshing...</span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadSr('refresh')">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div class="panel-body">
            <button class="btn btn-primary" id="btnAddSr">
                <i class="glyphicon glyphicon-plus"></i> Add Static Route
            </button>
            <div id="sr_error" class="text-danger" style="display:none; margin-top:10px;"></div>

            <div class="table-responsive">
                <table id="srTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="30"></th>
                            <th width="120">Destination</th>
                            <th width="120">Mask</th>
                            <th width="150">Interface Type</th>
                            <th width="130">Next Hop</th>
                            <th width="70">Distance</th>
                            <th width="70">Tag</th>
                            <th>Description</th>
                            <th width="130">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <ul>
            <li><strong>Interface VLAN</strong>: <code>ip route &lt;dest&gt; &lt;mask&gt; vlan&lt;id&gt; &lt;forwarding address&gt; &lt;distance&gt; tag &lt;tag&gt;</code></li>
            <li><strong>Null0</strong>: traffic to the destination is discarded; a forwarding address is still required by the switch command.</li>
            <li><strong>Gateway IP Address</strong>: <code>ip route &lt;dest&gt; &lt;mask&gt; &lt;gateway&gt; &lt;distance&gt; tag &lt;tag&gt;</code></li>
            <li>Delete rebuilds the exact route line, so routes without a tag, default routes and routes on other interfaces can't be deleted here.</li>
            <li>Be careful with routes covering the network this server reaches the switch from ({{ $device->hostname }}) - a wrong route can cut off management.</li>
        </ul>
    </div>
</div>

<!-- Add Static Route Modal -->
<div id="srModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sr_modal_title">Add Static Route</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Destination IP*</label>
                        <div class="col-sm-7">
                            <input type="text" id="sr_dest_ip" class="form-control" placeholder="172.16.10.0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Destination Mask*</label>
                        <div class="col-sm-7">
                            <select id="sr_dest_mask" class="form-control"></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Interface Type*</label>
                        <div class="col-sm-7">
                            <select id="sr_interface_type" class="form-control">
                                <option value="Gateway IP Address">Gateway IP Address</option>
                                <option value="Interface VLAN">Interface VLAN</option>
                                <option value="Null0">Null0</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group sr-field" data-types="Interface VLAN">
                        <label class="col-sm-4 control-label">Interface VLAN*</label>
                        <div class="col-sm-7">
                            <input type="number" id="sr_interface_vlan" class="form-control" min="1" max="4094" placeholder="1-4094" list="sr_vlan_list">
                            <datalist id="sr_vlan_list"></datalist>
                        </div>
                    </div>
                    <div class="form-group sr-field" data-types="Interface VLAN|Null0">
                        <label class="col-sm-4 control-label">Forwarding Address*</label>
                        <div class="col-sm-7">
                            <input type="text" id="sr_forwarding_routing_address" class="form-control" placeholder="192.168.8.1">
                        </div>
                    </div>
                    <div class="form-group sr-field" data-types="Gateway IP Address">
                        <label class="col-sm-4 control-label">Gateway IP*</label>
                        <div class="col-sm-7">
                            <input type="text" id="sr_gateway_ip" class="form-control" placeholder="192.168.14.1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Distance Metric*</label>
                        <div class="col-sm-7">
                            <input type="number" id="sr_distance_metric" class="form-control" min="1" max="255" value="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Routing Tag*</label>
                        <div class="col-sm-7">
                            <input type="number" id="sr_routing_tag" class="form-control" min="0" max="4294967295" value="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Description*</label>
                        <div class="col-sm-7">
                            <input type="text" id="sr_route_description" class="form-control" maxlength="64">
                            <span class="help-block">Letters, digits, <code>_ . -</code> only.</span>
                        </div>
                    </div>
                    <div id="sr_edit_note" class="alert alert-warning" style="display:none; margin-bottom:10px;">
                        Editing replaces the route on the switch: when only distance, tag or description change, the old
                        route is removed and the new one added (the old one is put back if that fails); when the destination
                        or next hop change, the new route is added first and the old one removed after.
                    </div>
                    <div id="sr_error_modal" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveSrBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const SR_DEVICE_IP = @json($device->hostname);
    const SR_API_TOKEN = @json($data['api_token']);
    const SR_API = "/api/v0/l3/";
    const SR_HEADERS = { "Authorization": "Bearer " + SR_API_TOKEN, "Accept": "application/json" };

    var srPeekTimer = null;

    function srEsc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function srErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    // Modal buttons have their own spinner; the table's Delete buttons
    // don't, so they show "Deleting..." while the switch is working.
    function srBusy(btn, busy) {
        const $btn = $(btn);
        if ($btn.find('.spinner-border').length) {
            $btn.find('.spinner-border').toggle(busy);
        } else if (busy) {
            $btn.data('label', $btn.html()).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');
        } else if ($btn.data('label') !== undefined) {
            $btn.html($btn.data('label'));
        }
        $btn.prop('disabled', busy);
    }

    function srShowError(id, message) {
        const box = document.getElementById(id);
        box.textContent = message;
        box.style.display = 'block';
    }

    // Dotted masks /32 ... /0, /24 selected by default.
    (function buildMasks() {
        let options = '';
        for (let prefix = 32; prefix >= 0; prefix--) {
            const bits = prefix === 0 ? 0 : (0xFFFFFFFF << (32 - prefix)) >>> 0;
            const mask = [24, 16, 8, 0].map(s => (bits >>> s) & 255).join('.');
            options += `<option value="${mask}" ${prefix === 24 ? 'selected' : ''}>${mask} (/${prefix})</option>`;
        }
        document.getElementById('sr_dest_mask').innerHTML = options;
    })();

    function srNextHop(row) {
        if (row.interface_type === 'Gateway IP Address') return srEsc(row.gateway_ip);
        if (row.interface_type === 'Interface VLAN') return 'vlan' + Number(row.interface_vlan) + ' via ' + srEsc(row.forwarding_routing_address);
        if (row.interface_type === 'Null0') return 'Null0 ' + srEsc(row.forwarding_routing_address || '');
        return srEsc(row.interface || row.forwarding_routing_address || '-');
    }

    var srBatch = L3BatchDelete({
        table: () => srTable,
        tableId: '#srTable',
        after: '#btnAddSr',
        url: SR_API + "staticroute/batchdelete/" + SR_DEVICE_IP,
        headers: SR_HEADERS,
        noun: 'static routes',
        canDelete: row => !!row.deletable,
        toItem: function (row) {
            const item = {
                dest_ip: row.destination_ip,
                dest_mask: row.destination_ip_mask,
                interface_type: row.interface_type,
                distance_metric: row.distance_metric,
                routing_tag: row.routing_tag
            };
            if (row.interface_type === 'Interface VLAN') item.interface_vlan = row.interface_vlan;
            if (row.interface_type === 'Interface VLAN' || row.interface_type === 'Null0') item.forwarding_routing_address = row.forwarding_routing_address;
            if (row.interface_type === 'Gateway IP Address') item.gateway_ip = row.gateway_ip;
            return item;
        },
        describe: row => `${row.destination_ip} ${row.destination_ip_mask} via ${srNextHop(row).replace(/<[^>]*>/g, '')}`,
        onData: data => renderSr(data, false),
        reload: () => loadSr('refresh')
    });

    var srTable = $('#srTable').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, searchable: false, render: (row, type, full, meta) => srBatch.checkbox(full, meta) },
            { data: "destination_ip", render: (data, type, row) => row.default_route ? '<em>default</em>' : srEsc(data) },
            { data: "destination_ip_mask", render: data => srEsc(data) || '-' },
            { data: "interface_type", render: (data, type, row) => srEsc(data || row.interface || '-') },
            { data: null, render: (row, type, full) => srNextHop(full) },
            { data: "distance_metric", render: (data, type, row) => row.distance_default ? `${data} <span class="text-muted">(default)</span>` : (data ?? '-') },
            { data: "routing_tag", render: data => data ?? '-' },
            { data: "route_description", render: data => srEsc(data) || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (row, type, full, meta) => full.deletable
                    ? `<button type="button" class="btn btn-xs btn-warning btn-edit-sr" data-row="${meta.row}">Edit</button>
                       <button type="button" class="btn btn-xs btn-danger btn-delete-sr" data-row="${meta.row}">Delete</button>`
                    : `<span class="text-muted" title="${srEsc(full.not_deletable_reason)}">n/a</span>`
            }
        ],
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No static routes configured" }
    });

    function renderSr(d, cached) {
        d = d || {};
        document.getElementById('sr_cache_note').style.display = cached ? 'inline' : 'none';
        srBatch.reset();
        srTable.options.data = d.routes || [];
        srTable.ajax.reload(null, false);
    }

    function loadSr(mode) {
        const loading = document.getElementById('sr_loading');
        loading.style.display = 'inline';
        document.getElementById('sr_error').style.display = 'none';
        clearTimeout(srPeekTimer);

        $.ajax({
            url: SR_API + "staticroute/show/" + SR_DEVICE_IP + (mode ? "?" + mode + "=1" : ""),
            headers: SR_HEADERS,
            success: function (res) {
                loading.style.display = 'none';
                renderSr(res.data, res.cached);
                if (res.cached) srPeekTimer = setTimeout(() => loadSr('peek'), 30000);
            },
            error: function (xhr) {
                loading.style.display = 'none';
                srShowError('sr_error', srErrorMessage(xhr, "Failed to read static routes from device"));
            }
        });
    }

    function srPost(payload, btn, onDone, onError, action) {
        if (btn) srBusy(btn, true);
        // A pending background re-read would redraw the table (and the
        // busy button) mid-request; the response carries fresh data anyway.
        clearTimeout(srPeekTimer);

        $.ajax({
            url: SR_API + "staticroute/" + (action || "set") + "/" + SR_DEVICE_IP,
            method: "POST",
            headers: SR_HEADERS,
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                if (btn) srBusy(btn, false);
                alert(res.message || "Saved");
                if (res.data) renderSr(res.data, false); else loadSr('refresh');
                if (onDone) onDone(res);
            },
            error: function (xhr) {
                if (btn) srBusy(btn, false);
                const message = srErrorMessage(xhr, "Request failed");
                if (onError) onError(message); else alert(message);
            }
        });
    }

    function showSrFields() {
        const type = document.getElementById('sr_interface_type').value;
        $('.sr-field').each(function () {
            $(this).toggle(String($(this).data('types')).split('|').indexOf(type) !== -1);
        });
    }

    $('#sr_interface_type').on('change', showSrFields);

    // VLAN suggestions come from the VLAN interface list (cached read).
    function loadSrVlans() {
        $.ajax({
            url: SR_API + "vlanip/show/" + SR_DEVICE_IP + "?peek=1",
            headers: SR_HEADERS,
            success: function (res) {
                const ids = ((res.data || {}).interfaces || []).map(r => Number(r.vlan_id));
                document.getElementById('sr_vlan_list').innerHTML = ids.map(id => `<option value="${id}">`).join('');
            }
        });
    }

    // The route being edited (null while adding).
    var srEditing = null;

    // Fields of a listed route as the API takes them (identifies it for delete/edit).
    function srRouteFields(row) {
        const item = {
            dest_ip: row.destination_ip,
            dest_mask: row.destination_ip_mask,
            interface_type: row.interface_type,
            distance_metric: row.distance_metric,
            routing_tag: row.routing_tag
        };
        if (row.interface_type === 'Interface VLAN') item.interface_vlan = row.interface_vlan;
        if (row.interface_type === 'Interface VLAN' || row.interface_type === 'Null0') item.forwarding_routing_address = row.forwarding_routing_address;
        if (row.interface_type === 'Gateway IP Address') item.gateway_ip = row.gateway_ip;
        return item;
    }

    $('#btnAddSr').on('click', function () {
        srEditing = null;
        document.getElementById('sr_modal_title').textContent = 'Add Static Route';
        document.getElementById('sr_edit_note').style.display = 'none';
        ['sr_dest_ip', 'sr_interface_vlan', 'sr_forwarding_routing_address', 'sr_gateway_ip', 'sr_route_description']
            .forEach(id => document.getElementById(id).value = '');
        document.getElementById('sr_dest_mask').value = '255.255.255.0';
        document.getElementById('sr_interface_type').value = 'Gateway IP Address';
        document.getElementById('sr_distance_metric').value = 1;
        document.getElementById('sr_routing_tag').value = 1;
        document.getElementById('sr_error_modal').style.display = 'none';
        showSrFields();
        $('#srModal').modal('show');
    });

    $('#srTable tbody').on('click', '.btn-edit-sr', function () {
        const row = srTable.row($(this).data('row')).data();
        srEditing = row;

        document.getElementById('sr_modal_title').textContent = `Edit Static Route ${row.destination_ip} ${row.destination_ip_mask}`;
        document.getElementById('sr_edit_note').style.display = 'block';
        document.getElementById('sr_error_modal').style.display = 'none';

        document.getElementById('sr_dest_ip').value = row.destination_ip || '';
        const mask = document.getElementById('sr_dest_mask');
        mask.value = row.destination_ip_mask;
        if (mask.value !== row.destination_ip_mask) mask.value = '255.255.255.0';
        document.getElementById('sr_interface_type').value = row.interface_type;
        document.getElementById('sr_interface_vlan').value = row.interface_vlan ?? '';
        document.getElementById('sr_forwarding_routing_address').value = row.forwarding_routing_address || '';
        document.getElementById('sr_gateway_ip').value = row.gateway_ip || '';
        document.getElementById('sr_distance_metric').value = row.distance_metric ?? 1;
        document.getElementById('sr_routing_tag').value = row.routing_tag ?? 1;
        document.getElementById('sr_route_description').value = row.route_description || '';

        showSrFields();
        $('#srModal').modal('show');
    });

    // Bootgrid stops row clicks from bubbling past <tbody>, so bind on it.
    $('#srTable tbody').on('click', '.btn-delete-sr', function () {
        const row = srTable.row($(this).data('row')).data();
        if (!confirm(`Delete static route ${row.destination_ip} ${row.destination_ip_mask} via ${srNextHop(row).replace(/<[^>]*>/g, '')}?`)) {
            return;
        }

        const payload = {
            operation: 'delete',
            dest_ip: row.destination_ip,
            dest_mask: row.destination_ip_mask,
            interface_type: row.interface_type,
            distance_metric: row.distance_metric,
            routing_tag: row.routing_tag
        };
        if (row.interface_type === 'Interface VLAN') payload.interface_vlan = row.interface_vlan;
        if (row.interface_type === 'Interface VLAN' || row.interface_type === 'Null0') payload.forwarding_routing_address = row.forwarding_routing_address;
        if (row.interface_type === 'Gateway IP Address') payload.gateway_ip = row.gateway_ip;

        srPost(payload, this);
    });

    $('#saveSrBtn').on('click', function () {
        document.getElementById('sr_error_modal').style.display = 'none';

        const type = document.getElementById('sr_interface_type').value;
        const description = document.getElementById('sr_route_description').value.trim();

        if (!/^[A-Za-z0-9_.-]+$/.test(description)) {
            srShowError('sr_error_modal', 'Description is required and may only contain letters, digits, _ . -');
            return;
        }

        const payload = {
            operation: 'set',
            dest_ip: document.getElementById('sr_dest_ip').value.trim(),
            dest_mask: document.getElementById('sr_dest_mask').value,
            interface_type: type,
            distance_metric: document.getElementById('sr_distance_metric').value,
            routing_tag: document.getElementById('sr_routing_tag').value,
            route_description: description
        };
        if (type === 'Interface VLAN') payload.interface_vlan = document.getElementById('sr_interface_vlan').value;
        if (type === 'Interface VLAN' || type === 'Null0') payload.forwarding_routing_address = document.getElementById('sr_forwarding_routing_address').value.trim();
        if (type === 'Gateway IP Address') payload.gateway_ip = document.getElementById('sr_gateway_ip').value.trim();

        if (srEditing) {
            const old = Object.assign(srRouteFields(srEditing), { route_description: srEditing.route_description || '' });
            const changed = Object.keys(payload).filter(k => k !== 'operation' && String(payload[k] ?? '') !== String(old[k] ?? ''));
            if (!changed.length) {
                srShowError('sr_error_modal', 'Nothing was changed.');
                return;
            }

            const edit = Object.assign({}, payload, { old: old });
            delete edit.operation;

            srPost(edit, this, function () {
                srEditing = null;
                $('#srModal').modal('hide');
            }, message => srShowError('sr_error_modal', message), 'edit');
            return;
        }

        srPost(payload, this, function () {
            $('#srModal').modal('hide');
        }, message => srShowError('sr_error_modal', message));
    });

    showSrFields();
    loadSr('');
    loadSrVlans();
</script>
