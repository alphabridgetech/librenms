<div>
    <span class="tw:text-nowrap tw:pr-1">
        <span class="tw:text-xl tw:font-bold">{{ $device->displayName() }}</span>
        {{ $device->hardware }}
    </span>
    <span class="tw:text-nowrap tw:pl-2 tw:pr-1">
        @if($device->os){{ \App\Facades\LibrenmsConfig::getOsSetting($device->os, 'text') }}@endif
        {{ $device->version }}
    </span>
    <span class="tw:text-nowrap tw:pl-2">
        @if($device->feature)({{ $device->features }})@endif
        @if($device->location)[{{ $device->location }}]@endif
    </span>
 

   @if($device->ports && $device->ports->count())
   <div class="tw:border-b tw:font-semibold mb-2">
        Ports
    </div>
    <div class="tw:flex tw:flex-wrap" style="width: 600px;margin-top: 7px;">
        @foreach($device->ports as $port)
            @php
                // Shorten ifName
                $shortName = preg_replace([
                    '/GigabitEthernet/', 
                    '/FastEthernet/',
                    '/TenGigabitEthernet/',
                    '/Ethernet/'
                ], [
                    'g', 
                    'f',
                    'tg',
                    'e'
                ], $port->ifName);
            @endphp

            <div class="tw:flex tw:flex-col tw:items-center tw:mx-1 tw:my-1">
                <!-- Short Name Label -->
                <div style="font-size:9px; line-height:1; margin-bottom:2px;">
                    {{ $shortName }}
                </div>
                
                <!-- Box -->
                <div class="border rounded"
                     style="width:40px;height:40px;font-size:7px;line-height:1;padding:2px;text-transform:lowercase;overflow:hidden; margin:5px 5px 5px 5px;
                            @if($port->ifOperStatus == 'up') background-color:#91D250;color:white;
                            @else background-color:#A5A5A5;color:white; @endif"
                     title="{{ $port->ifDescr }}">
                </div>
            </div>
        @endforeach
    </div>
@endif








    @foreach($graphs as $graph)
        @isset($graph['text'], $graph['graph'])
            <x-graph-row loading="lazy" :device="$device" :type="$graph['graph']" :title="$graph['text']" :graphs="[['from' => '-1d'], ['from' => '-7d']]"></x-graph-row>
        @endisset
    @endforeach
</div>
