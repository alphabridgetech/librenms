<x-panel class="tw:p-0 tw:w-min tw:min-w-full">
    <x-slot name="heading" class="tw:p-0">
    <div class="tw:opacity-90 tw:p-3 tw:mb-0 tw:border-b-2 tw:border-solid tw:border-gray-200 tw:dark:border-dark-gray-200 tw:rounded-t-lg">
        <span class="tw:text-nowrap tw:pr-1">
            <a href="{{ $href }}" class="tw:text-xl tw:font-bold">{{ $device->displayName() }}</a>
            @if($device->hardware)
                <span class="tw:ml-2">{{ $device->hardware }}</span>
            @endif
        </span>
        <span class="tw:text-nowrap tw:pl-2 tw:pr-1">
            @if($osText)
                <span>{{ $osText }}</span>
            @endif
            @if($device->version)
                <span class="tw:ml-1">{{ $device->version }}</span>
            @endif
        </span>
        <span class="tw:text-nowrap tw:pl-2">
            @if($device->features)
                <span>({{ $device->features }})</span>
            @endif
            @if($device->location)
                <span class="tw:ml-1">[{{ $device->location }}]</span>
            @endif
        </span>
    </div>
    </x-slot>
    {{-- Telequill: port status grid (network map popups only) --}}
    @if(! empty($showPorts) && $device->ports->count())
        <div class="tw:px-3 tw:pt-2">
            <div class="tw:border-b tw:font-semibold mb-2">{{ __('Ports') }}</div>
            <div class="tw:flex tw:flex-wrap" style="width: 600px; margin-top: 7px;">
                @foreach($device->ports as $port)
                    @php
                        $shortName = preg_replace(['/GigabitEthernet/', '/FastEthernet/', '/TenGigabitEthernet/', '/Ethernet/'], ['g', 'f', 'tg', 'e'], (string) $port->ifName);
                    @endphp
                    <div class="tw:flex tw:flex-col tw:items-center tw:mx-1 tw:my-1">
                        <div style="font-size:9px; line-height:1; margin-bottom:2px;">{{ $shortName }}</div>
                        <div class="border rounded"
                             style="width:40px;height:40px;font-size:7px;line-height:1;padding:2px;text-transform:lowercase;overflow:hidden;margin:5px;
                                    @if($port->ifOperStatus == 'up') background-color:#91D250;color:white; @else background-color:#A5A5A5;color:white; @endif"
                             title="{{ $port->ifDescr }}">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    <div>
        @forelse($graphs as $graph)
            <x-graph-row :device="$device->device_id" :type="$graph['type']" :title="$graph['title']" :graphs="$graph['graphs']" :absolute-size="1" />
        @empty
            <div class="tw:text-center tw:py-4 tw:text-gray-500">
                {{ __('No graphs available for this device') }}
            </div>
        @endforelse
    </div>
</x-panel>
