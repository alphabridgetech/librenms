@extends('layouts.librenmsv1')

@section('content')
    <x-device.page :device="$device" :dropdown-links="$data['dropdownLinks'] ?? []">
    @isset($data['submenu'])
        <x-submenu :title="$title" :menu="$data['submenu']" :device-id="$device_id" :current-tab="$current_tab" :selected="$vars" />
    @endisset

    @includeFirst(['device.tabs.config.' . $data['tab'], 'device.tabs.config.home'])

    </x-device.page>
@endsection
