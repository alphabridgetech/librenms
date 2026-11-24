<style>
    .spinner-border {
        width: 16px; height: 16px;
        border: 2px solid #fff; border-right-color: transparent; border-radius: 50%;
        display: inline-block; animation: spin 0.75s linear infinite; vertical-align: middle;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    #dcForm .form-group.dc-changed .form-control { background: #fff8e1; }
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>DHCP Client Configuration</strong>
        <span id="dc_cache_note" class="text-muted" style="display:none; margin-left:10px;">
            <i class="fa fa-spinner fa-spin"></i> showing last known data, refreshing in background...
        </span>
        <button type="button" class="btn btn-xs btn-default pull-right" onclick="dcLoad()"><i class="fa fa-refresh"></i> Refresh</button>
    </div>
    <div class="panel-body">
        <div id="dc_load_error" class="alert alert-danger" style="display:none;"></div>
        <form class="form-horizontal" id="dcForm" onsubmit="return false;">
            @foreach ([
                ['Boot_file_Name', 'boot_file_name', 'Boot File Name (add MAC)', 'toggle', ''],
                ['Vendor_Class_Identifier', 'vendor_class_identifier', 'Vendor Class Identifier', 'text', 'letters, digits, . _ -'],
                ['Client_Identifier', 'client_identifier', 'Client Identifier', 'toggle', ''],
                ['Minimal_acceptable_lease_time', 'minimal_acceptable_lease_time', 'Minimal Acceptable Lease Time', 'number:60:86400', '60-86400 seconds'],
                ['Number_of_packet_retransmission_times', 'packet_retransmission_times', 'Number of Packet Retransmission Times', 'number:1:10', '1-10'],
                ['Retry_interval', 'retry_interval', 'Retry Interval', 'number:1:1440', '1-1440 minutes'],
                ['Select_interval', 'select_interval', 'Select Interval', 'number:5:30', '5-30 seconds'],
                ['TFTP_Download', 'tftp_download', 'TFTP Download', 'toggle', ''],
                ['Shut_down_port_when_timeout', 'shutdown_port_on_timeout', 'Shut Down Port When Timeout', 'toggle', ''],
            ] as [$field, $key, $label, $type, $help])
                <div class="form-group">
                    <label class="col-sm-4 control-label">{{ $label }}</label>
                    <div class="col-sm-4">
                        @if ($type === 'toggle')
                            <select class="form-control dc-field" data-field="{{ $field }}" data-key="{{ $key }}" disabled>
                                <option value="Enable">enable</option>
                                <option value="Disable">disable</option>
                            </select>
                        @elseif ($type === 'text')
                            <input type="text" class="form-control dc-field" data-field="{{ $field }}" data-key="{{ $key }}" maxlength="64" disabled>
                        @else
                            @php [, $min, $max] = explode(':', $type); @endphp
                            <input type="number" class="form-control dc-field" data-field="{{ $field }}" data-key="{{ $key }}" min="{{ $min }}" max="{{ $max }}" disabled>
                        @endif
                        @if ($help)<span class="help-block">{{ $help }}</span>@endif
                    </div>
                </div>
            @endforeach
            <div id="dc_error" class="text-danger" style="display:none; margin:0 0 10px 15px;"></div>
            <div class="form-group" style="margin-bottom:0;">
                <div class="col-sm-offset-4 col-sm-8">
                    <button type="button" id="dcApplyBtn" class="btn btn-primary" disabled><span class="spinner-border" style="display:none;"></span> Apply</button>
                    <button type="button" id="dcResetBtn" class="btn btn-default" disabled>Reset</button>
                </div>
            </div>
        </form>
    </div>
    <div class="panel-footer">
        Settings the switch uses when it gets its own address by DHCP. Changed fields are highlighted; Apply saves only
        those to the switch and its startup configuration.
    </div>
</div>

<script>
    const DC_DEVICE_IP = "{{ $device->hostname }}";
    const DC_HEADERS = { "Authorization": "Bearer {{ $data['api_token'] }}", "Accept": "application/json" };
    let dcOriginal = {};

    function dcErrorText(xhr) {
        const errors = xhr.responseJSON?.errors;
        return errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'API communication error');
    }

    // The switch reports Client Identifier as the identifier itself:
    // "hrd-ether" is what enable sets ("ip dhcp client client-identifier
    // hrd-ether"). Show that as enable; any other identifier set on the
    // switch gets its own option so the dropdown is never left blank.
    function dcDisplayValue($el, value) {
        if (!$el.is('select')) return value;
        if ($el.data('field') === 'Client_Identifier' && value.toLowerCase() === 'hrd-ether') return 'Enable';
        if (value && !$el.find('option').filter((_, o) => o.value === value).length) {
            $el.append($('<option>').val(value).text(value + ' (set on switch)'));
        }
        return value;
    }

    function dcLoad() {
        $('#dc_load_error').hide();
        $.ajax({
            url: "/api/v0/dhcp/client/show/" + DC_DEVICE_IP,
            headers: DC_HEADERS,
            success: function (json) {
                $('#dc_cache_note').toggle(!!json.cached);
                if (json.status !== 'success') {
                    $('#dc_load_error').text(json.message || 'Failed to load DHCP client configuration').show();
                    return;
                }
                const client = json.client || {};
                dcOriginal = {};
                $('.dc-field').each(function () {
                    const v = client[$(this).data('key')];
                    const value = dcDisplayValue($(this), v == null ? '' : String(v));
                    dcOriginal[$(this).data('field')] = value;
                    $(this).val(value).prop('disabled', false).closest('.form-group').removeClass('dc-changed');
                });
                $('#dcApplyBtn, #dcResetBtn').prop('disabled', false);
            },
            error: function (xhr) { $('#dc_load_error').text(dcErrorText(xhr)).show(); }
        });
    }

    $('#dcForm').on('change input', '.dc-field', function () {
        $(this).closest('.form-group').toggleClass('dc-changed', $.trim($(this).val()) !== (dcOriginal[$(this).data('field')] || ''));
    });

    $('#dcResetBtn').on('click', function () {
        $('.dc-field').each(function () { $(this).val(dcOriginal[$(this).data('field')] || ''); });
        $('#dcForm .form-group').removeClass('dc-changed');
        $('#dc_error').hide();
    });

    $('#dcApplyBtn').on('click', function () {
        $('#dc_error').hide();
        const changes = {};
        $('.dc-field').each(function () {
            const value = $.trim($(this).val());
            if (value !== (dcOriginal[$(this).data('field')] || '')) changes[$(this).data('field')] = value;
        });
        if (!Object.keys(changes).length) {
            $('#dc_error').text('Nothing to apply - no setting has been changed.').show();
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true).find('.spinner-border').show();
        $.ajax({
            url: "/api/v0/dhcp/client/set/" + DC_DEVICE_IP,
            method: "POST", headers: DC_HEADERS, data: changes,
            success: function (r) {
                if (r.status === 'success') { alert(r.message); dcLoad(); }
                else { $('#dc_error').text(r.message || 'Request failed').show(); }
            },
            error: function (xhr) { $('#dc_error').text(dcErrorText(xhr)).show(); },
            complete: function () { $btn.prop('disabled', false).find('.spinner-border').hide(); }
        });
    });

    dcLoad();
</script>
