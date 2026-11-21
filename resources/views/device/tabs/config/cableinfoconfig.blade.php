@php
    // Copper ports only - the switch has no cable diagnostic for fiber ports
    // (tg/cg). g0/1 or GigaEthernet0/1; 100G's g0/0/0 is its management port.
    $cablePorts = \App\Models\Port::where('device_id', $device->device_id)
        ->where('deleted', 0)
        ->get(['ifName', 'ifOperStatus', 'ifAlias'])
        ->filter(fn ($p) => preg_match('#^(g|GigaEthernet)\d+/\d+$#i', (string) $p->ifName))
        ->map(fn ($p) => [
            'value'  => preg_replace('#^GigaEthernet#i', 'g', $p->ifName),
            'name'   => $p->ifName,
            'status' => $p->ifOperStatus,
            'alias'  => $p->ifAlias,
        ])
        ->sortBy('value', SORT_NATURAL)
        ->values();
@endphp

<style>
    .spinner-border {
        width: 16px;
        height: 16px;
        border: 2px solid #fff;
        border-right-color: transparent;
        border-radius: 50%;
        display: inline-block;
        animation: spin 0.75s linear infinite;
        vertical-align: middle;
    }

    @keyframes spin {
        100% { transform: rotate(360deg); }
    }

    .ci-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .ci-row select {
        width: auto;
        min-width: 240px;
    }

    #ciTable th {
        background: #f5f5f5;
        white-space: nowrap;
    }

    #ciTable td,
    #ciTable th {
        vertical-align: middle;
        text-align: center;
    }

    #ciTable tr.ci-latest td {
        background: #eef6ff;
    }

    .ci-ok { color: #1a8a4a; font-weight: 600; }
    .ci-open { color: #b26a00; font-weight: 600; }
    .ci-bad { color: #c62828; font-weight: 600; }
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Interface Cable Info</strong>
    </div>
    <div class="panel-body">
        @if ($cablePorts->isEmpty())
            <div class="alert alert-info" style="margin-bottom:0;">
                This switch has no copper (RJ45) ports. Cable diagnostics only work on copper ports -
                fiber ports (tg / cg) are not supported by the switch.
            </div>
        @else
            <div class="ci-row">
                <label for="ci_port" style="margin:0;">Port</label>
                <select id="ci_port" class="form-control">
                    @foreach ($cablePorts as $p)
                        <option value="{{ $p['value'] }}" data-status="{{ $p['status'] }}">
                            {{ $p['value'] }}{{ $p['alias'] ? ' - ' . $p['alias'] : '' }} ({{ $p['status'] ?: 'unknown' }})
                        </option>
                    @endforeach
                </select>
                <button type="button" id="ciTestBtn" class="btn btn-primary">
                    <span class="spinner-border" style="display:none;"></span> Test
                </button>
                <span id="ci_running" class="text-muted" style="display:none;">Testing cable, this takes a few seconds...</span>
            </div>
            <div id="ci_error" class="alert alert-danger" style="display:none; margin:12px 0 0;"></div>

            <div class="table-responsive" style="margin-top:15px;">
                <table id="ciTable" class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>Port</th>
                            <th>Cable State</th>
                            <th>Pair A</th>
                            <th>Pair B</th>
                            <th>Pair C</th>
                            <th>Pair D</th>
                            <th>Tested At</th>
                        </tr>
                    </thead>
                    <tbody id="ciBody">
                        <tr class="ci-empty"><td colspan="7" class="text-muted">Select a port and click Test.</td></tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="panel-footer">
        Runs a cable diagnostic (show cable interface) on one copper port and shows the cable state and each
        wire pair: <b>OK</b> - pair is fine, <b>Open</b> - pair not connected (no cable or cable not plugged in
        at the far end), <b>Short</b> - wires touching. The length is the distance to the far end or to the fault.
        Only copper (RJ45) ports can be tested.
    </div>
</div>

@if ($cablePorts->isNotEmpty())
<script>
    const CI_DEVICE_IP = "{{ $device->hostname }}";
    const CI_API_TOKEN = "{{ $data['api_token'] }}";

    function ciEsc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    // Colour a result: OK green, Open orange, Short / other faults red
    function ciValue(value) {
        if (!value) return '<span class="text-muted">-</span>';
        const v = String(value);
        let cls = 'ci-bad';
        if (/^ok\b/i.test(v)) cls = 'ci-ok';
        else if (/^open/i.test(v)) cls = 'ci-open';
        return `<span class="${cls}">${ciEsc(v)}</span>`;
    }

    $('#ciTestBtn').on('click', function () {
        const $opt = $('#ci_port option:selected');
        const port = $opt.val();

        if ($opt.data('status') === 'up' &&
            !confirm(`${port} is up. The cable test can briefly interrupt traffic on this port. Continue?`)) {
            return;
        }

        $('#ci_error').hide();
        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $('#ci_port').prop('disabled', true);
        $('#ci_running').show();

        $.ajax({
            url: "/api/v0/cableinfo/show/" + CI_DEVICE_IP,
            method: "POST",
            headers: { "Authorization": "Bearer " + CI_API_TOKEN, "Accept": "application/json" },
            data: { interface: port },
            success: function (r) {
                if (r.status !== 'success') {
                    $('#ci_error').text(r.message || 'Cable test failed').show();
                    return;
                }
                $('#ciBody .ci-empty').remove();
                $('#ciBody tr').removeClass('ci-latest');
                $('#ciBody').prepend(`<tr class="ci-latest">
                    <td><b>${ciEsc(r.interface)}</b></td>
                    <td>${ciValue(r.cable_state)}</td>
                    <td>${ciValue(r.pair_a)}</td>
                    <td>${ciValue(r.pair_b)}</td>
                    <td>${ciValue(r.pair_c)}</td>
                    <td>${ciValue(r.pair_d)}</td>
                    <td>${ciEsc(r.tested_at)}</td>
                </tr>`);
            },
            error: function (xhr) {
                const errors = xhr.responseJSON?.errors;
                $('#ci_error').text(errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error')).show();
            },
            complete: function () {
                $btn.prop('disabled', false).find('.spinner-border').hide();
                $('#ci_port').prop('disabled', false);
                $('#ci_running').hide();
            }
        });
    });
</script>
@endif
