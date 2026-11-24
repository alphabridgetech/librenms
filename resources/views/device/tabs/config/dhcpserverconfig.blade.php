<style>
    .spinner-border {
        width: 16px; height: 16px;
        border: 2px solid #fff; border-right-color: transparent; border-radius: 50%;
        display: inline-block; animation: spin 0.75s linear infinite; vertical-align: middle;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    #dsForm .form-group.ds-changed .form-control { background: #fff8e1; }
    #dsPoolTable th { background: #f5f5f5; white-space: nowrap; }
    #dsPoolTable td { vertical-align: middle; }
    .ds-lease-parts { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 6px; }
    .ds-lease-parts input { width: 80px; display: inline-block; }
</style>

{{-- Global --}}
<div class="panel panel-default">
    <div class="panel-heading">
        <strong>DHCP Server Configuration</strong>
        <span class="text-muted ds-cache-note" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <button type="button" class="btn btn-xs btn-default pull-right" onclick="dsLoad()"><i class="fa fa-refresh"></i> Refresh</button>
    </div>
    <div class="panel-body">
        <div class="alert alert-danger ds-load-error" style="display:none;"></div>
        <form class="form-horizontal" id="dsForm" onsubmit="return false;">
            <div class="form-group">
                <label class="col-sm-4 control-label">DHCP Server</label>
                <div class="col-sm-4">
                    <select class="form-control ds-field" data-field="DHCP_Server" data-key="dhcp_server" disabled>
                        <option value="Enable">enable</option>
                        <option value="Disable">disable</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-4 control-label">Number of ICMP Packets</label>
                <div class="col-sm-4">
                    <input type="number" class="form-control ds-field" data-field="Number_of_ICMP_packets" data-key="number_of_icmp_packets" min="0" max="10" disabled>
                    <span class="help-block">0-10 - pings sent before an address is offered</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-4 control-label">ICMP Timeout</label>
                <div class="col-sm-4">
                    <input type="number" class="form-control ds-field" data-field="ICMP_timeout" data-key="icmp_timeout" min="0" max="20" disabled>
                    <span class="help-block">0-20, in units of 100 ms</span>
                </div>
            </div>
            <div id="ds_error" class="text-danger" style="display:none; margin:0 0 10px 15px;"></div>
            <div class="form-group" style="margin-bottom:0;">
                <div class="col-sm-offset-4 col-sm-8">
                    <button type="button" id="dsApplyBtn" class="btn btn-primary" disabled><span class="spinner-border" style="display:none;"></span> Apply</button>
                    <button type="button" id="dsResetBtn" class="btn btn-default" disabled>Reset</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Pools --}}
<div class="panel panel-default">
    <div class="panel-heading">
        <strong>DHCP Address Pools</strong>
        <button type="button" class="btn btn-xs btn-primary pull-right" onclick="dsOpenPool(null)"><i class="fa fa-plus"></i> Add Pool</button>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table id="dsPoolTable" class="table table-bordered table-condensed table-striped" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Network</th>
                        <th>Gateway</th>
                        <th>Address Range</th>
                        <th>Lease Time</th>
                        <th>DNS</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="dsPoolBody">
                    <tr><td colspan="7" class="text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel-footer">
        The DHCP Server must be enabled for the pools to hand out addresses. Changes are saved to the startup configuration.
    </div>
</div>

<!-- Pool modal -->
<div id="dsPoolModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="ds_pool_title">Add Pool</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Pool Name*</label>
                        <div class="col-sm-8"><input type="text" id="ds_p_name" class="form-control" maxlength="32" placeholder="letters, digits, . _ -"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Network Number</label>
                        <div class="col-sm-8"><input type="text" id="ds_p_network" class="form-control ds-p" data-field="Network_Number" placeholder="192.168.100.0"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Network Mask</label>
                        <div class="col-sm-8"><input type="text" id="ds_p_mask" class="form-control ds-p" data-field="Network_Mask" placeholder="255.255.255.0"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Gateway</label>
                        <div class="col-sm-8"><input type="text" id="ds_p_gateway" class="form-control ds-p" data-field="Gateway" placeholder="192.168.100.1"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Address Range</label>
                        <div class="col-sm-4"><input type="text" id="ds_p_start" class="form-control ds-p" data-field="Address_Range_Start" placeholder="start"></div>
                        <div class="col-sm-4"><input type="text" id="ds_p_end" class="form-control ds-p" data-field="Address_Range_End" placeholder="end"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Lease Time</label>
                        <div class="col-sm-8">
                            <select id="ds_p_lease_mode" class="form-control">
                                <option value="Default">Default</option>
                                <option value="Infinite">Infinite</option>
                                <option value="Setting">Setting</option>
                            </select>
                            <div class="ds-lease-parts" id="ds_p_lease_parts" style="display:none;">
                                <input type="number" id="ds_p_days" class="form-control" min="0" max="365" placeholder="days"> days
                                <input type="number" id="ds_p_hours" class="form-control" min="0" max="23" placeholder="hours"> hours
                                <input type="number" id="ds_p_minutes" class="form-control" min="0" max="59" placeholder="min"> min
                            </div>
                        </div>
                    </div>
                    @for ($i = 1; $i <= 4; $i++)
                        <div class="form-group">
                            <label class="col-sm-4 control-label">DNS {{ $i }}</label>
                            <div class="col-sm-8"><input type="text" id="ds_p_dns{{ $i }}" class="form-control ds-p" data-field="DNS_{{ $i }}" placeholder="IPv4 address"></div>
                        </div>
                    @endfor
                    <div id="ds_p_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="dsPoolSaveBtn" class="btn btn-primary"><span class="spinner-border" style="display:none;"></span> Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    const DS_DEVICE_IP = "{{ $device->hostname }}";
    const DS_HEADERS = { "Authorization": "Bearer {{ $data['api_token'] }}", "Accept": "application/json" };
    let dsOriginal = {};
    let dsPools = [];
    let dsEditing = null;        // pool being edited, null when adding
    let dsPoolOriginal = {};     // its values when the modal opened

    function dsEsc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
    function dsErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }
    function dsIsIPv4(v) {
        return /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/.test(v) && v.split('.').every(p => +p <= 255);
    }

    function dsLoad() {
        $('.ds-load-error').hide();
        $.ajax({
            url: "/api/v0/dhcp/server/show/" + DS_DEVICE_IP,
            headers: DS_HEADERS,
            success: function (json) {
                $('.ds-cache-note').toggle(!!json.cached);
                if (json.status !== 'success') {
                    $('.ds-load-error').text(json.message || 'Failed to load DHCP server configuration').show();
                    return;
                }
                const server = json.server || {};
                dsOriginal = {};
                $('.ds-field').each(function () {
                    const v = server[$(this).data('key')];
                    const value = v == null ? '' : String(v);
                    dsOriginal[$(this).data('field')] = value;
                    $(this).val(value).prop('disabled', false).closest('.form-group').removeClass('ds-changed');
                });
                $('#dsApplyBtn, #dsResetBtn').prop('disabled', false);
                dsPools = json.pools || [];
                dsRenderPools();
            },
            error: function (xhr) { $('.ds-load-error').text(dsErrorText(xhr)).show(); }
        });
    }

    function dsRenderPools() {
        if (!dsPools.length) {
            $('#dsPoolBody').html('<tr><td colspan="7" class="text-muted">No address pools configured</td></tr>');
            return;
        }
        $('#dsPoolBody').html(dsPools.map((p, i) => `<tr>
            <td><b>${dsEsc(p['Name'])}</b></td>
            <td>${p['Network number'] ? dsEsc(p['Network number'] + ' / ' + p['Network mask']) : '<span class="text-muted">-</span>'}</td>
            <td>${dsEsc(p['Gateway']) || '<span class="text-muted">-</span>'}</td>
            <td>${dsEsc(p['Address range']) || '<span class="text-muted">-</span>'}</td>
            <td>${dsEsc(p['Address lease time'] || 'Default')}</td>
            <td>${dsEsc(p['DNS']) || '<span class="text-muted">-</span>'}</td>
            <td>
                <button type="button" class="btn btn-xs btn-warning" onclick="dsOpenPool(${i})">Edit</button>
                <button type="button" class="btn btn-xs btn-danger" onclick="dsDeletePool(${i})">Delete</button>
            </td>
        </tr>`).join(''));
    }

    // ---------- Global ----------
    $('#dsForm').on('change input', '.ds-field', function () {
        $(this).closest('.form-group').toggleClass('ds-changed', $.trim($(this).val()) !== (dsOriginal[$(this).data('field')] || ''));
    });
    $('#dsResetBtn').on('click', function () {
        $('.ds-field').each(function () { $(this).val(dsOriginal[$(this).data('field')] || ''); });
        $('#dsForm .form-group').removeClass('ds-changed');
        $('#ds_error').hide();
    });
    $('#dsApplyBtn').on('click', function () {
        $('#ds_error').hide();
        const changes = {};
        $('.ds-field').each(function () {
            const value = $.trim($(this).val());
            if (value !== (dsOriginal[$(this).data('field')] || '')) changes[$(this).data('field')] = value;
        });
        if (!Object.keys(changes).length) {
            $('#ds_error').text('Nothing to apply - no setting has been changed.').show();
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $.ajax({
            url: "/api/v0/dhcp/server/set/" + DS_DEVICE_IP,
            method: "POST", headers: DS_HEADERS, data: changes,
            success: function (r) {
                if (r.status === 'success') { alert(r.message); dsLoad(); }
                else { $('#ds_error').text(r.message || 'Request failed').show(); }
            },
            error: function (xhr) { $('#ds_error').text(dsErrorText(xhr)).show(); },
            complete: function () { $btn.prop('disabled', false).find('.spinner-border').hide(); }
        });
    });

    // ---------- Pools ----------
    function dsToggleLease() {
        $('#ds_p_lease_parts').toggle($('#ds_p_lease_mode').val() === 'Setting');
    }
    $('#ds_p_lease_mode').on('change', dsToggleLease);

    function dsLeaseValue() {
        const mode = $('#ds_p_lease_mode').val();
        if (mode !== 'Setting') return mode;
        return [$('#ds_p_days').val() || 0, $('#ds_p_hours').val() || 0, $('#ds_p_minutes').val() || 0].map(v => String(parseInt(v, 10) || 0)).join(' ');
    }

    function dsOpenPool(index) {
        $('#ds_p_error').hide();
        dsEditing = index === null ? null : dsPools[index];
        const p = dsEditing || {};
        const [start, end] = (p['Address range'] || '-').split('-');
        const dns = (p['DNS'] || '').split(',').map(s => s.trim()).filter(Boolean);

        $('#ds_pool_title').text(dsEditing ? 'Edit Pool - ' + p['Name'] : 'Add Pool');
        $('#ds_p_name').val(p['Name'] || '').prop('readonly', !!dsEditing);
        $('#ds_p_network').val(p['Network number'] || '');
        $('#ds_p_mask').val(p['Network mask'] || '');
        $('#ds_p_gateway').val(p['Gateway'] || '');
        $('#ds_p_start').val(start || '');
        $('#ds_p_end').val(end || '');
        for (let i = 1; i <= 4; i++) $('#ds_p_dns' + i).val(dns[i - 1] || '');

        // "1 Day 2 Hour 3 Minute" -> Setting 1/2/3
        const lease = p['Address lease time'] || 'Default';
        const m = /^(\d+) Day (\d+) Hour (\d+) Minute$/.exec(lease);
        $('#ds_p_lease_mode').val(m ? 'Setting' : (lease === 'Infinite' ? 'Infinite' : 'Default'));
        $('#ds_p_days').val(m ? m[1] : ''); $('#ds_p_hours').val(m ? m[2] : ''); $('#ds_p_minutes').val(m ? m[3] : '');
        dsToggleLease();

        dsPoolOriginal = dsPoolValues();
        $('#dsPoolModal').modal('show');
    }

    function dsPoolValues() {
        const v = { Address_Lease_Time: dsLeaseValue() };
        $('.ds-p').each(function () { v[$(this).data('field')] = $.trim($(this).val()); });
        return v;
    }

    $('#dsPoolSaveBtn').on('click', function () {
        $('#ds_p_error').hide();
        const name = $.trim($('#ds_p_name').val());
        const values = dsPoolValues();

        const problems = [];
        if (!/^[A-Za-z0-9_.\-]{1,32}$/.test(name)) problems.push('Pool Name: letters, digits, . _ - only');
        $('.ds-p').each(function () {
            const v = $.trim($(this).val());
            if (v && !dsIsIPv4(v)) problems.push($(this).closest('.form-group').find('label').text() + ': not a valid IPv4 address');
        });
        if (!!values.Network_Number !== !!values.Network_Mask) problems.push('Network Number and Network Mask go together');
        if (!!values.Address_Range_Start !== !!values.Address_Range_End) problems.push('Address Range needs both start and end');
        if (problems.length) { $('#ds_p_error').html(problems.map(dsEsc).join('<br>')).show(); return; }

        // Adding: send everything filled in. Editing: only what changed (the
        // switch rejects a save where nothing changes).
        const payload = { Pool_Name: name };
        Object.keys(values).forEach(function (k) {
            if (dsEditing ? values[k] !== dsPoolOriginal[k] : (values[k] && !(k === 'Address_Lease_Time' && values[k] === 'Default'))) {
                payload[k] = values[k];
            }
        });
        // network / range are sent as pairs
        [['Network_Number', 'Network_Mask'], ['Address_Range_Start', 'Address_Range_End']].forEach(([a, b]) => {
            if (a in payload || b in payload) { payload[a] = values[a]; payload[b] = values[b]; }
        });
        if (Object.keys(payload).length < 2) {
            $('#ds_p_error').text(dsEditing ? 'Nothing has been changed.' : 'Enter at least one pool setting.').show();
            return;
        }
        if (!dsEditing) payload.create = 1;

        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $.ajax({
            url: "/api/v0/dhcp/server/pool/" + DS_DEVICE_IP,
            method: "POST", headers: DS_HEADERS, data: payload,
            success: function (r) {
                if (r.status === 'success') { $('#dsPoolModal').modal('hide'); alert(r.message); dsLoad(); }
                else { $('#ds_p_error').text(r.message || 'Request failed').show(); }
            },
            error: function (xhr) { $('#ds_p_error').text(dsErrorText(xhr)).show(); },
            complete: function () { $btn.prop('disabled', false).find('.spinner-border').hide(); }
        });
    });

    function dsDeletePool(index) {
        const name = dsPools[index]['Name'];
        if (!confirm(`Delete DHCP address pool "${name}"?`)) return;
        $.ajax({
            url: "/api/v0/dhcp/server/pool/delete/" + DS_DEVICE_IP,
            method: "POST", headers: DS_HEADERS, data: { Pool_Name: name },
            success: function (r) { alert(r.message || r.status); if (r.status === 'success') dsLoad(); },
            error: function (xhr) { alert(dsErrorText(xhr)); }
        });
    }

    dsLoad();
</script>
