@extends('layouts.librenmsv1')

@section('title', __('SFP Inventory'))

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="tw:flex tw:items-center tw:justify-between tw:mb-4">
        <div>
            <h2 class="tw:text-2xl tw:font-bold tw:text-gray-800 tw:m-0">
                <i class="fa fa-cube tw:text-blue-600 tw:mr-2"></i>@lang('SFP & Optical Transceiver Inventory')
            </h2>
            <p class="tw:text-gray-500 tw:text-sm tw:mt-1 tw:mb-0">
                @lang('Comprehensive overview of all optical SFP, SFP+, and transceiver modules across network devices.')
            </p>
        </div>
        <div class="tw:flex tw:gap-2">
            <a href="{{ route('table.sfp-inventory.export') }}" class="btn btn-primary btn-sm">
                <i class="fa fa-download tw:mr-1"></i> @lang('Export CSV')
            </a>
            <button type="button" id="refresh_sfp_table" class="btn btn-default btn-sm">
                <i class="fa fa-refresh tw:mr-1"></i> @lang('Refresh')
            </button>
        </div>
    </div>

    <!-- Stat KPI Cards -->
    <div class="row tw:mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-default tw:shadow-sm tw:border-l-4 tw:border-l-blue-500">
                <div class="panel-body tw:p-4">
                    <div class="tw:flex tw:items-center">
                        <div class="tw:p-3 tw:rounded-lg tw:bg-blue-50 tw:text-blue-600 tw:mr-4">
                            <i class="fa fa-cube fa-2x"></i>
                        </div>
                        <div>
                            <div class="tw:text-xs tw:font-semibold tw:uppercase tw:tracking-wider tw:text-gray-500">@lang('Total SFPs / Optics')</div>
                            <div class="tw:text-2xl tw:font-bold tw:text-gray-800">{{ number_format($stats['total_sfps']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="panel panel-default tw:shadow-sm tw:border-l-4 tw:border-l-purple-500">
                <div class="panel-body tw:p-4">
                    <div class="tw:flex tw:items-center">
                        <div class="tw:p-3 tw:rounded-lg tw:bg-purple-50 tw:text-purple-600 tw:mr-4">
                            <i class="fa fa-bolt fa-2x"></i>
                        </div>
                        <div>
                            <div class="tw:text-xs tw:font-semibold tw:uppercase tw:tracking-wider tw:text-gray-500">@lang('10G SFP+ Modules')</div>
                            <div class="tw:text-2xl tw:font-bold tw:text-gray-800">{{ number_format($stats['sfp_plus_count']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="panel panel-default tw:shadow-sm tw:border-l-4 tw:border-l-green-500">
                <div class="panel-body tw:p-4">
                    <div class="tw:flex tw:items-center">
                        <div class="tw:p-3 tw:rounded-lg tw:bg-green-50 tw:text-green-600 tw:mr-4">
                            <i class="fa fa-building fa-2x"></i>
                        </div>
                        <div>
                            <div class="tw:text-xs tw:font-semibold tw:uppercase tw:tracking-wider tw:text-gray-500">@lang('Vendors')</div>
                            <div class="tw:text-2xl tw:font-bold tw:text-gray-800">{{ number_format($stats['vendors_count']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="panel panel-default tw:shadow-sm tw:border-l-4 tw:border-l-yellow-500">
                <div class="panel-body tw:p-4">
                    <div class="tw:flex tw:items-center">
                        <div class="tw:p-3 tw:rounded-lg tw:bg-yellow-50 tw:text-yellow-600 tw:mr-4">
                            <i class="fa fa-server fa-2x"></i>
                        </div>
                        <div>
                            <div class="tw:text-xs tw:font-semibold tw:uppercase tw:tracking-wider tw:text-gray-500">@lang('Devices with SFP')</div>
                            <div class="tw:text-2xl tw:font-bold tw:text-gray-800">{{ number_format($stats['devices_count']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <x-panel body-class="tw:p-0!">
        <x-slot name="heading">
            <h3 class="panel-title"><i class="fa fa-list tw:mr-2"></i>@lang('Transceiver Inventory List')</h3>
        </x-slot>

        <!-- Filters & Table -->
        <div class="tw:p-4 tw:bg-gray-50 tw:border-b tw:border-gray-200">
            <form id="sfp_filter_form" class="form-inline tw:flex tw:flex-wrap tw:gap-3 tw:items-center" role="form">
                @csrf
                <div class="form-group">
                    <label for="device" class="tw:mr-2 tw:text-sm tw:font-medium">@lang('Device'):</label>
                    <select name="device" id="device" class="form-control input-sm select2" style="min-width: 200px;">
                        <option value="">@lang('All Devices')</option>
                        @foreach($devices as $dev)
                            <option value="{{ $dev->device_id }}" {{ $filter['device'] == $dev->device_id ? 'selected' : '' }}>
                                {{ $dev->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(!empty($vendors))
                <div class="form-group">
                    <label for="vendor" class="tw:mr-2 tw:text-sm tw:font-medium">@lang('Vendor'):</label>
                    <select name="vendor" id="vendor" class="form-control input-sm">
                        <option value="">@lang('All Vendors')</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v }}" {{ $filter['vendor'] == $v ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                @if(!empty($types))
                <div class="form-group">
                    <label for="type" class="tw:mr-2 tw:text-sm tw:font-medium">@lang('Type'):</label>
                    <select name="type" id="type" class="form-control input-sm">
                        <option value="">@lang('All Types')</option>
                        @foreach($types as $t)
                            <option value="{{ $t }}" {{ $filter['type'] == $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="form-group">
                    <input type="text" name="model" id="model" value="{{ $filter['model'] }}" placeholder="@lang('Model')" class="form-control input-sm" />
                </div>

                <div class="form-group">
                    <input type="text" name="serial" id="serial" value="{{ $filter['serial'] }}" placeholder="@lang('Serial Number')" class="form-control input-sm" />
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa fa-search"></i> @lang('Search')
                </button>
                <button type="button" id="reset_sfp_filters" class="btn btn-default btn-sm">
                    <i class="fa fa-undo"></i> @lang('Reset')
                </button>
            </form>
        </div>

        <table id="sfp_inventory_grid" class="table table-hover table-condensed table-striped"
            data-url="{{ route('table.sfp-inventory') }}">
            <thead>
                <tr>
                    <th data-column-id="device">@lang('Device')</th>
                    <th data-column-id="port">@lang('Interface / Port')</th>
                    <th data-column-id="vendor" data-order="asc">@lang('Vendor')</th>
                    <th data-column-id="type">@lang('Type')</th>
                    <th data-column-id="model">@lang('Model')</th>
                    <th data-column-id="serial">@lang('Serial Number')</th>
                    <th data-column-id="wavelength">@lang('Wavelength')</th>
                    <th data-column-id="distance">@lang('Distance')</th>
                    <th data-column-id="connector">@lang('Connector')</th>
                    <th data-column-id="ddm">@lang('DDM')</th>
                </tr>
            </thead>
        </table>
    </x-panel>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var grid = $("#sfp_inventory_grid").bootgrid({
            ajax: true,
            post: function () {
                return {
                    device: $('#device').val(),
                    vendor: $('#vendor').val(),
                    type: $('#type').val(),
                    model: $('#model').val(),
                    serial: $('#serial').val(),
                    _token: '{{ csrf_token() }}'
                };
            },
            rowCount: [25, 50, 100, -1],
            templates: {
                header: "<div id=\"@{{ctx.id}}\" class=\"@{{css.header}} tw:flex tw:items-center tw:justify-between tw:p-3\">" +
                    "<div class=\"actionBar tw:ml-auto\"><div class=\"@{{css.actions}}\"></div></div>" +
                    "</div>"
            }
        });

        $('#sfp_filter_form').on('submit', function(e) {
            e.preventDefault();
            grid.bootgrid("reload");
        });

        $('#reset_sfp_filters').on('click', function() {
            $('#sfp_filter_form')[0].reset();
            if ($('#device').hasClass("select2-hidden-accessible")) {
                $('#device').val('').trigger('change');
            }
            grid.bootgrid("reload");
        });

        $('#refresh_sfp_table').on('click', function() {
            grid.bootgrid("reload");
        });
    });
</script>
@endpush
