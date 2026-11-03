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

    #pmSettings td, #pmPortTable td, #pmPortTable th {
        vertical-align: middle;
    }

    #pmSettings select, #pmPortTable select {
        width: auto;
        display: inline-block;
    }

    #pmPortTable input[type="text"] {
        width: 80px;
        display: inline-block;
    }

    #pmPortTable .pm-range {
        margin-left: 4px;
        color: #555;
    }

    .pm-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 30px;
        margin: 15px 0;
    }

    .pm-filters label {
        font-weight: normal;
        margin: 0 5px 0 0;
    }
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Port Mirror</strong>
        <span id="pm_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <button type="button" class="btn btn-xs btn-default pull-right" onclick="loadPortMirror()">
            <i class="fa fa-refresh"></i> Refresh
        </button>
    </div>
    <div class="panel-body">
        <div id="pm_loading" class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading port mirror configuration...</div>
        <div id="pm_load_error" class="alert alert-danger" style="display:none;"></div>

        <div id="pm_content" style="display:none;">
            <table id="pmSettings" class="table table-bordered table-condensed">
                <tr>
                    <td style="width:45%;">Mirror Port</td>
                    <td><select id="pm_mirror_port" class="form-control input-sm"></select></td>
                </tr>
                <tr>
                    <td>Mirror Type</td>
                    <td>
                        <select id="pm_mirror_type" class="form-control input-sm">
                            <option value="port-based">port-based</option>
                            <option value="flow-based">flow-based</option>
                        </select>
                    </td>
                </tr>
            </table>

            <div class="pm-filters">
                <strong>Filters</strong>
                <span>
                    <label for="pm_filter_type">Port Type:</label>
                    <select id="pm_filter_type" class="form-control input-sm" style="width:auto; display:inline-block;">
                        <option value="">All</option>
                        <option value="g">g (GigaEthernet)</option>
                        <option value="tg">tg (TGigaEthernet)</option>
                    </select>
                </span>
                <span>
                    <label for="pm_filter_slot">Slot Num:</label>
                    <select id="pm_filter_slot" class="form-control input-sm" style="width:auto; display:inline-block;"></select>
                </span>
                <span>
                    <label for="pm_filter_name">Name(s):</label>
                    <input type="text" id="pm_filter_name" class="form-control input-sm" style="width:180px; display:inline-block;" placeholder="e.g. g0/1, tg0/2">
                </span>
            </div>

            <div class="table-responsive">
                <table id="pmPortTable" class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>Mirrored Port</th>
                            <th>Mirror Mode</th>
                            <th>vid</th>
                            <th>tpid</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div id="pm_error" class="text-danger" style="display:none; margin-bottom:10px;"></div>

            <button type="button" id="savePortMirrorBtn" class="btn btn-primary">
                <span class="spinner-border" style="display:none;"></span> Apply
            </button>
        </div>
    </div>
    <div class="panel-footer">
        Help: tick each port whose traffic should be copied (Mirrored Port) and pick its direction. The vid and tpid belong to the Mirror Port (destination) row. Ports are taken directly from Telequill. A port already mirrored on the switch can't be unticked here - removing a mirrored port isn't supported by the playbook yet.
    </div>
</div>

<script>
    const PM_DEVICE_IP = "{{ $device->hostname }}";
    const PM_API_TOKEN = "{{ $data['api_token'] }}";

    // Page state, rebuilt on every load
    let pmPorts = [];
    let pmConfigured = {};   // port -> mode, as currently on the switch
    let pmChecked = {};      // port -> true
    let pmModes = {};        // port -> RX / TX / RX & TX
    let pmVid = '';
    let pmTpid = '';

    function pmShowError(msg) {
        const el = document.getElementById('pm_error');
        el.innerText = msg;
        el.style.display = "block";
    }

    function pmClearError() {
        const el = document.getElementById('pm_error');
        el.innerText = "";
        el.style.display = "none";
    }

    // "tg0/3" -> {type: "tg", slot: "0", num: 3}
    function pmParsePort(port) {
        const m = /^([a-z]+)(\d+)\/(\d+)$/i.exec(port) || [];
        return { type: (m[1] || '').toLowerCase(), slot: m[2] || '', num: parseInt(m[3] || '0', 10) };
    }

    function pmSortPorts(ports) {
        const order = { g: 0, tg: 1 };
        return ports.slice().sort(function (a, b) {
            const pa = pmParsePort(a), pb = pmParsePort(b);
            return ((order[pa.type] ?? 9) - (order[pb.type] ?? 9))
                || (parseInt(pa.slot, 10) - parseInt(pb.slot, 10))
                || (pa.num - pb.num);
        });
    }

    function pmModeSelect(port, disabled) {
        const mode = pmModes[port] || 'RX';
        const $select = $('<select class="form-control input-sm pm-mode">').attr('data-port', port).prop('disabled', disabled);
        ['RX', 'TX', 'RX & TX'].forEach(function (m) {
            $select.append($('<option>').val(m).text(m).prop('selected', m === mode));
        });
        return $select;
    }

    function pmRenderRows() {
        const mirrorPort = $('#pm_mirror_port').val();
        const $body = $('#pmPortTable tbody').empty();

        pmPorts.forEach(function (port) {
            const isDest = port === mirrorPort;
            const checked = !isDest && !!pmChecked[port];

            const $check = $('<input type="checkbox" class="pm-check">')
                .attr('data-port', port)
                .prop('checked', checked)
                .prop('disabled', isDest);

            const $vid = $('<input type="text" class="form-control input-sm" id="pm_vid">').val(isDest ? pmVid : '').prop('disabled', !isDest);
            const $tpid = $('<input type="text" class="form-control input-sm" id="pm_tpid">').val(isDest ? pmTpid : '').prop('disabled', !isDest);
            if (!isDest) {
                $vid.removeAttr('id');
                $tpid.removeAttr('id');
            }

            const $row = $('<tr>').attr('data-port', port).append(
                $('<td>').append($('<label style="font-weight:normal; margin:0;">').append($check, ' ', document.createTextNode(port))),
                $('<td>').append(pmModeSelect(port, !checked)),
                $('<td>').append($vid, $('<span class="pm-range">').text('(1-4094)')),
                $('<td>').append($tpid, $('<span class="pm-range">').text('(1-0xFFFF)'))
            );
            $body.append($row);
        });

        pmApplyFilters();
    }

    function pmApplyFilters() {
        const type = $('#pm_filter_type').val();
        const slot = $('#pm_filter_slot').val();
        const names = $('#pm_filter_name').val().split(/[\s,]+/).map(s => s.trim().toLowerCase()).filter(Boolean);

        $('#pmPortTable tbody tr').each(function () {
            const port = $(this).attr('data-port');
            const p = pmParsePort(port);
            const show = (!type || p.type === type)
                && (!slot || p.slot === slot)
                // "g0/1" matches only g0/1 (not tg0/1); a partial name like "tg" matches from the start
                && (!names.length || names.some(n => n.indexOf('/') !== -1 ? port.toLowerCase() === n : port.toLowerCase().indexOf(n) === 0));
            $(this).toggle(show);
        });
    }

    function loadPortMirror() {
        $('#pm_loading').show();
        $('#pm_load_error').hide();
        pmClearError();

        $.ajax({
            url: "/api/v0/portmirror/show/" + PM_DEVICE_IP,
            method: "GET",
            headers: {
                "Authorization": "Bearer " + PM_API_TOKEN,
                "Accept": "application/json"
            },
            success: function (json) {
                $('#pm_loading').hide();
                document.getElementById('pm_cache_note').style.display = json.cached ? "inline" : "none";

                if (json.status !== "success") {
                    $('#pm_load_error').text(json.message || 'Failed to load port mirror configuration').show();
                    return;
                }

                const mirror = json.mirror || {};
                pmConfigured = mirror.sources || {};
                pmChecked = {};
                pmModes = {};
                Object.keys(pmConfigured).forEach(function (port) {
                    pmChecked[port] = true;
                    pmModes[port] = pmConfigured[port];
                });
                pmVid = mirror.vid || '';
                pmTpid = mirror.tpid || '';

                // Keep switch-reported ports selectable even if LibreNMS doesn't list them
                const ports = (json.ports || []).slice();
                [mirror.mirror_port].concat(Object.keys(pmConfigured)).forEach(function (port) {
                    if (port && ports.indexOf(port) === -1) {
                        ports.push(port);
                    }
                });
                pmPorts = pmSortPorts(ports);

                const $dest = $('#pm_mirror_port').empty();
                pmPorts.forEach(port => $dest.append($('<option>').val(port).text(port)));
                $dest.val(mirror.mirror_port || pmPorts[0]);
                $('#pm_mirror_type').val(mirror.mirror_type || 'port-based');

                const slots = [...new Set(pmPorts.map(p => pmParsePort(p).slot))].sort();
                const $slot = $('#pm_filter_slot').empty().append($('<option>').val('').text('All'));
                slots.forEach(s => $slot.append($('<option>').val(s).text(s)));

                pmRenderRows();
                $('#pm_content').show();
            },
            error: function (xhr) {
                $('#pm_loading').hide();
                $('#pm_load_error').text(xhr.responseJSON?.message || 'API communication error').show();
            }
        });
    }

    $('#pmPortTable').on('change', '.pm-check', function () {
        const port = $(this).attr('data-port');

        if (!this.checked && pmConfigured[port]) {
            this.checked = true;
            pmShowError(port + ' is already mirrored on the switch. Removing a mirrored port is not supported yet.');
            return;
        }

        pmChecked[port] = this.checked;
        $(this).closest('tr').find('.pm-mode').prop('disabled', !this.checked);
    });

    $('#pmPortTable').on('change', '.pm-mode', function () {
        pmModes[$(this).attr('data-port')] = $(this).val();
    });

    $('#pmPortTable').on('input', '#pm_vid', function () { pmVid = $(this).val(); });
    $('#pmPortTable').on('input', '#pm_tpid', function () { pmTpid = $(this).val(); });

    $('#pm_mirror_port').on('change', function () {
        pmClearError();
        const port = $(this).val();
        if (pmConfigured[port]) {
            pmShowError(port + ' is currently a mirrored port, so it can\'t be the mirror port.');
        }
        pmChecked[port] = false;
        pmRenderRows();
    });

    $('#pm_filter_type, #pm_filter_slot').on('change', pmApplyFilters);
    $('#pm_filter_name').on('input', pmApplyFilters);

    $('#savePortMirrorBtn').on('click', function () {
        pmClearError();

        const mirrorPort = $('#pm_mirror_port').val();
        const vid = $.trim(pmVid);
        const tpid = $.trim(pmTpid);

        const sources = pmPorts
            .filter(port => port !== mirrorPort && pmChecked[port])
            .map(port => ({ port: port, mode: pmModes[port] || 'RX' }));

        if (!mirrorPort) {
            pmShowError('Select the mirror port.');
            return;
        }
        if (pmConfigured[mirrorPort]) {
            pmShowError(mirrorPort + ' is currently a mirrored port, so it can\'t be the mirror port.');
            return;
        }
        if (!sources.length) {
            pmShowError('Tick at least one mirrored port.');
            return;
        }
        if (!/^\d+$/.test(vid) || vid < 1 || vid > 4094) {
            pmShowError('vid on the ' + mirrorPort + ' row must be between 1 and 4094.');
            return;
        }
        if (!/^0x[0-9a-fA-F]{1,4}$/.test(tpid) || parseInt(tpid, 16) < 1) {
            pmShowError('tpid on the ' + mirrorPort + ' row must be a hex value from 0x1 to 0xFFFF, e.g. 0x8100.');
            return;
        }

        const $btn = $('#savePortMirrorBtn');
        $btn.prop('disabled', true);
        $btn.find('.spinner-border').show();

        $.ajax({
            url: "/api/v0/portmirror/set/" + PM_DEVICE_IP,
            method: "POST",
            headers: {
                "Authorization": "Bearer " + PM_API_TOKEN,
                "Accept": "application/json"
            },
            data: {
                mirror_port: mirrorPort,
                mirror_type: $('#pm_mirror_type').val(),
                vid: vid,
                tpid: tpid,
                sources: sources
            },
            success: function (response) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();

                if (response.status === "success") {
                    loadPortMirror();
                    alert(response.message || 'Port mirror updated successfully!');
                } else if (response.errors) {
                    pmShowError(Object.values(response.errors).flat().join(' '));
                } else {
                    pmShowError(response.message || 'Request failed');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $btn.find('.spinner-border').hide();
                const errors = xhr.responseJSON?.errors;
                pmShowError(errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error'));
            }
        });
    });

    loadPortMirror();
</script>
