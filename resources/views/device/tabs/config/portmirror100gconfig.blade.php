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

    #pm100Table td, #pm100Table th {
        vertical-align: middle;
    }

    .pm100-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .pm100-toolbar .pm100-right {
        margin-left: auto;
        display: flex;
        gap: 8px;
    }

    #pm100Modal .select2-container {
        width: 100% !important;
    }
</style>

@include('device.tabs.datatablepath.offline-datatable')

<div class="container-fluid" style="margin-top:30px; padding-left:0; padding-right:0;">
    <div class="pm100-toolbar">
        <div class="btn-group">
            <button type="button" class="btn btn-primary" id="pm100BtnNew">
                <i class="glyphicon glyphicon-plus"></i> New
            </button>
            <button type="button" class="btn btn-primary" id="pm100BtnDelete">
                <i class="glyphicon glyphicon-remove"></i> Delete
            </button>
        </div>
        <span id="pm100_cache_note" class="text-muted" style="display:none;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <div class="pm100-right">
            <button type="button" class="btn btn-primary btn-sm" onclick="pm100Table.ajax.reload(null, false)" title="Refresh">
                <i class="fa fa-refresh"></i>
            </button>
        </div>
    </div>

    <div id="pm100_load_error" class="alert alert-danger" style="display:none;"></div>

    <div class="table-responsive">
        <table id="pm100Table" class="table table-striped table-bordered table-condensed" style="width:100%;">
            <thead>
                <tr>
                    <th width="30"></th>
                    <th width="80">Session</th>
                    <th>Destination</th>
                    <th>Rx</th>
                    <th>Tx</th>
                    <th>Both</th>
                    <th width="90">Operation</th>
                </tr>
            </thead>
        </table>
    </div>

    <div class="alert alert-info" style="margin-top:20px;">
        <ul>
            <li>Session range: 1-4. Each session copies the traffic of its Rx / Tx / Both ports to the Destination port.</li>
            <li>Save replaces the whole session on the switch. Delete removes the ticked sessions.</li>
            <li>A port can't be both the Destination and a mirrored port in the same session.</li>
        </ul>
    </div>
</div>

<!-- New/Edit Port Mirror Session Modal -->
<div id="pm100Modal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="pm100_modal_title">New</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" onsubmit="return false;">
                    <div class="form-group">
                        <label class="col-sm-4 control-label"><span class="text-danger">*</span> Session</label>
                        <div class="col-sm-6">
                            <input type="number" id="pm100_session" class="form-control" min="1" max="4" placeholder="1-4">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Destination</label>
                        <div class="col-sm-6">
                            <select id="pm100_destination" class="form-control pm100-select" style="width:100%;"></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Rx</label>
                        <div class="col-sm-6">
                            <select id="pm100_rx" class="form-control pm100-select pm100-sources" data-mode="RX" multiple style="width:100%;"></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Tx</label>
                        <div class="col-sm-6">
                            <select id="pm100_tx" class="form-control pm100-select pm100-sources" data-mode="TX" multiple style="width:100%;"></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Both</label>
                        <div class="col-sm-6">
                            <select id="pm100_both" class="form-control pm100-select pm100-sources" data-mode="RX &amp; TX" multiple style="width:100%;"></select>
                        </div>
                    </div>
                    <div id="pm100_error" class="text-danger text-center" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer" style="text-align:center;">
                <button id="pm100SaveBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span>
                    Confirm
                </button>
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    const PM100_DEVICE_IP = "{{ $device->hostname }}";
    const PM100_API_TOKEN = "{{ $data['api_token'] }}";
    const PM100_HEADERS = { "Authorization": "Bearer " + PM100_API_TOKEN, "Accept": "application/json" };
    const PM100_TYPE_ORDER = { tg: 0, wg: 1, cg: 2 };

    // Filled on every table load
    let pm100Ports = [];
    let pm100Sessions = [];
    const pm100Selected = new Set();  // ticked session ids, kept across redraws

    // "cg0/0/4" -> sort key [type, 0, 0, 4]
    function pm100SortPorts(ports) {
        const key = port => {
            const m = /^([a-z]+)([\d\/]+)$/i.exec(port) || [];
            return { type: PM100_TYPE_ORDER[(m[1] || '').toLowerCase()] ?? 9, nums: (m[2] || '').split('/').map(Number) };
        };
        return ports.slice().sort(function (a, b) {
            const ka = key(a), kb = key(b);
            if (ka.type !== kb.type) return ka.type - kb.type;
            for (let i = 0; i < Math.max(ka.nums.length, kb.nums.length); i++) {
                if ((ka.nums[i] || 0) !== (kb.nums[i] || 0)) return (ka.nums[i] || 0) - (kb.nums[i] || 0);
            }
            return 0;
        });
    }

    // sources {port: "RX" | "TX" | "RX & TX"} -> ports of one mode, sorted
    function pm100PortsWithMode(sources, mode) {
        return pm100SortPorts(Object.keys(sources || {}).filter(port => sources[port] === mode));
    }

    function pm100ShowError(msg) {
        $('#pm100_error').text(msg).toggle(!!msg);
    }

    var pm100Table = $('#pm100Table').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "/api/v0/portmirror/show/" + PM100_DEVICE_IP,
            type: "GET",
            headers: PM100_HEADERS,
            dataSrc: function (json) {
                document.getElementById('pm100_cache_note').style.display = json.cached ? "inline" : "none";
                pm100Selected.clear();
                pm100SyncCheckAll();

                if (json.status !== "success") {
                    $('#pm100_load_error').text(json.message || 'Failed to load port mirror configuration').show();
                    return [];
                }
                $('#pm100_load_error').hide();

                pm100Sessions = (json.mirror && json.mirror.sessions) || [];

                // Keep switch-reported ports selectable even if LibreNMS doesn't list them
                const ports = (json.ports || []).slice();
                pm100Sessions.forEach(function (s) {
                    [s.mirror_port].concat(Object.keys(s.sources || {})).forEach(function (port) {
                        if (port && ports.indexOf(port) === -1) {
                            ports.push(port);
                        }
                    });
                });
                pm100Ports = pm100SortPorts(ports);

                return pm100Sessions.map(s => ({
                    session_id: s.session_id,
                    destination: s.mirror_port || '',
                    rx: pm100PortsWithMode(s.sources, 'RX').join(', '),
                    tx: pm100PortsWithMode(s.sources, 'TX').join(', '),
                    both: pm100PortsWithMode(s.sources, 'RX & TX').join(', ')
                }));
            },
            // The offline DataTable only passes complete through, not error
            complete: function (xhr) {
                if (xhr.status !== 200) {
                    $('#pm100_load_error').text(xhr.responseJSON?.message || 'API communication error').show();
                }
            }
        },
        columns: [
            {
                data: "session_id",
                orderable: false,
                searchable: false,
                render: data => `<input type="checkbox" class="pm100-check" value="${data}"${pm100Selected.has(data) ? ' checked' : ''}>`
            },
            { data: "session_id" },
            { data: "destination", render: data => data || '-' },
            { data: "rx", render: data => data || '' },
            { data: "tx", render: data => data || '' },
            { data: "both", render: data => data || '' },
            {
                data: "session_id",
                orderable: false,
                searchable: false,
                render: data => `<a href="javascript:void(0)" class="pm100-edit" data-session="${data}"><i class="fa fa-pencil-square-o"></i> Edit</a>`
            }
        ],
        order: [[1, "asc"]],
        lengthMenu: [10, 25, 50, 100],
        language: { emptyTable: "No port mirror sessions found" }
    });

    // Bootgrid builds the header from the <th> text, so the tick-all box is added afterwards
    $('#pm100Table thead th').first().find('.text').html('<input type="checkbox" id="pm100CheckAll">');

    function pm100SyncCheckAll() {
        const ids = pm100Sessions.map(s => s.session_id);
        $('#pm100CheckAll').prop('checked', ids.length > 0 && ids.every(id => pm100Selected.has(id)));
    }

    // Bootgrid stops click propagation at the rows; change events still bubble
    $('#pm100Table').on('change', '.pm100-check', function () {
        const id = parseInt(this.value, 10);
        if (this.checked) pm100Selected.add(id); else pm100Selected.delete(id);
        pm100SyncCheckAll();
    });

    $('#pm100Table').on('change', '#pm100CheckAll', function () {
        pm100Selected.clear();
        if (this.checked) pm100Sessions.forEach(s => pm100Selected.add(s.session_id));
        pm100Table.rows().invalidate().draw(false);
    });

    function pm100PortOptions($select, ports, blank) {
        $select.empty();
        if (blank) {
            $select.append($('<option>').val('').text(''));
        }
        ports.forEach(port => $select.append($('<option>').val(port).text(port)));
    }

    $('#pm100Modal .pm100-select').select2({
        dropdownParent: $('#pm100Modal'),
        placeholder: 'Select',
        allowClear: true,
        width: '100%'
    });

    // A port can be the destination or in one of Rx / Tx / Both - grey it out everywhere else
    function pm100SyncOptions() {
        const owner = {};
        $('#pm100_destination, #pm100Modal .pm100-sources').each(function () {
            [].concat($(this).val() || []).forEach(port => { owner[port] = this.id; });
        });
        $('#pm100_destination, #pm100Modal .pm100-sources').each(function () {
            const id = this.id;
            $(this).find('option').each(function () {
                this.disabled = !!this.value && !!owner[this.value] && owner[this.value] !== id;
            });
        });
    }

    function pm100OpenModal(session) {
        const editing = !!session;
        const used = pm100Sessions.map(s => s.session_id);
        const free = [1, 2, 3, 4].filter(id => used.indexOf(id) === -1);

        if (!editing && !free.length) {
            alert('All 4 sessions are in use. Edit or delete a session first.');
            return;
        }

        $('#pm100_modal_title').text(editing ? 'Edit' : 'New');
        $('#pm100_session').val(editing ? session.session_id : free[0]).prop('readonly', editing);

        pm100PortOptions($('#pm100_destination'), pm100Ports, true);
        $('#pm100_destination').val(editing ? session.mirror_port : '');

        const sources = editing ? session.sources : {};
        $('#pm100Modal .pm100-sources').each(function () {
            pm100PortOptions($(this), pm100Ports, false);
            $(this).val(pm100PortsWithMode(sources, $(this).data('mode')));
        });

        pm100SyncOptions();
        $('#pm100Modal .pm100-select').trigger('change.select2');
        pm100ShowError('');
        $('#pm100Modal').modal('show');
    }

    $('#pm100BtnNew').on('click', function () {
        pm100OpenModal(null);
    });

    $('#pm100Table tbody').on('click', '.pm100-edit', function () {
        const id = parseInt($(this).data('session'), 10);
        pm100OpenModal(pm100Sessions.find(s => s.session_id === id));
    });

    $('#pm100Modal').on('change', '.pm100-select', pm100SyncOptions);

    function pm100Set(data) {
        return $.ajax({
            url: "/api/v0/portmirror/set/" + PM100_DEVICE_IP,
            method: "POST",
            headers: PM100_HEADERS,
            data: data
        });
    }

    function pm100ErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    $('#pm100SaveBtn').on('click', function () {
        const btn = this;
        const sessionText = $.trim($('#pm100_session').val());
        const sessionId = parseInt(sessionText, 10);
        const destination = $('#pm100_destination').val();
        const editing = $('#pm100_session').prop('readonly');

        const sources = [];
        $('#pm100Modal .pm100-sources').each(function () {
            const mode = $(this).data('mode');
            ($(this).val() || []).forEach(port => sources.push({ port: port, mode: mode }));
        });

        if (!/^[1-4]$/.test(sessionText)) {
            pm100ShowError('Session must be a number from 1 to 4.');
            return;
        }
        if (!editing && pm100Sessions.some(s => s.session_id === sessionId)) {
            pm100ShowError('Session ' + sessionId + ' already exists. Use Edit to change it.');
            return;
        }
        if (!destination) {
            pm100ShowError('Select the destination port.');
            return;
        }
        if (!sources.length) {
            pm100ShowError('Select at least one port in Rx, Tx or Both.');
            return;
        }
        pm100ShowError('');

        $(btn).find('.spinner-border').show();
        $(btn).prop('disabled', true);

        pm100Set({ session_id: sessionId, mirror_port: destination, sources: sources })
            .done(function (res) {
                if (res.status === "success") {
                    $('#pm100Modal').modal('hide');
                    pm100Table.ajax.reload(null, false);
                    alert(res.message || 'Port mirror session saved');
                } else {
                    pm100ShowError(res.message || 'Request failed');
                }
            })
            .fail(xhr => pm100ShowError(pm100ErrorText(xhr)))
            .always(function () {
                $(btn).find('.spinner-border').hide();
                $(btn).prop('disabled', false);
            });
    });

    $('#pm100BtnDelete').on('click', function () {
        const ids = [...pm100Selected].sort((a, b) => a - b);
        if (!ids.length) {
            alert('Tick the sessions to delete.');
            return;
        }
        if (!confirm('Delete port mirror session(s) ' + ids.join(', ') + ' from the switch?')) {
            return;
        }

        const $btn = $(this).prop('disabled', true);
        const failed = [];

        // One session at a time - each call logs in to the switch
        ids.reduce(
            (chain, id) => chain.then(() => pm100Set({ session_id: id })
                .then(res => { if (res.status !== "success") failed.push(id + ': ' + (res.message || 'failed')); },
                      xhr => { failed.push(id + ': ' + pm100ErrorText(xhr)); return $.Deferred().resolve(); })),
            $.Deferred().resolve()
        ).always(function () {
            $btn.prop('disabled', false);
            pm100Table.ajax.reload(null, false);
            alert(failed.length ? 'Some sessions could not be deleted:\n' + failed.join('\n') : 'Deleted session(s) ' + ids.join(', '));
        });
    });
</script>
