@php($nsCaps = \App\Services\DeviceCapabilities::for($device))
<div class="alert alert-info" style="margin-top:10px;">
    <i class="fa fa-info-circle"></i>
    <strong>{{ $data['unsupported_tab'] }}</strong> is not available on this switch
    ({{ $nsCaps['hardware'] ?: '-' }}@if($nsCaps['speed']), {{ $nsCaps['speed'] }}@endif).
    <div class="text-muted" style="margin-top:6px; font-size:12px;">
        Set in resources/definitions/config_module_support.yaml
    </div>
</div>
