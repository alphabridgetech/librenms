<style>
    .spinner-border {
        width: 16px;
        height: 16px;
        border: 2px solid #fff;
        border-right-color: transparent;
        border-radius: 50%;
        display: inline-block;
        animation: spin 0.75s linear infinite;
        vertical-align: middle;
    }

    @keyframes spin {
        100% { transform: rotate(360deg); }
    }

    .pf-tabs {
        margin-bottom: 0;
    }

    .pf-tab-body {
        border: 1px solid #ddd;
        border-top: 0;
        padding: 15px;
    }

    #pfGlobalForm .form-group.pf-changed select,
    #pfGlobalForm .form-group.pf-changed input {
        background: #fff8e1;
    }

    /* Interface grid */
    .pf-grid-wrap {
        max-height: 560px;
        overflow: auto;
        border: 1px solid #ddd;
    }

    #pfGrid {
        margin-bottom: 0;
        min-width: 760px;
    }

    #pfGrid thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f5f5f5;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    #pfGrid td {
        vertical-align: middle;
        text-align: center;
        padding: 3px 5px;
    }

    #pfGrid td.pf-port {
        font-weight: 600;
        white-space: nowrap;
    }

    #pfGrid select {
        height: 26px;
        padding: 2px 4px;
        font-size: 12px;
        width: 100%;
        min-width: 90px;
    }

    #pfGrid tr.pf-changed td { background: #fff8e1; }
    #pfGrid tr.pf-saved td { background: #e8f5e9; }
    #pfGrid tr.pf-failed td { background: #fdecea; }

    .pf-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 18px;
        margin-top: 12px;
    }

    .pf-footer .pf-batch {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
    }

    .pf-footer select {
        height: 28px;
        padding: 2px 4px;
        width: auto;
        min-width: 110px;
        display: inline-block;
    }

    .pf-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
    }

    .pf-actions .btn {
        min-width: 110px;
    }
</style>

<ul class="nav nav-tabs pf-tabs">
    <li class="active"><a href="#pf_tab_global" data-toggle="tab">Port Filter Configuration</a></li>
    <li><a href="#pf_tab_interfaces" data-toggle="tab">Port Filter Interface Configuration</a></li>
</ul>

<div class="tab-content pf-tab-body">

    {{-- ============ Tab 1: global ============ --}}
    <div class="tab-pane active" id="pf_tab_global">
        <div class="panel panel-default" style="margin-bottom:0;">
            <div class="panel-heading">
                <strong>Port Filter Configuration</strong>
                <span id="pf_cache_note_g" class="text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
            </div>
            <div class="panel-body">
                <div id="pf_load_error_g" class="alert alert-danger" style="display:none;"></div>
                <form class="form-horizontal" id="pfGlobalForm" onsubmit="return false;">
                    @foreach ([
                        'filter_enable' => 'Filter',
                        'filter_dhcp' => 'DHCP Filter',
                        'filter_icmp' => 'ICMP Filter',
                        'filter_icmpv6' => 'ICMPv6 Filter',
                        'filter_igmp' => 'IGMP Filter',
                        'filter_source_ip' => 'Source IP Filter',
                    ] as $field => $label)
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{ $label }}</label>
                            <div class="col-sm-4">
                                <select class="form-control pf-global" data-field="{{ $field }}" disabled>
                                    <option value="Enable">enable</option>
                                    <option value="Disable">disable</option>
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Filter Mode</label>
                        <div class="col-sm-4">
                            <select class="form-control pf-global" data-field="filter_mode" disabled>
                                <option value="raw">raw</option>
                                <option value="hybrid">hybrid</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Filter Period</label>
                        <div class="col-sm-4">
                            <input type="number" class="form-control pf-global" data-field="filter_period" min="1" max="600" disabled>
                            <span class="help-block">1-600 seconds</span>
                        </div>
                    </div>
                    <div id="pf_global_error" class="text-danger" style="display:none; margin:0 0 10px 15px;"></div>
                    <div class="form-group" style="margin-bottom:0;">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="button" id="pfGlobalApplyBtn" class="btn btn-primary" disabled>
                                <span class="spinner-border" style="display:none;"></span> Apply
                            </button>
                            <button type="button" id="pfGlobalResetBtn" class="btn btn-default" disabled>Reset</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="panel-footer">
                Filter must be enabled for the packet filters to work. Changed fields are highlighted; Apply saves only
                those to the switch and its startup configuration.
            </div>
        </div>
    </div>

    {{-- ============ Tab 2: interfaces ============ --}}
    <div class="tab-pane" id="pf_tab_interfaces">
        <div class="panel panel-default" style="margin-bottom:0;">
            <div class="panel-heading">
                <strong>Port Filter Interface List</strong>
                <span id="pf_cache_note_i" class="text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="pfLoad()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                <div id="pf_load_error_i" class="alert alert-danger" style="display:none;"></div>

                <div class="pf-grid-wrap">
                    <table id="pfGrid" class="table table-bordered table-condensed table-striped">
                        <thead>
                            <tr>
                                <th style="width:34px;"></th>
                                <th>Port</th>
                                <th>ARP</th>
                                <th>Source IP</th>
                                <th>BPDU</th>
                                <th>DHCP</th>
                                <th>ICMP</th>
                                <th>ICMPv6</th>
                            </tr>
                        </thead>
                        <tbody id="pfGridBody">
                            <tr><td colspan="8" class="text-muted">Loading ports from switch...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="pf-footer">
                    <label style="margin:0; font-weight:normal;">
                        <input type="checkbox" id="pf_select_all"> Select All/Select None
                    </label>

                    <div class="pf-batch">
                        <span>Batch Operation</span>
                        <select id="pf_batch_field" class="form-control">
                            <option value="">-- filter --</option>
                            <option value="arp">ARP</option>
                            <option value="source_ip">Source IP</option>
                            <option value="bpdu">BPDU</option>
                            <option value="dhcp">DHCP</option>
                            <option value="icmp">ICMP</option>
                            <option value="icmpv6">ICMPv6</option>
                            <option value="all">All filters</option>
                        </select>
                        <select id="pf_batch_value" class="form-control">
                            <option value="">--</option>
                            <option value="Enable">enable</option>
                            <option value="Disable">disable</option>
                        </select>
                        <button type="button" id="pfBatchBtn" class="btn btn-default btn-sm">Set on ticked ports</button>
                    </div>

                    <div class="pf-actions">
                        <button type="button" id="pfApplyBtn" class="btn btn-primary">
                            <span class="spinner-border" style="display:none;"></span> Apply
                        </button>
                        <button type="button" id="pfResetBtn" class="btn btn-default">Reset</button>
                    </div>
                </div>

                <div id="pf_status" style="display:none; margin-top:10px;"></div>
            </div>
            <div class="panel-footer">
                Tick ports, pick a filter and enable/disable, then "Set on ticked ports" - or change a port's
                filters directly. Changed ports are highlighted; Apply saves all of them to the switch in one go
                (and to its startup configuration). Reset puts back the values read from the switch.
            </div>
        </div>
    </div>
</div>

<script>
    const PF_DEVICE_IP = "{{ $device->hostname }}";
    const PF_API_TOKEN = "{{ $data['api_token'] }}";
    const PF_HEADERS = { "Authorization": "Bearer " + PF_API_TOKEN, "Accept": "application/json" };
    const PF_FIELDS = ['arp', 'source_ip', 'bpdu', 'dhcp', 'icmp', 'icmpv6'];

    let pfGlobalOriginal = {};
    let pfOriginal = {};
    let pfBusy = false;

    function pfEsc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function pfErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    function pfStatus(html, type) {
        const el = $('#pf_status');
        if (!html) { el.hide(); return; }
        el.attr('class', 'alert alert-' + (type || 'info')).html(html).show();
    }

    // Natural port order: g0/2 before g0/10
    function pfNaturalCompare(a, b) {
        const ax = [], bx = [];
        String(a).replace(/(\d+)|(\D+)/g, (_, n, s) => ax.push([n ? parseInt(n, 10) : Infinity, s || '']));
        String(b).replace(/(\d+)|(\D+)/g, (_, n, s) => bx.push([n ? parseInt(n, 10) : Infinity, s || '']));
        while (ax.length && bx.length) {
            const an = ax.shift(), bn = bx.shift();
            const diff = (an[0] - bn[0]) || an[1].localeCompare(bn[1]);
            if (diff) return diff;
        }
        return ax.length - bx.length;
    }

    // ---------------- Load ----------------
    function pfLoad() {
        if (pfBusy) return;
        $('#pf_load_error_g, #pf_load_error_i').hide();

        $.ajax({
            url: "/api/v0/portfilter/show/" + PF_DEVICE_IP,
            headers: PF_HEADERS,
            success: function (json) {
                $('#pf_cache_note_g, #pf_cache_note_i').toggle(!!json.cached);
                if (json.status !== 'success') {
                    $('#pf_load_error_g, #pf_load_error_i').text(json.message || 'Failed to load port filter').show();
                    return;
                }
                pfRenderGlobal(json.global || {});
                pfRenderGrid(json.interfaces || []);
            },
            error: function (xhr) {
                $('#pf_load_error_g, #pf_load_error_i').text(xhr.responseJSON?.message || 'API communication error').show();
            }
        });
    }

    // ---------------- Global ----------------
    function pfRenderGlobal(global) {
        pfGlobalOriginal = {};
        $('.pf-global').each(function () {
            const field = $(this).data('field');
            const value = global[field] == null ? '' : String(global[field]);
            pfGlobalOriginal[field] = value;
            $(this).val(value).prop('disabled', false);
            $(this).closest('.form-group').removeClass('pf-changed');
        });
        $('#pfGlobalApplyBtn, #pfGlobalResetBtn').prop('disabled', false);
    }

    function pfGlobalChanges() {
        const changes = {};
        $('.pf-global').each(function () {
            const field = $(this).data('field');
            const value = $.trim($(this).val());
            if (value !== (pfGlobalOriginal[field] || '')) changes[field] = value;
        });
        return changes;
    }

    $('#pfGlobalForm').on('change input', '.pf-global', function () {
        const field = $(this).data('field');
        $(this).closest('.form-group').toggleClass('pf-changed', $.trim($(this).val()) !== (pfGlobalOriginal[field] || ''));
    });

    $('#pfGlobalResetBtn').on('click', function () {
        $('.pf-global').each(function () {
            $(this).val(pfGlobalOriginal[$(this).data('field')] || '');
            $(this).closest('.form-group').removeClass('pf-changed');
        });
        $('#pf_global_error').hide();
    });

    $('#pfGlobalApplyBtn').on('click', function () {
        $('#pf_global_error').hide();
        const changes = pfGlobalChanges();
        if (!Object.keys(changes).length) {
            $('#pf_global_error').text('Nothing to apply - no setting has been changed.').show();
            return;
        }
        if ('filter_period' in changes && !(/^\d+$/.test(changes.filter_period) && +changes.filter_period >= 1 && +changes.filter_period <= 600)) {
            $('#pf_global_error').text('Filter Period must be a number from 1 to 600.').show();
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/portfilter/global/" + PF_DEVICE_IP,
            method: "POST",
            headers: PF_HEADERS,
            data: changes,
            success: function (response) {
                if (response.status === 'success') {
                    Object.assign(pfGlobalOriginal, changes);
                    $('#pfGlobalForm .form-group').removeClass('pf-changed');
                    alert(response.message || 'Port filter configuration saved');
                } else {
                    $('#pf_global_error').text(response.message || 'Request failed').show();
                }
            },
            error: function (xhr) {
                $('#pf_global_error').text(pfErrorText(xhr)).show();
            },
            complete: function () {
                $btn.prop('disabled', false).find('.spinner-border').hide();
            }
        });
    });

    // ---------------- Interface grid ----------------
    function pfToggle(field, value) {
        return `<select class="form-control pf-field" data-field="${field}">
            <option value="Enable"${value === 'Enable' ? ' selected' : ''}>enable</option>
            <option value="Disable"${value !== 'Enable' ? ' selected' : ''}>disable</option>
        </select>`;
    }

    function pfRenderGrid(rows) {
        rows.sort((a, b) => pfNaturalCompare(a.port, b.port));
        pfOriginal = {};

        if (!rows.length) {
            $('#pfGridBody').html('<tr><td colspan="8" class="text-muted">No ports found on this device</td></tr>');
            return;
        }

        $('#pfGridBody').html(rows.map(function (r) {
            pfOriginal[r.port] = {};
            PF_FIELDS.forEach(f => pfOriginal[r.port][f] = r[f] === 'Enable' ? 'Enable' : 'Disable');
            return `<tr data-port="${pfEsc(r.port)}">
                <td><input type="checkbox" class="pf-check"></td>
                <td class="pf-port">${pfEsc(r.port)}</td>
                ${PF_FIELDS.map(f => `<td>${pfToggle(f, pfOriginal[r.port][f])}</td>`).join('')}
            </tr>`;
        }).join(''));
        $('#pf_select_all').prop('checked', false);
    }

    function pfRowValues($tr) {
        const values = {};
        $tr.find('.pf-field').each(function () { values[$(this).data('field')] = $(this).val(); });
        return values;
    }

    function pfRowChanged($tr) {
        const original = pfOriginal[$tr.data('port')] || {};
        const values = pfRowValues($tr);
        return PF_FIELDS.some(f => values[f] !== original[f]);
    }

    function pfMarkRow($tr) {
        $tr.removeClass('pf-saved pf-failed').toggleClass('pf-changed', pfRowChanged($tr));
    }

    $('#pfGridBody').on('change', '.pf-field', function () { pfMarkRow($(this).closest('tr')); });

    $('#pf_select_all').on('change', function () {
        $('#pfGridBody .pf-check').prop('checked', this.checked);
    });

    $('#pfGridBody').on('change', '.pf-check', function () {
        const all = $('#pfGridBody .pf-check');
        $('#pf_select_all').prop('checked', all.length > 0 && all.filter(':checked').length === all.length);
    });

    $('#pfBatchBtn').on('click', function () {
        const field = $('#pf_batch_field').val();
        const value = $('#pf_batch_value').val();
        const $checked = $('#pfGridBody .pf-check:checked').closest('tr');
        if (!field || !value) {
            pfStatus('Pick a filter and enable/disable for the Batch Operation.', 'warning');
            return;
        }
        if (!$checked.length) {
            pfStatus('Tick the ports to change first.', 'warning');
            return;
        }
        const fields = field === 'all' ? PF_FIELDS : [field];
        $checked.each(function () {
            const $tr = $(this);
            fields.forEach(f => $tr.find(`[data-field=${f}]`).val(value));
            pfMarkRow($tr);
        });
        pfStatus(`${field === 'all' ? 'All filters' : $('#pf_batch_field option:selected').text()} set to <b>${value.toLowerCase()}</b> on ${$checked.length} port(s). Click Apply to save.`, 'info');
    });

    $('#pfResetBtn').on('click', function () {
        if (pfBusy) return;
        $('#pfGridBody tr[data-port]').each(function () {
            const $tr = $(this);
            const original = pfOriginal[$tr.data('port')] || {};
            $tr.find('.pf-field').each(function () { $(this).val(original[$(this).data('field')]); });
            $tr.removeClass('pf-changed pf-saved pf-failed');
        });
        $('#pfGridBody .pf-check, #pf_select_all').prop('checked', false);
        $('#pf_batch_field, #pf_batch_value').val('');
        pfStatus('');
    });

    $('#pfApplyBtn').on('click', function () {
        if (pfBusy) return;

        const $changed = $('#pfGridBody tr[data-port]').filter(function () { return pfRowChanged($(this)); });
        if (!$changed.length) {
            pfStatus('Nothing to apply - no port has been changed.', 'warning');
            return;
        }

        // Send only the filters that changed on each port
        const ports = [];
        $changed.each(function () {
            const $tr = $(this);
            const port = $tr.data('port');
            const original = pfOriginal[port];
            const values = pfRowValues($tr);
            const item = { interface: port };
            PF_FIELDS.forEach(f => { if (values[f] !== original[f]) item[f] = values[f]; });
            ports.push(item);
        });

        pfBusy = true;
        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $('#pfResetBtn').prop('disabled', true);
        pfStatus(`Saving ${ports.length} port(s) to the switch...`, 'info');

        $.ajax({
            url: "/api/v0/portfilter/interface/" + PF_DEVICE_IP,
            method: "POST",
            headers: Object.assign({ "Content-Type": "application/json" }, PF_HEADERS),
            data: JSON.stringify({ ports: ports }),
            success: function (response) {
                if (response.status === 'success') {
                    $changed.each(function () {
                        const $tr = $(this);
                        pfOriginal[$tr.data('port')] = pfRowValues($tr);
                        $tr.removeClass('pf-changed pf-failed').addClass('pf-saved');
                    });
                    pfStatus(pfEsc(response.message || 'Port filter saved'), 'success');
                } else {
                    $changed.removeClass('pf-changed').addClass('pf-failed');
                    pfStatus(pfEsc(response.message || 'Request failed'), 'danger');
                }
            },
            error: function (xhr) {
                $changed.removeClass('pf-changed').addClass('pf-failed');
                pfStatus(pfEsc(pfErrorText(xhr)), 'danger');
            },
            complete: function () {
                pfBusy = false;
                $btn.prop('disabled', false).find('.spinner-border').hide();
                $('#pfResetBtn').prop('disabled', false);
                $('#pf_batch_field, #pf_batch_value').val('');
            }
        });
    });

    pfLoad();
</script>
