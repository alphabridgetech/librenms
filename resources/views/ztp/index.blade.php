@extends('layouts.librenmsv1')

@section('title', __('ZTP — Zero Touch Provisioning'))

@section('content')
<div class="container-fluid">
    <x-panel>
        <x-slot name="title">
            <i class="fa fa-magic fa-fw fa-lg"></i> {{ __('Zero Touch Provisioning (ZTP)') }}
            <a href="{{ route('ztp.create') }}" class="btn btn-success btn-sm pull-right">
                <i class="fa fa-plus"></i> {{ __('Register Device') }}
            </a>
        </x-slot>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <i class="fa fa-check-circle fa-fw"></i> {{ session('status') }}
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default text-center" style="padding: 15px; border-top: 4px solid #5bc0de;">
                    <div style="font-size: 2em; font-weight: bold; color: #5bc0de;">{{ $totalCount }}</div>
                    <div class="text-muted">{{ __('Total Registered') }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default text-center" style="padding: 15px; border-top: 4px solid #f0ad4e;">
                    <div style="font-size: 2em; font-weight: bold; color: #f0ad4e;">{{ $pendingCount }}</div>
                    <div class="text-muted">{{ __('Pending') }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default text-center" style="padding: 15px; border-top: 4px solid #5cb85c;">
                    <div style="font-size: 2em; font-weight: bold; color: #5cb85c;">{{ $provisionedCount }}</div>
                    <div class="text-muted">{{ __('Provisioned') }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default text-center" style="padding: 15px; border-top: 4px solid #d9534f;">
                    <div style="font-size: 2em; font-weight: bold; color: #d9534f;">{{ $failedCount }}</div>
                    <div class="text-muted">{{ __('Failed') }}</div>
                </div>
            </div>
        </div>

        {{-- Info Box --}}
        <div class="alert alert-info">
            <strong><i class="fa fa-info-circle"></i> {{ __('ZTP Config URL Format:') }}</strong>
            <code>{{ url('/ztp/config/{mac_address}') }}</code>
            <br><small>{{ __('Configure this URL in your DHCP server as Option 67 (bootfile-name). The switch will automatically substitute its own MAC address.') }}</small>
        </div>

        {{-- Devices Table --}}
        @if ($devices->isEmpty())
            <div class="alert alert-warning text-center">
                <i class="fa fa-exclamation-triangle fa-2x"></i><br>
                {{ __('No ZTP devices registered yet.') }}
                <a href="{{ route('ztp.create') }}">{{ __('Register your first device.') }}</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('MAC Address') }}</th>
                            <th>{{ __('Device Name') }}</th>
                            <th>{{ __('IP Address') }}</th>
                            <th>{{ __('SNMP Community') }}</th>
                            <th>{{ __('Template') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Last Seen') }}</th>
                            <th>{{ __('Provisioned At') }}</th>
                            <th style="min-width: 200px;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            <tr>
                                <td><code>{{ $device->mac_address }}</code></td>
                                <td><strong>{{ $device->device_name }}</strong></td>
                                <td>{{ $device->ip_address ?? '—' }}</td>
                                <td><code>{{ $device->snmp_community ?? 'public' }}</code></td>
                                <td>{{ $device->template_name ?? ($device->template_commands ? 'Custom' : '—') }}</td>
                                <td>
                                    <span class="label label-{{ $device->status_badge }}">
                                        {{ ucfirst($device->status) }}
                                    </span>
                                </td>
                                <td>{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : '—' }}</td>
                                <td>{{ $device->provisioned_at ? $device->provisioned_at->format('Y-m-d H:i') : '—' }}</td>
                                <td>
                                    {{-- Edit --}}
                                    <a href="{{ route('ztp.edit', $device->id) }}" class="btn btn-xs btn-primary" title="Edit">
                                        <i class="fa fa-pencil"></i>
                                    </a>

                                    {{-- View Config --}}
                                    <a href="{{ route('ztp.config', ['mac' => $device->mac_address]) }}" target="_blank" class="btn btn-xs btn-info" title="View Config File">
                                        <i class="fa fa-file-text-o"></i>
                                    </a>

                                    {{-- Command Preview --}}
                                    <button type="button" class="btn btn-xs btn-default"
                                        title="Backend Command Preview"
                                        data-toggle="modal"
                                        data-target="#cmdModal{{ $device->id }}">
                                        <i class="fa fa-terminal"></i>
                                    </button>

                                    {{-- Reset to Pending --}}
                                    @if ($device->status === 'provisioned')
                                        <form method="POST" action="{{ route('ztp.reset', $device->id) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-warning" title="Reset to Pending"
                                                onclick="return confirm('Reset this device to pending?')">
                                                <i class="fa fa-refresh"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Delete --}}
                                    <form method="POST" action="{{ route('ztp.destroy', $device->id) }}" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" title="Delete"
                                            onclick="return confirm('Delete ZTP entry for {{ $device->device_name }}?')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            {{-- ===== COMMAND PREVIEW MODAL ===== --}}
                            <div class="modal fade" id="cmdModal{{ $device->id }}" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content" style="border:none;">

                                        <div class="modal-header" style="background:#1e1e1e; color:#4ec9b0; border-radius:4px 4px 0 0; border-bottom:1px solid #333;">
                                            <button type="button" class="close" data-dismiss="modal" style="color:#4ec9b0; opacity:1; font-size:20px;">
                                                <span>&times;</span>
                                            </button>
                                            <h4 class="modal-title" style="font-family:monospace;">
                                                <i class="fa fa-terminal"></i>
                                                &nbsp;Backend Process Preview &mdash; <span style="color:#9cdcfe;">{{ $device->device_name }}</span>
                                            </h4>
                                        </div>

                                        <div class="modal-body" style="background:#1e1e1e; padding:0; font-family:monospace;">

                                            {{-- STEP 1 --}}
                                            <div style="padding:14px 18px; border-bottom:1px solid #2d2d2d;">
                                                <div style="color:#569cd6; font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
                                                    ► STEP 1 &nbsp;|&nbsp; Switch Calls This URL on Boot
                                                </div>
                                                <pre style="background:#111; color:#6a9955; padding:10px 14px; border-radius:4px; margin:0; font-size:13px; border-left:3px solid #4ec9b0;">GET {{ url('/ztp/config/' . $device->mac_address) }}</pre>
                                            </div>

                                            {{-- STEP 2 --}}
                                            <div style="padding:14px 18px; border-bottom:1px solid #2d2d2d;">
                                                <div style="color:#569cd6; font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
                                                    ► STEP 2 &nbsp;|&nbsp; CLI Config Served to Switch (plain text response)
                                                </div>
                                                @php
                                                    $configPreview = '<span style="color:#c586c0;">enable</span>' . "\n";
                                                    $configPreview .= '<span style="color:#c586c0;">config</span>' . "\n";
                                                    if ($device->device_name) {
                                                        $configPreview .= '<span style="color:#9cdcfe;">hostname</span> <span style="color:#ce9178;">' . e($device->device_name) . '</span>' . "\n";
                                                    }
                                                    if ($device->ip_address) {
                                                        $configPreview .= '<span style="color:#9cdcfe;">interface vlan 1</span>' . "\n";
                                                        $configPreview .= '  <span style="color:#9cdcfe;">ip address</span> <span style="color:#ce9178;">' . e($device->ip_address) . ' ' . e($device->subnet_mask ?? '255.255.255.0') . '</span>' . "\n";
                                                        $configPreview .= '  <span style="color:#9cdcfe;">no shutdown</span>' . "\n";
                                                    }
                                                    if ($device->gateway) {
                                                        $configPreview .= '<span style="color:#9cdcfe;">ip default-gateway</span> <span style="color:#ce9178;">' . e($device->gateway) . '</span>' . "\n";
                                                    }
                                                    if ($device->template_commands) {
                                                        $configPreview .= '<span style="color:#6a9955;"># Custom commands:</span>' . "\n";
                                                        $configPreview .= '<span style="color:#d4d4d4;">' . e($device->template_commands) . '</span>' . "\n";
                                                    }
                                                    if ($device->template_name) {
                                                        $configPreview .= '<span style="color:#f0ad4e;">[Commands from template: ' . e($device->template_name) . ']</span>' . "\n";
                                                    }
                                                    $configPreview .= '<span style="color:#c586c0;">end</span>' . "\n";
                                                    $configPreview .= '<span style="color:#c586c0;">write</span>';
                                                @endphp
                                                <pre style="background:#111; color:#d4d4d4; padding:10px 14px; border-radius:4px; margin:0; font-size:13px; border-left:3px solid #569cd6;">{!! $configPreview !!}</pre>
                                            </div>


                                            {{-- STEP 3 --}}
                                            <div style="padding:14px 18px;">
                                                <div style="color:#569cd6; font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
                                                    ► STEP 3 &nbsp;|&nbsp; LibreNMS Auto-Add Command (runs 60s after provisioning)
                                                </div>
                                                @if($device->ip_address)
                                                    <pre style="background:#111; color:#6a9955; padding:10px 14px; border-radius:4px; margin:0; font-size:13px; border-left:3px solid #5cb85c;"><span style="color:#d4d4d4;">$</span> php artisan device:add \
    --v2c \
    --community=<span style="color:#ce9178;">{{ $device->snmp_community ?? 'public' }}</span> \
    --ping-fallback \
    <span style="color:#ce9178;">{{ $device->ip_address }}</span></pre>
                                                    <div style="color:#6a6a6a; font-size:12px; margin-top:8px;">
                                                        <i class="fa fa-file-text-o"></i>
                                                        Log: <code style="color:#6a9955; background:transparent;">/tmp/ztp_add_{{ str_replace('.', '_', $device->ip_address) }}.log</code>
                                                    </div>
                                                @else
                                                    <div style="color:#f0ad4e; background:#2a1f00; padding:10px 14px; border-radius:4px; border-left:3px solid #f0ad4e;">
                                                        <i class="fa fa-exclamation-triangle"></i>
                                                        No IP address configured — LibreNMS auto-add will be <strong>skipped</strong>.
                                                    </div>
                                                @endif
                                            </div>

                                        </div>

                                        <div class="modal-footer" style="background:#1e1e1e; border-top:1px solid #2d2d2d; border-radius:0 0 4px 4px;">
                                            @if($device->ip_address)
                                            <a href="{{ route('ztp.config', ['mac' => $device->mac_address]) }}" target="_blank" class="btn btn-info btn-sm">
                                                <i class="fa fa-external-link"></i> View Live Config File
                                            </a>
                                            @endif
                                            <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">
                                                <i class="fa fa-times"></i> Close
                                            </button>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            {{-- ===== END MODAL ===== --}}

                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </x-panel>
</div>
@endsection
