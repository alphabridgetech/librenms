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
</style>

<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">
<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Port Configuration</strong>
        <span id="portconfig_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <button type="button" class="btn btn-xs btn-default pull-right" onclick="portConfigTable.ajax.reload()">
            <i class="fa fa-refresh"></i> Refresh
        </button>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table id="portConfigTable" class="table table-striped table-bordered table-condensed" style="width:auto;">
                <thead>
                    <tr>
                        <th>Interface</th>
                        <th>Status</th>
                        <th>Speed</th>
                        <th>Duplex</th>
                        <th>Flow Control</th>
                        <th>Medium</th>
                        <th>Fiber Auto</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    <div class="panel-footer">
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
                            <span class="help-block">Speed can only be changed on TGigaEthernet while Fiber Auto is Off.</span>
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
                return json.interfaces || [];
            }
        },
        columns: [
            { data: "interface" },
            { data: "status", render: d => d || '<span class="text-muted">-</span>' },
            { data: "speed", render: d => d || '<span class="text-muted">-</span>' },
            { data: "duplex", render: d => d || '<span class="text-muted">-</span>' },
            { data: "flow_control", render: d => d || '<span class="text-muted">-</span>' },
            { data: "medium", render: d => d || '<span class="text-muted">-</span>' },
            { data: "fiber_auto", render: d => d || '<span class="text-muted">-</span>' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    if (row.type !== 'GigaEthernet' && row.type !== 'TGigaEthernet') {
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

    $('#savePortConfigBtn').on('click', function () {
        const interfaceName = $('#edit_interface').val();
        const type = $('#edit_type').val();

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
    });
</script>
