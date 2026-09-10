@extends('layouts.librenmsv1')

@section('content')
    <x-device.page :device="$device" :dropdown-links="$data['dropdownLinks'] ?? []">
    @isset($data['submenu'])
        @php
            $cfgSelected = $vars;
            $cfgIsSelected = fn ($url) => $url === $cfgSelected;
        @endphp

        <style>
            .cfgsidebar-wrap {
                display: flex;
                align-items: flex-start;
                gap: 20px;
                margin-bottom: 20px;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .cfgsidebar-wrap * {
                box-sizing: border-box;
            }

            .cfgsidebar {
                flex: 0 0 230px;
                width: 230px;
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.08);
                overflow: hidden;
                align-self: flex-start;
            }

            .cfgsidebar-title {
                padding: 16px 18px;
                font-size: 15px;
                font-weight: 700;
                color: #2d3748;
                border-bottom: 1px solid #eef0f3;
            }

            .cfgsidebar-group {
                border-bottom: 1px solid #eef0f3;
            }

            .cfgsidebar-group:last-child {
                border-bottom: none;
            }

            .cfgsidebar-group-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 18px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .05em;
                text-transform: uppercase;
                color: #8892a0;
                cursor: pointer;
                user-select: none;
            }

            .cfgsidebar-group-header i {
                font-size: 11px;
                transition: transform .15s ease;
            }

            .cfgsidebar-group-header.collapsed i {
                transform: rotate(-90deg);
            }

            .cfgsidebar-items {
                padding-bottom: 4px;
            }

            .cfgsidebar-items.collapsed {
                display: none;
            }

            .cfgsidebar-item {
                display: block;
                padding: 9px 18px 9px 22px;
                font-size: 13.5px;
                color: #4a5568;
                border-left: 3px solid transparent;
                text-decoration: none;
            }

            .cfgsidebar-item:hover {
                background: #f7f9fc;
                color: #2d3748;
                text-decoration: none;
            }

            .cfgsidebar-item.active {
                background: #eef4ff;
                border-left-color: #4e73df;
                color: #4e73df;
                font-weight: 600;
            }

            .cfgsidebar-content {
                flex: 1 1 0;
                min-width: 0;
                max-width: 100%;
                overflow-x: auto;
            }

            .cfgsidebar-mobile-toggle {
                display: none;
                width: 100%;
                align-items: center;
                gap: 8px;
                text-align: left;
                padding: 12px 16px;
                background: #fff;
                border: none;
                border-radius: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.08);
                font-size: 14px;
                font-weight: 700;
                color: #2d3748;
                margin-bottom: 14px;
                cursor: pointer;
            }

            @media (max-width: 991px) {
                .cfgsidebar-wrap {
                    flex-direction: column;
                    gap: 0;
                }

                .cfgsidebar-mobile-toggle {
                    display: flex;
                }

                .cfgsidebar {
                    width: 100%;
                    flex: 1 1 auto;
                    display: none;
                    margin-bottom: 14px;
                }

                .cfgsidebar.mobile-open {
                    display: block;
                }

                .cfgsidebar-content {
                    width: 100%;
                }
            }
        </style>

        <div class="cfgsidebar-wrap">
            <button type="button" class="cfgsidebar-mobile-toggle" onclick="
                document.querySelector('.cfgsidebar').classList.toggle('mobile-open');
                this.classList.toggle('active');
            ">
                <i class="fa fa-bars" aria-hidden="true"></i> {{ $title }} Menu
            </button>

            <div class="cfgsidebar">
                <div class="cfgsidebar-title">{{ $title }}</div>

                @foreach ($data['submenu'] as $header => $items)
                    @if(is_int($header))
                        <div class="cfgsidebar-group">
                            <div class="cfgsidebar-items">
                                @foreach ($items as $sm)
                                    <a class="cfgsidebar-item @if($cfgIsSelected($sm['url'])) active @endif" href="{{ route('device', ['device' => $device_id, 'tab' => $current_tab, 'vars' => $sm['url']]) }}">{{ $sm['name'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @php
                            $groupHasActive = collect($items)->contains(fn ($sm) => $cfgIsSelected($sm['url']));
                            $groupId = 'cfgsidebar-group-' . \Illuminate\Support\Str::slug($header);
                        @endphp
                        <div class="cfgsidebar-group">
                            <div class="cfgsidebar-group-header" onclick="
                                this.classList.toggle('collapsed');
                                document.getElementById('{{ $groupId }}').classList.toggle('collapsed');
                            ">
                                <span>{{ $header }}</span>
                                <i class="fa fa-chevron-down" aria-hidden="true"></i>
                            </div>
                            <div class="cfgsidebar-items" id="{{ $groupId }}">
                                @foreach ($items as $sm)
                                    <a class="cfgsidebar-item @if($cfgIsSelected($sm['url'])) active @endif" href="{{ route('device', ['device' => $device_id, 'tab' => $current_tab, 'vars' => $sm['url']]) }}">{{ $sm['name'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="cfgsidebar-content">
                @includeFirst(['device.tabs.config.' . $data['tab'], 'device.tabs.config.home'])
            </div>
        </div>
    @else
        @includeFirst(['device.tabs.config.' . $data['tab'], 'device.tabs.config.home'])
    @endif

    </x-device.page>
@endsection
