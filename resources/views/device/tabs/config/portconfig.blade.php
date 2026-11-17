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

    .dataTables_wrapper {
        max-width: 100%;
    }

    .dataTables_filter input {
        max-width: 100%;
    }

    @media (max-width: 600px) {
        .dataTables_length,
        .dataTables_filter {
            text-align: left !important;
            margin-bottom: 8px;
        }
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Port Configuration</strong>
        <span id="portconfig_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <span id="portconfig_playbook" class="label label-default" style="display:none; margin-left:10px; font-weight:normal;"></span>
        <button type="button" class="btn btn-xs btn-default pull-right" onclick="portConfigTable.ajax.reload()">
            <i class="fa fa-refresh"></i> Refresh
        </button>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table id="portConfigTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                <thead>
                    <tr>
                        <th>Interface</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Speed</th>
                        <th>Duplex</th>
                        <th>Flow Control</th>
                        <th>Medium</th>
                        <th>Fiber Auto</th>
                        <th>Auto-Nego</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    <div class="panel-footer" id="portconfig_help_attribute" style="display:none;">
        Help: 10G (tg), 25G (wg) and 100G (cg) ports support Status, Speed, Flow Control, Fiber Auto, Auto-Nego and Description. Duplex is fixed to Full.
    </div>
    <div class="panel-footer" id="portconfig_help_classic">
        Help: GigaEthernet supports Status, Speed, Duplex and Flow Control. TGigaEthernet supports Status, Fiber Auto and Flow Control - Speed can only be set on TGigaEthernet when Fiber Auto is Off, and Duplex is fixed to Full.
    </div>
</div>

<!-- Edit Port Configuration Modal -->
<div id="editPortConfigModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Port Configuration - <span id="edit_interface_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="edit_interface" value="">
                    <input type="hidden" id="edit_type" value="">

                    <div class="form-group">
                        <label class="col-sm-4 control-label">Status</label>
                        <div class="col-sm-8">
                            <select id="edit_status" class="form-control">
                                <option value="">-- No change --</option>
                                <option value="enable">Enable</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="edit_speed_group">
                        <label class="col-sm-4 control-label">Speed</label>
                        <div class="col-sm-8">
                            <select id="edit_speed" class="form-control"></select>
                        </div>
                    </div>

                    <div class="form-group" id="edit_duplex_group">
                        <label class="col-sm-4 control-label">Duplex</label>
                        <div class="col-sm-8">
                            <select id="edit_duplex" class="form-control">
                                <option value="">-- No change --</option>
                                <option value="full">Full</option>
                                <option value="half">Half</option>
                                <option value="auto">Auto</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="edit_fiber_auto_group">
                        <label class="col-sm-4 control-label">Fiber Auto</label>
                        <div class="col-sm-8">
                            <select id="edit_fiber_auto" class="form-control">
                                <option value="">-- No change --</option>
                                <option value="on">On</option>
                                <option value="off">Off</option>
                            </select>
                            <span class="help-block" id="edit_fiber_auto_help">Speed can only be changed on TGigaEthernet while Fiber Auto is Off.</span>
                        </div>
                    </div>

                    <div class="form-group" id="edit_auto_nego_group" style="display:none;">
                        <label class="col-sm-4 control-label">Auto-Nego</label>
                        <div class="col-sm-8">
                            <select id="edit_auto_nego" class="form-control">
                                <option value="">-- No change --</option>
                                <option value="enable">Enable</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label">Flow Control</label>
                        <div class="col-sm-8">
                            <select id="edit_flow_control" class="form-control">
                                <option value="">-- No change --</option>
                                <option value="on">On</option>
                                <option value="off">Off</option>
                                <option value="auto" id="edit_flow_control_auto_opt">Auto</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="edit_description_group" style="display:none;">
                        <label class="col-sm-4 control-label">Description</label>
                        <div class="col-sm-8">
                            <input type="text" id="edit_description" class="form-control" maxlength="64" placeholder="e.g. Uplink to core">
                            <span class="help-block">Letters, digits, space and _ . , : / - only. Leave as is for no change.</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="savePortConfigBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function setCookie(name, value, days = 7) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 86400000));
        document.cookie = name + "=" + encodeURIComponent(value) + ";expires=" + d.toUTCString() + ";path=/";
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    const DEVICE_IP = "{{ $device->hostname }}";
    const API_TOKEN = "{{ $data['api_token'] }}";
    const COOKIE_PREFIX = DEVICE_IP + "_";
    setCookie(COOKIE_PREFIX + "device_ip", DEVICE_IP);
    setCookie(COOKIE_PREFIX + "api_token", API_TOKEN);

    // "10000" (Mb, list-format playbooks) -> "10G"
    const speedLabel = mb => Number(mb) >= 1000 ? (Number(mb) / 1000) + 'G' : mb + 'M';

    var portConfigTable = $('#portConfigTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/portconfig/show/" + DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('portconfig_cache_note').style.display = json.cached ? "inline" : "none";
                const rows = json.interfaces || [];
                portConfigLayout(rows, json.playbook);
                return rows;
            }
        },
        columns: [
            { data: "interface" },
            { data: "description", render: d => d ? $('<span>').text(d).html() : '<span class="text-muted">-</span>' },
            { data: "status", render: d => d || '<span class="text-muted">-</span>' },
            { data: "speed", render: (d, t, row) => !d ? '<span class="text-muted">-</span>' : (row.format === 'attribute' && /^\d+$/.test(d) ? speedLabel(d) : d) },
            { data: "duplex", render: d => d || '<span class="text-muted">-</span>' },
            { data: "flow_control", render: d => d || '<span class="text-muted">-</span>' },
            { data: "medium", render: d => d || '<span class="text-muted">-</span>' },
            { data: "fiber_auto", render: d => d || '<span class="text-muted">-</span>' },
            { data: "auto_nego", render: d => d || '<span class="text-muted">-</span>' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    if (!(SPEED_OPTIONS[row.type] || ATTRIBUTE_SPEEDS[row.type])) {
                        return '<span class="text-muted">Not editable</span>';
                    }
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-portconfig">Edit</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No interfaces found"
        }
    });

    // Columns 1-based, as in the <thead>. Description, Medium and Auto-Nego
    // only exist in some playbooks' output - hide a column whose field the
    // API doesn't send for this switch.
    const OPTIONAL_COLUMNS = { description: 2, medium: 7, auto_nego: 9 };

    function portConfigLayout(rows, playbook) {
        const css = Object.entries(OPTIONAL_COLUMNS)
            .filter(([field]) => !rows.some(r => field in r))
            .map(([, n]) => `#portConfigTable th:nth-child(${n}), #portConfigTable td:nth-child(${n}) { display: none; }`)
            .join('\n');
        let style = document.getElementById('portconfig_columns_css');
        if (!style) {
            style = document.createElement('style');
            style.id = 'portconfig_columns_css';
            document.head.appendChild(style);
        }
        style.textContent = css;

        const attribute = rows.some(r => r.format === 'attribute');
        $('#portconfig_help_attribute').toggle(attribute);
        $('#portconfig_help_classic').toggle(!attribute);

        // Which playbook read the switch: "port_configuration/switchv1alphabridge/100g/..." -> "switchv1alphabridge / 100g"
        const label = $('#portconfig_playbook');
        if (playbook) {
            const variant = playbook.split('/').slice(1, -1).join(' / ');
            label.attr('class', 'label ' + (variant ? 'label-info' : 'label-default'))
                .text('Playbook: ' + (variant || 'common')).attr('title', 'playbooks/' + playbook).show();
        } else {
            label.hide();
        }
    }

    // Speeds (Mb) the list-format SET playbook takes, up to each port class's own speed.
    const ATTRIBUTE_SPEEDS = {
        '10G':  ['100', '1000', '10000'],
        '25G':  ['1000', '10000', '25000'],
        '100G': ['10000', '25000', '100000']
    };

    const SPEED_OPTIONS = {
        GigaEthernet: [
            { value: '', label: '-- No change --' },
            { value: '10M', label: '10M' },
            { value: '100M', label: '100M' },
            { value: 'auto', label: 'Auto' }
        ],
        TGigaEthernet: [
            { value: '', label: '-- No change --' },
            { value: '100M', label: '100M' },
            { value: '1000M', label: '1000M' },
            { value: '10G', label: '10G' }
        ]
    };

    $('#portConfigTable tbody').on('click', '.btn-edit-portconfig', function () {
        const row = portConfigTable.row($(this).closest('tr')).data();

        $('#edit_interface').val(row.interface);
        $('#edit_type').val(row.type);
        $('#editPortConfigModal').data('format', row.format || 'classic');

        if (row.format === 'attribute') {
            openAttributeEditor(row);
            return;
        }
        $('#edit_auto_nego_group, #edit_description_group').hide();
        $('#edit_fiber_auto option[value="enable"], #edit_fiber_auto option[value="disable"]').remove();
        if (!$('#edit_fiber_auto option[value="on"]').length) {
            $('#edit_fiber_auto').append('<option value="on">On</option><option value="off">Off</option>');
        }
        $('#edit_fiber_auto_help').show();
        $('#edit_interface_label').text(row.interface + ' (' + row.type + ')');

        // Pre-fill each field with the interface's current value so the
        // modal shows what's actually configured, not a blank "no change".
        $('#edit_status').val(row.status || '');
        $('#edit_duplex').val(row.duplex || '');
        $('#edit_fiber_auto').val(row.fiber_auto || '');
        $('#edit_flow_control').val(row.flow_control || '');

        const speedSelect = $('#edit_speed');
        speedSelect.empty();
        (SPEED_OPTIONS[row.type] || []).forEach(opt => {
            speedSelect.append(`<option value="${opt.value}">${opt.label}</option>`);
        });
        speedSelect.val(row.speed || '');

        if (row.type === 'GigaEthernet') {
            $('#edit_duplex_group').show();
            $('#edit_fiber_auto_group').hide();
            $('#edit_flow_control_auto_opt').show();
        } else {
            // TGigaEthernet: no duplex (fixed Full), has fiber_auto,
            // flow_control only supports on/off (no auto). If the
            // interface's current flow_control somehow is "auto" (not
            // valid for this type), don't silently resend it - clear it
            // to "no change" since the hidden option can't be selected.
            $('#edit_duplex_group').hide();
            $('#edit_fiber_auto_group').show();
            $('#edit_flow_control_auto_opt').hide();
            if ($('#edit_flow_control').val() === 'auto') {
                $('#edit_flow_control').val('');
            }
        }

        $('#editPortConfigModal').modal('show');
    });

    // Editor for the list-format playbooks (10G/25G/100G ports): duplex is
    // always full, fiber auto is enable/disable, plus auto-nego and description.
    function openAttributeEditor(row) {
        $('#edit_interface_label').text(row.interface + ' (' + row.type + ')');

        const speedSelect = $('#edit_speed').empty().append('<option value="">-- No change --</option>');
        (ATTRIBUTE_SPEEDS[row.type] || []).forEach(mb => speedSelect.append(`<option value="${mb}">${speedLabel(mb)}</option>`));
        speedSelect.val(row.speed || '');

        $('#edit_fiber_auto').find('option[value="on"], option[value="off"], option[value="enable"], option[value="disable"]').remove();
        $('#edit_fiber_auto').append('<option value="enable">Enable</option><option value="disable">Disable</option>');

        $('#edit_status').val(row.status || '');
        $('#edit_fiber_auto').val(row.fiber_auto || '');
        $('#edit_auto_nego').val(row.auto_nego || '');
        $('#edit_flow_control').val(row.flow_control === 'auto' ? '' : (row.flow_control || ''));
        $('#edit_description').val(row.description || '').data('original', row.description || '');

        $('#edit_duplex_group').hide();
        $('#edit_flow_control_auto_opt').hide();
        $('#edit_fiber_auto_help').hide();
        $('#edit_fiber_auto_group, #edit_auto_nego_group, #edit_description_group').show();

        // Only send what was changed.
        $('#editPortConfigModal').data('original', row);
        $('#editPortConfigModal').modal('show');
    }

    function attributePayload() {
        const row = $('#editPortConfigModal').data('original') || {};
        const payload = { interface: row.interface };
        const pick = (field, value) => { if (value && value !== (row[field] || '')) payload[field] = value; };

        pick('status', $('#edit_status').val());
        pick('speed', $('#edit_speed').val());
        pick('flow_control', $('#edit_flow_control').val());
        pick('fiber_auto', $('#edit_fiber_auto').val());
        pick('auto_nego', $('#edit_auto_nego').val());

        const desc = $('#edit_description').val().trim();
        if (desc !== $('#edit_description').data('original')) {
            if (!desc) return { error: 'The description cannot be cleared from here.' };
            if (!/^[A-Za-z0-9 _.,:\/-]+$/.test(desc)) return { error: 'Description: letters, digits, space and _ . , : / - only.' };
            payload.description = desc;
        }
        return payload;
    }

    $('#savePortConfigBtn').on('click', function () {
        const interfaceName = $('#edit_interface').val();
        const type = $('#edit_type').val();

        if ($('#editPortConfigModal').data('format') === 'attribute') {
            const payload = attributePayload();
            if (payload.error) { alert(payload.error); return; }
            if (Object.keys(payload).length === 1) { alert('Please change at least one field.'); return; }
            return savePortConfig(payload);
        }

        const payload = { interface: interfaceName };
        const status = $('#edit_status').val();
        const speed = $('#edit_speed').val();
        const duplex = $('#edit_duplex').val();
        const fiberAuto = $('#edit_fiber_auto').val();
        const flowControl = $('#edit_flow_control').val();

        if (status) payload.status = status;
        if (speed) payload.speed = speed;
        if (type === 'GigaEthernet' && duplex) payload.duplex = duplex;
        if (type === 'TGigaEthernet' && fiberAuto) payload.fiber_auto = fiberAuto;
        if (flowControl) payload.flow_control = flowControl;

        if (Object.keys(payload).length === 1) {
            alert('Please change at least one field.');
            return;
        }

        if (type === 'TGigaEthernet' && speed && fiberAuto !== 'off') {
            alert('Speed can only be changed on TGigaEthernet when Fiber Auto is set to Off in this same request.');
            return;
        }

        savePortConfig(payload);
    });

    function savePortConfig(payload) {
        const $btn = $('#savePortConfigBtn');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/portconfig/set/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();

                if (response.status === "success") {
                    $('#editPortConfigModal').modal('hide');
                    portConfigTable.ajax.reload(null, false);
                    alert('Port configuration updated successfully!');
                } else {
                    alert('Error: ' + (response.message || JSON.stringify(response)));
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();
                alert('Request failed: ' + (xhr.responseJSON?.message || xhr.responseText));
            }
        });
    }
</script>
