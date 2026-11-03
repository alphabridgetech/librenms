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
        <strong>Keepalive</strong>
        <span id="keepalive_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadKeepaliveTable()">
            <i class="fa fa-refresh"></i> Refresh
        </button>
    </div>
    <div class="panel-body">
        <div id="keepalive_load_error" class="alert alert-danger" style="display:none;"></div>
        <div class="table-responsive">
            <table id="keepaliveTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                <thead>
                    <tr>
                        <th>Interface</th>
                        <th>Keepalive</th>
                        <th>Period (s)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    <div class="panel-footer">
        Help: ports are read from the switch (GigaEthernet and TGigaEthernet). Enable requires a period of 0-32767 seconds. Changes are saved to the switch's startup configuration.
    </div>
</div>

<!-- Edit Keepalive Modal -->
<div id="editKeepaliveModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Keepalive - <span id="ka_edit_interface_label"></span></h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <input type="hidden" id="ka_edit_interface" value="">

                    <div class="form-group">
                        <label class="col-sm-4 control-label">Status</label>
                        <div class="col-sm-8">
                            <select id="ka_edit_status" class="form-control">
                                <option value="Enable">Enable</option>
                                <option value="Disable">Disable</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group ka-period-detail">
                        <label class="col-sm-4 control-label">Period (seconds)</label>
                        <div class="col-sm-8">
                            <input type="number" id="ka_edit_period" class="form-control" min="0" max="32767">
                            <span class="help-block">0-32767</span>
                        </div>
                    </div>

                    <div id="ka_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="saveKeepaliveBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const KA_DEVICE_IP = "{{ $device->hostname }}";
    const KA_API_TOKEN = "{{ $data['api_token'] }}";

    function kaShowError(msg) {
        const el = document.getElementById('ka_edit_error');
        el.innerText = msg;
        el.style.display = "block";
    }

    function kaClearError() {
        const el = document.getElementById('ka_edit_error');
        el.innerText = "";
        el.style.display = "none";
    }

    // Plain string sorting would put "GigaEthernet0/10" before
    // "GigaEthernet0/2" - compare the text/number chunks instead.
    function kaNaturalCompare(a, b) {
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

    $.fn.dataTable.ext.type.order['ka-interface-natural-asc'] = function (a, b) {
        return kaNaturalCompare(a, b);
    };
    $.fn.dataTable.ext.type.order['ka-interface-natural-desc'] = function (a, b) {
        return kaNaturalCompare(b, a);
    };

    var keepaliveTable = $('#keepaliveTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/keepalive/show/" + KA_DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + KA_API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('keepalive_cache_note').style.display = json.cached ? "inline" : "none";

                if (json.status !== "success") {
                    $('#keepalive_load_error').text(json.message || 'Failed to load keepalive configuration').show();
                    return [];
                }

                $('#keepalive_load_error').hide();
                return json.interfaces || [];
            },
            error: function (xhr) {
                $('#keepalive_load_error').text(xhr.responseJSON?.message || 'API communication error').show();
                keepaliveTable.clear().draw();
            }
        },
        columns: [
            { data: "interface", type: "ka-interface-natural" },
            {
                data: "status",
                render: status => status === 'Enable'
                    ? '<span class="label label-success">Enable</span>'
                    : '<span class="text-muted">Disable</span>'
            },
            { data: null, render: row => row.status === 'Enable' ? row.period : '<span class="text-muted">-</span>' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-keepalive">Edit</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No GigaEthernet/TGigaEthernet ports found on this device"
        }
    });

    function loadKeepaliveTable() {
        keepaliveTable.ajax.reload(null, false);
    }

    function kaTogglePeriod() {
        $('.ka-period-detail').toggle($('#ka_edit_status').val() === 'Enable');
    }

    $('#ka_edit_status').on('change', kaTogglePeriod);

    $('#keepaliveTable tbody').on('click', '.btn-edit-keepalive', function () {
        kaClearError();
        const row = keepaliveTable.row($(this).closest('tr')).data();

        $('#ka_edit_interface').val(row.interface);
        $('#ka_edit_interface_label').text(row.interface);
        $('#ka_edit_status').val(row.status === 'Enable' ? 'Enable' : 'Disable');
        $('#ka_edit_period').val(row.status === 'Enable' ? row.period : '');
        kaTogglePeriod();

        $('#editKeepaliveModal').modal('show');
    });

    $('#saveKeepaliveBtn').on('click', function () {
        kaClearError();

        const payload = {
            interface: $('#ka_edit_interface').val(),
            status: $('#ka_edit_status').val()
        };

        if (payload.status === 'Enable') {
            payload.period = $.trim($('#ka_edit_period').val());
            if (!/^\d+$/.test(payload.period) || payload.period > 32767) {
                kaShowError('Period must be a whole number from 0 to 32767.');
                return;
            }
        }

        const $btn = $('#saveKeepaliveBtn');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/keepalive/set/" + KA_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + KA_API_TOKEN,
                "Accept": "application/json"
            },
            data: payload,
            success: function (response) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();

                if (response.status === "success") {
                    $('#editKeepaliveModal').modal('hide');
                    loadKeepaliveTable();
                    alert(response.message || 'Keepalive updated successfully!');
                } else if (response.errors) {
                    kaShowError(Object.values(response.errors).flat().join(' '));
                } else {
                    kaShowError(response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();
                const errors = xhr.responseJSON?.errors;
                kaShowError(errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error'));
            }
        });
    });
</script>
