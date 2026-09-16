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
        <strong>Rate Limit</strong>
        <span id="ratelimit_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadRateLimitTable()">
            <i class="fa fa-refresh"></i> Refresh
        </button>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table id="rateLimitTable" class="table table-striped table-bordered table-condensed" style="width:auto;">
                <thead>
                    <tr>
                        <th>Interface</th>
                        <th>Receive (Ingress)</th>
                        <th>Send (Egress)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    <div class="panel-footer">
        Help: ports are taken directly from Telequill (every GigaEthernet/TGigaEthernet port on this device, regardless of VLAN). Enable requires a Speed Unit and Speed - 64kbps accepts 1-15625, Percent accepts 1-100.
    </div>
</div>

<!-- Edit Rate Limit Modal -->
<div id="editRateLimitModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Rate Limit - <span id="rl_edit_interface_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="rl_edit_interface" value="">

                    <h5><strong>Receive (Ingress)</strong></h5>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Status</label>
                        <div class="col-sm-8">
                            <select id="rl_edit_receive_status" class="form-control">
                                <option value="">-- No change --</option>
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group rl-receive-detail" style="display:none;">
                        <label class="col-sm-4 control-label">Speed Unit</label>
                        <div class="col-sm-8">
                            <select id="rl_edit_receive_speed_unit" class="form-control">
                                <option value="64kbps">64kbps</option>
                                <option value="Percent">Percent</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group rl-receive-detail" style="display:none;">
                        <label class="col-sm-4 control-label">Speed</label>
                        <div class="col-sm-8">
                            <input type="number" id="rl_edit_receive_speed" class="form-control" min="1" max="15625">
                            <span class="help-block" id="rl_edit_receive_speed_help">64kbps: 1-15625</span>
                        </div>
                    </div>

                    <hr>

                    <h5><strong>Send (Egress)</strong></h5>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Status</label>
                        <div class="col-sm-8">
                            <select id="rl_edit_send_status" class="form-control">
                                <option value="">-- No change --</option>
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group rl-send-detail" style="display:none;">
                        <label class="col-sm-4 control-label">Speed Unit</label>
                        <div class="col-sm-8">
                            <select id="rl_edit_send_speed_unit" class="form-control">
                                <option value="64kbps">64kbps</option>
                                <option value="Percent">Percent</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group rl-send-detail" style="display:none;">
                        <label class="col-sm-4 control-label">Speed</label>
                        <div class="col-sm-8">
                            <input type="number" id="rl_edit_send_speed" class="form-control" min="1" max="15625">
                            <span class="help-block" id="rl_edit_send_speed_help">64kbps: 1-15625</span>
                        </div>
                    </div>

                    <div id="rl_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="saveRateLimitBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const RL_DEVICE_IP = "{{ $device->hostname }}";
    const RL_API_TOKEN = "{{ $data['api_token'] }}";

    function rlShowError(msg) {
        const el = document.getElementById('rl_edit_error');
        el.innerText = msg;
        el.style.display = "block";
    }

    function rlClearError() {
        const el = document.getElementById('rl_edit_error');
        el.innerText = "";
        el.style.display = "none";
    }

    function rlFormatDirection(status, unit, speed) {
        if (status !== 'Enable') {
            return '<span class="text-muted">Disable</span>';
        }
        const unitLabel = unit || '';
        const speedLabel = (speed || speed === 0) ? speed : '';
        return '<span class="label label-success">Enable</span> ' + speedLabel + ' ' + unitLabel;
    }

    // Plain string sorting would put "GigaEthernet0/10" before
    // "GigaEthernet0/2" - split into text/number chunks and compare
    // numerically so descending actually reads 0/28, 0/27, ... 0/1.
    function rlNaturalCompare(a, b) {
        const ax = [], bx = [];
        String(a).replace(/(\d+)|(\D+)/g, function (_, num, str) {
            ax.push([num ? parseInt(num, 10) : Infinity, str || '']);
        });
        String(b).replace(/(\d+)|(\D+)/g, function (_, num, str) {
            bx.push([num ? parseInt(num, 10) : Infinity, str || '']);
        });
        while (ax.length && bx.length) {
            const an = ax.shift();
            const bn = bx.shift();
            const diff = (an[0] - bn[0]) || an[1].localeCompare(bn[1]);
            if (diff) return diff;
        }
        return ax.length - bx.length;
    }

    $.fn.dataTable.ext.type.order['interface-natural-asc'] = function (a, b) {
        return rlNaturalCompare(a, b);
    };
    $.fn.dataTable.ext.type.order['interface-natural-desc'] = function (a, b) {
        return rlNaturalCompare(b, a);
    };

    var rateLimitTable = $('#rateLimitTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/ratelimit/show/" + RL_DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + RL_API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('ratelimit_cache_note').style.display = json.cached ? "inline" : "none";

                if (json.status !== "success") {
                    return [];
                }

                const ports = json.ports || [];
                const entries = json.entries || {};

                // Every LibreNMS-known GigaEthernet/TGigaEthernet port is
                // always shown, whether or not the switch currently
                // returned a rate-limit entry for it (defaults to Disable
                // for any port the SSH show didn't report on).
                return ports.map(function (port) {
                    const entry = entries[port] || {};
                    return {
                        interface: port,
                        receive_status: entry.receive_status || 'Disable',
                        receive_speed_unit: entry.receive_speed_unit || '',
                        receive_speed: entry.receive_speed,
                        send_status: entry.send_status || 'Disable',
                        send_speed_unit: entry.send_speed_unit || '',
                        send_speed: entry.send_speed
                    };
                });
            }
        },
        columns: [
            { data: "interface", type: "interface-natural" },
            { data: null, render: row => rlFormatDirection(row.receive_status, row.receive_speed_unit, row.receive_speed) },
            { data: null, render: row => rlFormatDirection(row.send_status, row.send_speed_unit, row.send_speed) },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-ratelimit">Edit</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No GigaEthernet/TGigaEthernet ports found for this device"
        }
    });

    function loadRateLimitTable() {
        rateLimitTable.ajax.reload(null, false);
    }

    function rlToggleDetail(statusSelectId, detailClass, speedUnitId) {
        const status = document.getElementById(statusSelectId).value;
        document.querySelectorAll('.' + detailClass).forEach(function (el) {
            el.style.display = (status === 'Enable') ? '' : 'none';
        });
        rlUpdateSpeedHelp(speedUnitId);
    }

    function rlUpdateSpeedHelp(speedUnitId) {
        const unit = document.getElementById(speedUnitId).value;
        const speedInput = document.getElementById(speedUnitId.replace('_speed_unit', '_speed'));
        const help = document.getElementById(speedUnitId.replace('_speed_unit', '_speed_help'));
        if (unit === 'Percent') {
            speedInput.min = 1;
            speedInput.max = 100;
            help.innerText = 'Percent: 1-100';
        } else {
            speedInput.min = 1;
            speedInput.max = 15625;
            help.innerText = '64kbps: 1-15625';
        }
    }

    $('#rl_edit_receive_status').on('change', function () {
        rlToggleDetail('rl_edit_receive_status', 'rl-receive-detail', 'rl_edit_receive_speed_unit');
    });
    $('#rl_edit_send_status').on('change', function () {
        rlToggleDetail('rl_edit_send_status', 'rl-send-detail', 'rl_edit_send_speed_unit');
    });
    $('#rl_edit_receive_speed_unit').on('change', function () {
        rlUpdateSpeedHelp('rl_edit_receive_speed_unit');
    });
    $('#rl_edit_send_speed_unit').on('change', function () {
        rlUpdateSpeedHelp('rl_edit_send_speed_unit');
    });

    $('#rateLimitTable tbody').on('click', '.btn-edit-ratelimit', function () {
        rlClearError();
        const row = rateLimitTable.row($(this).closest('tr')).data();

        $('#rl_edit_interface').val(row.interface);
        $('#rl_edit_interface_label').text(row.interface);

        // Pre-select the port's actual current status (not blank "-- No
        // change --") so the modal shows what's really configured - the
        // user can still switch either back to "-- No change --" to leave
        // that direction untouched.
        $('#rl_edit_receive_status').val(row.receive_status || 'Disable');
        $('#rl_edit_send_status').val(row.send_status || 'Disable');
        $('#rl_edit_receive_speed_unit').val(row.receive_speed_unit || '64kbps');
        $('#rl_edit_receive_speed').val(row.receive_speed || '');
        $('#rl_edit_send_speed_unit').val(row.send_speed_unit || '64kbps');
        $('#rl_edit_send_speed').val(row.send_speed || '');

        rlToggleDetail('rl_edit_receive_status', 'rl-receive-detail', 'rl_edit_receive_speed_unit');
        rlToggleDetail('rl_edit_send_status', 'rl-send-detail', 'rl_edit_send_speed_unit');

        $('#editRateLimitModal').modal('show');
    });

    $('#saveRateLimitBtn').on('click', function () {
        rlClearError();

        const payload = { interface: $('#rl_edit_interface').val() };
        const receiveStatus = $('#rl_edit_receive_status').val();
        const sendStatus = $('#rl_edit_send_status').val();

        if (!receiveStatus && !sendStatus) {
            rlShowError('Set Receive Status and/or Send Status to Enable or Disable.');
            return;
        }

        if (receiveStatus) {
            payload.receive_status = receiveStatus;
            if (receiveStatus === 'Enable') {
                payload.receive_speed_unit = $('#rl_edit_receive_speed_unit').val();
                payload.receive_speed = $('#rl_edit_receive_speed').val();
                if (!payload.receive_speed) {
                    rlShowError('Receive Speed is required when Receive Status is Enable.');
                    return;
                }
            }
        }

        if (sendStatus) {
            payload.send_status = sendStatus;
            if (sendStatus === 'Enable') {
                payload.send_speed_unit = $('#rl_edit_send_speed_unit').val();
                payload.send_speed = $('#rl_edit_send_speed').val();
                if (!payload.send_speed) {
                    rlShowError('Send Speed is required when Send Status is Enable.');
                    return;
                }
            }
        }

        const $btn = $('#saveRateLimitBtn');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/ratelimit/set/" + RL_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + RL_API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();

                if (response.status === "success") {
                    $('#editRateLimitModal').modal('hide');
                    loadRateLimitTable();
                    alert(response.message || 'Rate limit updated successfully!');
                } else if (response.errors) {
                    rlShowError(Object.values(response.errors).flat().join(' '));
                } else {
                    rlShowError(response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();
                rlShowError(xhr.responseJSON?.message || 'API communication error');
            }
        });
    });
</script>
