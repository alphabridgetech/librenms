<?php
/*
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 *
 * @package    LibreNMS
 * @subpackage webui
 * @link       https://www.librenms.org
 * @copyright  2017 LibreNMS
 * @author     LibreNMS Contributors
*/

use App\Models\Device;

$no_refresh = true;
$param = [];
if ($device_id = (int) Request::get('device')) {
    $device = Device::find($device_id);
}
$start_date = Request::input('start_date');
$end_date = Request::input('end_date');

$pagetitle[] = 'Eventlog';
?>

<div class="panel panel-default panel-condensed">
    <div class="panel-heading">
        <strong>Eventlog</strong>
    </div>

    <?php
    require_once 'includes/html/common/eventlog.inc.php';
    echo implode('', $common_output);
    ?>
</div>

<script>
    $('.actionBar').append(
        '<div class="pull-left">' +
        '<form method="post" action="" class="form-inline" role="form" id="result_form">' +
        '<?php echo csrf_field() ?>' +
        <?php
        if (! isset($vars['fromdevice'])) {
            ?>
        '<div class="form-group">' +
        '<label><strong>Device&nbsp;&nbsp;</strong></label>' +
        '<select name="device" id="device" class="form-control">' +
        '<option value="">All Devices</option>' +
            <?php
            if ($device instanceof Device) {
                echo "'<option value=$device->device_id>" . $device->displayName() . "</option>' +";
            } ?>
        '</select>' +
        '</div>&nbsp;&nbsp;&nbsp;&nbsp;' +
            <?php
        } else {
            echo "'&nbsp;&nbsp;<input type=\"hidden\" name=\"device\" id=\"device\" value=\"" . $vars['device'] . "\">' + ";
        }
        ?>
        '<div class="form-group"><label><strong>Type&nbsp;&nbsp;</strong></label>' +
        '<select name="eventtype" id="eventtype" class="form-control input-sm">' +
        '<option value="">All types</option>' +
        <?php
        if ($type = Request::get('eventtype')) {
            $js_type = addcslashes(htmlentities($type), "'");
            echo "'<option value=\"$js_type\">$js_type</option>' +";
        }
        ?>
        '</select>' +
        '</div>&nbsp;&nbsp;' +
        '<div class="form-group"><label><strong>From&nbsp;&nbsp;</strong></label>' +
        '<input type="date" name="start_date" id="start_date" class="form-control input-sm" value="<?php echo htmlspecialchars($start_date ?? ""); ?>">' +
        '</div>&nbsp;&nbsp;' +
        '<div class="form-group"><label><strong>To&nbsp;&nbsp;</strong></label>' +
        '<input type="date" name="end_date" id="end_date" class="form-control input-sm" value="<?php echo htmlspecialchars($end_date ?? ""); ?>">' +
        '</div>&nbsp;&nbsp;' +
        '<button type="submit" class="btn btn-default">Filter</button>&nbsp;&nbsp;' +
        '<button type="button" id="btnExportCsv" class="btn btn-success">' +
        '<i class="fa fa-download fa-fw"></i> Export CSV' +
        '</button>&nbsp;&nbsp;' +
        '<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#forwardSyslogModal">' +
        '<i class="fa fa-cog fa-fw"></i> Syslog Forwarding' +
        '</button>&nbsp;&nbsp;' +
        '<button type="button" class="btn btn-info" data-toggle="modal" data-target="#forwardSnmpTrapModal">' +
        '<i class="fa fa-cog fa-fw"></i> SNMP Trap Forwarding' +
        '</button>' +
        '</form>' +
        '</div>'
    );

    <?php if (! isset($vars['fromdevice'])) { ?>
    $("#device").select2({
        theme: 'bootstrap',
        dropdownAutoWidth : true,
        width: "auto",
        allowClear: true,
        placeholder: "All Devices",
        ajax: {
            url: '<?php echo url('/ajax/select/device'); ?>',
            delay: 200
        }
    })<?php echo $device_id ? ".val($device_id).trigger('change');" : ''; ?>;
    <?php } ?>

    $("#eventtype").select2({
        theme: 'bootstrap',
        dropdownAutoWidth : true,
        width: "auto",
        allowClear: true,
        placeholder: "All Types",
        ajax: {
            url: '<?php echo url('/ajax/select/eventlog'); ?>',
            delay: 200,
            data: function(params) {
                return {
                    field: "type",
                    device: $('#device').val(),
                    term: params.term,
                    page: params.page || 1
                }
            }
        }
    })<?php echo Request::get('eventtype') ? ".val('" . htmlspecialchars(Request::get('eventtype')) . "').trigger('change');" : ''; ?>;

    $(document).on('submit', '#forwardSyslogForm', function (e) {
        e.preventDefault();
        var btn = $('#btnForwardSyslog');
        var alertDiv = $('#forwardSyslogAlert');
        
        btn.prop('disabled', true).text('Saving...');
        alertDiv.hide().removeClass('alert-success alert-danger');
        
        $.ajax({
            url: '<?php echo url('/ajax/table/eventlog/forward'); ?>',
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                btn.prop('disabled', false).text('Save Configuration');
                if (response.success) {
                    alertDiv.addClass('alert-success').text(response.message).show();
                    setTimeout(function() {
                        $('#forwardSyslogModal').modal('hide');
                        alertDiv.hide();
                    }, 3000);
                } else {
                    alertDiv.addClass('alert-danger').text(response.message).show();
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).text('Save Configuration');
                var errorMsg = 'An error occurred while saving configuration.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alertDiv.addClass('alert-danger').text(errorMsg).show();
            }
        });
    });

    $(document).on('click', '#btnTestForwardSyslog', function (e) {
        e.preventDefault();
        var btn = $(this);
        var alertDiv = $('#forwardSyslogAlert');
        var form = $('#forwardSyslogForm');
        
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        btn.prop('disabled', true).text('Testing...');
        alertDiv.hide().removeClass('alert-success alert-danger');
        
        $.ajax({
            url: '<?php echo url('/ajax/table/eventlog/forward/test'); ?>',
            method: 'POST',
            data: form.serialize(),
            success: function (response) {
                btn.prop('disabled', false).text('Test Connection');
                if (response.success) {
                    alertDiv.addClass('alert-success').text(response.message).show();
                } else {
                    alertDiv.addClass('alert-danger').text(response.message).show();
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).text('Test Connection');
                var errorMsg = 'An error occurred while testing connection.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alertDiv.addClass('alert-danger').text(errorMsg).show();
            }
        });
    });

    $(document).on('submit', '#forwardSnmpTrapForm', function (e) {
        e.preventDefault();
        var btn = $('#btnForwardSnmpTrap');
        var alertDiv = $('#forwardSnmpTrapAlert');
        
        btn.prop('disabled', true).text('Saving...');
        alertDiv.hide().removeClass('alert-success alert-danger');
        
        $.ajax({
            url: '<?php echo url('/ajax/table/eventlog/forward-snmptrap'); ?>',
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                btn.prop('disabled', false).text('Save Configuration');
                if (response.success) {
                    alertDiv.addClass('alert-success').text(response.message).show();
                    setTimeout(function() {
                        $('#forwardSnmpTrapModal').modal('hide');
                        alertDiv.hide();
                    }, 3000);
                } else {
                    alertDiv.addClass('alert-danger').text(response.message).show();
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).text('Save Configuration');
                var errorMsg = 'An error occurred while saving configuration.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alertDiv.addClass('alert-danger').text(errorMsg).show();
            }
        });
    });

    $(document).on('click', '#btnTestForwardSnmpTrap', function (e) {
        e.preventDefault();
        var btn = $(this);
        var alertDiv = $('#forwardSnmpTrapAlert');
        var form = $('#forwardSnmpTrapForm');
        
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        btn.prop('disabled', true).text('Testing...');
        alertDiv.hide().removeClass('alert-success alert-danger');
        
        $.ajax({
            url: '<?php echo url('/ajax/table/eventlog/forward-snmptrap/test'); ?>',
            method: 'POST',
            data: form.serialize(),
            success: function (response) {
                btn.prop('disabled', false).text('Test Connection');
                if (response.success) {
                    alertDiv.addClass('alert-success').text(response.message).show();
                } else {
                    alertDiv.addClass('alert-danger').text(response.message).show();
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).text('Test Connection');
                var errorMsg = 'An error occurred while testing connection.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alertDiv.addClass('alert-danger').text(errorMsg).show();
            }
        });
    });

    $(document).on('submit', '#result_form', function (e) {
        e.preventDefault();
        $("#eventlog").bootgrid("reload");
    });

    $(document).on('click', '#btnExportCsv', function () {
        var params = $.param({
            device: $('#device').val() || '',
            eventtype: $('#eventtype').val() || '',
            start_date: $('#start_date').val() || '',
            end_date: $('#end_date').val() || '',
            searchPhrase: $('#eventlog-header .search-field').val() || ''
        });
        window.location.href = '<?php echo url('/ajax/table/eventlog/export'); ?>?' + params;
    });
</script>

<?php
$saved_syslog_host = \App\Facades\LibrenmsConfig::get('eventlog_forward_syslog_host', '');
$saved_syslog_port = \App\Facades\LibrenmsConfig::get('eventlog_forward_syslog_port', 514);
?>

<!-- Forward to Syslog Modal -->
<div class="modal fade" id="forwardSyslogModal" tabindex="-1" role="dialog" aria-labelledby="forwardSyslogModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="forwardSyslogModalLabel">Configure Eventlog Syslog Forwarding</h4>
            </div>
            <form id="forwardSyslogForm">
                <?php echo csrf_field() ?>
                <div class="modal-body">
                    <div id="forwardSyslogAlert" class="alert" style="display: none;"></div>
                    
                    <p class="text-muted">Enter the IP address/hostname and port of your external Syslog server. Once saved, all new event log entries will be forwarded automatically in real-time.</p>

                    <div class="form-group">
                        <label for="syslog_ip">Syslog Server IP / Hostname</label>
                        <input type="text" class="form-control" id="syslog_ip" name="syslog_ip" value="<?php echo htmlspecialchars($saved_syslog_host); ?>" placeholder="e.g. 192.168.1.100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="syslog_port">Port</label>
                        <input type="number" class="form-control" id="syslog_port" name="syslog_port" value="<?php echo htmlspecialchars($saved_syslog_port); ?>" min="1" max="65535" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="btnTestForwardSyslog">Test Connection</button>
                    <button type="submit" class="btn btn-primary" id="btnForwardSyslog">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$saved_snmptrap_host = \App\Facades\LibrenmsConfig::get('snmptrap_forward_host', '');
$saved_snmptrap_port = \App\Facades\LibrenmsConfig::get('snmptrap_forward_port', 162);
?>

<!-- Forward to SNMP Trap Modal -->
<div class="modal fade" id="forwardSnmpTrapModal" tabindex="-1" role="dialog" aria-labelledby="forwardSnmpTrapModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="forwardSnmpTrapModalLabel">Configure SNMP Trap Forwarding</h4>
            </div>
            <form id="forwardSnmpTrapForm">
                <?php echo csrf_field() ?>
                <div class="modal-body">
                    <div id="forwardSnmpTrapAlert" class="alert" style="display: none;"></div>
                    
                    <p class="text-muted">Enter the IP address/hostname and port of your external SNMP Trap server/receiver. Once saved, all incoming SNMP traps will be forwarded automatically in real-time.</p>

                    <div class="form-group">
                        <label for="snmptrap_ip">SNMP Trap Server IP / Hostname</label>
                        <input type="text" class="form-control" id="snmptrap_ip" name="snmptrap_ip" value="<?php echo htmlspecialchars($saved_snmptrap_host); ?>" placeholder="e.g. 192.168.1.100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="snmptrap_port">Port</label>
                        <input type="number" class="form-control" id="snmptrap_port" name="snmptrap_port" value="<?php echo htmlspecialchars($saved_snmptrap_port); ?>" min="1" max="65535" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="btnTestForwardSnmpTrap">Test Connection</button>
                    <button type="submit" class="btn btn-primary" id="btnForwardSnmpTrap">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>
