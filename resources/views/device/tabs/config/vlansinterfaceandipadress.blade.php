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

    .l3-mgmt-row-note {
        margin-left: 6px;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')
@include('device.tabs.datatablepath.l3-batch-delete')

<div class="container-fluid l3-page" style="margin-top:30px; padding-left:0; padding-right:0;">

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>VLAN Interfaces and IP Addresses</strong>
            <span id="vip_loading" class="text-muted" style="margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> reading from device...
            </span>
            <span id="vip_cache_note" class="text-muted" style="display:none; margin-left:10px;">last known data, refreshing...</span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadVip('refresh')">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div class="panel-body">
            <button class="btn btn-primary" id="btnAddVip">
                <i class="glyphicon glyphicon-plus"></i> Configure VLAN Interface
            </button>
            <span id="vip_mgmt_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-lock"></i> Management VLAN: <strong id="vip_mgmt_vlan">-</strong> (locked)
            </span>
            <div id="vip_error" class="text-danger" style="display:none; margin-top:10px;"></div>

            <div class="table-responsive">
                <table id="vipTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="30"></th>
                            <th width="90">VLAN</th>
                            <th width="170">IP Attribute</th>
                            <th width="150">IP Address</th>
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
            <li>The VLAN that carries this switch's management address (<code>{{ $device->hostname }}</code>) is locked: it can't be deleted or edited here, because the set playbook removes the primary IP before re-adding it, which would cut this server off from the switch.</li>
            <li>Delete removes the whole VLAN interface (<code>no interface vlan</code>), including any IPv6 configuration on it.</li>
            <li>The switch only reports the primary IP address - not its mask or the secondary addresses - so Edit starts the mask at /24. Set every value you want.</li>
            <li>Setting a VLAN replaces its primary IP. Secondary addresses are added; existing ones are not removed.</li>
        </ul>
    </div>
</div>

<!-- Configure VLAN Interface Modal -->
<div id="vipModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="vip_modal_title">Configure VLAN Interface</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN ID*</label>
                        <div class="col-sm-5">
                            <select id="vip_vlan_id" class="form-control"></select>
                        </div>
                        <div class="col-sm-3" id="vip_vlan_other_wrap" style="display:none; padding-left:0;">
                            <input type="number" id="vip_vlan_other" class="form-control" min="1" max="4094" placeholder="1-4094">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">IP Attribute*</label>
                        <div class="col-sm-7">
                            <select id="vip_ip_attribute" class="form-control">
                                <option value="Manual configuration">Manual configuration</option>
                                <option value="DHCP Auto configuration">DHCP Auto configuration</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group vip-manual">
                        <label class="col-sm-4 control-label">Primary IP*</label>
                        <div class="col-sm-4">
                            <input type="text" id="vip_ip_address" class="form-control" placeholder="192.168.1.1">
                        </div>
                        <div class="col-sm-4" style="padding-left:0;">
                            <select id="vip_ip_mask" class="form-control vip-mask"></select>
                        </div>
                    </div>
                    @foreach ([1, 2] as $n)
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Secondary IP {{ $n }}</label>
                            <div class="col-sm-4">
                                <input type="text" id="vip_secondary_ip_address_{{ $n }}" class="form-control" placeholder="optional">
                            </div>
                            <div class="col-sm-4" style="padding-left:0;">
                                <select id="vip_secondary_ip_mask_{{ $n }}" class="form-control vip-mask"></select>
                            </div>
                        </div>
                    @endforeach
                    <div id="vip_error_modal" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveVipBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const VIP_DEVICE_IP = @json($device->hostname);
    const VIP_API_TOKEN = @json($data['api_token']);
    const VIP_API = "/api/v0/l3/";
    const VIP_HEADERS = { "Authorization": "Bearer " + VIP_API_TOKEN, "Accept": "application/json" };
    // The management VLAN is the one whose IP is one of these addresses.
    const VIP_MGMT_IPS = @json(array_values(array_unique(array_filter([$device->hostname, $device->ip, $device->overwrite_ip]))));

    var vipManagementVlan = null;
    var vipKnownVlans = [];
    var vipPeekTimer = null;

    function vipEsc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    // Laravel returns 422 { message, errors: {field: [msg]} } on validation
    // failure - show the first field error rather than the generic message.
    function vipErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    // Modal buttons have their own spinner; the table's Delete buttons
    // don't, so they show "Deleting..." while the switch is working.
    function vipBusy(btn, busy) {
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

    function vipShowError(id, message) {
        const box = document.getElementById(id);
        box.textContent = message;
        box.style.display = 'block';
    }

    function vipIsManagement(row) {
        return !!row && (row.management === true
            || (row.ip_address && VIP_MGMT_IPS.indexOf(row.ip_address) !== -1)
            || (vipManagementVlan !== null && Number(row.vlan_id) === Number(vipManagementVlan)));
    }

    // Dotted masks /32 ... /1, /24 selected by default.
    (function buildMasks() {
        let options = '';
        for (let prefix = 32; prefix >= 1; prefix--) {
            const bits = prefix === 0 ? 0 : (0xFFFFFFFF << (32 - prefix)) >>> 0;
            const mask = [24, 16, 8, 0].map(s => (bits >>> s) & 255).join('.');
            options += `<option value="${mask}" ${prefix === 24 ? 'selected' : ''}>${mask} (/${prefix})</option>`;
        }
        $('.vip-mask').html(options);
    })();

    var vipBatch = L3BatchDelete({
        table: () => vipTable,
        tableId: '#vipTable',
        after: '#btnAddVip',
        url: VIP_API + "vlanip/batchdelete/" + VIP_DEVICE_IP,
        headers: VIP_HEADERS,
        noun: 'VLAN interfaces',
        canDelete: row => !vipIsManagement(row),
        toItem: row => ({ vlan_id: row.vlan_id }),
        describe: row => `VLAN ${row.vlan_id}` + (row.ip_address ? ` (${row.ip_address})` : ''),
        warning: 'Each one runs "no interface vlan <id>": its IPv4 and IPv6 addresses are removed. The management VLAN is never included.',
        onData: data => renderVip(data, false),
        reload: () => loadVip('refresh')
    });

    var vipTable = $('#vipTable').DataTable({
        data: [],
        columns: [
            { data: null, orderable: false, searchable: false, render: (row, type, full, meta) => vipBatch.checkbox(full, meta) },
            { data: "vlan_id" },
            { data: "ip_attribute", render: data => vipEsc(data) || '-' },
            { data: "ip_address", render: data => data ? vipEsc(data) : '<span class="text-muted">none</span>' },
            {
                data: "operate",
                render: data => String(data).toLowerCase() === 'up'
                    ? '<span class="label label-success">up</span>'
                    : '<span class="label label-default">' + (vipEsc(data) || '-') + '</span>'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (row, type, full, meta) => vipIsManagement(full)
                    ? `<button type="button" class="btn btn-xs btn-warning btn-edit-vip" data-row="${meta.row}"><i class="fa fa-lock"></i> Edit</button>
                       <span class="label label-primary l3-mgmt-row-note" title="Carries the management address - locked">Management</span>`
                    : `<button type="button" class="btn btn-xs btn-warning btn-edit-vip" data-row="${meta.row}">Edit</button>
                       <button type="button" class="btn btn-xs btn-danger btn-delete-vip" data-row="${meta.row}">Delete</button>`
            }
        ],
        order: [[1, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No VLAN interfaces found" }
    });

    function renderVip(d, cached) {
        d = d || {};
        vipManagementVlan = d.management_vlan ?? null;
        vipKnownVlans = d.known_vlans || [];

        document.getElementById('vip_cache_note').style.display = cached ? 'inline' : 'none';
        document.getElementById('vip_mgmt_note').style.display = vipManagementVlan !== null ? 'inline' : 'none';
        document.getElementById('vip_mgmt_vlan').textContent = vipManagementVlan ?? '-';

        vipBatch.reset();
        vipTable.options.data = d.interfaces || [];
        vipTable.ajax.reload(null, false);
    }

    // mode: '' (cached + background refresh), 'refresh' (live), 'peek'
    // (re-read the file the background refresh wrote).
    function loadVip(mode) {
        const loading = document.getElementById('vip_loading');
        loading.style.display = 'inline';
        document.getElementById('vip_error').style.display = 'none';
        clearTimeout(vipPeekTimer);

        $.ajax({
            url: VIP_API + "vlanip/show/" + VIP_DEVICE_IP + (mode ? "?" + mode + "=1" : ""),
            headers: VIP_HEADERS,
            success: function (res) {
                loading.style.display = 'none';
                renderVip(res.data, res.cached);
                // Pick up the background refresh once it has had time to finish.
                if (res.cached) vipPeekTimer = setTimeout(() => loadVip('peek'), 25000);
            },
            error: function (xhr) {
                loading.style.display = 'none';
                vipShowError('vip_error', vipErrorMessage(xhr, "Failed to read VLAN interfaces from device"));
            }
        });
    }

    function vipPost(payload, btn, onDone, onError) {
        if (btn) vipBusy(btn, true);
        // A pending background re-read would redraw the table (and the
        // busy button) mid-request; the response carries fresh data anyway.
        clearTimeout(vipPeekTimer);

        $.ajax({
            url: VIP_API + "vlanip/set/" + VIP_DEVICE_IP,
            method: "POST",
            headers: VIP_HEADERS,
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                if (btn) vipBusy(btn, false);
                alert(res.message || "Saved");
                if (res.data) renderVip(res.data, false); else loadVip('refresh');
                if (onDone) onDone(res);
            },
            error: function (xhr) {
                if (btn) vipBusy(btn, false);
                const message = vipErrorMessage(xhr, "Request failed");
                if (onError) onError(message); else alert(message);
            }
        });
    }

    function showVipFields() {
        $('.vip-manual').toggle(document.getElementById('vip_ip_attribute').value === 'Manual configuration');
        document.getElementById('vip_vlan_other_wrap').style.display =
            document.getElementById('vip_vlan_id').value === 'other' ? 'block' : 'none';
    }

    $('#vip_ip_attribute, #vip_vlan_id').on('change', showVipFields);

    function openVipModal(row) {
        const select = document.getElementById('vip_vlan_id');
        const existing = vipTable.rows().data().map(r => Number(r.vlan_id));

        if (row) {
            select.innerHTML = `<option value="${Number(row.vlan_id)}">VLAN ${Number(row.vlan_id)}</option>`;
            select.disabled = true;
        } else {
            // VLANs from the switch's VLAN list (and existing interfaces),
            // without the management VLAN.
            const ids = Array.from(new Set(vipKnownVlans.concat(existing).map(Number)))
                .filter(id => id >= 1 && id <= 4094 && id !== Number(vipManagementVlan))
                .sort((a, b) => a - b);
            select.innerHTML = ids.map(id => `<option value="${id}">VLAN ${id}${existing.indexOf(id) !== -1 ? ' (has interface)' : ''}</option>`).join('')
                + '<option value="other">Other...</option>';
            select.disabled = false;
        }

        document.getElementById('vip_modal_title').textContent = row ? 'Edit VLAN ' + row.vlan_id + ' Interface' : 'Configure VLAN Interface';
        document.getElementById('vip_vlan_other').value = '';
        document.getElementById('vip_ip_attribute').value = row && /dhcp/i.test(row.ip_attribute) ? 'DHCP Auto configuration' : 'Manual configuration';
        document.getElementById('vip_ip_address').value = row ? (row.ip_address || '') : '';
        ['vip_ip_mask', 'vip_secondary_ip_mask_1', 'vip_secondary_ip_mask_2'].forEach(id => document.getElementById(id).value = '255.255.255.0');
        document.getElementById('vip_secondary_ip_address_1').value = '';
        document.getElementById('vip_secondary_ip_address_2').value = '';
        document.getElementById('vip_error_modal').style.display = 'none';
        showVipFields();
        $('#vipModal').modal('show');
    }

    $('#btnAddVip').on('click', () => openVipModal(null));

    // Bootgrid stops row clicks from bubbling past <tbody>, so bind on it.
    $('#vipTable tbody').on('click', '.btn-edit-vip', function () {
        const row = vipTable.row($(this).data('row')).data();

        if (vipIsManagement(row)) {
            alert(`WARNING: VLAN ${row.vlan_id} carries the management address ${row.ip_address || VIP_DEVICE_IP} that this server uses to reach the switch.\n\n`
                + `It is locked: the set playbook removes the primary IP ("no ip address") before re-adding it, which would drop the connection and leave the switch unreachable. Change it from the switch console instead.`);
            return;
        }

        openVipModal(row);
    });

    $('#vipTable tbody').on('click', '.btn-delete-vip', function () {
        const row = vipTable.row($(this).data('row')).data();
        if (vipIsManagement(row)) {
            alert(`VLAN ${row.vlan_id} is the management VLAN and can't be deleted.`);
            return;
        }
        if (!confirm(`Delete the VLAN ${row.vlan_id} interface (no interface vlan ${row.vlan_id})?\nIts IPv4 and IPv6 addresses are removed.`)) {
            return;
        }

        vipPost({ operation: 'delete', vlan_id: row.vlan_id }, this);
    });

    $('#saveVipBtn').on('click', function () {
        document.getElementById('vip_error_modal').style.display = 'none';

        const selected = document.getElementById('vip_vlan_id').value;
        const vlanId = selected === 'other' ? document.getElementById('vip_vlan_other').value : selected;
        const attribute = document.getElementById('vip_ip_attribute').value;

        if (!vlanId || Number(vlanId) < 1 || Number(vlanId) > 4094) {
            vipShowError('vip_error_modal', 'Enter a VLAN ID between 1 and 4094.');
            return;
        }
        if (vipManagementVlan !== null && Number(vlanId) === Number(vipManagementVlan)) {
            vipShowError('vip_error_modal', `VLAN ${vlanId} is the management VLAN and is locked.`);
            return;
        }

        const payload = { operation: 'set', vlan_id: vlanId, ip_attribute: attribute };
        if (attribute === 'Manual configuration') {
            payload.ip_address = document.getElementById('vip_ip_address').value.trim();
            payload.ip_mask = document.getElementById('vip_ip_mask').value;
        }
        [1, 2].forEach(function (n) {
            const ip = document.getElementById('vip_secondary_ip_address_' + n).value.trim();
            if (ip) {
                payload['secondary_ip_address_' + n] = ip;
                payload['secondary_ip_mask_' + n] = document.getElementById('vip_secondary_ip_mask_' + n).value;
            }
        });

        const all = [payload.ip_address, payload.secondary_ip_address_1, payload.secondary_ip_address_2];
        if (all.some(ip => ip && VIP_MGMT_IPS.indexOf(ip) !== -1)) {
            vipShowError('vip_error_modal', 'That is the management address of this switch.');
            return;
        }

        vipPost(payload, this, function () {
            $('#vipModal').modal('hide');
        }, message => vipShowError('vip_error_modal', message));
    });

    loadVip('');
</script>
