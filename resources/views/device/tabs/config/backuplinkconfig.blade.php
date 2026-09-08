<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">
<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

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
        100% {
            transform: rotate(360deg);
        }
    }

    .dataTables_wrapper thead th {
        white-space: nowrap;
    }
</style>

<div class="container" style="margin-top:30px;">

    <div class="panel panel-info">
        <div class="panel-heading" data-toggle="collapse" data-target="#addBackuplinkCollapse" style="cursor:pointer;">
            <strong>Add / Configure BackupLink Group</strong>
            <i class="fa fa-chevron-down pull-right"></i>
        </div>
        <div id="addBackuplinkCollapse" class="collapse">
            <div class="panel-body">
                <form class="form-horizontal" id="backuplinkAddForm">
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Group ID*</label>
                        <div class="col-sm-6">
                            <input type="number" id="backuplink_add_group_id" class="form-control" min="1" max="8" placeholder="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Preemption Mode*</label>
                        <div class="col-sm-6">
                            <select id="backuplink_add_mode" class="form-control">
                                <option value="none">No Preemption</option>
                                <option value="forced">Active Port Preempt First (forced)</option>
                                <option value="bandwidth">High Bandwidth Port Preempt First</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="backuplink_add_delay_group">
                        <label class="col-sm-2 control-label">Preemption Delay*</label>
                        <div class="col-sm-6">
                            <input type="number" id="backuplink_add_delay" class="form-control" min="0" placeholder="0">
                        </div>
                    </div>
                    <div id="backuplink_add_error" class="text-danger" style="display:none; margin-left:15px;"></div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-6">
                            <button type="button" id="backuplinkAddBtn" class="btn btn-primary" onclick="addBackuplink()">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="panel-footer">
                CLI: <code>backup-link-group &lt;group-id&gt; preemption-mode &lt;mode&gt; delay &lt;delay&gt;</code>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Current BackupLink Groups</strong>
            <span id="backuplink_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadBackuplinkTable()">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table id="backuplinkTable" class="table table-condensed table-hover table-striped" style="width:auto;">
                    <thead>
                        <tr>
                            <th width="90">Group ID</th>
                            <th width="260">Preemption Mode</th>
                            <th width="130">Preemption Delay</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Edit BackupLink Modal -->
<div id="editBackuplinkModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit BackupLink Group</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="backuplinkEditForm">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Group ID</label>
                        <div class="col-sm-8">
                            <input type="text" id="backuplink_edit_group_id" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Preemption Mode*</label>
                        <div class="col-sm-8">
                            <select id="backuplink_edit_mode" class="form-control">
                                <option value="none">No Preemption</option>
                                <option value="forced">Active Port Preempt First (forced)</option>
                                <option value="bandwidth">High Bandwidth Port Preempt First</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="backuplink_edit_delay_group">
                        <label class="col-sm-4 control-label">Preemption Delay*</label>
                        <div class="col-sm-8">
                            <input type="number" id="backuplink_edit_delay" class="form-control" min="0">
                        </div>
                    </div>
                    <div id="backuplink_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="backuplinkEditBtn" class="btn btn-warning" onclick="editBackuplink()">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const backuplinkIp = "{{ $device->hostname }}";
    const backuplinkApiToken = "{{ $data['api_token'] }}";

    function showFieldError(id, msg) {
        const el = document.getElementById(id);
        el.innerText = msg;
        el.style.display = "block";
    }

    function clearFieldError(id) {
        const el = document.getElementById(id);
        el.innerText = "";
        el.style.display = "none";
    }

    function setBusy(btnId, busyLabel, busy) {
        const btn = document.getElementById(btnId);
        btn.disabled = busy;
        btn.innerHTML = busy ? '<span class="spinner-border"></span> ' + busyLabel : btn.dataset.label;
    }

    document.getElementById('backuplinkAddBtn').dataset.label = 'Save';
    document.getElementById('backuplinkEditBtn').dataset.label = 'Update';

    $('#addBackuplinkCollapse').on('show.bs.collapse', function () {
        $(this).prev('.panel-heading').find('.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }).on('hide.bs.collapse', function () {
        $(this).prev('.panel-heading').find('.fa-chevron-up').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });

    function toggleDelayField(modeSelectId, delayGroupId) {
        const mode = document.getElementById(modeSelectId).value;
        document.getElementById(delayGroupId).style.display = (mode === 'none') ? 'none' : '';
    }

    $('#backuplink_add_mode').on('change', function () {
        toggleDelayField('backuplink_add_mode', 'backuplink_add_delay_group');
    });
    $('#backuplink_edit_mode').on('change', function () {
        toggleDelayField('backuplink_edit_mode', 'backuplink_edit_delay_group');
    });
    toggleDelayField('backuplink_add_mode', 'backuplink_add_delay_group');

    var backuplinkTable = $('#backuplinkTable').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: `/api/v0/backuplink/show/${backuplinkIp}`,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + backuplinkApiToken,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('backuplink_cache_note').style.display = json.cached ? "inline" : "none";
                return json.groups || [];
            }
        },
        columns: [
            { data: "group_id" },
            { data: "preemption_mode" },
            { data: "preemption_delay" },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-backuplink">Edit</button> ' +
                        '<button type="button" class="btn btn-xs btn-danger btn-delete-backuplink">Delete</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No BackupLink groups configured"
        }
    });

    function loadBackuplinkTable() {
        backuplinkTable.ajax.reload();
    }

    $('#backuplinkTable tbody').on('click', '.btn-edit-backuplink', function () {
        const row = backuplinkTable.row($(this).closest('tr')).data();
        openEditBackuplinkModal(row);
    });

    $('#backuplinkTable tbody').on('click', '.btn-delete-backuplink', function () {
        const row = backuplinkTable.row($(this).closest('tr')).data();
        deleteBackuplinkRow(row['group_id']);
    });

    function openEditBackuplinkModal(row) {
        clearFieldError('backuplink_edit_error');
        document.getElementById('backuplink_edit_group_id').value = row['group_id'] || '';

        const modeMap = {
            'No Preemption': 'none',
            'Active Port Preempt First': 'forced',
            'High Bandwidth Port Preempt First': 'bandwidth'
        };
        const mode = modeMap[row['preemption_mode']] || 'none';
        document.getElementById('backuplink_edit_mode').value = mode;
        document.getElementById('backuplink_edit_delay').value = row['preemption_delay'] || 0;
        toggleDelayField('backuplink_edit_mode', 'backuplink_edit_delay_group');

        $('#editBackuplinkModal').modal('show');
    }

    function callBackuplinkApi(body, errorId, btnId, busyLabel, onSuccess) {
        clearFieldError(errorId);
        setBusy(btnId, busyLabel, true);

        fetch(`/api/v0/backuplink/set/${backuplinkIp}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + backuplinkApiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(body)
        })
        .then(r => r.json())
        .then(res => {
            setBusy(btnId, busyLabel, false);
            if (res.status === "success") {
                alert(res.message || "Success");
                if (onSuccess) onSuccess();
            } else if (res.errors) {
                showFieldError(errorId, Object.values(res.errors).flat().join(' '));
            } else {
                showFieldError(errorId, res.message || "Request failed");
            }
        })
        .catch(() => {
            setBusy(btnId, busyLabel, false);
            showFieldError(errorId, "API communication error");
        });
    }

    function addBackuplink() {
        const groupId = parseInt(document.getElementById('backuplink_add_group_id').value, 10);
        const mode = document.getElementById('backuplink_add_mode').value;
        const delay = parseInt(document.getElementById('backuplink_add_delay').value, 10) || 0;

        if (!groupId || groupId < 1 || groupId > 8) {
            showFieldError('backuplink_add_error', 'Group ID must be between 1 and 8');
            return;
        }
        if (mode !== 'none' && delay < 0) {
            showFieldError('backuplink_add_error', 'Preemption Delay must be 0 or greater');
            return;
        }

        callBackuplinkApi(
            { operation: 'add', group_id: groupId, preemption_mode: mode, preemption_delay: delay },
            'backuplink_add_error', 'backuplinkAddBtn', 'Saving...',
            function () {
                document.getElementById('backuplinkAddForm').reset();
                toggleDelayField('backuplink_add_mode', 'backuplink_add_delay_group');
                loadBackuplinkTable();
            }
        );
    }

    function editBackuplink() {
        const groupId = parseInt(document.getElementById('backuplink_edit_group_id').value, 10);
        const mode = document.getElementById('backuplink_edit_mode').value;
        const delay = parseInt(document.getElementById('backuplink_edit_delay').value, 10) || 0;

        callBackuplinkApi(
            { operation: 'add', group_id: groupId, preemption_mode: mode, preemption_delay: delay },
            'backuplink_edit_error', 'backuplinkEditBtn', 'Updating...',
            function () {
                $('#editBackuplinkModal').modal('hide');
                loadBackuplinkTable();
            }
        );
    }

    function deleteBackuplinkRow(groupId) {
        if (!confirm(`Delete BackupLink group ${groupId}?`)) {
            return;
        }

        fetch(`/api/v0/backuplink/set/${backuplinkIp}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + backuplinkApiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ operation: 'delete', group_id: groupId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                alert(res.message || "Deleted successfully");
                loadBackuplinkTable();
            } else {
                alert(res.message || "Failed to delete BackupLink group");
            }
        })
        .catch(() => alert("API communication error"));
    }
</script>
