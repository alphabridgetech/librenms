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

    .tab-pane .table-responsive {
        margin-top: 15px;
        border: none;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    @php
        // Same short-form port mapping used by the other L2 tabs.
        $dhcpPorts = collect($data['interfaces'] ?? [])
            ->map(function ($ifName) {
                if (preg_match('/^GigaEthernet(\d+\/\d+)$/i', $ifName, $m)) {
                    return 'g' . $m[1];
                }
                if (preg_match('/^TGigaEthernet(\d+\/\d+)$/i', $ifName, $m)) {
                    return 'tg' . $m[1];
                }
                return null;
            })
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values();
    @endphp

    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li class="active"><a href="#dhcp_global_tab" data-toggle="tab">Global Configuration</a></li>
        <li><a href="#dhcp_vlan_tab" data-toggle="tab">VLAN Configuration</a></li>
        <li><a href="#dhcp_interface_tab" data-toggle="tab">Interface Configuration</a></li>
        <li><a href="#dhcp_option82_tab" data-toggle="tab">Option82 Configuration</a></li>
        <li><a href="#dhcp_binding_tab" data-toggle="tab">Binding Port List</a></li>
        <li><a href="#dhcp_binding_applied_tab" data-toggle="tab">DHCP Binding Table</a></li>
    </ul>

    <div class="tab-content" style="margin-top:15px;">

        <!-- Global Configuration -->
        <div class="tab-pane active" id="dhcp_global_tab">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <strong>DHCP Snooping - Global Configuration</strong>
                    <span id="dhcp_global_loading" class="text-muted" style="margin-left:10px;">
                        <i class="fa fa-spinner fa-spin"></i> reading configuration from device...
                    </span>
                    <span id="dhcp_global_error" class="text-danger" style="display:none; margin-left:10px;"></span>
                    <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadDhcpGlobal()">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
                <div class="panel-body">
                    <form class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">DHCP Snooping*</label>
                            <div class="col-sm-5">
                                <select id="dhcp_snooping" class="form-control">
                                    <option value="enable">Enable</option>
                                    <option value="disable">Disable</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Binding Save Update Interval (min)*</label>
                            <div class="col-sm-5">
                                <input type="number" id="dhcp_update_interval" class="form-control" min="2" max="1440">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Filter Packets From Upper Interface*</label>
                            <div class="col-sm-5">
                                <select id="dhcp_filter_upper_interface" class="form-control">
                                    <option value="enable">Enable</option>
                                    <option value="disable">Disable</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Option82 Information Format*</label>
                            <div class="col-sm-5">
                                <select id="dhcp_option82_format" class="form-control">
                                    <option value="disable">Disable</option>
                                    <option value="snmp-ifindex">snmp-ifindex</option>
                                    <option value="hn-type">hn-type</option>
                                    <option value="cm-type">cm-type</option>
                                    <option value="hw-type">hw-type</option>
                                    <option value="manual">manual</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <div class="col-sm-offset-4 col-sm-5">
                                <button type="button" id="dhcpGlobalApplyBtn" class="btn btn-primary" onclick="applyDhcpGlobal()">
                                    <span class="spinner-border" style="display:none;"></span> Apply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="alert alert-info">
                <ul>
                    <li>Update interval range: 2-1440 minutes.</li>
                    <li>When DHCP Snooping is Disable, only "no ip dhcp-relay snooping" is sent - the other fields are not applied.</li>
                </ul>
            </div>
        </div>

        <!-- VLAN Configuration -->
        <div class="tab-pane" id="dhcp_vlan_tab">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <strong>DHCP Snooping - VLAN Configuration</strong>
                    <span id="dhcp_vlan_loading" class="text-muted" style="margin-left:10px;">
                        <i class="fa fa-spinner fa-spin"></i> reading configuration from device...
                    </span>
                    <span id="dhcp_vlan_error" class="text-danger" style="display:none; margin-left:10px;"></span>
                    <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadDhcpVlan()">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
                <div class="panel-body">
                    <form class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">DHCP Snooping VLAN*</label>
                            <div class="col-sm-5">
                                <input type="number" id="dhcp_new_dhcp_vlan" class="form-control" min="1" max="4094">
                                <span class="help-block">Current: <span id="dhcp_old_dhcp_vlan">-</span></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Dynamic ARP Inspection VLAN*</label>
                            <div class="col-sm-5">
                                <input type="number" id="dhcp_new_arp_vlan" class="form-control" min="1" max="4094">
                                <span class="help-block">Current: <span id="dhcp_old_arp_vlan">-</span></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Verify Source VLAN*</label>
                            <div class="col-sm-5">
                                <input type="number" id="dhcp_new_verify_source_vlan" class="form-control" min="1" max="4094">
                                <span class="help-block">Current: <span id="dhcp_old_verify_source_vlan">-</span></span>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <div class="col-sm-offset-4 col-sm-5">
                                <button type="button" id="dhcpVlanApplyBtn" class="btn btn-primary" onclick="applyDhcpVlan()">
                                    <span class="spinner-border" style="display:none;"></span> Apply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="alert alert-info">
                <ul>
                    <li>VLAN range: 1-4094. All three VLANs are required.</li>
                    <li>Applying replaces the current VLAN(s) shown under each field with the new VLAN.</li>
                </ul>
            </div>
        </div>

        <!-- Interface Configuration -->
        <div class="tab-pane" id="dhcp_interface_tab">
            <span id="dhcp_interface_cache_note" class="text-muted" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="dhcpInterfaceTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="dhcpInterfaceTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="90">Port</th>
                            <th width="140">DHCP Trust</th>
                            <th width="160">ARP Inspection Trust</th>
                            <th width="140">IP Source Trust</th>
                            <th width="70">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <!-- Option82 Configuration -->
        <div class="tab-pane" id="dhcp_option82_tab">
            <span id="dhcp_option82_cache_note" class="text-muted" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
            </span>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="dhcpOption82Table.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="dhcpOption82Table" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="90">Port</th>
                            <th width="220">Option Type</th>
                            <th width="220">Packets Operate Options</th>
                            <th width="130">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Option82 can only be configured on g0/1 - g0/10.</li>
                    <li>The switch does not report the current Circuit ID / Remote ID / Vendor Specific values, so Configure starts with them blank - fill in every field you want kept.</li>
                    <li>Hex values must be hyphen-separated byte pairs, e.g. aa-bb-cc-dd.</li>
                </ul>
            </div>
        </div>

        <!-- Binding Port List (static) -->
        <div class="tab-pane" id="dhcp_binding_tab">
            <button class="btn btn-primary" id="btnAddDhcpBinding">
                <i class="glyphicon glyphicon-plus"></i> Add Binding
            </button>
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="dhcpBindingTable && dhcpBindingTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="dhcpBindingTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="160">MAC Address</th>
                            <th width="140">IP Address</th>
                            <th width="90">Port</th>
                            <th width="80">VLAN</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Static IP source bindings ("ip source binding ...") configured on the switch.</li>
                    <li>This list is read live from the switch each time, so it can take a few seconds to load.</li>
                </ul>
            </div>
        </div>

        <!-- DHCP Binding Table (dynamic, read-only) -->
        <div class="tab-pane" id="dhcp_binding_applied_tab">
            <button type="button" class="btn btn-xs btn-default pull-right" onclick="dhcpBindingAppliedTable && dhcpBindingAppliedTable.ajax.reload(null, false)">
                <i class="fa fa-refresh"></i> Refresh
            </button>

            <div class="table-responsive">
                <table id="dhcpBindingAppliedTable" class="table table-striped table-bordered table-condensed" style="width:100%;">
                    <thead>
                        <tr>
                            <th width="160">MAC Address</th>
                            <th width="140">IP Address</th>
                            <th width="100">Type</th>
                            <th width="80">VLAN</th>
                            <th width="90">Interface</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul>
                    <li>Read-only view of the switch's DHCP snooping binding table ("show ip dhcp-relay snooping binding"), read live each time.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Edit Interface Modal -->
<div id="dhcpInterfaceModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="dhcp_interface_modal_title">Edit Interface</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="dhcp_interface_port">
                    @foreach (['dhcp_trust' => 'DHCP Trust', 'arp_trust' => 'ARP Inspection Trust', 'ip_source_trust' => 'IP Source Trust'] as $field => $label)
                        <div class="form-group">
                            <label class="col-sm-5 control-label">{{ $label }}*</label>
                            <div class="col-sm-7">
                                <select id="dhcp_interface_{{ $field }}" class="form-control">
                                    <option value="Trust">Trust</option>
                                    <option value="Distrust">Distrust</option>
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div id="dhcp_interface_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveDhcpInterfaceBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Configure Option82 Modal -->
<div id="dhcpOption82Modal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="dhcp_option82_modal_title">Configure Option82</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <input type="hidden" id="dhcp_option82_port">
                    @foreach (['circuit_id' => 'Circuit ID', 'remote_id' => 'Remote ID', 'vendor_specific' => 'Vendor Specific'] as $field => $label)
                        <div class="form-group">
                            <label class="col-sm-4 control-label">{{ $label }}</label>
                            <div class="col-sm-3">
                                <select id="dhcp_option82_{{ $field }}_type" class="form-control">
                                    <option value="">Not set</option>
                                    <option value="string">string</option>
                                    <option value="hex">hex</option>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <input type="text" id="dhcp_option82_{{ $field }}_value" class="form-control" maxlength="64">
                            </div>
                        </div>
                    @endforeach
                    @foreach (['drop' => 'Drop', 'replace' => 'Replace', 'transmit' => 'Transmit'] as $field => $label)
                        <div class="form-group">
                            <label class="col-sm-4 control-label">{{ $label }}*</label>
                            <div class="col-sm-8">
                                <select id="dhcp_option82_{{ $field }}" class="form-control">
                                    <option value="disable">Disable</option>
                                    <option value="enable">Enable</option>
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div id="dhcp_option82_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveDhcpOption82Btn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Binding Modal -->
<div id="dhcpBindingModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Binding</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">MAC Address*</label>
                        <div class="col-sm-8">
                            <input type="text" id="dhcp_binding_mac_address" class="form-control" placeholder="e.g. f4:ee:08:ea:ea:1d">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">IP Address*</label>
                        <div class="col-sm-8">
                            <input type="text" id="dhcp_binding_ip_address" class="form-control" placeholder="e.g. 192.168.2.10">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Port*</label>
                        <div class="col-sm-8">
                            <select id="dhcp_binding_port" class="form-control">
                                @foreach ($dhcpPorts as $port)
                                    <option value="{{ $port }}">{{ $port }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">VLAN ID*</label>
                        <div class="col-sm-8">
                            <input type="number" id="dhcp_binding_vlan_id" class="form-control" min="1" max="4094">
                        </div>
                    </div>
                    <div id="dhcp_binding_error" class="text-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="saveDhcpBindingBtn" class="btn btn-success">
                    <span class="spinner-border" style="display:none;"></span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const DHCP_DEVICE_IP = "{{ $device->hostname }}";
    const DHCP_API_TOKEN = "{{ $data['api_token'] }}";
    const DHCP_API = "/api/v0/dhcpsnooping/";
    const DHCP_HEADERS = { "Authorization": "Bearer " + DHCP_API_TOKEN, "Accept": "application/json" };

    // Laravel returns 422 { message, errors: {field: [msg]} } on validation
    // failure - show the first field error rather than the generic message.
    function dhcpErrorMessage(xhr, fallback) {
        const errors = xhr.responseJSON?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first.length) return first[0];
        }
        return xhr.responseJSON?.message || fallback;
    }

    function dhcpBusy(btn, busy) {
        $(btn).find('.spinner-border').toggle(busy);
        $(btn).prop('disabled', busy);
    }

    // POST to a dhcpsnooping/<path>/set endpoint. onError receives the
    // message; on success the server's message is alerted and onDone runs.
    function dhcpPost(path, payload, btn, onDone, onError) {
        if (btn) dhcpBusy(btn, true);

        $.ajax({
            url: DHCP_API + path + "/" + DHCP_DEVICE_IP,
            method: "POST",
            headers: DHCP_HEADERS,
            contentType: "application/json",
            data: JSON.stringify(payload),
            success: function (res) {
                if (btn) dhcpBusy(btn, false);
                alert(res.message || "Saved");
                if (onDone) onDone(res);
            },
            error: function (xhr) {
                if (btn) dhcpBusy(btn, false);
                const message = dhcpErrorMessage(xhr, "Request failed");
                if (onError) onError(message); else alert(message);
            }
        });
    }

    function dhcpShowError(id, message) {
        const box = document.getElementById(id);
        box.innerText = message;
        box.style.display = 'block';
    }

    // GET a form-style section (global / vlan) and hand its data to fill().
    function dhcpLoadForm(path, prefix, fill) {
        const loading = document.getElementById(prefix + '_loading');
        const error = document.getElementById(prefix + '_error');
        loading.style.display = 'inline';
        error.style.display = 'none';

        $.ajax({
            url: DHCP_API + path + "/" + DHCP_DEVICE_IP,
            headers: DHCP_HEADERS,
            success: function (res) {
                loading.style.display = 'none';
                if (res.data) fill(res.data);
            },
            error: function (xhr) {
                loading.style.display = 'none';
                error.innerText = dhcpErrorMessage(xhr, "Failed to read configuration from device");
                error.style.display = 'inline';
            }
        });
    }

    /* -----------------------------------------------------
       GLOBAL CONFIGURATION
    ----------------------------------------------------- */
    function loadDhcpGlobal() {
        dhcpLoadForm('global/show', 'dhcp_global', function (d) {
            document.getElementById('dhcp_snooping').value = d.dhcp_snooping === 'enable' ? 'enable' : 'disable';
            document.getElementById('dhcp_update_interval').value = d.update_interval || '';
            document.getElementById('dhcp_filter_upper_interface').value = d.filter_upper_interface === 'enable' ? 'enable' : 'disable';
            const format = document.getElementById('dhcp_option82_format');
            format.value = $(format).find('option[value="' + d.option82_format + '"]').length ? d.option82_format : 'disable';
        });
    }

    function applyDhcpGlobal() {
        dhcpPost('global/set', {
            dhcp_snooping: document.getElementById('dhcp_snooping').value,
            update_interval: document.getElementById('dhcp_update_interval').value,
            filter_upper_interface: document.getElementById('dhcp_filter_upper_interface').value,
            option82_format: document.getElementById('dhcp_option82_format').value
        }, document.getElementById('dhcpGlobalApplyBtn'), loadDhcpGlobal);
    }

    /* -----------------------------------------------------
       VLAN CONFIGURATION
    ----------------------------------------------------- */
    // What the switch currently has - sent as old_* so the set playbook
    // removes it before applying the new VLAN.
    let dhcpCurrentVlans = { dhcp_vlan: '', arp_vlan: '', verify_source_vlan: '' };

    function loadDhcpVlan() {
        dhcpLoadForm('vlan/show', 'dhcp_vlan', function (d) {
            dhcpCurrentVlans = d;
            ['dhcp_vlan', 'arp_vlan', 'verify_source_vlan'].forEach(function (field) {
                const current = d[field] || '';
                document.getElementById('dhcp_old_' + field).textContent = current || '-';
                // Prefill the input only when the switch has a single VLAN.
                document.getElementById('dhcp_new_' + field).value = /^\d+$/.test(current) ? current : '';
            });
        });
    }

    function applyDhcpVlan() {
        dhcpPost('vlan/set', {
            new_dhcp_vlan: document.getElementById('dhcp_new_dhcp_vlan').value,
            new_arp_vlan: document.getElementById('dhcp_new_arp_vlan').value,
            new_verify_source_vlan: document.getElementById('dhcp_new_verify_source_vlan').value,
            old_dhcp_vlan: dhcpCurrentVlans.dhcp_vlan || '',
            old_arp_vlan: dhcpCurrentVlans.arp_vlan || '',
            old_verify_source_vlan: dhcpCurrentVlans.verify_source_vlan || ''
        }, document.getElementById('dhcpVlanApplyBtn'), loadDhcpVlan);
    }

    /* -----------------------------------------------------
       INTERFACE CONFIGURATION
    ----------------------------------------------------- */
    const dhcpTrustLabel = data => data === 'Trust'
        ? '<span class="label label-success">Trust</span>'
        : '<span class="label label-default">Distrust</span>';

    var dhcpInterfaceTable = $('#dhcpInterfaceTable').DataTable({
        ajax: {
            url: DHCP_API + "interface/show/" + DHCP_DEVICE_IP,
            type: "GET",
            headers: DHCP_HEADERS,
            dataSrc: function (json) {
                document.getElementById('dhcp_interface_cache_note').style.display = json.cached ? "inline" : "none";
                return json.data || [];
            }
        },
        columns: [
            { data: "port" },
            { data: "dhcp_trust", render: dhcpTrustLabel },
            { data: "arp_trust", render: dhcpTrustLabel },
            { data: "ip_source_trust", render: dhcpTrustLabel },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (row, type, full, meta) => `<button type="button" class="btn btn-xs btn-warning btn-edit-dhcp-interface" data-row="${meta.row}">Edit</button>`
            }
        ],
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No interfaces found" }
    });

    $('#dhcpInterfaceTable tbody').on('click', '.btn-edit-dhcp-interface', function () {
        const row = dhcpInterfaceTable.row($(this).data('row')).data();

        document.getElementById('dhcp_interface_modal_title').innerText = 'Edit Interface ' + row.port;
        document.getElementById('dhcp_interface_port').value = row.port;
        ['dhcp_trust', 'arp_trust', 'ip_source_trust'].forEach(function (field) {
            document.getElementById('dhcp_interface_' + field).value = row[field] === 'Trust' ? 'Trust' : 'Distrust';
        });
        document.getElementById('dhcp_interface_error').style.display = 'none';
        $('#dhcpInterfaceModal').modal('show');
    });

    $('#saveDhcpInterfaceBtn').on('click', function () {
        document.getElementById('dhcp_interface_error').style.display = 'none';

        dhcpPost('interface/set', {
            port: document.getElementById('dhcp_interface_port').value,
            dhcp_trust: document.getElementById('dhcp_interface_dhcp_trust').value,
            arp_trust: document.getElementById('dhcp_interface_arp_trust').value,
            ip_source_trust: document.getElementById('dhcp_interface_ip_source_trust').value
        }, this, function () {
            $('#dhcpInterfaceModal').modal('hide');
            dhcpInterfaceTable.ajax.reload(null, false);
        }, message => dhcpShowError('dhcp_interface_error', message));
    });

    /* -----------------------------------------------------
       OPTION82 CONFIGURATION
    ----------------------------------------------------- */
    const DHCP_OPTION82_PORT = /^g0\/([1-9]|10)$/;

    var dhcpOption82Table = $('#dhcpOption82Table').DataTable({
        ajax: {
            url: DHCP_API + "option82/show/" + DHCP_DEVICE_IP,
            type: "GET",
            headers: DHCP_HEADERS,
            dataSrc: function (json) {
                document.getElementById('dhcp_option82_cache_note').style.display = json.cached ? "inline" : "none";
                return json.data || [];
            }
        },
        columns: [
            { data: "port" },
            { data: "option_type", render: data => data || '-' },
            { data: "packets_options", render: data => data || '-' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row, type, full, meta) {
                    if (!DHCP_OPTION82_PORT.test(row.port || '')) {
                        return '<span class="text-muted">not supported</span>';
                    }
                    const clear = (row.option_type || row.packets_options)
                        ? `<button type="button" class="btn btn-xs btn-danger btn-clear-dhcp-option82" data-port="${row.port}">Clear</button>`
                        : '';
                    return `<button type="button" class="btn btn-xs btn-warning btn-edit-dhcp-option82" data-row="${meta.row}">Configure</button> ${clear}`;
                }
            }
        ],
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No interfaces found" }
    });

    $('#dhcpOption82Table tbody').on('click', '.btn-edit-dhcp-option82', function () {
        const row = dhcpOption82Table.row($(this).data('row')).data();
        const packets = String(row.packets_options || '').split(',');

        document.getElementById('dhcp_option82_modal_title').innerText = 'Configure Option82 ' + row.port;
        document.getElementById('dhcp_option82_port').value = row.port;
        ['circuit_id', 'remote_id', 'vendor_specific'].forEach(function (field) {
            document.getElementById('dhcp_option82_' + field + '_type').value = '';
            document.getElementById('dhcp_option82_' + field + '_value').value = '';
        });
        ['drop', 'replace', 'transmit'].forEach(function (field) {
            document.getElementById('dhcp_option82_' + field).value = packets.indexOf(field) !== -1 ? 'enable' : 'disable';
        });
        document.getElementById('dhcp_option82_error').style.display = 'none';
        $('#dhcpOption82Modal').modal('show');
    });

    $('#dhcpOption82Table tbody').on('click', '.btn-clear-dhcp-option82', function () {
        const port = $(this).data('port');
        if (!confirm(`Clear all Option82 settings on ${port}?`)) {
            return;
        }

        dhcpPost('option82/set', { operation: 'delete', port: port }, null, function () {
            dhcpOption82Table.ajax.reload(null, false);
        });
    });

    $('#saveDhcpOption82Btn').on('click', function () {
        document.getElementById('dhcp_option82_error').style.display = 'none';

        const payload = { operation: 'set', port: document.getElementById('dhcp_option82_port').value };
        ['circuit_id', 'remote_id', 'vendor_specific'].forEach(function (field) {
            payload[field + '_type'] = document.getElementById('dhcp_option82_' + field + '_type').value;
            payload[field + '_value'] = document.getElementById('dhcp_option82_' + field + '_value').value.trim();
        });
        ['drop', 'replace', 'transmit'].forEach(function (field) {
            payload[field] = document.getElementById('dhcp_option82_' + field).value;
        });

        dhcpPost('option82/set', payload, this, function () {
            $('#dhcpOption82Modal').modal('hide');
            dhcpOption82Table.ajax.reload(null, false);
        }, message => dhcpShowError('dhcp_option82_error', message));
    });

    /* -----------------------------------------------------
       BINDING PORT LIST / DHCP BINDING TABLE
       Both are read live from the switch (no cache), so each table is
       only created - and first loaded - when its tab is opened.
    ----------------------------------------------------- */
    var dhcpBindingTable = null;
    var dhcpBindingAppliedTable = null;

    function initDhcpBindingTable() {
        if (dhcpBindingTable) return;

        dhcpBindingTable = $('#dhcpBindingTable').DataTable({
            ajax: {
                url: DHCP_API + "binding/show/" + DHCP_DEVICE_IP,
                type: "GET",
                headers: DHCP_HEADERS,
                dataSrc: json => json.data || []
            },
            columns: [
                { data: "mac_address" },
                { data: "ip_address" },
                { data: "port", render: data => data || '-' },
                { data: "vlan_id", render: data => data ?? '-' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (row, type, full, meta) => `<button type="button" class="btn btn-xs btn-danger btn-delete-dhcp-binding" data-row="${meta.row}">Delete</button>`
                }
            ],
            order: [],
            lengthMenu: [10, 25, 50, 100],
            language: { emptyTable: "No static bindings configured" }
        });

        // Bootgrid stops row clicks from bubbling past <tbody>, and <tbody>
        // only exists once the table is created - so bind here.
        $('#dhcpBindingTable tbody').on('click', '.btn-delete-dhcp-binding', function () {
            const row = dhcpBindingTable.row($(this).data('row')).data();
            if (!confirm(`Delete binding ${row.mac_address} / ${row.ip_address} (VLAN ${row.vlan_id})?`)) {
                return;
            }

            dhcpPost('binding/set', {
                operation: 'delete',
                mac_address: row.mac_address,
                ip_address: row.ip_address,
                vlan_id: row.vlan_id
            }, null, function () {
                dhcpBindingTable.ajax.reload(null, false);
            });
        });
    }

    function initDhcpBindingAppliedTable() {
        if (dhcpBindingAppliedTable) return;

        dhcpBindingAppliedTable = $('#dhcpBindingAppliedTable').DataTable({
            ajax: {
                url: DHCP_API + "binding/applied/show/" + DHCP_DEVICE_IP,
                type: "GET",
                headers: DHCP_HEADERS,
                dataSrc: json => json.data || []
            },
            columns: [
                { data: "mac_address" },
                { data: "ip_address" },
                { data: "type", render: data => data || '-' },
                { data: "vlan_id", render: data => data ?? '-' },
                { data: "interface", render: data => data || '-' }
            ],
            order: [],
            lengthMenu: [10, 25, 50, 100],
            language: { emptyTable: "No DHCP snooping bindings" }
        });
    }

    $('#btnAddDhcpBinding').on('click', function () {
        document.getElementById('dhcp_binding_mac_address').value = '';
        document.getElementById('dhcp_binding_ip_address').value = '';
        document.getElementById('dhcp_binding_vlan_id').value = '';
        document.getElementById('dhcp_binding_error').style.display = 'none';
        $('#dhcpBindingModal').modal('show');
    });

    $('#saveDhcpBindingBtn').on('click', function () {
        document.getElementById('dhcp_binding_error').style.display = 'none';

        dhcpPost('binding/set', {
            operation: 'add',
            mac_address: document.getElementById('dhcp_binding_mac_address').value.trim(),
            ip_address: document.getElementById('dhcp_binding_ip_address').value.trim(),
            port: document.getElementById('dhcp_binding_port').value,
            vlan_id: document.getElementById('dhcp_binding_vlan_id').value
        }, this, function () {
            $('#dhcpBindingModal').modal('hide');
            dhcpBindingTable.ajax.reload(null, false);
        }, message => dhcpShowError('dhcp_binding_error', message));
    });

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        if (target === '#dhcp_binding_tab') initDhcpBindingTable();
        if (target === '#dhcp_binding_applied_tab') initDhcpBindingAppliedTable();
    });

    loadDhcpGlobal();
    loadDhcpVlan();
</script>
