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

    .igp-page .table-responsive {
        margin-top: 15px;
        border: none;
    }

    .igp-state {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 10px;
    }

    .igp-state-item {
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 8px 12px;
        min-width: 260px;
    }

    .igp-state-item strong {
        min-width: 110px;
    }

    .igp-state-item .label {
        font-size: 12px;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')

<div class="container-fluid igp-page" style="margin-top:30px; padding-left:0; padding-right:0;">

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>IGMP Proxy</strong>
            <span id="igp_loading" class="text-muted" style="margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> reading from device...
            </span>
            <span id="igp_cache_note" class="text-muted" style="display:none; margin-left:10px;">last known data, refreshing...</span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadIgp('refresh')">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div class="panel-body">
            <div class="igp-state">
                <div class="igp-state-item">
                    <strong>IGMP Snooping</strong>
                    <span id="igp_snooping" class="label label-default">-</span>
                    <button type="button" class="btn btn-xs btn-default igp-toggle" data-field="igmp_snooping" id="igp_snooping_btn" disabled>-</button>
                </div>
                <div class="igp-state-item">
                    <strong>IGMP Proxy</strong>
                    <span id="igp_proxy" class="label label-default">-</span>
                    <button type="button" class="btn btn-xs btn-default igp-toggle" data-field="igmp_proxy_status" id="igp_proxy_btn" disabled>-</button>
                </div>
            </div>

            <button class="btn btn-primary" id="btnAddIgp">
                <i class="glyphicon glyphicon-plus"></i> Add VLAN Map
            </button>
            <div id="igp_error" class="text-danger" style="display:none; margin-top:10px;"></div>

            <div class="table-responsive">
                <table id="igpTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="150">Agent VLAN</th>
                            <th width="150">Client VLAN</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <ul>
            <li>IGMP proxy answers IGMP for the hosts in a <strong>client VLAN</strong> and forwards their joins/leaves to the <strong>agent VLAN</strong> (the upstream / multicast source side).</li>
            <li>IGMP proxy needs IGMP snooping. Adding a map turns snooping on, and disabling snooping also disables the proxy.</li>
            <li>Adding a map creates the agent and client VLANs on the switch if they don't exist yet.</li>
        </ul>
    </div>
</div>

<!-- Add VLAN Map Modal -->
<div id="igpModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add IGMP Proxy VLAN Map</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    @foreach (['agent' => 'Agent VLAN', 'client' => 'Client VLAN'] as $key => $label)
                        <div class="form-group">
                            <label class="col-sm-4 control-label">{{ $label }}*</label>
                            <div class="col-sm-5">
                                <select id="igp_{{ $key }}_vlan" class="form-control igp-vlan-select" data-other="#igp_{{ $key }}_other_wrap"></select>
                            </div>
                            <div class="col-sm-3" id="igp_{{ $key }}_other_wrap" style="display:none; padding-left:0;">
                                <input type="number" id="igp_{{ $key }}_other" class="form-control" min="1" max="4094" placeholder="1-4094">
                            </div>
                        </div>
                    @endforeach
                    <div class="form-group">
                        <div class="col-sm-offset-4 col-sm-8">
                            <label style="font-weight:normal;">
                                <input type="checkbox" id="igp_enable_proxy" checked> Also enable IGMP proxy
                            </label>
                        </div>
                    </div>
                    <div id="igp_error_modal" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveIgpBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const IGP_DEVICE_IP = @json($device->hostname);
    const IGP_API = "/api/v0/l3/igmpproxy/";
    const IGP_HEADERS = { "Authorization": "Bearer " + @json($data['api_token']), "Accept": "application/json" };

    var igpState = { igmp_snooping: 'unknown', igmp_proxy: 'unknown' };
    var igpKnownVlans = [];
    var igpPeekTimer = null;

    function igpEsc(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function igpErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    function igpShowError(id, message) {
        const box = document.getElementById(id);
        box.textContent = message;
        box.style.display = 'block';
    }

    function igpBusy(btn, busy, text) {
        const $btn = $(btn);
        if ($btn.find('.spinner-border').length) {
            $btn.find('.spinner-border').toggle(busy);
        } else if (busy) {
            $btn.data('label', $btn.html()).html('<i class="fa fa-spinner fa-spin"></i> ' + (text || 'Working...'));
        } else if ($btn.data('label') !== undefined) {
            $btn.html($btn.data('label'));
        }
        $btn.prop('disabled', busy);
    }

    var igpTable = $('#igpTable').DataTable({
        data: [],
        columns: [
            { data: "agent_vlan", render: d => 'VLAN ' + igpEsc(d) },
            { data: "client_vlan", render: d => 'VLAN ' + igpEsc(d) },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (row, type, full, meta) => `<button type="button" class="btn btn-xs btn-danger btn-delete-igp" data-row="${meta.row}">Delete</button>`
            }
        ],
        order: [[0, "asc"], [1, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No IGMP proxy VLAN maps" }
    });

    function igpStateLabel(id, btnId, value) {
        const on = value === 'enabled';
        const known = value === 'enabled' || value === 'disabled';
        const label = document.getElementById(id);
        label.className = 'label ' + (on ? 'label-success' : (known ? 'label-default' : 'label-warning'));
        label.textContent = known ? value : 'unknown';

        const btn = document.getElementById(btnId);
        btn.disabled = false;
        btn.className = 'btn btn-xs igp-toggle ' + (on ? 'btn-danger' : 'btn-success');
        btn.textContent = on ? 'Disable' : 'Enable';
        btn.dataset.target = on ? 'disable' : 'enable';
    }

    function renderIgp(d, cached) {
        d = d || {};
        igpState = { igmp_snooping: d.igmp_snooping || 'unknown', igmp_proxy: d.igmp_proxy || 'unknown' };
        igpKnownVlans = d.known_vlans || [];

        document.getElementById('igp_cache_note').style.display = cached ? 'inline' : 'none';
        igpStateLabel('igp_snooping', 'igp_snooping_btn', igpState.igmp_snooping);
        igpStateLabel('igp_proxy', 'igp_proxy_btn', igpState.igmp_proxy);

        igpTable.options.data = d.mappings || [];
        igpTable.ajax.reload(null, false);
    }

    // mode: '' (cached + background refresh), 'refresh' (live), 'peek'
    // (re-read the file the background refresh wrote).
    function loadIgp(mode) {
        const loading = document.getElementById('igp_loading');
        loading.style.display = 'inline';
        document.getElementById('igp_error').style.display = 'none';
        clearTimeout(igpPeekTimer);

        $.ajax({
            url: IGP_API + "show/" + IGP_DEVICE_IP + (mode ? "?" + mode + "=1" : ""),
            headers: IGP_HEADERS,
            success: function (res) {
                loading.style.display = 'none';
                renderIgp(res.data, res.cached);
                if (res.cached) igpPeekTimer = setTimeout(() => loadIgp('peek'), 25000);
            },
            error: function (xhr) {
                loading.style.display = 'none';
                igpShowError('igp_error', igpErrorMessage(xhr, "Failed to read IGMP proxy configuration from the device"));
            }
        });
    }

    function igpPost(payload, btn, busyText, onDone, onError) {
        if (btn) igpBusy(btn, true, busyText);
        clearTimeout(igpPeekTimer);

        $.ajax({
            url: IGP_API + "set/" + IGP_DEVICE_IP,
            method: "POST",
            headers: IGP_HEADERS,
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                if (btn) igpBusy(btn, false);
                if (res.data) renderIgp(res.data, false); else loadIgp('refresh');
                alert(res.message || "Saved");
                if (onDone) onDone(res);
            },
            error: function (xhr) {
                if (btn) igpBusy(btn, false);
                const message = igpErrorMessage(xhr, "Request failed");
                if (onError) onError(message); else alert(message);
            }
        });
    }

    // Enable / Disable buttons for snooping and proxy
    $('.igp-toggle').on('click', function () {
        const field = this.dataset.field;
        const target = this.dataset.target;
        const name = field === 'igmp_snooping' ? 'IGMP snooping' : 'IGMP proxy';

        if (field === 'igmp_snooping' && target === 'disable'
            && !confirm('Disable IGMP snooping?\nIGMP proxy is disabled with it.')) {
            return;
        }
        if (field === 'igmp_proxy_status' && target === 'disable' && !confirm('Disable IGMP proxy?')) {
            return;
        }

        igpPost({ [field]: target }, this, (target === 'enable' ? 'Enabling ' : 'Disabling ') + name + '...');
    });

    function igpVlanOptions(select) {
        const ids = Array.from(new Set(igpKnownVlans.map(Number)))
            .filter(id => id >= 1 && id <= 4094)
            .sort((a, b) => a - b);
        select.innerHTML = ids.map(id => `<option value="${id}">VLAN ${id}</option>`).join('')
            + '<option value="other">Other...</option>';
    }

    $('.igp-vlan-select').on('change', function () {
        $(this.dataset.other).toggle(this.value === 'other');
    });

    function igpSelectedVlan(key) {
        const value = document.getElementById('igp_' + key + '_vlan').value;
        return value === 'other' ? document.getElementById('igp_' + key + '_other').value : value;
    }

    $('#btnAddIgp').on('click', function () {
        ['agent', 'client'].forEach(function (key) {
            const select = document.getElementById('igp_' + key + '_vlan');
            igpVlanOptions(select);
            document.getElementById('igp_' + key + '_other').value = '';
            $(select).trigger('change');
        });
        // Pick a different VLAN for the client by default
        const client = document.getElementById('igp_client_vlan');
        if (client.options.length > 2) client.selectedIndex = 1;
        $(client).trigger('change');

        document.getElementById('igp_enable_proxy').checked = igpState.igmp_proxy !== 'enabled';
        document.getElementById('igp_enable_proxy').closest('.form-group').style.display = igpState.igmp_proxy === 'enabled' ? 'none' : '';
        document.getElementById('igp_error_modal').style.display = 'none';
        $('#igpModal').modal('show');
    });

    $('#saveIgpBtn').on('click', function () {
        document.getElementById('igp_error_modal').style.display = 'none';

        const agent = Number(igpSelectedVlan('agent'));
        const client = Number(igpSelectedVlan('client'));

        if (!Number.isInteger(agent) || agent < 1 || agent > 4094 || !Number.isInteger(client) || client < 1 || client > 4094) {
            igpShowError('igp_error_modal', 'Enter VLAN IDs between 1 and 4094.');
            return;
        }
        if (agent === client) {
            igpShowError('igp_error_modal', 'Agent VLAN and client VLAN must be different.');
            return;
        }
        if (igpTable.rows().data().some(r => Number(r.agent_vlan) === agent && Number(r.client_vlan) === client)) {
            igpShowError('igp_error_modal', `VLAN ${agent} is already mapped to VLAN ${client}.`);
            return;
        }

        const payload = { proxy_action: 'add', agent_vlan: agent, client_vlan: client };
        if (document.getElementById('igp_enable_proxy').checked) payload.igmp_proxy_status = 'enable';

        igpPost(payload, this, null, function () {
            $('#igpModal').modal('hide');
        }, message => igpShowError('igp_error_modal', message));
    });

    // Bootgrid stops row clicks from bubbling past <tbody>, so bind on it.
    $('#igpTable tbody').on('click', '.btn-delete-igp', function () {
        const row = igpTable.row($(this).data('row')).data();
        if (!confirm(`Remove the IGMP proxy map agent VLAN ${row.agent_vlan} -> client VLAN ${row.client_vlan}?`)) {
            return;
        }

        igpPost({ proxy_action: 'delete', agent_vlan: row.agent_vlan, client_vlan: row.client_vlan }, this, 'Deleting...');
    });

    loadIgp('');
</script>
