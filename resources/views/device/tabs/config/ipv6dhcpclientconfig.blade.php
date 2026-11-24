<style>
    .spinner-border {
        width: 16px; height: 16px;
        border: 2px solid #fff; border-right-color: transparent; border-radius: 50%;
        display: inline-block; animation: spin 0.75s linear infinite; vertical-align: middle;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    #v6cTable th { background: #f5f5f5; white-space: nowrap; }
    #v6cTable td { vertical-align: middle; }
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>IPv6 DHCP Client Configuration</strong>
        <span id="v6c_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <span class="pull-right">
            <button type="button" class="btn btn-xs btn-default" onclick="v6cLoad()"><i class="fa fa-refresh"></i> Refresh</button>
            <button type="button" class="btn btn-xs btn-primary" id="v6cAddBtn" onclick="v6cAdd()" disabled><i class="fa fa-plus"></i> Add</button>
        </span>
    </div>
    <div class="panel-body">
        <div id="v6c_load_error" class="alert alert-danger" style="display:none;"></div>
        <div class="table-responsive">
            <table id="v6cTable" class="table table-bordered table-condensed table-striped" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>VLAN Interface</th>
                        <th>Prefix Name</th>
                        <th>Stateless Allocation</th>
                        <th>Rapid Commit</th>
                        <th>Non-temp Address Allocation</th>
                        <th style="width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="v6cBody"><tr><td colspan="6" class="text-muted">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="panel-footer">
        VLAN interfaces are read from the switch. A Prefix Name requests an IPv6 prefix by DHCPv6 prefix delegation;
        once set it cannot be changed here, and Stateless Allocation is turned off. Changes are saved to the startup configuration.
    </div>
</div>

<div id="v6cModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="v6c_title">Edit IPv6 DHCP Client</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <div class="form-group" id="v6c_vlan_group" style="display:none;">
                        <label class="col-sm-5 control-label">VLAN Interface*</label>
                        <div class="col-sm-7">
                            <select id="v6c_vlan" class="form-control"></select>
                            <span class="help-block">VLAN interfaces on this switch - create new ones under VLAN Interfaces and IP Addresses</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Prefix Name</label>
                        <div class="col-sm-7">
                            <input type="text" id="v6c_prefix" class="form-control" maxlength="32" placeholder="(none)">
                            <span class="help-block" id="v6c_prefix_help"></span>
                        </div>
                    </div>
                    @foreach (['Stateless_Allocation' => 'Stateless Allocation', 'Rapid_Commit' => 'Rapid Commit', 'Non_temp_Address_Allocation' => 'Non-temp Address Allocation'] as $field => $label)
                        <div class="form-group">
                            <label class="col-sm-5 control-label">{{ $label }}</label>
                            <div class="col-sm-7">
                                <select class="form-control v6c-toggle" data-field="{{ $field }}">
                                    <option value="Enable">enable</option>
                                    <option value="Disable">disable</option>
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div id="v6c_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="v6cSaveBtn" class="btn btn-primary"><span class="spinner-border" style="display:none;"></span> Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    const V6C_DEVICE_IP = "{{ $device->hostname }}";
    const V6C_HEADERS = { "Authorization": "Bearer {{ $data['api_token'] }}", "Accept": "application/json" };
    const V6C_KEYS = { Stateless_Allocation: 'stateless_allocation', Rapid_Commit: 'rapid_commit', Non_temp_Address_Allocation: 'non_temp_address_allocation' };
    let v6cVlans = [];
    let v6cCurrent = null;
    let v6cMode = 'edit';    // 'add' or 'edit'

    function v6cEsc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
    function v6cBadge(v) { return v === 'Enable' ? '<span class="label label-success">enable</span>' : '<span class="text-muted">disable</span>'; }
    function v6cErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    function v6cLoad() {
        $('#v6c_load_error').hide();
        $.ajax({
            url: "/api/v0/ipv6dhcp/client/show/" + V6C_DEVICE_IP,
            headers: V6C_HEADERS,
            success: function (json) {
                $('#v6c_cache_note').toggle(!!json.cached);
                if (json.status !== 'success') {
                    $('#v6c_load_error').text(json.message || 'Failed to load IPv6 DHCP client configuration').show();
                    return;
                }
                v6cVlans = (json.vlans || []).sort((a, b) => a.vlan_id - b.vlan_id);
                $('#v6cAddBtn').prop('disabled', !v6cVlans.length);
                if (!v6cVlans.length) {
                    $('#v6cBody').html('<tr><td colspan="6" class="text-muted">No VLAN interfaces found on this switch</td></tr>');
                    return;
                }
                $('#v6cBody').html(v6cVlans.map((v, i) => `<tr>
                    <td><b>VLAN ${v6cEsc(v.vlan_id)}</b></td>
                    <td>${v6cEsc(v.prefix_name) || '<span class="text-muted">-</span>'}</td>
                    <td>${v6cBadge(v.stateless_allocation)}</td>
                    <td>${v6cBadge(v.rapid_commit)}</td>
                    <td>${v6cBadge(v.non_temp_address_allocation)}</td>
                    <td><button type="button" class="btn btn-xs btn-warning" onclick="v6cEdit(${i})">Edit</button></td>
                </tr>`).join(''));
            },
            error: function (xhr) { $('#v6c_load_error').text(v6cErrorText(xhr)).show(); }
        });
    }

    function v6cSyncStateless() {
        // A prefix (existing or new) turns stateless allocation off on the switch
        const hasPrefix = !!$.trim($('#v6c_prefix').val());
        const $s = $('.v6c-toggle[data-field=Stateless_Allocation]');
        if (hasPrefix) $s.val('Disable');
        $s.prop('disabled', hasPrefix);
    }
    $('#v6c_prefix').on('input', v6cSyncStateless);

    function v6cEdit(index) {
        $('#v6c_error').hide();
        v6cMode = 'edit';
        v6cCurrent = v6cVlans[index];
        $('#v6c_title').text('Edit IPv6 DHCP Client - VLAN ' + v6cCurrent.vlan_id);
        $('#v6c_vlan_group').hide();
        const hasPrefix = !!v6cCurrent.prefix_name;
        $('#v6c_prefix').val(v6cCurrent.prefix_name || '').prop('readonly', hasPrefix);
        $('#v6c_prefix_help').text(hasPrefix ? 'Already set - cannot be changed here' : 'Optional - letters, digits, . _ -');
        $('.v6c-toggle').each(function () {
            $(this).val(v6cCurrent[V6C_KEYS[$(this).data('field')]] === 'Enable' ? 'Enable' : 'Disable');
        });
        v6cSyncStateless();
        $('#v6cModal').modal('show');
    }

    // A VLAN with no IPv6 DHCP client settings yet
    function v6cIsUnconfigured(v) {
        return !v.prefix_name && [v.stateless_allocation, v.rapid_commit, v.non_temp_address_allocation].every(x => x !== 'Enable');
    }

    function v6cAdd() {
        $('#v6c_error').hide();
        v6cMode = 'add';
        v6cCurrent = null;
        $('#v6c_title').text('Add IPv6 DHCP Client');
        // Unconfigured VLANs first
        const free = v6cVlans.filter(v6cIsUnconfigured), used = v6cVlans.filter(v => !v6cIsUnconfigured(v));
        $('#v6c_vlan').html(
            (free.length ? '<optgroup label="Not configured">' + free.map(v => `<option value="${v.vlan_id}">VLAN ${v.vlan_id}</option>`).join('') + '</optgroup>' : '') +
            (used.length ? '<optgroup label="Already configured">' + used.map(v => `<option value="${v.vlan_id}">VLAN ${v.vlan_id}</option>`).join('') + '</optgroup>' : '')
        );
        $('#v6c_vlan_group').show();
        $('#v6c_prefix').val('').prop('readonly', false);
        $('#v6c_prefix_help').text('Optional - letters, digits, . _ - (turns Stateless Allocation off)');
        $('.v6c-toggle').val('Disable');
        v6cSyncStateless();
        $('#v6cModal').modal('show');
    }

    $('#v6cSaveBtn').on('click', function () {
        if (v6cMode === 'add') { v6cSaveAdd($(this)); return; }
        $('#v6c_error').hide();
        const payload = { VLAN_ID: v6cCurrent.vlan_id };
        const prefix = $.trim($('#v6c_prefix').val());
        if (prefix && !v6cCurrent.prefix_name) {
            if (!/^[A-Za-z0-9_.\-]{1,32}$/.test(prefix)) { $('#v6c_error').text('Prefix Name: letters, digits, . _ - only').show(); return; }
            payload.Prefix_Name = prefix;
        }
        let changed = !!payload.Prefix_Name;
        $('.v6c-toggle').each(function () {
            const field = $(this).data('field');
            if ($(this).prop('disabled')) return;     // stateless locked by a prefix
            const value = $(this).val();
            if (value !== (v6cCurrent[V6C_KEYS[field]] === 'Enable' ? 'Enable' : 'Disable')) { payload[field] = value; changed = true; }
        });
        if (!changed) { $('#v6c_error').text('Nothing has been changed.').show(); return; }

        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $.ajax({
            url: "/api/v0/ipv6dhcp/client/set/" + V6C_DEVICE_IP,
            method: "POST", headers: V6C_HEADERS, data: payload,
            success: function (r) {
                if (r.status === 'success') { $('#v6cModal').modal('hide'); alert(r.message); v6cLoad(); }
                else { $('#v6c_error').text(r.message || 'Request failed').show(); }
            },
            error: function (xhr) { $('#v6c_error').text(v6cErrorText(xhr)).show(); },
            complete: function () { $btn.prop('disabled', false).find('.spinner-border').hide(); }
        });
    });

    function v6cSaveAdd($btn) {
        $('#v6c_error').hide();
        const payload = { mode: 'add', VLAN_ID: $('#v6c_vlan').val() };
        const prefix = $.trim($('#v6c_prefix').val());
        if (prefix) {
            if (!/^[A-Za-z0-9_.\-]{1,32}$/.test(prefix)) { $('#v6c_error').text('Prefix Name: letters, digits, . _ - only').show(); return; }
            payload.Prefix_Name = prefix;
        }
        // Add sends every setting (Stateless is left out when a prefix is set)
        $('.v6c-toggle').each(function () {
            if (!$(this).prop('disabled')) payload[$(this).data('field')] = $(this).val();
        });

        $btn.prop('disabled', true).find('.spinner-border').show();
        $.ajax({
            url: "/api/v0/ipv6dhcp/client/set/" + V6C_DEVICE_IP,
            method: "POST", headers: V6C_HEADERS, data: payload,
            success: function (r) {
                if (r.status === 'success') { $('#v6cModal').modal('hide'); alert(r.message); v6cLoad(); }
                else { $('#v6c_error').text(r.message || 'Request failed').show(); }
            },
            error: function (xhr) { $('#v6c_error').text(v6cErrorText(xhr)).show(); },
            complete: function () { $btn.prop('disabled', false).find('.spinner-border').hide(); }
        });
    }

    v6cLoad();
</script>
