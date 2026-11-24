<style>
    .spinner-border {
        width: 16px; height: 16px;
        border: 2px solid #fff; border-right-color: transparent; border-radius: 50%;
        display: inline-block; animation: spin 0.75s linear infinite; vertical-align: middle;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    #v6sTable th { background: #f5f5f5; white-space: nowrap; }
    #v6sTable td { vertical-align: middle; word-break: break-all; }
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>IPv6 DHCP Address Pools</strong>
        <span id="v6s_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <span class="pull-right">
            <button type="button" class="btn btn-xs btn-default" onclick="v6sLoad()"><i class="fa fa-refresh"></i> Refresh</button>
            <button type="button" class="btn btn-xs btn-primary" onclick="v6sOpen(null)"><i class="fa fa-plus"></i> Add Pool</button>
        </span>
    </div>
    <div class="panel-body">
        <div id="v6s_load_error" class="alert alert-danger" style="display:none;"></div>
        <div class="table-responsive">
            <table id="v6sTable" class="table table-bordered table-condensed table-striped" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Domain Name</th>
                        <th>Non-temporary Address Range</th>
                        <th>DNS Server</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="v6sBody"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="panel-footer">
        DHCPv6 pools the switch hands addresses out from. Changes are saved to the startup configuration.
    </div>
</div>

<div id="v6sModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="v6s_title">Add Pool</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Pool Name*</label>
                        <div class="col-sm-8"><input type="text" id="v6s_name" class="form-control" maxlength="32" placeholder="letters, digits, . _ -"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Domain Name</label>
                        <div class="col-sm-8"><input type="text" id="v6s_domain" class="form-control" maxlength="64" placeholder="example.com"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Address Range Start</label>
                        <div class="col-sm-8"><input type="text" id="v6s_start" class="form-control" placeholder="2001:db8::10"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Address Range End</label>
                        <div class="col-sm-8"><input type="text" id="v6s_end" class="form-control" placeholder="2001:db8::ff"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">DNS Server</label>
                        <div class="col-sm-8"><input type="text" id="v6s_dns" class="form-control" placeholder="IPv6 address"></div>
                    </div>
                    <div id="v6s_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="v6sSaveBtn" class="btn btn-primary"><span class="spinner-border" style="display:none;"></span> Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    const V6S_DEVICE_IP = "{{ $device->hostname }}";
    const V6S_HEADERS = { "Authorization": "Bearer {{ $data['api_token'] }}", "Accept": "application/json" };
    let v6sPools = [];
    let v6sEditing = null;

    function v6sEsc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
    function v6sErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }
    // Rough IPv6 check - the server validates properly
    function v6sIsIPv6(v) { return /^[0-9A-Fa-f:.]+$/.test(v) && v.includes(':'); }

    function v6sLoad() {
        $('#v6s_load_error').hide();
        $.ajax({
            url: "/api/v0/ipv6dhcp/server/show/" + V6S_DEVICE_IP,
            headers: V6S_HEADERS,
            success: function (json) {
                $('#v6s_cache_note').toggle(!!json.cached);
                if (json.status !== 'success') {
                    $('#v6s_load_error').text(json.message || 'Failed to load IPv6 DHCP pools').show();
                    return;
                }
                v6sPools = json.pools || [];
                if (!v6sPools.length) {
                    $('#v6sBody').html('<tr><td colspan="5" class="text-muted">No IPv6 DHCP pools configured</td></tr>');
                    return;
                }
                $('#v6sBody').html(v6sPools.map((p, i) => `<tr>
                    <td><b>${v6sEsc(p['Name'])}</b></td>
                    <td>${v6sEsc(p['Domain name']) || '<span class="text-muted">-</span>'}</td>
                    <td>${v6sEsc(p['Address range']) || '<span class="text-muted">-</span>'}</td>
                    <td>${v6sEsc(p['DNS']) || '<span class="text-muted">-</span>'}</td>
                    <td>
                        <button type="button" class="btn btn-xs btn-warning" onclick="v6sOpen(${i})">Edit</button>
                        <button type="button" class="btn btn-xs btn-danger" onclick="v6sDelete(${i})">Delete</button>
                    </td>
                </tr>`).join(''));
            },
            error: function (xhr) { $('#v6s_load_error').text(v6sErrorText(xhr)).show(); }
        });
    }

    // "a-b" -> [a, b]; IPv6 addresses contain no "-"
    function v6sRange(p) {
        const r = (p && p['Address range']) || '';
        const i = r.indexOf('-');
        return i > 0 ? [r.slice(0, i), r.slice(i + 1)] : ['', ''];
    }

    function v6sOpen(index) {
        $('#v6s_error').hide();
        v6sEditing = index === null ? null : v6sPools[index];
        const p = v6sEditing || {};
        const [start, end] = v6sRange(p);
        $('#v6s_title').text(v6sEditing ? 'Edit Pool - ' + p['Name'] : 'Add Pool');
        $('#v6s_name').val(p['Name'] || '').prop('readonly', !!v6sEditing);
        $('#v6s_domain').val(p['Domain name'] || '');
        $('#v6s_start').val(start);
        $('#v6s_end').val(end);
        $('#v6s_dns').val((p['DNS'] || '').split(',')[0].trim());
        $('#v6sModal').modal('show');
    }

    $('#v6sSaveBtn').on('click', function () {
        $('#v6s_error').hide();
        const v = {
            name: $.trim($('#v6s_name').val()), domain: $.trim($('#v6s_domain').val()),
            start: $.trim($('#v6s_start').val()), end: $.trim($('#v6s_end').val()), dns: $.trim($('#v6s_dns').val())
        };
        const problems = [];
        if (!/^[A-Za-z0-9_.\-]{1,32}$/.test(v.name)) problems.push('Pool Name: letters, digits, . _ - only');
        if (v.domain && !/^[A-Za-z0-9.\-]+$/.test(v.domain)) problems.push('Domain Name: letters, digits, . - only');
        if (!!v.start !== !!v.end) problems.push('Address Range needs both start and end');
        ['start', 'end', 'dns'].forEach(k => { if (v[k] && !v6sIsIPv6(v[k])) problems.push(k + ': not an IPv6 address'); });
        if (problems.length) { $('#v6s_error').html(problems.map(v6sEsc).join('<br>')).show(); return; }

        const payload = { Pool_Name: v.name };
        if (v.domain) payload.Domain_Name = v.domain;
        if (v.dns) payload.DNS_Server = v.dns;
        if (v.start) { payload.Address_Range_Start = v.start; payload.Address_Range_End = v.end; }
        if (v6sEditing) {
            const [oldStart, oldEnd] = v6sRange(v6sEditing);
            if (oldStart) { payload.Old_Range_Start = oldStart; payload.Old_Range_End = oldEnd; }
        } else {
            payload.create = 1;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $.ajax({
            url: "/api/v0/ipv6dhcp/server/pool/" + V6S_DEVICE_IP,
            method: "POST", headers: V6S_HEADERS, data: payload,
            success: function (r) {
                if (r.status === 'success') { $('#v6sModal').modal('hide'); alert(r.message); v6sLoad(); }
                else { $('#v6s_error').text(r.message || 'Request failed').show(); }
            },
            error: function (xhr) { $('#v6s_error').text(v6sErrorText(xhr)).show(); },
            complete: function () { $btn.prop('disabled', false).find('.spinner-border').hide(); }
        });
    });

    function v6sDelete(index) {
        const name = v6sPools[index]['Name'];
        if (!confirm(`Delete IPv6 DHCP pool "${name}"?`)) return;
        $.ajax({
            url: "/api/v0/ipv6dhcp/server/pool/delete/" + V6S_DEVICE_IP,
            method: "POST", headers: V6S_HEADERS, data: { Pool_Name: name },
            success: function (r) { alert(r.message || r.status); if (r.status === 'success') v6sLoad(); },
            error: function (xhr) { alert(v6sErrorText(xhr)); }
        });
    }

    v6sLoad();
</script>
