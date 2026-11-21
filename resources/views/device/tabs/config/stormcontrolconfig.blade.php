@php
    $stormTypes = [
        'broadcast' => 'Broadcast Storm Control',
        'multicast' => 'Multicast Storm Control',
        'unicast'   => 'Unknown Unicast Storm Control',
    ];
@endphp

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

    .sc-tabs { margin-bottom: 0; }

    .sc-tab-body {
        border: 1px solid #ddd;
        border-top: 0;
        padding: 15px;
    }

    .sc-grid-wrap {
        max-height: 560px;
        overflow: auto;
        border: 1px solid #ddd;
    }

    .sc-grid {
        margin-bottom: 0;
        min-width: 480px;
    }

    .sc-grid thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f5f5f5;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    .sc-grid td {
        vertical-align: middle;
        text-align: center;
        padding: 3px 5px;
    }

    .sc-grid td.sc-port {
        font-weight: 600;
        white-space: nowrap;
    }

    .sc-grid select,
    .sc-grid input[type=text] {
        height: 26px;
        padding: 2px 4px;
        font-size: 12px;
        width: 100%;
    }

    .sc-grid .sc-col-status { width: 160px; }
    .sc-grid .sc-col-threshold { width: 200px; }

    .sc-grid tr.sc-changed td { background: #fff8e1; }
    .sc-grid tr.sc-saved td { background: #e8f5e9; }
    .sc-grid tr.sc-failed td { background: #fdecea; }

    .sc-invalid {
        border-color: #c62828 !important;
        box-shadow: 0 0 0 1px #c62828 inset;
    }

    .sc-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 18px;
        margin-top: 12px;
    }

    .sc-footer .sc-batch {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
    }

    .sc-footer select,
    .sc-footer input[type=text] {
        height: 28px;
        padding: 2px 4px;
        width: auto;
        display: inline-block;
    }

    .sc-footer select { min-width: 100px; }
    .sc-footer input[type=text] { width: 130px; }

    .sc-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
    }

    .sc-actions .btn { min-width: 110px; }
</style>

<ul class="nav nav-tabs sc-tabs">
    @foreach ($stormTypes as $type => $label)
        <li class="{{ $loop->first ? 'active' : '' }}"><a href="#sc_tab_{{ $type }}" data-toggle="tab">{{ $label }}</a></li>
    @endforeach
</ul>

<div class="tab-content sc-tab-body">
    @foreach ($stormTypes as $type => $label)
        <div class="tab-pane {{ $loop->first ? 'active' : '' }} sc-pane" id="sc_tab_{{ $type }}" data-type="{{ $type }}">
            <div class="panel panel-default" style="margin-bottom:0;">
                <div class="panel-heading">
                    <strong>{{ $label }} Interface List</strong>
                    <span class="text-muted sc-cache-note" style="display:none; margin-left:10px;">
                        <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                    </span>
                    <button type="button" class="btn btn-xs btn-default pull-right" onclick="scLoad()">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
                <div class="panel-body">
                    <div class="alert alert-danger sc-load-error" style="display:none;"></div>

                    <div class="sc-grid-wrap">
                        <table class="table table-bordered table-condensed table-striped sc-grid">
                            <thead>
                                <tr>
                                    <th style="width:34px;"></th>
                                    <th>Port</th>
                                    <th>Status</th>
                                    <th>Threshold (pps)</th>
                                </tr>
                            </thead>
                            <tbody class="sc-body">
                                <tr><td colspan="4" class="text-muted">Loading ports from switch...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sc-footer">
                        <label style="margin:0; font-weight:normal;">
                            <input type="checkbox" class="sc-select-all"> Select All/Select None
                        </label>

                        <div class="sc-batch">
                            <span>Batch Operation</span>
                            <strong>Status</strong>
                            <select class="form-control sc-batch-status">
                                <option value="">--</option>
                                <option value="Enable">enable</option>
                                <option value="Disable">disable</option>
                            </select>
                            <strong>Threshold</strong>
                            <input type="text" class="form-control sc-batch-threshold" placeholder="1-1048575">
                            <button type="button" class="btn btn-default btn-sm sc-batch-btn">Set on ticked ports</button>
                        </div>

                        <div class="sc-actions">
                            <button type="button" class="btn btn-primary sc-apply-btn">
                                <span class="spinner-border" style="display:none;"></span> Apply
                            </button>
                            <button type="button" class="btn btn-default sc-reset-btn">Reset</button>
                        </div>
                    </div>

                    <div class="sc-status" style="display:none; margin-top:10px;"></div>
                </div>
                <div class="panel-footer">
                    Limits {{ strtolower(str_replace(' Storm Control', '', $label)) }} traffic on a port to the threshold in packets per
                    second (1-1048575). Tick ports and use the Batch Operation, or change a port directly; changed ports are
                    highlighted and Apply saves all of them in one go (and to the startup configuration). Disable removes the limit.
                </div>
            </div>
        </div>
    @endforeach
</div>

<script>
    const SC_DEVICE_IP = "{{ $device->hostname }}";
    const SC_API_TOKEN = "{{ $data['api_token'] }}";
    const SC_HEADERS = { "Authorization": "Bearer " + SC_API_TOKEN, "Accept": "application/json" };
    const SC_TYPES = ['broadcast', 'multicast', 'unicast'];

    // Values read from the switch: SC_ORIGINAL[type][port] = {status, threshold}
    const SC_ORIGINAL = { broadcast: {}, multicast: {}, unicast: {} };
    let scBusy = false;

    function scEsc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function scErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    function scPane(type) {
        return $(`#sc_tab_${type}`);
    }

    function scStatus(type, html, kind) {
        const el = scPane(type).find('.sc-status');
        if (!html) { el.hide(); return; }
        el.attr('class', 'sc-status alert alert-' + (kind || 'info')).html(html).show();
    }

    function scValidThreshold(value) {
        return /^\d+$/.test(value) && +value >= 1 && +value <= 1048575;
    }

    // Natural port order: g0/2 before g0/10, tg0/0/2 before tg0/0/10
    function scNaturalCompare(a, b) {
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
    function scLoad() {
        if (scBusy) return;
        $('.sc-load-error').hide();

        $.ajax({
            url: "/api/v0/stormcontrol/show/" + SC_DEVICE_IP,
            headers: SC_HEADERS,
            success: function (json) {
                $('.sc-cache-note').toggle(!!json.cached);
                if (json.status !== 'success') {
                    $('.sc-load-error').text(json.message || 'Failed to load storm control').show();
                    return;
                }
                const rows = (json.interfaces || []).sort((a, b) => scNaturalCompare(a.port, b.port));
                SC_TYPES.forEach(type => scRender(type, rows));
            },
            error: function (xhr) {
                $('.sc-load-error').text(xhr.responseJSON?.message || 'API communication error').show();
            }
        });
    }

    function scRender(type, rows) {
        SC_ORIGINAL[type] = {};
        const $body = scPane(type).find('.sc-body');

        if (!rows.length) {
            $body.html('<tr><td colspan="4" class="text-muted">No ports found on this device</td></tr>');
            return;
        }

        $body.html(rows.map(function (r) {
            const v = r[type] || {};
            const status = v.status === 'Enable' ? 'Enable' : 'Disable';
            const threshold = v.threshold == null ? '' : String(v.threshold);
            SC_ORIGINAL[type][r.port] = { status: status, threshold: threshold };
            return `<tr data-port="${scEsc(r.port)}">
                <td><input type="checkbox" class="sc-check"></td>
                <td class="sc-port">${scEsc(r.port)}</td>
                <td class="sc-col-status">
                    <select class="form-control sc-field" data-field="status">
                        <option value="Enable"${status === 'Enable' ? ' selected' : ''}>enable</option>
                        <option value="Disable"${status !== 'Enable' ? ' selected' : ''}>disable</option>
                    </select>
                </td>
                <td class="sc-col-threshold">
                    <input type="text" class="form-control sc-field" data-field="threshold" value="${scEsc(threshold)}" placeholder="1-1048575">
                </td>
            </tr>`;
        }).join(''));

        $body.find('tr[data-port]').each(function () { scSyncThreshold($(this)); });
        scPane(type).find('.sc-select-all').prop('checked', false);
    }

    // ---------------- Row state ----------------
    function scRowValues($tr) {
        return {
            status: $tr.find('[data-field=status]').val(),
            threshold: $.trim($tr.find('[data-field=threshold]').val())
        };
    }

    function scRowChanged(type, $tr) {
        const o = SC_ORIGINAL[type][$tr.data('port')] || {};
        const v = scRowValues($tr);
        if (v.status !== o.status) return true;
        return v.status === 'Enable' && v.threshold !== o.threshold;
    }

    // Threshold only applies while the port is enabled
    function scSyncThreshold($tr) {
        const enabled = $tr.find('[data-field=status]').val() === 'Enable';
        $tr.find('[data-field=threshold]').prop('disabled', !enabled);
    }

    function scMarkRow(type, $tr) {
        $tr.removeClass('sc-saved sc-failed').toggleClass('sc-changed', scRowChanged(type, $tr));
    }

    $('.sc-pane').each(function () {
        const $pane = $(this);
        const type = $pane.data('type');

        $pane.on('change input', '.sc-field', function () {
            const $tr = $(this).closest('tr');
            if ($(this).data('field') === 'status') scSyncThreshold($tr);
            $(this).removeClass('sc-invalid');
            scMarkRow(type, $tr);
        });

        $pane.find('.sc-select-all').on('change', function () {
            $pane.find('.sc-check').prop('checked', this.checked);
        });

        $pane.on('change', '.sc-check', function () {
            const all = $pane.find('.sc-check');
            $pane.find('.sc-select-all').prop('checked', all.length > 0 && all.filter(':checked').length === all.length);
        });

        // Batch: status and/or threshold on the ticked ports
        $pane.find('.sc-batch-btn').on('click', function () {
            const status = $pane.find('.sc-batch-status').val();
            const threshold = $.trim($pane.find('.sc-batch-threshold').val());
            const $checked = $pane.find('.sc-check:checked').closest('tr');

            if (!status && !threshold) {
                scStatus(type, 'Pick a Status and/or enter a Threshold for the Batch Operation.', 'warning');
                return;
            }
            if (threshold && !scValidThreshold(threshold)) {
                $pane.find('.sc-batch-threshold').addClass('sc-invalid');
                scStatus(type, 'Threshold must be a number from 1 to 1048575.', 'danger');
                return;
            }
            if (!$checked.length) {
                scStatus(type, 'Tick the ports to change first.', 'warning');
                return;
            }
            $pane.find('.sc-batch-threshold').removeClass('sc-invalid');

            $checked.each(function () {
                const $tr = $(this);
                if (status) $tr.find('[data-field=status]').val(status);
                // A threshold on its own also enables the port
                if (threshold && !status) $tr.find('[data-field=status]').val('Enable');
                if (threshold) $tr.find('[data-field=threshold]').val(threshold);
                scSyncThreshold($tr);
                scMarkRow(type, $tr);
            });
            scStatus(type, `Updated ${$checked.length} port(s). Click Apply to save.`, 'info');
        });

        $pane.find('.sc-reset-btn').on('click', function () {
            if (scBusy) return;
            $pane.find('tr[data-port]').each(function () {
                const $tr = $(this);
                const o = SC_ORIGINAL[type][$tr.data('port')] || {};
                $tr.find('[data-field=status]').val(o.status);
                $tr.find('[data-field=threshold]').val(o.threshold).removeClass('sc-invalid');
                scSyncThreshold($tr);
                $tr.removeClass('sc-changed sc-saved sc-failed');
            });
            $pane.find('.sc-check, .sc-select-all').prop('checked', false);
            $pane.find('.sc-batch-status').val('');
            $pane.find('.sc-batch-threshold').val('').removeClass('sc-invalid');
            scStatus(type, '');
        });

        $pane.find('.sc-apply-btn').on('click', function () {
            if (scBusy) return;

            const $changed = $pane.find('tr[data-port]').filter(function () { return scRowChanged(type, $(this)); });
            if (!$changed.length) {
                scStatus(type, 'Nothing to apply - no port has been changed.', 'warning');
                return;
            }

            const ports = [];
            let invalid = false;
            $changed.each(function () {
                const $tr = $(this);
                const v = scRowValues($tr);
                if (v.status === 'Enable' && !scValidThreshold(v.threshold)) {
                    $tr.find('[data-field=threshold]').addClass('sc-invalid');
                    invalid = true;
                    return;
                }
                ports.push({ interface: $tr.data('port'), threshold: v.status === 'Enable' ? v.threshold : 'Disable' });
            });
            if (invalid) {
                scStatus(type, 'Enabled ports need a threshold from 1 to 1048575 (highlighted).', 'danger');
                return;
            }

            scBusy = true;
            const $btn = $(this);
            $btn.prop('disabled', true).find('.spinner-border').show();
            $pane.find('.sc-reset-btn').prop('disabled', true);
            scStatus(type, `Saving ${ports.length} port(s) to the switch...`, 'info');

            $.ajax({
                url: "/api/v0/stormcontrol/set/" + SC_DEVICE_IP,
                method: "POST",
                headers: Object.assign({ "Content-Type": "application/json" }, SC_HEADERS),
                data: JSON.stringify({ type: type, ports: ports }),
                success: function (response) {
                    if (response.status === 'success') {
                        $changed.each(function () {
                            const $tr = $(this);
                            const v = scRowValues($tr);
                            SC_ORIGINAL[type][$tr.data('port')] = {
                                status: v.status,
                                threshold: v.status === 'Enable' ? v.threshold : ''
                            };
                            if (v.status !== 'Enable') $tr.find('[data-field=threshold]').val('');
                            $tr.removeClass('sc-changed sc-failed').addClass('sc-saved');
                        });
                        scStatus(type, scEsc(response.message || 'Storm control saved'), 'success');
                    } else {
                        $changed.removeClass('sc-changed').addClass('sc-failed');
                        scStatus(type, scEsc(response.message || 'Request failed'), 'danger');
                    }
                },
                error: function (xhr) {
                    $changed.removeClass('sc-changed').addClass('sc-failed');
                    scStatus(type, scEsc(scErrorText(xhr)), 'danger');
                },
                complete: function () {
                    scBusy = false;
                    $btn.prop('disabled', false).find('.spinner-border').hide();
                    $pane.find('.sc-reset-btn').prop('disabled', false);
                    $pane.find('.sc-batch-status').val('');
                    $pane.find('.sc-batch-threshold').val('');
                }
            });
        });
    });

    scLoad();
</script>
