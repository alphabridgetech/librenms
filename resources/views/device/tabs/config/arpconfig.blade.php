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
</style>

<div class="container" style="margin-top:30px;">

    <div class="panel panel-info">
        <div class="panel-heading" data-toggle="collapse" data-target="#addArpCollapse" style="cursor:pointer;">
            <strong>Add Static ARP Entry</strong>
            <i class="fa fa-chevron-down pull-right"></i>
        </div>
        <div id="addArpCollapse" class="collapse">
            <div class="panel-body">
                <form class="form-horizontal" id="arpAddForm">
                    <div class="form-group">
                        <label class="col-sm-2 control-label">IP Address*</label>
                        <div class="col-sm-6">
                            <input type="text" id="arp_add_ip" class="form-control" placeholder="192.168.2.100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label">MAC Address*</label>
                        <div class="col-sm-6">
                            <input type="text" id="arp_add_mac" class="form-control" placeholder="78:18:00:00:00:89">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Interface VLAN*</label>
                        <div class="col-sm-6">
                            <input type="number" id="arp_add_vlan" class="form-control" min="1" max="4094" placeholder="200">
                        </div>
                    </div>
                    <div id="arp_add_error" class="text-danger" style="display:none; margin-left:15px;"></div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-6">
                            <button type="button" id="arpAddBtn" class="btn btn-primary" onclick="addArp()">
                                Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="panel-footer">
                CLI: <code>arp &lt;ip-address&gt; &lt;mac-address&gt; vlan &lt;vlan-id&gt;</code>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Current Static ARP Entries</strong>
            <span id="arp_cache_note" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadArpTable()">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table id="arpTable" class="table table-striped table-bordered table-condensed" style="width:auto;">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>MAC Address</th>
                            <th>Interface VLAN</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Edit ARP Modal -->
<div id="editArpModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Static ARP Entry</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="arpEditForm">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">IP Address</label>
                        <div class="col-sm-8">
                            <input type="text" id="arp_edit_ip" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">New MAC Address*</label>
                        <div class="col-sm-8">
                            <input type="text" id="arp_edit_mac" class="form-control" placeholder="78:18:00:00:00:AA">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Interface VLAN*</label>
                        <div class="col-sm-8">
                            <input type="number" id="arp_edit_vlan" class="form-control" min="1" max="4094" placeholder="300">
                        </div>
                    </div>
                    <div id="arp_edit_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="arpEditBtn" class="btn btn-warning" onclick="editArp()">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $('#addArpCollapse').on('show.bs.collapse', function () {
        $(this).prev('.panel-heading').find('.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }).on('hide.bs.collapse', function () {
        $(this).prev('.panel-heading').find('.fa-chevron-up').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });

    const arpIp = "{{ $device->hostname }}";
    const arpApiToken = "{{ $data['api_token'] }}";
    const MAC_RE = /^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/;
    const IPV4_RE = /^(\d{1,3}\.){3}\d{1,3}$/;

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

    document.getElementById('arpAddBtn').dataset.label = 'Add';
    document.getElementById('arpEditBtn').dataset.label = 'Update';

    var arpTable = $('#arpTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: `/api/v0/arp/show/${arpIp}`,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + arpApiToken,
                "Accept": "application/json"
            },
            dataSrc: function (json) {
                document.getElementById('arp_cache_note').style.display = json.cached ? "inline" : "none";
                return json.arp_entries || [];
            }
        },
        columns: [
            { data: "IP Address" },
            { data: "MAC Address" },
            { data: "Interface VLAN" },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function () {
                    return '<button type="button" class="btn btn-xs btn-warning btn-edit-arp">Edit</button> ' +
                        '<button type="button" class="btn btn-xs btn-danger btn-delete-arp">Delete</button>';
                }
            }
        ],
        order: [[0, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No static ARP entries found"
        }
    });

    function loadArpTable() {
        arpTable.ajax.reload();
    }

    function openEditArpModal(row) {
        clearFieldError('arp_edit_error');
        document.getElementById('arp_edit_ip').value = row['IP Address'] || '';
        document.getElementById('arp_edit_mac').value = row['MAC Address'] || '';
        document.getElementById('arp_edit_vlan').value = row['Interface VLAN'] || '';
        $('#editArpModal').modal('show');
    }

    $('#arpTable tbody').on('click', '.btn-edit-arp', function () {
        const row = arpTable.row($(this).closest('tr')).data();
        openEditArpModal(row);
    });

    $('#arpTable tbody').on('click', '.btn-delete-arp', function () {
        const row = arpTable.row($(this).closest('tr')).data();
        deleteArpRow(row['IP Address'], row['Interface VLAN']);
    });

    function callArpApi(url, body, errorId, btnId, busyLabel, onSuccess) {
        clearFieldError(errorId);
        setBusy(btnId, busyLabel, true);

        fetch(url, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + arpApiToken,
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

    function addArp() {
        const ip = document.getElementById('arp_add_ip').value.trim();
        const mac = document.getElementById('arp_add_mac').value.trim();
        const vlan = parseInt(document.getElementById('arp_add_vlan').value, 10);

        if (!IPV4_RE.test(ip)) {
            showFieldError('arp_add_error', 'Enter a valid IPv4 address');
            return;
        }
        if (!MAC_RE.test(mac)) {
            showFieldError('arp_add_error', 'MAC address must be in the format XX:XX:XX:XX:XX:XX');
            return;
        }
        if (!vlan || vlan < 1 || vlan > 4094) {
            showFieldError('arp_add_error', 'Interface VLAN must be between 1 and 4094');
            return;
        }

        callArpApi(`/api/v0/arp/add/${arpIp}`, { ip_address: ip, mac_address: mac, interface_vlan: vlan }, 'arp_add_error', 'arpAddBtn', 'Adding...', function () {
            document.getElementById('arpAddForm').reset();
            loadArpTable();
        });
    }

    function editArp() {
        const ip = document.getElementById('arp_edit_ip').value.trim();
        const mac = document.getElementById('arp_edit_mac').value.trim();
        const vlan = parseInt(document.getElementById('arp_edit_vlan').value, 10);

        if (!IPV4_RE.test(ip)) {
            showFieldError('arp_edit_error', 'Enter a valid IPv4 address');
            return;
        }
        if (!MAC_RE.test(mac)) {
            showFieldError('arp_edit_error', 'MAC address must be in the format XX:XX:XX:XX:XX:XX');
            return;
        }
        if (!vlan || vlan < 1 || vlan > 4094) {
            showFieldError('arp_edit_error', 'Interface VLAN must be between 1 and 4094');
            return;
        }

        callArpApi(`/api/v0/arp/edit/${arpIp}`, { ip_address: ip, mac_address: mac, interface_vlan: vlan }, 'arp_edit_error', 'arpEditBtn', 'Updating...', function () {
            $('#editArpModal').modal('hide');
            document.getElementById('arpEditForm').reset();
            loadArpTable();
        });
    }

    function deleteArpRow(ip, vlan) {
        if (!confirm(`Delete static ARP entry for ${ip} on VLAN ${vlan}?`)) {
            return;
        }

        fetch(`/api/v0/arp/delete/${arpIp}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + arpApiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ ip_address: ip, interface_vlan: vlan })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                alert(res.message || "Deleted successfully");
                loadArpTable();
            } else {
                alert(res.message || "Failed to delete ARP entry");
            }
        })
        .catch(() => alert("API communication error"));
    }
</script>
