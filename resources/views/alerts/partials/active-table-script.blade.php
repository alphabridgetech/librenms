<script type="text/javascript">
    var alerts_grid = $("#alerts_{{ $unique_id }}").bootgrid({
        ajax: true,
        @if(empty($device_id) || $device_id == -1)
        // Telequill: Device / Hostname filters
        templates: {
            header: '<div id="@{{ctx.id}}" class="@{{css.header}}"><div class="row">' +
                '<div class="col-sm-12 actionBar" style="margin-bottom: 10px;"><span class="pull-left"><div class="form-inline">' +
                '<div class="form-group"><label><strong>{{ __('Device') }}&nbsp;</strong></label>' +
                '<select id="alerts_device_id_{{ $unique_id }}" class="form-control input-sm" style="min-width: 150px;"></select></div>' +
                '<div class="form-group" style="margin-left: 5px;"><label><strong>{{ __('Hostname') }}&nbsp;</strong></label>' +
                '<select id="alerts_hostname_filter_{{ $unique_id }}" class="form-control input-sm" style="min-width: 150px;"></select></div>' +
                '<button type="button" id="alerts_clear_filter_{{ $unique_id }}" class="btn btn-default input-sm" style="margin-left: 5px;"><i class="fa fa-refresh"></i> {{ __('Clear Filter') }}</button>' +
                '</div></span></div>' +
                '<div class="col-sm-12 actionBar"><p class="@{{css.search}}"></p><p class="@{{css.actions}}"></p></div></div></div>'
        },
        @endif
        post: function() {
            return {
                rule_id: '{{ $rule_id }}',
                alert_id: '{{ $alert_id }}',
                acknowledged: '{{ $acknowledged }}',
                fired: '{{ $fired }}',
                state: '{{ $state }}',
                min_severity: '{{ $min_severity }}',
                group: '{{ $group }}',
                proc: '{{ $proc }}',
                device_id: $('#alerts_device_id_{{ $unique_id }}').val() || '{{ $device_id }}',
                hostname: $('#alerts_hostname_filter_{{ $unique_id }}').val() || '',
            };
        },
        url: "{{ route('table.alerts') }}",
        sort: {
            @if($sort === 'severity' || $sort == 1)
            severity: 'desc',
            @else
            timestamp: 'desc',
            @endif
        },
        rowCount: [50, 100, 250, -1],
    }).on("loaded.rs.jquery.bootgrid", function() {
        alerts_grid = $(this);
        alerts_grid.find(".incident-toggle").each(function() {
            $(this).parent().addClass('incident-toggle-td');
        }).on("click", function() {
            var target = $(this).data("target");
            $(target).collapse('toggle');
            $(this).toggleClass('fa-plus fa-minus');
        });
        alerts_grid.find(".incident").each(function() {
            $(this).parent().addClass('col-lg-4 col-md-4 col-sm-4 col-xs-4');
            $(this).parent().parent().on("mouseenter", function() {
                $(this).find(".incident-toggle").fadeIn(200);
            }).on("mouseleave", function() {
                $(this).find(".incident-toggle").fadeOut(200);
            });
        });
        alerts_grid.find(".command-ack-alert").on("click", function(e) {
            e.preventDefault();
            var alert_state = $(this).data("alert_state");
            var alert_id = $(this).data('alert_id');
            $('#ack_alert_id').val(alert_id);
            $('#ack_alert_state').val(alert_state);
            $('#ack_msg').val('');
            $("#alert_ack_modal").modal('show');
        });
        alerts_grid.find(".command-alert-note").on("click", function(e) {
            e.preventDefault();
            var alert_id = $(this).data('alert_id');
            $('#alert_id').val(alert_id);
            $("#alert_notes_modal").modal('show');
        });
        alerts_grid.find(".command-alert-details").on("click", function(e) {
            e.preventDefault();
            var alert_log_id = $(this).data('alert_log_id');
            $('#alert_log_id').val(alert_log_id);
            $("#alert_details_modal").modal('show');
        });
    });

    @if(empty($device_id) || $device_id == -1)
    $(document).on("change", "#alerts_device_id_{{ $unique_id }}, #alerts_hostname_filter_{{ $unique_id }}", function() {
        $("#alerts_{{ $unique_id }}").bootgrid("reload");
    });
    $(document).on("click", "#alerts_clear_filter_{{ $unique_id }}", function(e) {
        e.preventDefault();
        $("#alerts_hostname_filter_{{ $unique_id }}").val(null).trigger("change");
        $("#alerts_device_id_{{ $unique_id }}").val(null).trigger("change");
    });
    init_select2("#alerts_device_id_{{ $unique_id }}", "device", {}, '', "{{ __('All Devices') }}");
    init_select2("#alerts_hostname_filter_{{ $unique_id }}", "device", {field: "hostname"}, '', "{{ __('All Hostnames') }}");
    @endif
</script>
