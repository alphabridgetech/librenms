@extends('layouts.librenmsv1')

@section('title', __('System Software Bulk Upload'))

@section('content')
<div class="container-fluid">
    <x-panel>
        <x-slot name="title">
            <i class="fa fa-upload fa-fw fa-lg"></i> {{ __('System Software Bulk Upload') }}
        </x-slot>

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

        @can('create', \App\Models\CustomMib::class)
        <form class="mb-4 card p-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label">{{ __('Link to device (optional)') }}</label>
                    <select name="model_name" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('-- None --') }}</option>
                        @foreach ($deviceFilter as $hardware)
                            <option value="{{ $hardware }}" {{ ($modelName ?? '') === $hardware ? 'selected' : '' }}>
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

        <div class="table-responsive">
            <table id="customsysupload" class="table table-bordered table-condensed">
                <thead>
                    <tr>
                        <th data-column-id="device_id"
                            data-identifier="true"
                            data-type="numeric"
                            data-visible="false">
                        </th>

                        <th data-column-id="select"
                            data-formatter="select"
                            data-sortable="false"
                            data-visible="false">
                        </th>

                        <th data-column-id="hostname">{{ __('Hostname') }}</th>
                        <th data-column-id="hardware">{{ __('Hardware') }}</th>
                        <th data-column-id="sysObjectID">{{ __('sysObjectID') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                    <tr>
                        <td>{{ $device->device_id }}</td>
                        <td></td>
                        <td>{{ $device->hostname }}</td>
                        <td>{{ $device->hardware }}</td>
                        <td>{{ $device->sysObjectID }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="row mt-3">
            <div class="col-md-6"></div>
            <div class="col-md-6">
                <button type="button" id="uploadBtn" class="btn btn-primary w-100">
                    <i class="fa fa-upload"></i> {{ __('Upload') }}
                </button>
            </div>
        </div>
    </x-panel>
</div>
@endsection
@section('javascript')
<script>
$(function () {

    let grid = $("#customsysupload").bootgrid({
        rowCount: -1,
        selection: true,
        multiSelect: true,
        keepSelection: true,

        formatters: {
            "select": function () {
                return '<input type="checkbox" class="bootgrid-checkbox">';
            }
        }
    });

    // Add Select All checkbox
    grid.on("loaded.rs.jquery.bootgrid", function () {

        $('th[data-column-id="select"] .text').html(
            '<input type="checkbox" id="checkAll">'
        );

        // Select all rows
        $('#checkAll').on('change', function () {
            if (this.checked) {
                grid.bootgrid('select');
            } else {
                grid.bootgrid('deselect');
            }
        });
    });

    // Keep header checkbox in sync
    grid.on("selected.rs.jquery.bootgrid deselected.rs.jquery.bootgrid", function () {

        let totalRows = $("#customsysupload tbody tr").length;
        let selectedRows = grid.bootgrid("getSelectedRows").length;

        $('#checkAll').prop('checked', totalRows === selectedRows);
    });

    // Upload logic
    $('#uploadBtn').on('click', function () {

        let deviceIds = grid.bootgrid("getSelectedRows");

        if (!deviceIds.length) {
            alert('Please select at least one device');
            return;
        }

        let file = $('#sysfile')[0].files[0];
        if (!file) {
            alert('Please select a software file');
            return;
        }

        let formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('sysfile', file);

        deviceIds.forEach(id => formData.append('device_ids[]', id));

        $.ajax({
            url: "{{ route('syssoftbulk.store') }}",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function () {
                alert('Upload successful!');
            },
            error: function (xhr) {
                alert('Upload failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    });
});
</script>
@endsection
