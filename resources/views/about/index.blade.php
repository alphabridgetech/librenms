@extends('layouts.librenmsv1')

@section('title', __('About'))

@section('content')
<div class="modal fade" id="git_log" tabindex="-1" role="dialog" aria-labelledby="git_log_label" aria-hidden="true">
    <div class="modal-dialog" style="width: 700px; max-width: 90%;">
        <div class="modal-content">
            <div class="modal-header" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel" style="font-weight: 600; color: #333;">{{ __('Version Update History') }}</h4>
            </div>
            <div class="modal-body" style="max-height: 450px; overflow-y: auto; padding: 20px;">
                @if(empty($version_changelog))
                    <div class="alert alert-info">{{ __('No version changes found or Git is not available.') }}</div>
                @else
                    @foreach ($version_changelog as $v)
                        <div class="version-section" style="margin-bottom: 25px;">
                            <h4 style="border-bottom: 2px solid #5c7080; padding-bottom: 8px; margin-top: 5px; color: #1a252c; font-weight: bold;">
                                <i class="fa fa-tag"></i> {{ __('Version') }}: {{ $v['version'] }}
                                @if(!empty($v['date']))
                                    <span class="label label-default" style="float: right; font-size: 12px; margin-top: 3px;">{{ $v['date'] }}</span>
                                @endif
                            </h4>
                            <div style="padding-left: 15px;">
                                <ul style="list-style-type: none; padding-left: 0; margin-bottom: 0;">
                                    @foreach ($v['commits'] as $commit)
                                        <li style="margin-bottom: 8px; line-height: 1.5; font-size: 14px;">
                                            <span class="label label-primary" style="font-family: monospace; font-size: 11px; padding: 2px 5px; display: inline-block; min-width: 60px; text-align: center; margin-right: 8px;">{{ $commit['sha'] }}</span>
                                            <span style="color: #2b3a42;">{{ $commit['subject'] }}</span>
                                            @if($commit['date'] !== $v['date'])
                                                <small class="text-muted" style="font-size: 11px; margin-left: 5px;">({{ $commit['date'] }})</small>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-default" data-dismiss="modal">@Lang('Close')</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">

            <h3>{{ __('Telequill is an autodiscovering PHP/MySQL-based network monitoring system') }}</h3>
            <table class='table table-condensed table-hover'>
                <tr>
                    <td><b>{{ __('Version') }}</b></td>
                    <td><a href="#git_log" data-toggle="modal">{{ $version_local }}<span id='version_date' style="display: none;">{{ $git_date }}</span></a></td>
                </tr>
                <tr>
                    <td><b>{{ __('Database Schema') }}</b></td>
                    <td>{{ $db_schema }}</td>
                </tr>
                <tr>
                    <td><b>{{ __('Web Server') }}</b></td>
                    <td>{{ $version_webserver }}</td>
                </tr>
                <tr>
                    <td><b>{{ __('PHP') }}</b></td>
                    <td>{{ $version_php }}</td>
                </tr>
                <tr>
                    <td><b>{{ __('Python') }}</b></td>
                    <td>{{ $version_python }}</td>
                </tr>
                <tr>
                    <td><b>{{ __('Database') }}</b></td>
                    <td>{{ $version_database }}</td>
                </tr>
                <tr>
                    <td><a target="_blank" href="https://laravel.com/"><b>{{ __('Laravel') }}</b></a></td>
                    <td>{{ $version_laravel }}</td>
                </tr>
                <tr>
                    <td><a target="_blank" href="https://oss.oetiker.ch/rrdtool/"><b>{{ __('RRDtool') }}</b></a></td>
                    <td>{{ $version_rrdtool }}</td>
                </tr>
            </table>

          <h3>{{ __('Telequill is a community-based project') }}</h3>
          <p>
            {{ __('Please feel free to join us and contribute code, documentation, and bug reports:') }}
            <br />
            <a target="_blank" href="https://www.alphabridge.tech/">{{ __('Web site') }}</a> |
            <a target="_blank" href="https://www.alphabridge.tech/wp-content/uploads/2024/06/TeleQuill_EMS_datasheet.pdf">{{ __('Docs') }}</a> |
            <a target="_blank" href="https://community.telequill.org/c/help">{{ __('Bug tracker') }}</a> |
            <a target="_blank" href="https://www.alphabridge.tech/product-filter/">{{ __('Merch Shop') }}</a> |
            <a target="_blank" href="https://www.alphabridge.tech/support-guide/">{{ __('Community Forum') }}</a> |
            <a target="_blank" href="https://twitter.com/telequill">{{ __('Twitter') }}</a> |
            <a target="_blank" href="https://www.linkedin.com/company/alphabridge">{{ __('LinkedIn') }}</a> |

            
          </p>
          <h3>{{ __('Contributors') }}</h3>
          <b>1.Rajiv Mittal</b><br>
          <b>2.Kunal verma</b> <br>
          <b>3.Tagore pattela</b>
      </div>
      <div class="col-md-6">

        <h3>{{ __('Reporting & Statistics') }}</h3>

        <table class='table table-condensed'>

            @admin
            <tr>
                <td colspan='4'>
                    <div>
                        <label for="reporting.usage" class="bg-info">{{ __('Opt in to send anonymous reports to Telequill?') }}</label>
                    </div>
                    <div>
                        {{ __('Error reporting:') }} <input type="checkbox" id="reporting.error" name="reporting" data-size="small" @if($error_reporting_status) checked @endif>
                    </div>
                   
                    @if($reporting_clearable)
                        <div class="tw:mt-2">
                            <button class='btn btn-danger btn-xs' type='submit' name='clear-reporting' id='clear-reporting'>{{ __('Clear reporting data') }}</button>
                        </div>
                    @endif
                </td>
            </tr>
            @endadmin

            <tr>
                <td><i class='fa fa-fw fa-server fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Devices') }}</b></td>
                <td class='text-right'>{{ $stat_devices }}</td>
                <td><i class='fa fa-fw fa-link fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Ports') }}</b></td>
                <td class='text-right'>{{ $stat_ports }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-battery-empty fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('IPv4 Addresses') }}</b></td>
                <td class='text-right'>{{ $stat_ipv4_addy }}</td>
                <td><i class='fa fa-fw fa-battery-empty fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('IPv4 Networks') }}</b></td>
                <td class='text-right'>{{ $stat_ipv4_nets }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-battery-full fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('IPv6 Addresses') }}</b></td>
                <td class='text-right'>{{ $stat_ipv6_addy }}</td>
                <td><i class='fa fa-fw fa-battery-full fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('IPv6 Networks') }}</b></td>
                <td class='text-right'>{{ $stat_ipv6_nets }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-cogs fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Services') }}</b></td>
                <td class='text-right'>{{ $stat_services }}</td>
                <td><i class='fa fa-fw fa-cubes fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Applications') }}</b></td>
                <td class='text-right'>{{ $stat_apps }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-microchip fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Processors') }}</b></td>
                <td class='text-right'>{{ $stat_processors }}</td>
                <td><i class='fa-fw fas fa-memory fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Memory') }}</b></td>
                <td class='text-right'>{{ $stat_memory }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-database fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Storage') }}</b></td>
                <td class='text-right'>{{ $stat_storage }}</td>
                <td><i class='fa fa-fw fa-hdd-o fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Disk I/O') }}</b></td>
                <td class='text-right'>{{ $stat_diskio }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-cube fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('HR-MIB') }}</b></td>
                <td class='text-right'>{{ $stat_hrdev }}</td>
                <td><i class='fa fa-fw fa-cube fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Entity-MIB') }}</b></td>
                <td class='text-right'>{{ $stat_entphys }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-clone fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Syslog Entries') }}</b></td>
                <td class='text-right'>{{ $stat_syslog }}</td>
                <td><i class='fa fa-fw fa-bookmark fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Eventlog Entries') }}</b></td>
                <td class='text-right'>{{ $stat_events }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-dashboard fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('sensors.title') }}</b></td>
                <td class='text-right'>{{ $stat_sensors }}</td>
                <td><i class='fa fa-fw fa-wifi fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Wireless Sensors') }}</b></td>
                <td class='text-right'>{{ $stat_wireless }}</td>
            </tr>
            <tr>
                <td><i class='fa fa-fw fa-print fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('Toner') }}</b></td>
                <td class='text-right'>{{ $stat_toner }}</td>
                <td><i class='fa fa-fw fa-code-fork fa-lg icon-theme' aria-hidden='true'></i> <b>{{ __('QoS Queues') }}</b></td>
                <td class='text-right'>{{ $stat_qos }}</td>
            </tr>
        </table>

        <h3>{{ __('License') }}</h3>
        <pre>
copyright © 2026 Telequill Technologies. 
All Rights Reserved.

This software and its associated documentation are proprietary 
and confidential. Unauthorized copying, distribution, modification,
reverse engineering, or use of this software, in whole or in part,
is strictly prohibited without prior written permission from Telequill 
Technologies.

This software is licensed, not sold. Use of this software is subject
to the terms and conditions of the applicable commercial license 
agreement. A valid paid license is required for installation, access, 
and continued use.

THE SOFTWARE IS PROVIDED "AS IS" WITHOUT WARRANTY OF ANY KIND, EXCEPT
AS EXPRESSLY PROVIDED IN THE APPLICABLE LICENSE AGREEMENT. IN NO 
EVENT SHALL TELEQUILL TECHNOLOGIES BE LIABLE FOR ANY INDIRECT, 
INCIDENTAL, SPECIAL, OR CONSEQUENTIAL DAMAGES ARISING
OUT OF THE USE OR INABILITY TO USE THIS SOFTWARE.

<a target="_blank" href="https://www.alphabridge.tech/">https://www.alphabridge.tech/</a>.</pre>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $("[name='reporting']").bootstrapSwitch('offColor','danger','size','mini');
    $('input[name="reporting"]').on('switchChange.bootstrapSwitch',  function(event, state) {
        event.preventDefault();
        const type = event.target.id;
        $.ajax({
            type: 'PUT',
            url: '{{ route('settings.update', '?') }}'.replace('?', type),
            data: JSON.stringify({value: state}),
            contentType: "application/json",
            success: function(data){},
            error:function(){
                return $("#" + type).bootstrapSwitch("toggle");
            }
        });
    });
    $('#clear-reporting').on("click", function(event) {
        event.preventDefault();
        $.ajax({
            type: 'DELETE',
            url: '{{ route('reporting.clear') }}',
            success: function(){
                $('#clear-reporting').remove();
                $("#callback").bootstrapSwitch('state', false);
            },
            error:function(){}
        });
    });

    var ver_date = $('#version_date');
    if (ver_date.text()) {
        ver_date.text(' - '.concat(moment(ver_date.text()))).show();
    }
</script>
@endsection
