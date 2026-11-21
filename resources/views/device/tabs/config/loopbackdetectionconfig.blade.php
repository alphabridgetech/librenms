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

    .ld-tabs {
        margin-bottom: 0;
    }

    .ld-tab-body {
        border: 1px solid #ddd;
        border-top: 0;
        padding: 15px;
    }

    .ld-global-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .ld-global-row select {
        width: auto;
        min-width: 140px;
    }

    /* Interface grid */
    .ld-grid-wrap {
        max-height: 560px;
        overflow: auto;
        border: 1px solid #ddd;
    }

    #ldGrid {
        margin-bottom: 0;
        min-width: 1050px;
    }

    #ldGrid thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f5f5f5;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
        font-size: 12px;
    }

    #ldGrid td {
        vertical-align: middle;
        text-align: center;
        padding: 3px 5px;
    }

    #ldGrid td.ld-port {
        font-weight: 600;
        white-space: nowrap;
    }

    #ldGrid select,
    #ldGrid input[type=text] {
        height: 26px;
        padding: 2px 4px;
        font-size: 12px;
        width: 100%;
    }

    #ldGrid .ld-col-toggle { min-width: 95px; }
    #ldGrid .ld-col-control { min-width: 115px; }
    #ldGrid .ld-col-vlan { min-width: 90px; }
    #ldGrid .ld-col-mac { min-width: 140px; }

    #ldGrid tr.ld-changed td {
        background: #fff8e1;
    }

    #ldGrid tr.ld-saved td {
        background: #e8f5e9;
    }

    #ldGrid tr.ld-failed td {
        background: #fdecea;
    }

    .ld-invalid {
        border-color: #c62828 !important;
        box-shadow: 0 0 0 1px #c62828 inset;
    }

    .ld-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 18px;
        margin-top: 12px;
    }

    .ld-footer .ld-batch {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
    }

    .ld-footer select {
        height: 28px;
        padding: 2px 4px;
        width: auto;
        min-width: 110px;
        display: inline-block;
    }

    .ld-footer .ld-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
    }

    .ld-footer .ld-actions .btn {
        min-width: 110px;
    }

    #ld_status {
        margin-top: 10px;
    }
</style>

<ul class="nav nav-tabs ld-tabs">
    <li class="active"><a href="#ld_tab_global" data-toggle="tab">Loopback Detection</a></li>
    <li><a href="#ld_tab_interfaces" data-toggle="tab">Loopback Detection Interface Configuration Info</a></li>
</ul>

<div class="tab-content ld-tab-body">

    {{-- ============ Tab 1: global ============ --}}
    <div class="tab-pane active" id="ld_tab_global">
        <div class="panel panel-default" style="margin-bottom:0;">
            <div class="panel-heading"><strong>Loopback Detection Global Configuration</strong></div>
            <div class="panel-body">
                <div class="ld-global-row">
                    <label for="ld_global_status" style="margin:0;">Loopback Detection</label>
                    <select id="ld_global_status" class="form-control" disabled>
                        <option value="Enable">enable</option>
                        <option value="Disable">disable</option>
                    </select>
                    <button type="button" id="ldGlobalApplyBtn" class="btn btn-primary" disabled>
                        <span class="spinner-border" style="display:none;"></span> Apply
                    </button>
                    <span id="ld_global_current" class="text-muted">Reading from switch...</span>
                </div>
                <div id="ld_global_error" class="text-danger" style="display:none; margin-top:8px;"></div>
            </div>
            <div class="panel-footer">
                Global Loopback Detection must be enabled for the port settings to take effect.
            </div>
        </div>
    </div>

    {{-- ============ Tab 2: interfaces ============ --}}
    <div class="tab-pane" id="ld_tab_interfaces">
        <div class="panel panel-default" style="margin-bottom:0;">
            <div class="panel-heading">
                <strong>Loopback Detection interface List</strong>
                <span id="ld_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                    <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
                </span>
                <button type="button" class="btn btn-xs btn-default pull-right" onclick="ldLoad()">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="panel-body">
                <div id="ld_load_error" class="alert alert-danger" style="display:none;"></div>

                <div class="ld-grid-wrap">
                    <table id="ldGrid" class="table table-bordered table-condensed table-striped">
                        <thead>
                            <tr>
                                <th style="width:34px;"></th>
                                <th>Port</th>
                                <th>Loopback Detection</th>
                                <th>Control</th>
                                <th>Existence Loopback</th>
                                <th>Detection VLAN</th>
                                <th>Frames Monitor</th>
                                <th>Forbid Loopback<br>Detection Packets</th>
                                <th>dest-mac<br>&lt;HHHH.HHHH.HHHH&gt;</th>
                            </tr>
                        </thead>
                        <tbody id="ldGridBody">
                            <tr><td colspan="9" class="text-muted">Loading ports from switch...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="ld-footer">
                    <label style="margin:0; font-weight:normal;">
                        <input type="checkbox" id="ld_select_all"> Select All/Select None
                    </label>

                    <div class="ld-batch">
                        <span>Batch Operation</span>
                        <strong>Loopback Detection</strong>
                        <select id="ld_batch_detection" class="form-control">
                            <option value="">--</option>
                            <option value="Enable">enable</option>
                            <option value="Disable">disable</option>
                        </select>
                        <strong>Control</strong>
                        <select id="ld_batch_control" class="form-control">
                            <option value="">--</option>
                            <option value="learning">learning</option>
                            <option value="block">block</option>
                            <option value="shutdown">shutdown</option>
                            <option value="isolate-vlan">isolate-vlan</option>
                        </select>
                    </div>

                    <div class="ld-actions">
                        <button type="button" id="ldApplyBtn" class="btn btn-primary">
                            <span class="spinner-border" style="display:none;"></span> Apply
                        </button>
                        <button type="button" id="ldResetBtn" class="btn btn-default">Reset</button>
                    </div>
                </div>

                <div id="ld_status" style="display:none;"></div>
            </div>
            <div class="panel-footer">
                Tick ports and pick a Batch Operation value to set it on all ticked ports, or change a port's
                fields directly. Changed ports are highlighted; Apply saves only those ports to the switch
                (and its startup configuration). Reset puts back the values read from the switch.
                Setting Control to blank removes it from the port. Empty Detection VLAN or dest-mac keep the switch's current value.
            </div>
        </div>
    </div>
</div>

<script>
    const LD_DEVICE_IP = "{{ $device->hostname }}";
    const LD_API_TOKEN = "{{ $data['api_token'] }}";
    const LD_HEADERS = { "Authorization": "Bearer " + LD_API_TOKEN, "Accept": "application/json" };

    const LD_TOGGLES = [['Enable', 'enable'], ['Disable', 'disable']];
    const LD_CONTROLS = [['', ''], ['learning', 'learning'], ['block', 'block'], ['shutdown', 'shutdown'], ['isolate-vlan', 'isolate-vlan']];
    const LD_MAC_RE = /^([0-9A-Fa-f]{4}\.){2}[0-9A-Fa-f]{4}$|^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/;

    // Values read from the switch, by port - used by Reset and to find changed rows
    let ldOriginal = {};
    let ldBusy = false;

    function ldEsc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function ldErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    // Natural port order: g0/2 before g0/10, tg0/0/2 before tg0/0/10
    function ldNaturalCompare(a, b) {
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

    function ldSelect(field, options, value) {
        const opts = options.map(([v, label]) =>
            `<option value="${ldEsc(v)}"${v === value ? ' selected' : ''}>${ldEsc(label)}</option>`).join('');
        return `<select class="form-control ld-field" data-field="${field}">${opts}</select>`;
    }

    function ldInput(field, value, placeholder) {
        return `<input type="text" class="form-control ld-field" data-field="${field}" value="${ldEsc(value)}" placeholder="${placeholder}">`;
    }

    function ldStatus(html, type) {
        const el = $('#ld_status');
        if (!html) { el.hide(); return; }
        el.attr('class', 'alert alert-' + (type || 'info')).html(html).show();
    }

    // ---------------- Load ----------------
    function ldLoad() {
        if (ldBusy) return;
        $('#ld_load_error').hide();

        $.ajax({
            url: "/api/v0/loopbackdetection/show/" + LD_DEVICE_IP,
            headers: LD_HEADERS,
            success: function (json) {
                $('#ld_cache_note').toggle(!!json.cached);
                if (json.status !== 'success') {
                    $('#ld_load_error').text(json.message || 'Failed to load loopback detection').show();
                    $('#ld_global_current').text('Could not read from switch');
                    return;
                }
                ldShowGlobal(json.global_status);
                ldRender(json.interfaces || []);
            },
            error: function (xhr) {
                $('#ld_load_error').text(xhr.responseJSON?.message || 'API communication error').show();
                $('#ld_global_current').text('Could not read from switch');
            }
        });
    }

    function ldRender(rows) {
        rows.sort((a, b) => ldNaturalCompare(a.port, b.port));
        ldOriginal = {};

        if (!rows.length) {
            $('#ldGridBody').html('<tr><td colspan="9" class="text-muted">No ports found on this device</td></tr>');
            return;
        }

        const html = rows.map(function (r) {
            const row = {
                loopback_detection: r.loopback_detection === 'Enable' ? 'Enable' : 'Disable',
                control: r.control || '',
                existence: r.existence === 'Enable' ? 'Enable' : 'Disable',
                detection_vlan: r.detection_vlan || '',
                frames_monitor: r.frames_monitor === 'Enable' ? 'Enable' : 'Disable',
                forbid_packets: r.forbid_packets === 'Enable' ? 'Enable' : 'Disable',
                dest_mac: r.dest_mac || ''
            };
            ldOriginal[r.port] = row;

            return `<tr data-port="${ldEsc(r.port)}">
                <td><input type="checkbox" class="ld-check"></td>
                <td class="ld-port">${ldEsc(r.port)}</td>
                <td class="ld-col-toggle">${ldSelect('loopback_detection', LD_TOGGLES, row.loopback_detection)}</td>
                <td class="ld-col-control">${ldSelect('control', LD_CONTROLS, row.control)}</td>
                <td class="ld-col-toggle">${ldSelect('existence', LD_TOGGLES, row.existence)}</td>
                <td class="ld-col-vlan">${ldInput('detection_vlan', row.detection_vlan, '1-4094')}</td>
                <td class="ld-col-toggle">${ldSelect('frames_monitor', LD_TOGGLES, row.frames_monitor)}</td>
                <td class="ld-col-toggle">${ldSelect('forbid_packets', LD_TOGGLES, row.forbid_packets)}</td>
                <td class="ld-col-mac">${ldInput('dest_mac', row.dest_mac, 'HHHH.HHHH.HHHH')}</td>
            </tr>`;
        }).join('');

        $('#ldGridBody').html(html);
        $('#ld_select_all').prop('checked', false);
        $('#ldGridBody tr').each(function () { ldSyncForbid($(this)); });
    }

    // ---------------- Row state ----------------
    function ldRowValues($tr) {
        const values = {};
        $tr.find('.ld-field').each(function () {
            values[$(this).data('field')] = $.trim($(this).val());
        });
        return values;
    }

    function ldRowChanged($tr) {
        const original = ldOriginal[$tr.data('port')] || {};
        const values = ldRowValues($tr);
        return Object.keys(values).some(k => (values[k] || '') !== (original[k] || ''));
    }

    // The switch turns forbid-packet off while detection is enabled
    function ldSyncForbid($tr) {
        const enabled = $tr.find('[data-field=loopback_detection]').val() === 'Enable';
        const $forbid = $tr.find('[data-field=forbid_packets]');
        if (enabled) $forbid.val('Disable');
        $forbid.prop('disabled', enabled);
    }

    function ldMarkRow($tr) {
        $tr.removeClass('ld-saved ld-failed').toggleClass('ld-changed', ldRowChanged($tr));
    }

    $('#ldGridBody').on('change input', '.ld-field', function () {
        const $tr = $(this).closest('tr');
        if ($(this).data('field') === 'loopback_detection') ldSyncForbid($tr);
        $(this).removeClass('ld-invalid');
        ldMarkRow($tr);
    });

    // ---------------- Select all / batch ----------------
    $('#ld_select_all').on('change', function () {
        $('#ldGridBody .ld-check').prop('checked', this.checked);
    });

    $('#ldGridBody').on('change', '.ld-check', function () {
        const all = $('#ldGridBody .ld-check');
        $('#ld_select_all').prop('checked', all.length > 0 && all.filter(':checked').length === all.length);
    });

    function ldBatch(field, value) {
        if (!value) return;
        const $checked = $('#ldGridBody .ld-check:checked').closest('tr');
        if (!$checked.length) {
            ldStatus('Tick the ports to change first, then pick the Batch Operation value.', 'warning');
            return;
        }
        $checked.each(function () {
            const $tr = $(this);
            $tr.find(`[data-field=${field}]`).val(value);
            if (field === 'loopback_detection') ldSyncForbid($tr);
            ldMarkRow($tr);
        });
        ldStatus(`${field === 'control' ? 'Control' : 'Loopback Detection'} set to <b>${ldEsc(value)}</b> on ${$checked.length} port(s). Click Apply to save.`, 'info');
    }

    $('#ld_batch_detection').on('change', function () { ldBatch('loopback_detection', this.value); });
    $('#ld_batch_control').on('change', function () { ldBatch('control', this.value); });

    // ---------------- Reset ----------------
    $('#ldResetBtn').on('click', function () {
        if (ldBusy) return;
        $('#ldGridBody tr[data-port]').each(function () {
            const $tr = $(this);
            const original = ldOriginal[$tr.data('port')] || {};
            $tr.find('.ld-field').each(function () {
                $(this).val(original[$(this).data('field')] || '').removeClass('ld-invalid');
            });
            ldSyncForbid($tr);
            $tr.removeClass('ld-changed ld-saved ld-failed');
        });
        $('#ldGridBody .ld-check, #ld_select_all').prop('checked', false);
        $('#ld_batch_detection, #ld_batch_control').val('');
        ldStatus('');
    });

    // ---------------- Apply ----------------
    function ldValidate($tr, values) {
        let ok = true;
        const $vlan = $tr.find('[data-field=detection_vlan]');
        if (values.detection_vlan && !(/^\d+$/.test(values.detection_vlan) && +values.detection_vlan >= 1 && +values.detection_vlan <= 4094)) {
            $vlan.addClass('ld-invalid'); ok = false;
        }
        const $mac = $tr.find('[data-field=dest_mac]');
        if (values.dest_mac && !LD_MAC_RE.test(values.dest_mac)) {
            $mac.addClass('ld-invalid'); ok = false;
        }
        return ok;
    }

    function ldPayload(port, values) {
        const original = ldOriginal[port] || {};
        const payload = {
            interface: port,
            loopback_detection: values.loopback_detection,
            existence: values.existence,
            frames_monitor: values.frames_monitor,
            forbid_packets: values.loopback_detection === 'Enable' ? 'Disable' : values.forbid_packets
        };
        if (values.control) {
            payload.control = values.control;
        } else if (original.control) {
            // Control blanked: remove it from the switch ("no loopback-detection control")
            payload.control = 'none';
        }
        if (values.detection_vlan) payload.detection_vlan = values.detection_vlan;
        if (values.dest_mac) payload.dest_mac = values.dest_mac;
        return payload;
    }

    $('#ldApplyBtn').on('click', async function () {
        if (ldBusy) return;

        const changed = $('#ldGridBody tr[data-port]').filter(function () { return ldRowChanged($(this)); }).toArray().map(tr => $(tr));
        if (!changed.length) {
            ldStatus('Nothing to apply - no port has been changed.', 'warning');
            return;
        }

        const invalid = changed.filter($tr => !ldValidate($tr, ldRowValues($tr)));
        if (invalid.length) {
            ldStatus('Fix the highlighted fields: Detection VLAN 1-4094, dest-mac HHHH.HHHH.HHHH.', 'danger');
            return;
        }

        ldBusy = true;
        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $('#ldResetBtn').prop('disabled', true);

        const failures = [];
        for (let i = 0; i < changed.length; i++) {
            const $tr = changed[i];
            const port = $tr.data('port');
            const values = ldRowValues($tr);
            ldStatus(`Saving ${i + 1} of ${changed.length}: <b>${ldEsc(port)}</b>...`, 'info');

            try {
                const response = await $.ajax({
                    url: "/api/v0/loopbackdetection/interface/" + LD_DEVICE_IP,
                    method: "POST",
                    headers: LD_HEADERS,
                    data: ldPayload(port, values)
                });
                if (response.status === 'success') {
                    ldOriginal[port] = Object.assign({}, values, {
                        forbid_packets: values.loopback_detection === 'Enable' ? 'Disable' : values.forbid_packets
                    });
                    $tr.removeClass('ld-changed ld-failed').addClass('ld-saved');
                } else {
                    failures.push(`${port}: ${response.message || 'failed'}`);
                    $tr.removeClass('ld-changed').addClass('ld-failed');
                }
            } catch (xhr) {
                failures.push(`${port}: ${ldErrorText(xhr)}`);
                $tr.removeClass('ld-changed').addClass('ld-failed');
            }
        }

        ldBusy = false;
        $btn.prop('disabled', false).find('.spinner-border').hide();
        $('#ldResetBtn').prop('disabled', false);
        $('#ld_batch_detection, #ld_batch_control').val('');

        const saved = changed.length - failures.length;
        if (failures.length) {
            ldStatus(`${saved} of ${changed.length} port(s) saved. Failed:<br>` + failures.map(ldEsc).join('<br>'), 'danger');
        } else {
            ldStatus(`${saved} port(s) saved to the switch.`, 'success');
        }
    });

    // ---------------- Global ----------------
    function ldShowGlobal(status) {
        const value = status === 'Enable' ? 'Enable' : 'Disable';
        $('#ld_global_status').val(value).prop('disabled', false);
        $('#ldGlobalApplyBtn').prop('disabled', false);
        $('#ld_global_current').html('Current on switch: ' + (value === 'Enable'
            ? '<span class="label label-success">enable</span>'
            : '<span class="label label-default">disable</span>'));
    }

    $('#ldGlobalApplyBtn').on('click', function () {
        $('#ld_global_error').hide();
        const status = $('#ld_global_status').val();
        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/loopbackdetection/global/" + LD_DEVICE_IP,
            method: "POST",
            headers: LD_HEADERS,
            data: { status: status },
            success: function (response) {
                $btn.prop('disabled', false).find('.spinner-border').hide();
                if (response.status === 'success') {
                    ldShowGlobal(status);
                    alert(response.message || 'Loopback detection updated');
                } else {
                    $('#ld_global_error').text(response.message || 'Request failed').show();
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).find('.spinner-border').hide();
                $('#ld_global_error').text(ldErrorText(xhr)).show();
            }
        });
    });

    ldLoad();
</script>
