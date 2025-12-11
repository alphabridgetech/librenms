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
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

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

            <table id="vlan-table" class="table table-hover table-condensed table-striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th data-column-id="select" data-formatter="select" data-sortable="false" style="width:40px;"></th>
                        <th data-column-id="id" data-type="numeric">VLAN ID</th>
                        <th data-column-id="name">VLAN Name</th>
                        <th data-column-id="operate" data-formatter="operate" data-sortable="false" style="width:80px;">Operate</th>
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
        <div class="tab-pane" id="vlan_batch"><h4>VLAN Batch Configuration</h4></div>
        <div class="tab-pane" id="interface_vlan"><h4>Interface VLAN Attribute</h4></div>
        <div class="tab-pane" id="voice_vlan"><h4>Voice VLAN</h4></div>
        <div class="tab-pane" id="interface_voice_vlan"><h4>Interface Voice VLAN</h4></div>
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
    const expires = new Date(Date.now() + days*864e5).toUTCString();
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
var vlanGrid = $("#vlan-table").bootgrid({
    ajax: true,
    search: true,
    rowCount: [10, 25, 50, -1],

    ajaxSettings: {
        method: "GET",
        headers: {
            "Authorization": "Bearer " + API_TOKEN,
            "Accept": "application/json"
        }
    },

    url: "/api/v0/getvlan/" + DEVICE_IP,

    post: function () {
        return { device: DEVICE_IP };
    },

    responseHandler: function (response) {
        // Ensure data array exists
        let rows = [];
        if (response.vlans && Array.isArray(response.vlans)) {
            rows = response.vlans;
        } else if (Array.isArray(response)) {
            rows = response;
        }

        return {
            current: 1,
            rowCount: rows.length,
            rows: rows,
            total: rows.length
        };
    },

    formatters: {
        select: function(column, row) {
            return `<input type="checkbox" class="row-checkbox" data-id="${row.id}">`;
        },
        operate: function(column, row) {
            return `<a href="#" class="btn btn-xs btn-info edit-vlan"
                        data-id="${row.id}" data-name="${row.name}">
                        Edit
                    </a>`;
        }
    }
}).on("loaded.rs.jquery.bootgrid", function () {
    $("#selectAllLabel").on("change", function(){
        $(".row-checkbox").prop("checked", this.checked);
    });
});

/* -----------------------
   ADD VLAN MODAL
----------------------- */
$("#btnAddVlan").on("click", function () {
    $("#addVlanForm")[0].reset();
    $("#addVlanModal").modal("show");
});

$("#saveVlanBtn").on("click", function () {
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
        data: { vlan_id: vlan_id, vlan_name: vlan_name },
        success: function (response) {
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
        error: function (xhr) {
            $(".spinner-border").hide();
            $("#saveVlanBtn").prop("disabled", false);
            alert("Request Failed: " + xhr.responseText);
        }
    });
});
</script>
