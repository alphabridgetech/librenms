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

    .r6-summary td:first-child {
        width: 55%;
        color: #777;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')
@include('device.tabs.datatablepath.l3-batch-delete')

<div class="container-fluid l3-page" style="margin-top:30px; padding-left:0; padding-right:0;">

    <div class="row">
        <div class="col-md-5">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>Current Global Settings</strong>
                    <span id="r6_loading" class="text-muted" style="margin-left:10px;">
                        <i class="fa fa-spinner fa-spin"></i> reading from device (about a minute)...
                    </span>
                    <span id="r6_cache_note" class="text-muted" style="display:none; margin-left:10px;">last known data, refreshing...</span>
                    <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadR6('refresh')">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
                <table class="table table-condensed r6-summary" style="margin-bottom:0;">
                    <tr><td>Maximum Equal-Cost Paths</td><td id="r6_cur_equal_cost_paths">-</td></tr>
                    <tr><td>Maximum Number of Routes</td><td id="r6_cur_max_number_routes">-</td></tr>
                    <tr><td>Maximum Static Routes</td><td id="r6_cur_max_static_routes">-</td></tr>
                    <tr><td>Maximum Dynamic Routes</td><td id="r6_cur_max_dynamic_routes">-</td></tr>
                    <tr><td>Unlimited Prefix Length</td><td id="r6_cur_prefixlen_unlimited">-</td></tr>
                </table>
                <div id="r6_error" class="text-danger" style="display:none; padding:8px 15px;"></div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="panel panel-info">
                <div class="panel-heading"><strong>Global IPv6 Route Settings</strong></div>
                <div class="panel-body">
                    <form class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-5 control-label">Maximum Equal-Cost Paths*</label>
                            <div class="col-sm-6">
                                <input type="number" id="r6_equal_cost_paths" class="form-control" min="1" max="8" value="4">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-5 control-label">Maximum Number of Routes*</label>
                            <div class="col-sm-6">
                                <input type="number" id="r6_max_number_routes" class="form-control" min="64" max="640000" value="633000">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-5 control-label">Maximum Static Routes*</label>
                            <div class="col-sm-6">
                                <input type="number" id="r6_max_static_routes" class="form-control" min="1" max="32000" value="3000">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-5 control-label">Maximum Dynamic Routes*</label>
                            <div class="col-sm-6">
                                <input type="number" id="r6_max_dynamic_routes" class="form-control" min="1" max="128000" value="12800">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-5 control-label">Unlimited Prefix Length*</label>
                            <div class="col-sm-6">
                                <select id="r6_prefixlen_unlimited" class="form-control">
                                    <option value="enable">Enable</option>
                                    <option value="disable">Disable</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-5 control-label">Apply with Route*</label>
                            <div class="col-sm-6">
                                <select id="r6_global_route" class="form-control"></select>
                                <span class="help-block" id="r6_global_route_help">The switch playbook sets the global values together with a route - pick an existing route to re-apply.</span>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <div class="col-sm-offset-5 col-sm-6">
                                <button type="button" id="r6GlobalApplyBtn" class="btn btn-primary" onclick="applyR6Global()">
                                    <span class="spinner-border" style="display:none;"></span> Apply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <h4>IPv6 Static Routes</h4>
    <button class="btn btn-primary" id="btnAddR6">
        <i class="glyphicon glyphicon-plus"></i> Add IPv6 Route
    </button>

    <div class="table-responsive">
        <table id="r6Table" class="table table-striped table-bordered table-condensed" style="width:100%;">
            <thead>
                <tr>
                    <th width="30"></th>
                    <th>IPv6 Prefix</th>
                    <th>Gateway</th>
                    <th width="80">Actions</th>
                </tr>
            </thead>
        </table>
    </div>

    <div class="alert alert-info" style="margin-top:20px;">
        <ul>
            <li>Reading the IPv6 route configuration takes about a minute, and every change re-reads it.</li>
            <li>Adding a route also applies the global settings from the form above; Apply re-sends the selected route with the new global settings.</li>
            <li>Delete removes only that route; the global settings are not changed.</li>
        </ul>
    </div>
</div>

<!-- Add IPv6 Route Modal -->
<div id="r6Modal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add IPv6 Route</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">IPv6 Prefix*</label>
                        <div class="col-sm-7">
                            <input type="text" id="r6_ipv6_address" class="form-control" placeholder="2001:db8:82::/64">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Gateway*</label>
                        <div class="col-sm-7">
                            <input type="text" id="r6_ipv6_gateway" class="form-control" placeholder="2001:db8:82::1">
                        </div>
                    </div>
                    <p class="text-muted" id="r6_modal_globals"></p>
                    <div id="r6_error_modal" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveR6Btn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const R6_DEVICE_IP = @json($device->hostname);
    const R6_API_TOKEN = @json($data['api_token']);
    const R6_API = "/api/v0/l3/";
    const R6_HEADERS = { "Authorization": "Bearer " + R6_API_TOKEN, "Accept": "application/json" };
    const R6_PREFIX = /^[0-9A-Fa-f:]+\/[0-9]{1,3}$/;
    const R6_ADDRESS = /^[0-9A-Fa-f:]+$/;
    const R6_GLOBAL_FIELDS = ['equal_cost_paths', 'max_number_routes', 'max_static_routes', 'max_dynamic_routes', 'prefixlen_unlimited'];

    var r6PeekTimer = null;

    function r6Esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function r6ErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    // Modal buttons have their own spinner; the table's Delete buttons
    // don't, so they show "Deleting..." while the switch is working.
    function r6Busy(btn, busy) {
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

    function r6ShowError(id, message) {
        const box = document.getElementById(id);
        box.textContent = message;
        box.style.display = 'block';
    }

    function r6Text(id, value) {
        document.getElementById(id).textContent = (value === null || value === undefined || value === '') ? '-' : value;
    }

    function r6Globals() {
        const values = {};
        R6_GLOBAL_FIELDS.forEach(f => values[f] = document.getElementById('r6_' + f).value);
        return values;
    }

    var r6Batch = L3BatchDelete({
        table: () => r6Table,
        tableId: '#r6Table',
        after: '#btnAddR6',
        url: R6_API + "ipv6route/batchdelete/" + R6_DEVICE_IP,
        headers: R6_HEADERS,
        noun: 'IPv6 routes',
        canDelete: row => !!row.ipv6_address && !!row.ipv6_gateway,
        toItem: row => ({ ipv6_address: row.ipv6_address, ipv6_gateway: row.ipv6_gateway }),
        describe: row => `${row.ipv6_address} via ${row.ipv6_gateway}`,
        onData: data => renderR6(data, false),
        reload: () => loadR6('refresh')
    });

    var r6Table = $('#r6Table').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, searchable: false, render: (row, type, full, meta) => r6Batch.checkbox(full, meta) },
            { data: "ipv6_address", render: data => r6Esc(data) },
            { data: "ipv6_gateway", render: data => r6Esc(data) },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (row, type, full, meta) => `<button type="button" class="btn btn-xs btn-danger btn-delete-r6" data-row="${meta.row}">Delete</button>`
            }
        ],
        order: [[1, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No IPv6 routes configured" }
    });

    function renderR6(d, cached) {
        d = d || {};
        const g = d.global || {};
        const routes = d.routes || [];

        document.getElementById('r6_cache_note').style.display = cached ? 'inline' : 'none';

        r6Text('r6_cur_equal_cost_paths', g.equal_cost_paths);
        r6Text('r6_cur_max_number_routes', g.max_number_routes);
        r6Text('r6_cur_max_static_routes', g.max_static_routes);
        r6Text('r6_cur_max_dynamic_routes', g.max_dynamic_routes);
        r6Text('r6_cur_prefixlen_unlimited', g.prefixlen_unlimited === 'enable' ? 'Enable' : 'Disable');

        // Values the switch doesn't report keep the form's current value.
        R6_GLOBAL_FIELDS.forEach(function (f) {
            if (g[f] !== null && g[f] !== undefined && g[f] !== '') document.getElementById('r6_' + f).value = g[f];
        });

        const select = document.getElementById('r6_global_route');
        select.innerHTML = routes.map((r, i) => `<option value="${i}">${r6Esc(r.ipv6_address)} via ${r6Esc(r.ipv6_gateway)}</option>`).join('');
        select.disabled = routes.length === 0;
        document.getElementById('r6GlobalApplyBtn').disabled = routes.length === 0;
        document.getElementById('r6_global_route_help').textContent = routes.length
            ? 'The switch playbook sets the global values together with a route - the selected route is re-applied.'
            : 'No route yet - add one below; it is applied together with these global settings.';

        r6Batch.reset();
        r6Table.options.data = routes;
        r6Table.ajax.reload(null, false);
    }

    function loadR6(mode) {
        const loading = document.getElementById('r6_loading');
        loading.style.display = 'inline';
        document.getElementById('r6_error').style.display = 'none';
        clearTimeout(r6PeekTimer);

        $.ajax({
            url: R6_API + "ipv6route/show/" + R6_DEVICE_IP + (mode ? "?" + mode + "=1" : ""),
            headers: R6_HEADERS,
            success: function (res) {
                loading.style.display = 'none';
                renderR6(res.data, res.cached);
                // The IPv6 route read takes ~70 s - peek once it should be done.
                if (res.cached) r6PeekTimer = setTimeout(() => loadR6('peek'), 80000);
            },
            error: function (xhr) {
                loading.style.display = 'none';
                r6ShowError('r6_error', r6ErrorMessage(xhr, "Failed to read IPv6 route configuration from device"));
            }
        });
    }

    function r6Post(payload, btn, onDone, onError) {
        if (btn) r6Busy(btn, true);
        // A pending background re-read would redraw the table (and the
        // busy button) mid-request; the response carries fresh data anyway.
        clearTimeout(r6PeekTimer);

        $.ajax({
            url: R6_API + "ipv6route/set/" + R6_DEVICE_IP,
            method: "POST",
            headers: R6_HEADERS,
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                if (btn) r6Busy(btn, false);
                alert(res.message || "Saved");
                if (res.data) renderR6(res.data, false); else loadR6('refresh');
                if (onDone) onDone(res);
            },
            error: function (xhr) {
                if (btn) r6Busy(btn, false);
                const message = r6ErrorMessage(xhr, "Request failed");
                if (onError) onError(message); else alert(message);
            }
        });
    }

    function applyR6Global() {
        const route = r6Table.rows().data()[Number(document.getElementById('r6_global_route').value)];
        if (!route) {
            alert('Add an IPv6 route first - the global settings are applied together with a route.');
            return;
        }
        if (!confirm(`Apply the global IPv6 route settings (re-applying route ${route.ipv6_address} via ${route.ipv6_gateway})?`)) {
            return;
        }

        r6Post(Object.assign({
            operation: 'set',
            ipv6_address: route.ipv6_address,
            ipv6_gateway: route.ipv6_gateway
        }, r6Globals()), document.getElementById('r6GlobalApplyBtn'));
    }

    $('#btnAddR6').on('click', function () {
        const g = r6Globals();
        document.getElementById('r6_ipv6_address').value = '';
        document.getElementById('r6_ipv6_gateway').value = '';
        document.getElementById('r6_modal_globals').textContent =
            `Also applies the global settings from the form: equal-cost paths ${g.equal_cost_paths}, max routes ${g.max_number_routes}, `
            + `static ${g.max_static_routes}, dynamic ${g.max_dynamic_routes}, unlimited prefix length ${g.prefixlen_unlimited}.`;
        document.getElementById('r6_error_modal').style.display = 'none';
        $('#r6Modal').modal('show');
    });

    // Bootgrid stops row clicks from bubbling past <tbody>, so bind on it.
    $('#r6Table tbody').on('click', '.btn-delete-r6', function () {
        const row = r6Table.row($(this).data('row')).data();
        if (!confirm(`Delete IPv6 route ${row.ipv6_address} via ${row.ipv6_gateway}?`)) {
            return;
        }

        r6Post({ operation: 'delete', ipv6_address: row.ipv6_address, ipv6_gateway: row.ipv6_gateway }, this);
    });

    $('#saveR6Btn').on('click', function () {
        document.getElementById('r6_error_modal').style.display = 'none';

        const address = document.getElementById('r6_ipv6_address').value.trim();
        const gateway = document.getElementById('r6_ipv6_gateway').value.trim();

        if (!R6_PREFIX.test(address)) {
            r6ShowError('r6_error_modal', 'IPv6 Prefix must look like 2001:db8:82::/64.');
            return;
        }
        if (!R6_ADDRESS.test(gateway)) {
            r6ShowError('r6_error_modal', 'Gateway must be an IPv6 address such as 2001:db8:82::1.');
            return;
        }

        r6Post(Object.assign({ operation: 'set', ipv6_address: address, ipv6_gateway: gateway }, r6Globals()), this, function () {
            $('#r6Modal').modal('hide');
        }, message => r6ShowError('r6_error_modal', message));
    });

    loadR6('');
</script>
