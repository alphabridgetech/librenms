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
            <strong>VLAN Interface IPv6</strong>
            <span id="v6_loading" class="text-muted" style="margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> reading from device...
            </span>
            <span id="v6_cache_note" class="text-muted" style="display:none; margin-left:10px;">last known data, refreshing...</span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadV6('refresh')">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div class="panel-body">
            <button class="btn btn-primary" id="btnAddV6">
                <i class="glyphicon glyphicon-plus"></i> Configure VLAN IPv6
            </button>
            <span id="v6_mgmt_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-lock"></i> Management VLAN: <strong id="v6_mgmt_vlan">-</strong> (delete locked)
            </span>
            <div id="v6_error" class="text-danger" style="display:none; margin-top:10px;"></div>

            <div class="table-responsive">
                <table id="v6Table" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="30"></th>
                            <th width="90">VLAN</th>
                            <th width="160">IP Attribute</th>
                            <th width="100">IPv6</th>
                            <th width="220">IPv6 Address</th>
                            <th width="90">Operate</th>
                            <th width="170">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <ul>
            <li><strong>Delete removes the whole VLAN interface</strong> (<code>no interface vlan</code>), including its IPv4 address - so it is locked on the management VLAN.</li>
            <li>Auto Configuration sends <code>ipv6 address autoconfig</code> (or <code>no ipv6 address autoconfig</code> when disabled).</li>
            <li>A manual IPv6 address is added to the interface; leaving it blank keeps the current addresses. An existing address is not removed by Set.</li>
        </ul>
    </div>
</div>

<!-- Configure VLAN IPv6 Modal -->
<div id="v6Modal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="v6_modal_title">Configure VLAN IPv6</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN ID*</label>
                        <div class="col-sm-5">
                            <select id="v6_vlan_id" class="form-control"></select>
                        </div>
                        <div class="col-sm-3" id="v6_vlan_other_wrap" style="display:none; padding-left:0;">
                            <input type="number" id="v6_vlan_other" class="form-control" min="1" max="4094" placeholder="1-4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">IPv6*</label>
                        <div class="col-sm-7">
                            <select id="v6_ipv6_enable" class="form-control">
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Auto Configuration*</label>
                        <div class="col-sm-7">
                            <select id="v6_ip_attribute" class="form-control">
                                <option value="Disable">Disable</option>
                                <option value="Enable">Enable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">IPv6 Address</label>
                        <div class="col-sm-7">
                            <input type="text" id="v6_ipv6_address" class="form-control" placeholder="2001:db8:82::1/64">
                            <span class="help-block">Optional, in address/prefix form.</span>
                        </div>
                    </div>
                    <div id="v6_mgmt_warning" class="alert alert-warning" style="display:none;">
                        This is the management VLAN. IPv6 changes don't touch its IPv4 address, but make sure they can't affect your access to the switch.
                    </div>
                    <div id="v6_error_modal" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveV6Btn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const V6_DEVICE_IP = @json($device->hostname);
    const V6_API_TOKEN = @json($data['api_token']);
    const V6_API = "/api/v0/l3/";
    const V6_HEADERS = { "Authorization": "Bearer " + V6_API_TOKEN, "Accept": "application/json" };
    const V6_PREFIX = /^[0-9A-Fa-f:]+\/[0-9]{1,3}$/;

    var v6ManagementVlan = null;
    var v6KnownVlans = [];
    var v6PeekTimer = null;

    function v6Esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function v6ErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    // Modal buttons have their own spinner; the table's Delete buttons
    // don't, so they show "Deleting..." while the switch is working.
    function v6Busy(btn, busy) {
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

    function v6ShowError(id, message) {
        const box = document.getElementById(id);
        box.textContent = message;
        box.style.display = 'block';
    }

    function v6IsManagement(row) {
        return !!row && (row.management === true || (v6ManagementVlan !== null && Number(row.vlan_id) === Number(v6ManagementVlan)));
    }

    const v6Toggle = data => data === 'Enable'
        ? '<span class="label label-success">Enable</span>'
        : '<span class="label label-default">' + (v6Esc(data) || '-') + '</span>';

    var v6Batch = L3BatchDelete({
        table: () => v6Table,
        tableId: '#v6Table',
        after: '#btnAddV6',
        url: V6_API + "vlanipv6/batchdelete/" + V6_DEVICE_IP,
        headers: V6_HEADERS,
        noun: 'VLAN interfaces',
        canDelete: row => !v6IsManagement(row),
        toItem: row => ({ vlan_id: row.vlan_id }),
        describe: row => `VLAN ${row.vlan_id}` + (row.ipv6_address ? ` (${row.ipv6_address})` : ''),
        warning: 'Each one runs "no interface vlan <id>", which removes the whole interface including its IPv4 address. The management VLAN is never included.',
        onData: data => renderV6(data, false),
        reload: () => loadV6('refresh')
    });

    var v6Table = $('#v6Table').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, searchable: false, render: (row, type, full, meta) => v6Batch.checkbox(full, meta) },
            { data: "vlan_id" },
            { data: "ip_attribute", render: data => v6Esc(data) || '-' },
            { data: "ipv6_enable", render: v6Toggle },
            { data: "ipv6_address", render: data => data ? v6Esc(data) : '<span class="text-muted">none</span>' },
            {
                data: "operate",
                render: data => String(data).toLowerCase() === 'up'
                    ? '<span class="label label-success">up</span>'
                    : '<span class="label label-default">' + (v6Esc(data) || '-') + '</span>'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (row, type, full, meta) => `<button type="button" class="btn btn-xs btn-warning btn-edit-v6" data-row="${meta.row}">Edit</button> `
                    + (v6IsManagement(full)
                        ? '<span class="label label-primary" title="Carries the management address - delete locked"><i class="fa fa-lock"></i> Management</span>'
                        : `<button type="button" class="btn btn-xs btn-danger btn-delete-v6" data-row="${meta.row}">Delete</button>`)
            }
        ],
        order: [[1, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No VLAN interfaces found" }
    });

    function renderV6(d, cached) {
        d = d || {};
        v6ManagementVlan = d.management_vlan ?? null;
        v6KnownVlans = d.known_vlans || [];

        document.getElementById('v6_cache_note').style.display = cached ? 'inline' : 'none';
        document.getElementById('v6_mgmt_note').style.display = v6ManagementVlan !== null ? 'inline' : 'none';
        document.getElementById('v6_mgmt_vlan').textContent = v6ManagementVlan ?? '-';

        v6Batch.reset();
        v6Table.options.data = d.interfaces || [];
        v6Table.ajax.reload(null, false);
    }

    function loadV6(mode) {
        const loading = document.getElementById('v6_loading');
        loading.style.display = 'inline';
        document.getElementById('v6_error').style.display = 'none';
        clearTimeout(v6PeekTimer);

        $.ajax({
            url: V6_API + "vlanipv6/show/" + V6_DEVICE_IP + (mode ? "?" + mode + "=1" : ""),
            headers: V6_HEADERS,
            success: function (res) {
                loading.style.display = 'none';
                renderV6(res.data, res.cached);
                if (res.cached) v6PeekTimer = setTimeout(() => loadV6('peek'), 30000);
            },
            error: function (xhr) {
                loading.style.display = 'none';
                v6ShowError('v6_error', v6ErrorMessage(xhr, "Failed to read VLAN IPv6 configuration from device"));
            }
        });
    }

    function v6Post(payload, btn, onDone, onError) {
        if (btn) v6Busy(btn, true);
        // A pending background re-read would redraw the table (and the
        // busy button) mid-request; the response carries fresh data anyway.
        clearTimeout(v6PeekTimer);

        $.ajax({
            url: V6_API + "vlanipv6/set/" + V6_DEVICE_IP,
            method: "POST",
            headers: V6_HEADERS,
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                if (btn) v6Busy(btn, false);
                alert(res.message || "Saved");
                if (res.data) renderV6(res.data, false); else loadV6('refresh');
                if (onDone) onDone(res);
            },
            error: function (xhr) {
                if (btn) v6Busy(btn, false);
                const message = v6ErrorMessage(xhr, "Request failed");
                if (onError) onError(message); else alert(message);
            }
        });
    }

    $('#v6_vlan_id').on('change', function () {
        document.getElementById('v6_vlan_other_wrap').style.display = this.value === 'other' ? 'block' : 'none';
        document.getElementById('v6_mgmt_warning').style.display = Number(this.value) === Number(v6ManagementVlan) ? 'block' : 'none';
    });

    function openV6Modal(row) {
        const select = document.getElementById('v6_vlan_id');
        const existing = v6Table.rows().data().map(r => Number(r.vlan_id));

        if (row) {
            select.innerHTML = `<option value="${Number(row.vlan_id)}">VLAN ${Number(row.vlan_id)}</option>`;
            select.disabled = true;
        } else {
            const ids = Array.from(new Set(v6KnownVlans.concat(existing).map(Number)))
                .filter(id => id >= 1 && id <= 4094)
                .sort((a, b) => a - b);
            select.innerHTML = ids.map(id => `<option value="${id}">VLAN ${id}${existing.indexOf(id) !== -1 ? ' (has interface)' : ''}</option>`).join('')
                + '<option value="other">Other...</option>';
            select.disabled = false;
        }

        document.getElementById('v6_modal_title').textContent = row ? 'Edit VLAN ' + row.vlan_id + ' IPv6' : 'Configure VLAN IPv6';
        document.getElementById('v6_vlan_other').value = '';
        document.getElementById('v6_ipv6_enable').value = row ? (row.ipv6_enable === 'Enable' ? 'Enable' : 'Disable') : 'Enable';
        document.getElementById('v6_ip_attribute').value = row && row.autoconfig === 'Enable' ? 'Enable' : 'Disable';
        document.getElementById('v6_ipv6_address').value = row && V6_PREFIX.test(row.ipv6_address || '') ? row.ipv6_address : '';
        document.getElementById('v6_error_modal').style.display = 'none';
        $(select).trigger('change');
        $('#v6Modal').modal('show');
    }

    $('#btnAddV6').on('click', () => openV6Modal(null));

    // Bootgrid stops row clicks from bubbling past <tbody>, so bind on it.
    $('#v6Table tbody').on('click', '.btn-edit-v6', function () {
        const row = v6Table.row($(this).data('row')).data();
        if (v6IsManagement(row) && !confirm(`WARNING: VLAN ${row.vlan_id} is the management VLAN of this switch. Edit its IPv6 settings anyway?`)) {
            return;
        }
        openV6Modal(row);
    });

    $('#v6Table tbody').on('click', '.btn-delete-v6', function () {
        const row = v6Table.row($(this).data('row')).data();
        if (v6IsManagement(row)) {
            alert(`VLAN ${row.vlan_id} is the management VLAN and can't be deleted.`);
            return;
        }
        if (!confirm(`Delete the VLAN ${row.vlan_id} interface (no interface vlan ${row.vlan_id})?\nThis removes the whole interface, including its IPv4 address.`)) {
            return;
        }

        v6Post({ operation: 'delete', vlan_id: row.vlan_id }, this);
    });

    $('#saveV6Btn').on('click', function () {
        document.getElementById('v6_error_modal').style.display = 'none';

        const selected = document.getElementById('v6_vlan_id').value;
        const vlanId = selected === 'other' ? document.getElementById('v6_vlan_other').value : selected;
        const address = document.getElementById('v6_ipv6_address').value.trim();

        if (!vlanId || Number(vlanId) < 1 || Number(vlanId) > 4094) {
            v6ShowError('v6_error_modal', 'Enter a VLAN ID between 1 and 4094.');
            return;
        }
        if (address && !V6_PREFIX.test(address)) {
            v6ShowError('v6_error_modal', 'IPv6 Address must be in address/prefix form, e.g. 2001:db8:82::1/64.');
            return;
        }

        const payload = {
            operation: 'set',
            vlan_id: vlanId,
            ip_attribute: document.getElementById('v6_ip_attribute').value,
            ipv6_enable: document.getElementById('v6_ipv6_enable').value
        };
        if (address) payload.ipv6_address = address;

        v6Post(payload, this, function () {
            $('#v6Modal').modal('hide');
        }, message => v6ShowError('v6_error_modal', message));
    });

    loadV6('');
</script>
