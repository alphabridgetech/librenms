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

        <!-- VLAN Configuration Tab -->
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

<script>
/* ------------------------------------------------------
   COOKIE FUNCTIONS
------------------------------------------------------ */
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

/* ------------------------------------------------------
   LOAD VALUES (from cookie OR fallback Blade variables)
------------------------------------------------------ */
let DEVICE_IP = getCookie("device_ip") || "{{ $device->hostname }}";
let API_TOKEN  = getCookie("api_token") || "{{ $data['api_token'] }}";

/* Save cookies immediately when page loads */
setCookie("device_ip", DEVICE_IP);
setCookie("api_token", API_TOKEN);

/* ------------------------------------------------------
   BOOTGRID INITIALIZATION
------------------------------------------------------ */
var vlanGrid = $("#vlan-table").bootgrid({
    ajax: true,
    search: true,

    ajaxSettings: {
        method: "GET",
        headers: {
            "Authorization": "Bearer " + API_TOKEN,
            "Accept": "application/json"
        }
    },

    responseHandler: function (response) {
        if (response.vlans && response.vlans.error) {
            alert("❌ VLAN Fetch Error: " + response.vlans.error);
            return { current: 1, rowCount: -1, rows: [], total: 0 };
        }

        if (response.vlans && Array.isArray(response.vlans)) {
            return {
                current: 1,
                rowCount: -1,
                rows: response.vlans,
                total: response.count ?? response.vlans.length
            };
        }

        if (response.raw) {
            try {
                const parsed = JSON.parse(response.raw);
                if (Array.isArray(parsed)) {
                    return { current: 1, rowCount: -1, rows: parsed, total: parsed.length };
                }
            } catch (e) {}
        }

        return response;
    },

    post: function () {
        return { device: DEVICE_IP };
    },

    url: "/api/v0/getvlan/" + DEVICE_IP,

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
})
.on("loaded.rs.jquery.bootgrid", function () {

    $("#vlanSearch").on("input", function () {
        vlanGrid.bootgrid("search", $(this).val());
    });

    $("#selectAllLabel").on("change", function(){
        $(".row-checkbox").prop("checked", this.checked);
    });
});
</script>
