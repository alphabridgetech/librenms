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

<link rel="stylesheet" href="//cdn.datatables.net/1.10.25/css/dataTables.bootstrap.min.css">

<script src="//cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="//cdn.datatables.net/1.10.25/js/dataTables.bootstrap.min.js"></script>

<div class="container" style="margin-top:30px;">
    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li class="active"><a href="#vlan_config" data-toggle="tab">VLAN Configuration</a></li>
        <li><a href="#vlan_batch" data-toggle="tab">VLAN Batch Configuration</a></li>
        <li><a href="#interface_vlan" data-toggle="tab">Interface VLAN Attribute</a></li>
        <li><a href="#voice_vlan" data-toggle="tab">Voice VLAN</a></li>
        <li><a href="#interface_voice_vlan" data-toggle="tab">Interface Voice VLAN</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">
        <div class="tab-pane active" id="vlan_config">
            <button class="btn btn-primary" id="btnAddVlan">
                <i class="glyphicon glyphicon-plus"></i> Add
            </button>
            <div class="row" style="margin-top:15px;">
                <div class="col-sm-6">
                    <p id="pagingInfo" class="small-muted">Loading...</p>
                </div>
            </div>

            <table id="vlanTable" class="table table-striped table-bordered table-condensed">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>VLAN ID</th>
                        <th>VLAN Name</th>
                        <th width="80">Operate</th>
                    </tr>
                </thead>
            </table>


            <div class="row" style="margin-top:10px;">
                <div class="col-sm-6">
                    <label><input id="selectAllLabel" type="checkbox"> Select All / None</label>
                </div>
                <div class="col-sm-6 text-right">
                    <button id="batchDeleteBtn" class="btn btn-danger btn-sm">Batch Delete</button>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>The default VLAN cannot be deleted.</li>
                    <li>Click Edit to browse or reset VLAN.</li>
                    <li>For 100+ VLANs use <code>show vlan</code> in CLI.</li>
                </ul>
            </div>
        </div>

        <!-- Other Tabs -->
        <div class="tab-pane" id="vlan_batch">

            <div class="container-fluid" style="margin-top: 20px;">
                <div class="panel panel-primary">

                    <div class="panel-heading">
                        <h3 class="panel-title">Batch VLAN Configuration</h3>
                    </div>

                    <div class="panel-body">
                        <table class="table table-striped table-bordered" style="margin:0;">
                            <tbody>

                                <tr>
                                    <th width="200">VLAN Configured</th>
                                    <td id="vlan_configured">1,200</td>
                                </tr>

                                <tr>
                                    <th>VLAN Add</th>
                                    <td>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="vlan_add">
                                            </div>
                                            <div class="col-sm-6 text-muted" style="line-height:34px;">
                                                &lt;2-4094&gt;
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>VLAN Delete</th>
                                    <td>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="vlan_delete">
                                            </div>
                                            <div class="col-sm-6 text-muted" style="line-height:34px;">
                                                &lt;2-4094&gt;
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>

                    <div class="panel-footer text-right">

                        <button class="btn btn-primary" onclick="applyVlanBatch()"><span class="spinner-border"
                                style="display:none;"></span>Apply</button>
                        <button class="btn btn-default" onclick="resetVlanBatch()">Reset</button>
                    </div>

                </div>

                <!-- Help Panel -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Help</h3>
                    </div>
                    <div class="panel-body">
                        <ul style="margin-bottom:0;">
                            <li>VLAN ID (2-4094), such as (2,3,5,7) or (2-3,5-7) or (2-7) or (2 3,5 7-9)</li>
                            <li>VLAN Operate: First add; Second delete.</li>
                        </ul>
                    </div>
                </div>

            </div>

        </div>






        <div class="tab-pane" id="interface_vlan">
            <button class="btn btn-primary" id="btnAddVlan">
                <i class="glyphicon glyphicon-plus"></i> Add
            </button>
            <div class="row" style="margin-top:15px;">
                <div class="col-sm-6">
                    <p id="pagingInfo" class="small-muted">Loading...</p>
                </div>
            </div>

           <table id="interfaceTable" class="table table-striped table-bordered table-condensed">
    <thead>
        <tr>
            <th width="40">
                <input type="checkbox" id="selectAll">
            </th>
            <th>Port</th>
            <th>Status</th>
            <th>Vlan</th>
            <th>Duplex</th>
            <th>Speed</th>
            <th width="80">Type</th>
        </tr>
    </thead>
</table>



            <div class="row" style="margin-top:10px;">
                <div class="col-sm-6">
                    <label><input id="selectAllLabel" type="checkbox"> Select All / None</label>
                </div>
                <div class="col-sm-6 text-right">
                    <button id="batchDeleteBtn" class="btn btn-danger btn-sm">Batch Delete</button>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>VLAN-allowed and VLAN-untagged: (1-4094), such as (1,3,5,7) Or (1,3-5,7) Or (1-7) Or (1 3,5 7-9)</li>
                </ul>
            </div>
        </div>




        <div class="tab-pane" id="voice_vlan">
            <h4>Voice VLAN</h4>
        </div>
        <div class="tab-pane" id="interface_voice_vlan">
            <h4>Interface Voice VLAN</h4>
        </div>
    </div>
</div>

<!-- Add VLAN Modal -->
<div id="addVlanModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add VLAN</h4>
            </div>
            <div class="modal-body">
                <form id="addVlanForm">
                    <div class="form-group">
                        <label for="vlan_id">VLAN ID</label>
                        <input type="number" class="form-control" id="vlan_id" name="vlan_id"
                            placeholder="Enter VLAN ID" required>
                    </div>
                    <div class="form-group">
                        <label for="vlan_name">VLAN Name</label>
                        <input type="text" class="form-control" id="vlan_name" name="vlan_name"
                            placeholder="Enter VLAN Name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveVlanBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save VLAN
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    /* -----------------------
   COOKIE FUNCTIONS (per hostname)
----------------------- */
    function setCookie(name, value, days = 7) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + expires + "; path=/";
    }

    function getCookie(name) {
        const cookies = document.cookie.split("; ").reduce((acc, cookie) => {
            const [key, val] = cookie.split("=");
            acc[key] = decodeURIComponent(val);
            return acc;
        }, {});
        return cookies[name] || null;
    }

    /* -----------------------
       DEVICE VARIABLES
    ----------------------- */
    const HOSTNAME = "{{ $device->hostname }}";
    const COOKIE_PREFIX = HOSTNAME + "_"; // cookie key prefix per device
    let DEVICE_IP = getCookie(COOKIE_PREFIX + "device_ip") || HOSTNAME;
    let API_TOKEN = getCookie(COOKIE_PREFIX + "api_token") || "{{ $data['api_token'] }}";
    setCookie(COOKIE_PREFIX + "device_ip", DEVICE_IP);
    setCookie(COOKIE_PREFIX + "api_token", API_TOKEN);

    /* -----------------------
       BOOTGRID INITIALIZATION
    ----------------------- */
    var vlanTable = $('#vlanTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "/api/v0/getvlan/" + DEVICE_IP,
            type: "GET",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            dataSrc: function(json) {
                if (json.vlans) return json.vlans;
                return json;
            }
        },
        columns: [{
                data: "id",
                orderable: false,
                render: function(data) {
                    return '<input type="checkbox" class="row-check" value="' + data + '">';
                }
            },
            {
                data: "id"
            },
            {
                data: "name"
            },
            {
                data: null,
                orderable: false,
                render: function(row) {
                    return `
                    <button class="btn btn-xs btn-info edit-vlan"
                        data-id="${row.id}"
                        data-name="${row.name}">
                        Edit
                    </button>
                `;
                }
            }
        ],
        order: [
            [1, "asc"]
        ],
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No VLANs found"
        }
    });


    /* -----------------------
       HANDLE EDIT BUTTON
    ----------------------- */
    var interfaceTable = $('#interfaceTable').DataTable({
    processing: true,
    serverSide: false,
    ajax: {
        url: "/api/v0/vlan/interface/" + DEVICE_IP,
        type: "GET",
        headers: {
            "Authorization": "Bearer " + API_TOKEN,
            "Accept": "application/json"
        },
        dataSrc: function (json) {
            return json.interfaces || [];
        }
    },
    columns: [
        {
            data: "name",
            orderable: false,
            render: function (data) {
                return `<input type="checkbox" class="row-check" value="${data}">`;
            }
        },
        { data: "name" },          // Port
        { data: "status" },        // Status
        { data: "vlan" },          // VLAN
        { data: "duplex" },        // Duplex
        { data: "speed" },         // Speed
        { data: "type" }           // Type
    ],
    order: [[1, "asc"]],
    lengthMenu: [10, 25, 50, 100],
    language: {
        emptyTable: "No interfaces found"
    }
});


    /* -----------------------
       ADD VLAN MODAL
    ----------------------- */
    $("#btnAddVlan").on("click", function() {
        $("#addVlanForm")[0].reset();
        $("#addVlanModal").modal("show");
    });

    $('#selectAll').on('change', function() {
        $('.row-check').prop('checked', this.checked);
    });

    $('#batchDeleteBtn').on('click', function() {
        var ids = [];

        $('.row-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert("Please select VLANs");
            return;
        }

        // AJAX call for delete
        console.log(ids);
    });



    $("#saveVlanBtn").on("click", function() {
        let vlan_id = $("#vlan_id").val();
        let vlan_name = $("#vlan_name").val();

        if (!vlan_id || !vlan_name) {
            alert("Please enter both VLAN ID and VLAN Name.");
            return;
        }

        $(".spinner-border").show();
        $("#saveVlanBtn").prop("disabled", true);

        $.ajax({
            url: "/api/v0/addvlan/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN
            },
            data: {
                vlan_id: vlan_id,
                vlan_name: vlan_name
            },
            success: function(response) {
                $(".spinner-border").hide();
                $("#saveVlanBtn").prop("disabled", false);

                if (response.status === "success") {
                    $("#addVlanModal").modal("hide");
                    $("#vlan-table").bootgrid("reload");
                    alert("VLAN added successfully!");
                } else {
                    alert("Error: " + JSON.stringify(response));
                }
            },
            error: function(xhr) {
                $(".spinner-border").hide();
                $("#saveVlanBtn").prop("disabled", false);
                alert("Request Failed: " + xhr.responseText);
            }
        });
    });

    function applyVlanBatch() {

        var vlanAdd = $("#vlan_add").val().trim();
        var vlanDelete = $("#vlan_delete").val().trim();
        $(".spinner-border").show();

        if (!vlanAdd && !vlanDelete) {
            alert("Please enter VLAN Add or VLAN Delete value");
            return;
        }

        $.ajax({
            url: "/api/v0/vlan/batch/" + DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + API_TOKEN,
                "Accept": "application/json"
            },
            contentType: "application/json",

            data: JSON.stringify({
                device: DEVICE_IP,
                vlan_add: vlanAdd,
                vlan_delete: vlanDelete
            }),

            success: function(response) {

                alert(response.message || "VLAN batch configuration applied successfully");
                $(".spinner-border").hide();

                // Clear inputs
                resetVlanBatch();

                // Reload VLAN grid
                $("#vlan-table").bootgrid("reload");
            },

            error: function(xhr) {
                $(".spinner-border").hide();

                var msg = "Failed to apply VLAN configuration";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    }

    function resetVlanBatch() {
        $("#vlan_add").val("");
        $("#vlan_delete").val("");
    }

    function isValidVlanInput(value) {
        var regex = /^[0-9,\-\s]+$/;
        return regex.test(value);
    }
</script>
