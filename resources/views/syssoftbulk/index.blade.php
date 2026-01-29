@extends('layouts.librenmsv1')

@section('title', __('System Software Bulk Upload'))

@section('content')
    <div class="container-fluid">
        <x-panel>
            <x-slot name="title">
                <i class="fa fa-upload fa-fw fa-lg"></i> {{ __('System Software Bulk Upload') }}
            </x-slot>

            {{-- status / errors --}}
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Upload Form --}}
            @can('create', \App\Models\CustomMib::class)
                <form action="{{ route('syssoftbulk.index') }}" method="GET" enctype="multipart/form-data" class="mb-4 card p-3">
                    @csrf
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Link to device (optional)') }}</label>
                            <select name="model_name" class="form-control" onchange="this.form.submit()">
                                <option value="">{{ __('-- None --') }}</option>
                                @foreach ($deviceFilter as $hardware)
                                    <option value="{{ $hardware }}"
                                        {{ ($modelName ?? '') === $hardware ? 'selected' : '' }}>
                                        {{ $hardware }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="sysfile" class="form-label">{{ __('Select Software file') }}</label>
                            <input type="file" name="sysfile" id="sysfile" class="form-control mt-3" required>
                            <div class="form-text">Accepted: .bin</div>
                        </div>
                    </div>
                </form>

            @endcan



            {{-- Table --}}
            <div class="table-responsive">
                <table id="customsysupload" class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                          <th data-column-id="select" data-formatter="checkbox" data-sortable="false" style="width:30px; text-align:center;"></th>
                            <th data-column-id="filename" data-formatter="text">{{ __('Device Names') }}</th>
                            <th data-column-id="model_name" data-formatter="text">{{ __('hostname') }}</th>
                            <th data-column-id="uploader" data-formatter="text">{{ __('hardware') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="device_ids[]" value="{{ $device->device_id }}">
                                </td>
                                </td>
                                <td>{{ $device->device_id }}</td>
                                <td>{{ $device->hostname }}</td>
                                <td>{{ $device->hardware }}</td>
                                <td>{{ $device->sysObjectID }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{-- End Table --}}
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                </div>

                <div class="col-md-6">
                    <label class="form-label d-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="fa fa-upload"></i> {{ __('Upload') }}
                    </button>
                </div>

            </div>
        </x-panel>
    </div>
@endsection




@section('javascript')
    <script type="application/javascript">
    $(document).ready(function () {

    //device_ids getting value on button click
    $('button[type="submit"]').on('click', function(e) {
       
    });


    // Initialize Bootgrid
    var grid = $("#customsysupload");

    grid.bootgrid({
        formatters: {
            checkbox: function(column, row) {
                // Each row checkbox
                return '<input type="checkbox" class="rowCheckbox" name="device_ids[]" value="' + row.device_id + '">';
            },
            text: function(column, row) {
                return row[column.id] || '';
            }
        },
        rowCount: -1 // show all rows
    });

    grid.css('display','table');

    // Add "Select All" checkbox manually **above the table**
    if ($('#selectAllWrapper').length === 0) {
        $('<div id="selectAllWrapper" class="checkbox" style="margin-bottom:10px;">' +
            '<label><input type="checkbox" id="checkAll"> Select All</label>' +
          '</div>').insertBefore('#custommibs'); // prepend before table
    }

    // "Select All" functionality
    $(document).on('change', '#checkAll', function () {
        $('.rowCheckbox').prop('checked', $(this).prop('checked'));
    });

    // Automatically uncheck Select All if any row is unchecked
    $(document).on('change', '.rowCheckbox', function () {
        if ($('.rowCheckbox:checked').length === $('.rowCheckbox').length) {
            $('#checkAll').prop('checked', true);
        } else {
            $('#checkAll').prop('checked', false);
        }
    });

});

</script>
@endsection

@section('css')
    <style>
        #customsysupload form {
            display: inline;
        }
    </style>
@endsection
@section('javascript')
<script>
$(function () {

    // Bootgrid init
    $("#customsysupload").bootgrid({
        formatters: {
            checkbox: function (column, row) {
                return '<input type="checkbox" class="rowCheckbox" value="'+ row.device_id +'">';
            }
        },
        rowCount: -1
    });

    // Select All
    $('#checkAll').on('change', function () {
        $('.rowCheckbox').prop('checked', this.checked);
    });

    $(document).on('change', '.rowCheckbox', function () {
        $('#checkAll').prop(
            'checked',
            $('.rowCheckbox:checked').length === $('.rowCheckbox').length
        );
    });

    // Upload via AJAX
    $('#uploadBtn').on('click', function () {

        let deviceIds = [];
        $('.rowCheckbox:checked').each(function () {
            deviceIds.push($(this).val());
        });

        if (deviceIds.length === 0) {
            alert('Select at least one device');
            return;
        }

        let file = $('#sysfile')[0].files[0];
        if (!file) {
            alert('Please select a file');
            return;
        }

        let formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('sysfile', file);
        deviceIds.forEach(id => formData.append('device_ids[]', id));

        $.ajax({
            url: "{{ route('syssoftbulk.store') }}",
            method: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function (res) {
                alert('Upload successful');
            },
            error: function () {
                alert('Upload failed');
            }
        });
    });

});
</script>
@endsection
