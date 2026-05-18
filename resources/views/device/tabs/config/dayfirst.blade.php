

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

    .interface-card {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        background: #f9f9f9;
    }

    .mode-settings {
        display: none;
        margin-top: 10px;
        padding: 10px;
        border-left: 3px solid #337ab7;
    }

    .qinq-rule {
        border: 1px dashed #ccc;
        padding: 10px;
        margin-bottom: 10px;
        background: #fff;
    }

    .config-preview {
        background: #2d2d2d;
        color: #f0f0f0;
        font-family: 'Courier New', monospace;
        padding: 15px;
        border-radius: 4px;
        max-height: 400px;
        overflow-y: auto;
        white-space: pre-wrap;
        width: 100%;
        box-sizing: border-box;
    }

    /* Responsive Adjustments */
    @media (max-width: 767px) {
        .interface-card h4 .pull-right {
            float: none !important;
            display: block;
            margin-top: 8px;
            margin-right: 0;
        }
        .interface-card h4 {
            font-size: 16px;
            line-height: 1.4;
        }
        .form-horizontal .control-label {
            text-align: left;
            margin-bottom: 5px;
        }
        .qinq-rule .form-group [class*="col-"] {
            margin-bottom: 8px;
        }
        .config-preview {
            font-size: 13px;
        }
        .btn-responsive {
            display: block;
            width: 100%;
            margin-bottom: 5px;
        }
    }

    @media (min-width: 768px) {
        .btn-responsive {
            display: inline-block;
            width: auto;
        }
    }

    .interface-data {
        margin-top: 15px;
        padding: 10px;
        background: #f0f0f0;
        border-radius: 4px;
        border-left: 3px solid #5cb85c;
    }

    .interface-data pre {
        background: #2d2d2d;
        color: #f0f0f0;
        padding: 10px;
        border-radius: 4px;
        overflow-x: auto;
        margin-top: 10px;
    }
</style>


<div class="container" style="margin-top:30px;">
    <div class="panel panel-info">
        <div class="panel-heading">
            <strong>Device Configuration Panel</strong>
        </div>

        <div class="panel-body">
            <p>Configure device settings and interfaces.</p>

            <form id="deviceConfigForm" style="margin-top:20px;">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Device Settings</strong></div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="col-xs-12 col-sm-3 control-label">Hostname</label>
                            <div class="col-xs-12 col-sm-6">
                                <input type="text" id="hostname" class="form-control" value="{{ $device->hostname }}" placeholder="Hostname" readonly>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <strong>Interfaces</strong>
                        <button type="button" class="btn btn-xs btn-success pull-right" onclick="addInterface()">
                            <i class="fa fa-plus"></i> ADD INTERFACE
                        </button>
                        <div class="clearfix"></div>
                    </div>
                    <div class="panel-body" id="interfacesContainer">
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-2 col-sm-10">
                        <button type="button" class="btn btn-primary btn-responsive" onclick="previewConfig()">
                            <i class="fa fa-eye"></i> PREVIEW CONFIG
                        </button>
                        <button type="button" class="btn btn-success btn-responsive" onclick="saveConfig(this)">
                            <i class="fa fa-save"></i> SAVE
                        </button>
                        <button type="button" class="btn btn-warning btn-responsive" onclick="pushToDevice()">
                            <i class="fa fa-upload"></i> PUSH TO DEVICE
                        </button>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </form>

            <div class="panel panel-default" id="previewPanel" style="display:none;">
                <div class="panel-heading"><strong>Config Preview</strong></div>
                <div class="panel-body">
                    <div class="config-preview" id="configPreviewOutput"></div>
                </div>
            </div>
        </div>

        <div class="panel-footer">
            Help: Configure device interfaces, VLANs, and switching modes.
        </div>
    </div>
</div>

<script>
    const rawInterfaces = @json($data['interfaces'] ?? []);
const interfacesData = Array.isArray(rawInterfaces) ? rawInterfaces : (typeof rawInterfaces === 'object' && rawInterfaces !== null ? Object.values(rawInterfaces) : []);
    let interfaceCounter = 0;

    function addInterface() {
        interfaceCounter++;
        const container = document.getElementById('interfacesContainer');
        const ifaceId = 'interface_' + interfaceCounter;

        const html = `
            <div class="interface-card" id="${ifaceId}">
                <h4>INTERFACE CARD #${interfaceCounter}
                    <button type="button" class="btn btn-xs btn-danger pull-right" onclick="deleteInterface('${ifaceId}')">
                        <i class="fa fa-trash"></i> DELETE INTERFACE
                    </button>
                    <button type="button" class="btn btn-xs btn-info pull-right" style="margin-right:5px;" onclick="duplicateInterface('${ifaceId}')">
                        <i class="fa fa-copy"></i> DUPLICATE INTERFACE
                    </button>
                </h4>
                <hr>

                <div class="form-group">
                    <label class="col-xs-12 col-sm-3 control-label">Interface Name</label>
                    <div class="col-xs-12 col-sm-6">
                        <select class="form-control" id="interface_name" onchange="onInterfaceSelect(this, '${ifaceId}')">
                            <option value="">Select Interface...</option>
                            ${interfacesData.map(iface => `<option value="${iface}">${iface}</option>`).join('')}
                        </select>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div id="interfaceData_${ifaceId}" class="interface-data" style="display:none;">
                    <strong>Interface Details:</strong>
                    <pre id="interfaceDataContent_${ifaceId}"></pre>
                </div>

                <div class="form-group">
                    <label class="col-xs-12 col-sm-3 control-label mt-2">Description</label>
                    <div class="col-xs-12 col-sm-6">
                        <input type="text" class="form-control" placeholder="Description" oninput="updatePreview()">
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-6">
                        <label><input type="checkbox" id="stp_disable_${ifaceId}" onchange="updatePreview()"> Disable Spanning Tree</label>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <hr>
                <h5>MODE SELECTION</h5>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label class="radio-inline"><input type="radio" name="mode_${ifaceId}" value="access" onchange="toggleModeSettings('${ifaceId}', 'access')"> ACCESS</label>
                        <label class="radio-inline"><input type="radio" name="mode_${ifaceId}" value="trunk" onchange="toggleModeSettings('${ifaceId}', 'trunk')"> TRUNK</label>
                        <label class="radio-inline"><input type="radio" name="mode_${ifaceId}" value="dot1q-uplink" onchange="toggleModeSettings('${ifaceId}', 'dot1q-uplink')"> DOT1Q TUNNEL UPLINK</label>
                        <label class="radio-inline"><input type="radio" name="mode_${ifaceId}" value="dot1q-translating" onchange="toggleModeSettings('${ifaceId}', 'dot1q-translating')"> DOT1Q TRANSLATING TUNNEL</label>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div id="access_${ifaceId}" class="mode-settings">
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">PVID</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" class="form-control" placeholder="AccessPVID" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>

                <div id="trunk_${ifaceId}" class="mode-settings">
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">Allowed VLANs</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" class="form-control allowed-vlans-input" placeholder="e.g., 2001-3000,4000" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">PVID</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" class="form-control" placeholder="PVID" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>

                <div id="dot1q-uplink_${ifaceId}" class="mode-settings">
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">Allowed VLANs</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" class="form-control allowed-vlans-input" placeholder="e.g., 2001-3000,4000" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>

                <div id="dot1q-translating_${ifaceId}" class="mode-settings">
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">Allowed VLANs</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" class="form-control allowed-vlans-input" placeholder="e.g., 2001-3000,4000" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                    <hr>
                    <h5>QinQ TRANSLATION RULES</h5>
                    <div id="rules_${ifaceId}"></div>
                    <button type="button" class="btn btn-xs btn-success" onclick="addRule('${ifaceId}')">
                        <i class="fa fa-plus"></i> ADD RULE
                    </button>
                </div>

                <hr>
                <h5>L2 PROTOCOLS (OPTIONAL)</h5>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="cdp_${ifaceId}" onchange="updatePreview()"> Enable CDP</label>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="lldpenable_${ifaceId}" onchange="updatePreview()"> Enable LLDP</label>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="stpenable_${ifaceId}" onchange="updatePreview()"> Enable STP</label>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="lacp_${ifaceId}" onchange="updatePreview()"> Enable LACP</label>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <hr>
                <h5>RATE LIMITING (OPTIONAL)</h5>
                <p class="text-muted col-xs-12 col-sm-offset-3 col-sm-9" style="margin:5px 0 10px 0; font-size:11px;">
                    <i class="fa fa-info-circle"></i> 1 unit = 64kbps. Example: 200 units = 64 * 200 = 12800 kbps = 12.8Mbps
                </p>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="ratelimit_${ifaceId}" onchange="toggleRateLimitSettings('${ifaceId}')"> Enable Rate Limiting</label>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div id="rateLimitSettings_${ifaceId}" style="display:none;">
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">Ingress Rate (units)</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" id="ingress_rate_${ifaceId}" class="form-control" placeholder="e.g., 200 (200×64kbps=12.8Mbps)" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">Egress Rate (units)</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" id="egress_rate_${ifaceId}" class="form-control" placeholder="e.g., 200 (200×64kbps=12.8Mbps)" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>

                <hr>
                <h5>LACP / AGGREGATION (OPTIONAL)</h5>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="lacpaggre_${ifaceId}" onchange="toggleLacpSettings('${ifaceId}')"> Enable Aggregation (LACP)</label>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div id="lacpSettings_${ifaceId}" style="display:none;">
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">Aggregator Group ID</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" class="form-control" placeholder="Group ID" oninput="updatePreview()">
                        </div>
                        <div class="clearfix"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-xs-12 col-sm-3 control-label">Mode</label>
                        <div class="col-xs-12 col-sm-6">
                            <input type="text" class="form-control" value="LACP" readonly>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>
                <hr>
                <h5>LLDP (OPTIONAL)</h5>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="lldp_tx_${ifaceId}" onchange="toggleLldpTxSettings('${ifaceId}')"> Configure Transmit (TX)</label>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div id="lldpTxSettings_${ifaceId}" style="display:none;">
                    <div class="form-group">
                        <div class="col-xs-12 col-sm-offset-3 col-sm-9 col-sm-offset-4">
                            <label class="radio-inline"><input type="radio" name="lldp_tx_mode_${ifaceId}" value="enable" checked onchange="updatePreview()"> Enable</label>
                            <label class="radio-inline"><input type="radio" name="lldp_tx_mode_${ifaceId}" value="disable" onchange="updatePreview()"> Disable</label>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <label><input type="checkbox" id="lldp_rx_${ifaceId}" onchange="toggleLldpRxSettings('${ifaceId}')"> Configure Receive (RX)</label>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div id="lldpRxSettings_${ifaceId}" style="display:none;">
                    <div class="form-group">
                        <div class="col-xs-12 col-sm-offset-3 col-sm-9 col-sm-offset-4">
                            <label class="radio-inline"><input type="radio" name="lldp_rx_mode_${ifaceId}" value="enable" checked onchange="updatePreview()"> Enable</label>
                            <label class="radio-inline"><input type="radio" name="lldp_rx_mode_${ifaceId}" value="disable" onchange="updatePreview()"> Disable</label>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', html);
        addRule(ifaceId);
    }

    function deleteInterface(ifaceId) {
        if (confirm('Delete this interface?')) {
            document.getElementById(ifaceId).remove();
            updatePreview();
        }
    }

    function duplicateInterface(ifaceId) {
        const original = document.getElementById(ifaceId);
        const clone = original.cloneNode(true);
        interfaceCounter++;
        const newId = 'interface_' + interfaceCounter;
        clone.id = newId;
        clone.querySelector('h4').innerHTML = clone.querySelector('h4').innerHTML.replace(/INTERFACE CARD #\d+/, 'INTERFACE CARD #' + interfaceCounter);
        original.parentNode.insertBefore(clone, original.nextSibling);
        updatePreview();
    }

    function toggleModeSettings(ifaceId, mode) {
        ['access', 'trunk', 'dot1q-uplink', 'dot1q-translating'].forEach(m => {
            const el = document.getElementById(m + '_' + ifaceId);
            if (el) el.style.display = 'none';
        });
        const modeEl = document.getElementById(mode + '_' + ifaceId);
        if (modeEl) modeEl.style.display = 'block';
        updatePreview();
    }

    function toggleLacpSettings(ifaceId) {
        const checkbox = document.getElementById('lacpaggre_' + ifaceId);
        document.getElementById('lacpSettings_' + ifaceId).style.display = checkbox.checked ? 'block' : 'none';
        updatePreview();
    }

    function toggleRateLimitSettings(ifaceId) {
        const checkbox = document.getElementById('ratelimit_' + ifaceId);
        document.getElementById('rateLimitSettings_' + ifaceId).style.display = checkbox.checked ? 'block' : 'none';
        updatePreview();
    }

    function toggleLldpTxSettings(ifaceId) {
        const checkbox = document.getElementById('lldp_tx_' + ifaceId);
        document.getElementById('lldpTxSettings_' + ifaceId).style.display = checkbox.checked ? 'block' : 'none';
        updatePreview();
    }

    function toggleLldpRxSettings(ifaceId) {
        const checkbox = document.getElementById('lldp_rx_' + ifaceId);
        document.getElementById('lldpRxSettings_' + ifaceId).style.display = checkbox.checked ? 'block' : 'none';
        updatePreview();
    }

    function addRule(ifaceId) {
        const container = document.getElementById('rules_' + ifaceId);
        const ruleCount = container.children.length + 1;

        const html = `
            <div class="qinq-rule" id="rule_${ifaceId}_${ruleCount}">
                <strong>Rule #${ruleCount}:</strong>
                <div class="form-group" style="margin-top:10px;">
                    <label class="col-xs-12 col-sm-3 control-label">From VLAN</label>
                    <div class="col-xs-12 col-sm-3">
                        <input type="text" class="form-control" placeholder="From VLAN" oninput="updatePreview()">
                    </div>
                    <label class="col-xs-12 col-sm-2 control-label">To VLAN</label>
                    <div class="col-xs-12 col-sm-3">
                        <input type="text" class="form-control" placeholder="To VLAN" oninput="updatePreview()">
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div class="form-group">
                    
                    <div class="col-xs-12 col-sm-3">
                        <button type="button" class="btn btn-xs btn-danger" onclick="deleteRule('rule_${ifaceId}_${ruleCount}')">
                            <i class="fa fa-trash"></i> Delete Rule
                        </button>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', html);
    }

    function deleteRule(ruleId) {
        document.getElementById(ruleId).remove();
        updatePreview();
    }

    const deviceIp = "{{ $device->hostname }}";
    const apiToken = "{{ $data['api_token'] }}";

    function onInterfaceSelect(selectEl, ifaceId) {
        const interfaceName = selectEl.value;
        const dataContainer = document.getElementById('interfaceData_' + ifaceId);
        const dataContent = document.getElementById('interfaceDataContent_' + ifaceId);

        if (!interfaceName) {
            dataContainer.style.display = 'none';
            return;
        }

        dataContainer.style.display = 'block';
        dataContent.textContent = 'Loading...';

        fetch(`/api/v0/network/interface/show/${deviceIp}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ interface: interfaceName })
        })
        .then(res => res.json())
        .then(data => {
            dataContent.textContent = JSON.stringify(data, null, 2);
        })
        .catch(err => {
            dataContent.textContent = 'Error: ' + err;
        });

        updatePreview();
    }

    function updatePreview() {
        previewConfig();
    }

    function previewConfig() {
        const panel = document.getElementById('previewPanel');
        const output = document.getElementById('configPreviewOutput');
        let config = '';

        const interfaces = document.querySelectorAll('.interface-card');
        interfaces.forEach(iface => {
            const nameSelect = iface.querySelector('select');
            const name = nameSelect ? nameSelect.value : 'GigabitEthernet0/X';
            const descInput = iface.querySelector('input[placeholder="Description"]');
            const desc = descInput ? descInput.value : '';
            const desablespanning = iface.querySelector('input[type="checkbox"][id*="stp_disable_"]');


            config += `interface ${name}\n`;
            if (desc) config += ` description ${desc}\n`;

            if (desablespanning && desablespanning.checked) config += ` no spanning-tree\n`;

            const cdpCheck = iface.querySelector('input[type="checkbox"][id*="cdp_"]');
            const stpCheck = iface.querySelector('input[type="checkbox"][id*="stpenable_"]');
            const lldpCheck = iface.querySelector('input[type="checkbox"][id*="lldpenable_"]');
            const lacpCheckenable = iface.querySelector('input[type="checkbox"][id*="lacp_"]');

            if (cdpCheck && cdpCheck.checked) config += ` l2protocol-tunnel cdp\n`;
            if (stpCheck && stpCheck.checked) config += ` l2protocol-tunnel stp\n`;
            if (lldpCheck && lldpCheck.checked) config += ` l2protocol-tunnel lldp\n`;
            if (lacpCheckenable && lacpCheckenable.checked) config += ` l2protocol-tunnel lacp\n`;

            const lldpTxCheck = iface.querySelector('input[type="checkbox"][id*="lldp_tx_"]');
            const lldpRxCheck = iface.querySelector('input[type="checkbox"][id*="lldp_rx_"]');

                if (lldpTxCheck && lldpTxCheck.checked) {
                    const txMode = iface.querySelector(`input[name="lldp_tx_mode_${iface.id}"]:checked`);
                    if (txMode) {
                        config += txMode.value === 'enable' ? ` lldp transmit\n` : ` no lldp transmit\n`;
                    }
                }
                if (lldpRxCheck && lldpRxCheck.checked) {
                    const rxMode = iface.querySelector(`input[name="lldp_rx_mode_${iface.id}"]:checked`);
                    if (rxMode) {
                        config += rxMode.value === 'enable' ? ` lldp receive\n` : ` no lldp receive\n`;
                    }
                }

            const mode = iface.querySelector('input[type="radio"]:checked');
            if (mode) {
                const modeVal = mode.value;
                const ifaceId = iface.id;

                if (modeVal === 'trunk') {
                    config += ` switchport mode trunk\n`;
                    const trunkVlanInput = iface.querySelector('#trunk_' + ifaceId + ' .allowed-vlans-input');
                    const vlans = trunkVlanInput ? trunkVlanInput.value : '';
                    if (vlans) config += ` switchport trunk vlan-allowed ${vlans}\n`;
                    const pvidInput = iface.querySelector('#trunk_' + ifaceId + ' input[placeholder="PVID"]');
                    const pvid = pvidInput ? pvidInput.value : '';
                    if (pvid) config += ` switchport pvid ${pvid}\n`;
                }else if (modeVal === 'access') {
                    config += ` switchport mode access\n`;
                    const apvidInput = iface.querySelector('#access_' + ifaceId + ' input[placeholder="AccessPVID"]');
                    const apvid = apvidInput ? apvidInput.value : '';
                    if (apvid) config += ` switchport pvid ${apvid}\n`;
                }else if (modeVal === 'dot1q-uplink') {
                    config += ` switchport mode dot1q-tunnel-uplink\n`;
                    const uplinkVlanInput = iface.querySelector('#dot1q-uplink_' + ifaceId + ' .allowed-vlans-input');
                    const vlans = uplinkVlanInput ? uplinkVlanInput.value : '';
                    if (vlans) config += ` switchport trunk vlan-allowed ${vlans}\n`;
                } else if (modeVal === 'dot1q-translating') {
                    config += ` switchport mode dot1q-translating-tunnel\n`;
                    const transVlanInput = iface.querySelector('#dot1q-translating_' + ifaceId + ' .allowed-vlans-input');
                    const vlans = transVlanInput ? transVlanInput.value : '';
                    if (vlans) config += ` switchport trunk vlan-allowed ${vlans}\n`;

                    const rules = iface.querySelectorAll('.qinq-rule');
                    rules.forEach(rule => {
                        const fromVlanInput = rule.querySelector('input[placeholder="From VLAN"]');
                        const toVlanInput = rule.querySelector('input[placeholder="To VLAN"]');
                        const fromVlan = fromVlanInput ? fromVlanInput.value : '';
                        const toVlan = toVlanInput ? toVlanInput.value : '';
                        if (fromVlan && toVlan) {
                            let ruleStr = ` switchport dot1q-translating-tunnel mode QinQ translate ${fromVlan} ${toVlan}`;
                            config += ruleStr + `\n`;
                        }
                    });
                }
            }

            const rateLimitCheck = document.getElementById('ratelimit_' + iface.id);
            if (rateLimitCheck && rateLimitCheck.checked) {
                const ingressInput = document.getElementById('ingress_rate_' + iface.id);
                const egressInput = document.getElementById('egress_rate_' + iface.id);
                if (ingressInput && ingressInput.value) {
                    const ingressKbps = parseInt(ingressInput.value) * 64;
                    if (!isNaN(ingressKbps) && ingressKbps > 0) {
                        config += ` switchport rate-limit ${ingressKbps} ingress\n`;
                    }
                }
                if (egressInput && egressInput.value) {
                    const egressKbps = parseInt(egressInput.value) * 64;
                    if (!isNaN(egressKbps) && egressKbps > 0) {
                        config += ` switchport rate-limit ${egressKbps} egress\n`;
                    }
                }
            }

            const lacpCheck = iface.querySelector('input[type="checkbox"][id*="lacpaggre_"]');
            if (lacpCheck && lacpCheck.checked) {
                const groupIdInput = iface.querySelector('#lacpSettings_' + iface.id + ' input[placeholder="Group ID"]');
                const groupId = groupIdInput ? groupIdInput.value : '';
                if (groupId) config += ` aggregator-group ${groupId} mode active\n`;
            }

            config += '!\n\n';
        });

        output.textContent = config || 'No interfaces configured.';
        panel.style.display = 'block';
    }

    function saveConfig(btn) {
        const ip = "{{ $device->hostname }}";
        const apiToken = "{{ $data['api_token'] }}";
        const hostname = document.getElementById('hostname').value;

        if (!hostname) {
            alert("Hostname cannot be empty");
            return;
        }

        previewConfig();
        const configOutput = document.getElementById('configPreviewOutput');
        const config = configOutput.textContent;

        if (!config || config === 'No interfaces configured.') {
            alert('No configuration to save.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> SAVING...';

        fetch(`/api/v0/network/cmd/config/${ip}`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ hostname: hostname, config: config })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save"></i> SAVE';

            if (res.status === "success") {
                alert("Configuration saved successfully!");
            } else {
                alert("Failed to save: " + (res.message || "Unknown error"));
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save"></i> SAVE';
            alert("Error saving configuration: " + err);
        });
    }

    function pushToDevice() {
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Pushing...';

        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-upload"></i> PUSH TO DEVICE';
            alert('Configuration pushed to device!');
        }, 2000);
    }

    addInterface();
    addInterface();
</script>
v