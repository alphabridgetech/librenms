@extends('layouts.librenmsv1')

@section('title', __('ZTP — Edit Device'))

@section('content')
<div class="container-fluid">
    <x-panel>
        <x-slot name="title">
            <i class="fa fa-pencil fa-fw fa-lg"></i> {{ __('Edit ZTP Device') }}: <strong>{{ $ztp->device_name }}</strong>
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

        <form method="POST" action="{{ route('ztp.update', $ztp->id) }}" class="form-horizontal">
            @csrf
            @method('PUT')

            {{-- MAC Address --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('MAC Address') }} <span class="text-danger">*</span></label>
                <div class="col-sm-6">
                    <input type="text" name="mac_address" class="form-control" placeholder="aa:bb:cc:dd:ee:ff"
                        value="{{ old('mac_address', $ztp->mac_address) }}" required>
                </div>
            </div>

            {{-- Device Name / Hostname --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Hostname') }} <span class="text-danger">*</span></label>
                <div class="col-sm-6">
                    <input type="text" name="device_name" class="form-control"
                        value="{{ old('device_name', $ztp->device_name) }}" required>
                </div>
            </div>

            {{-- IP Address --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('IP Address') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="ip_address" class="form-control"
                        value="{{ old('ip_address', $ztp->ip_address) }}">
                </div>
            </div>

            {{-- Subnet Mask --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Subnet Mask') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="subnet_mask" class="form-control"
                        value="{{ old('subnet_mask', $ztp->subnet_mask) }}">
                </div>
            </div>

            {{-- Gateway --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Default Gateway') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="gateway" class="form-control"
                        value="{{ old('gateway', $ztp->gateway) }}">
                </div>
            </div>

            {{-- SNMP Community --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('SNMP Community') }}</label>
                <div class="col-sm-4">
                    <input type="text" name="snmp_community" class="form-control"
                        value="{{ old('snmp_community', $ztp->snmp_community ?? 'public') }}">
                    <span class="help-block">{{ __('Used by LibreNMS to auto-discover this device after provisioning.') }}</span>
                </div>
            </div>

            {{-- Status --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Status') }}</label>
                <div class="col-sm-4">
                    <select name="status" class="form-control">
                        <option value="pending"     {{ $ztp->status === 'pending'     ? 'selected' : '' }}>Pending</option>
                        <option value="provisioned" {{ $ztp->status === 'provisioned' ? 'selected' : '' }}>Provisioned</option>
                        <option value="failed"      {{ $ztp->status === 'failed'      ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
            </div>

            <hr>
            <h5><i class="fa fa-file-text-o"></i> {{ __('Configuration Source') }}</h5>

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
                                {{ old('template_name', $ztp->template_name) === $tpl['name'] ? 'selected' : '' }}>
                                {{ $tpl['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="template_folder" id="template_folder"
                        value="{{ old('template_folder', $ztp->template_folder) }}">
                </div>
            </div>
            @endif

            {{-- Custom Commands --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Custom CLI Commands') }}</label>
                <div class="col-sm-6">
                    <textarea name="template_commands" class="form-control" rows="8">{{ old('template_commands', $ztp->template_commands) }}</textarea>
                </div>
            </div>

            {{-- Notes --}}
            <div class="form-group">
                <label class="col-sm-3 control-label">{{ __('Notes') }}</label>
                <div class="col-sm-6">
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $ztp->notes) }}</textarea>
                </div>
            </div>

            <hr>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> {{ __('Save Changes') }}
                    </button>
                    <a href="{{ route('ztp.index') }}" class="btn btn-default">{{ __('Cancel') }}</a>
                </div>
            </div>

        </form>
    </x-panel>
</div>

<script>
    document.getElementById('template_name')?.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        document.getElementById('template_folder').value = selected.dataset.folder || '';
    });
</script>
@endsection
