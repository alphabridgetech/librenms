@extends('layouts.librenmsv1')

@section('title', __('ZTP — Register Device'))

@section('content')
<div class="container-fluid">
    <x-panel>
        <x-slot name="title">
            <i class="fa fa-magic fa-fw fa-lg"></i> {{ __('Register Device for ZTP') }}
        </x-slot>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('ztp.store') }}" class="form-horizontal">
            @csrf

            {{-- MAC Address --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('MAC Address') }} <span class="text-danger">*</span></label>
                <div class="col-sm-6">
                    <input type="text" name="mac_address" class="form-control" placeholder="aa:bb:cc:dd:ee:ff"
                        value="{{ old('mac_address') }}" required>
                    <span class="help-block">{{ __('Switch MAC address (used by DHCP to identify this device)') }}</span>
                </div>
            </div>

            {{-- Device Name / Hostname --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Hostname') }} <span class="text-danger">*</span></label>
                <div class="col-sm-6">
                    <input type="text" name="device_name" class="form-control" placeholder="switch-floor1-01"
                        value="{{ old('device_name') }}" required>
                    <span class="help-block">{{ __('Hostname to assign to the device via the "hostname" command') }}</span>
                </div>
            </div>

            {{-- IP Address --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('IP Address') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.10"
                        value="{{ old('ip_address') }}">
                </div>
            </div>

            {{-- Subnet Mask --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Subnet Mask') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="subnet_mask" class="form-control" placeholder="255.255.255.0"
                        value="{{ old('subnet_mask', '255.255.255.0') }}">
                </div>
            </div>

            {{-- Gateway --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Default Gateway') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="gateway" class="form-control" placeholder="192.168.1.1"
                        value="{{ old('gateway') }}">
                </div>
            </div>

            {{-- SNMP Community --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('SNMP Community') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="snmp_community" class="form-control" placeholder="public"
                        value="{{ old('snmp_community', 'public') }}">
                    <span class="help-block">{{ __('Used by LibreNMS to auto-discover this device after provisioning.') }}</span>
                </div>
            </div>

            <hr>
            <h5><i class="fa fa-file-text-o"></i> {{ __('Configuration Source') }}</h5>
            <p class="text-muted">{{ __('Choose either a saved template OR enter custom CLI commands below.') }}</p>

            {{-- Template Dropdown --}}
            @if (!empty($templates))
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Use Template') }}</label>
                <div class="col-sm-6">
                    <select name="template_name" id="template_name" class="form-control">
                        <option value="">{{ __('-- None (use custom commands below) --') }}</option>
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl['name'] }}"
                                data-folder="{{ $tpl['template_folder'] ?? '' }}"
                                {{ old('template_name') === $tpl['name'] ? 'selected' : '' }}>
                                {{ $tpl['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="template_folder" id="template_folder" value="{{ old('template_folder') }}">
                </div>
            </div>
            @endif

            {{-- Custom Commands --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Custom CLI Commands') }}</label>
                <div class="col-sm-6">
                    <textarea name="template_commands" class="form-control" rows="8"
                        placeholder="vlan 10&#10;  name MANAGEMENT&#10;interface GigaEthernet0/1&#10;  switchport mode trunk">{{ old('template_commands') }}</textarea>
                    <span class="help-block">{{ __('Enter CLI commands (one per line). These are appended after hostname/IP config.') }}</span>
                </div>
            </div>

            {{-- Notes --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Notes') }}</label>
                <div class="col-sm-6">
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>

            <hr>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> {{ __('Register Device') }}
                    </button>
                    <a href="{{ route('ztp.index') }}" class="btn btn-default">{{ __('Cancel') }}</a>
                </div>
            </div>

        </form>
    </x-panel>
</div>

<script>
    // Sync template_folder hidden field when a template is selected
    document.getElementById('template_name')?.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        document.getElementById('template_folder').value = selected.dataset.folder || '';
    });
</script>
@endsection
