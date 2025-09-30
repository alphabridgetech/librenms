@extends('layouts.librenmsv1')

@section('title', __('Custom MIBs Upload'))

@section('content')
<div class="container-fluid">
    <x-panel>
        <x-slot name="title">
            <i class="fa fa-upload fa-fw fa-lg"></i> {{ __('Custom MIBs Upload') }}
        </x-slot>

        {{-- status / errors --}}
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Upload Form --}}
        @can('create', \App\Models\CustomMib::class)
        <form action="{{ route('mibs.store') }}" method="POST" enctype="multipart/form-data" class="mb-4 card p-3">
    @csrf
    <div class="row g-3 align-items-center">
        <div class="col-md-3">
            <label class="form-label">{{ __('Link to device (optionalS)') }}</label>
            <select name="model_name" class="form-control">
                <option value="">{{ __('-- None --') }}</option>
                @foreach($devices as $dev)
                    @php
                        // Some DB columns might be sysObjectID or sysObjectId—handle both
                        $oid = $dev->sysObjectID ?? $dev->sysObjectID ?? '';
                        // Trim leading/trailing dots and explode
                        $parts = explode('.', trim($oid, '.'));
                        // Get the second-last piece as model number
                        $modelNum = count($parts) >= 2 ? $parts[count($parts) - 2] : '';
                    @endphp
                    <option value="{{ $modelNum }}">
                        {{ ($dev->sysName ?? '') . '(' . ($modelNum ?? '') . ')' }}

                    </option>
                @endforeach
            </select>

        </div>

        <div class="col-md-5">
            <label for="mibfile" class="form-label">{{ __('Select MIB file') }}</label>
            <input type="file" name="mibfile" id="mibfile" class="form-control" required>
            <div class="form-text">Accepted: .mib, .txt — max 5MB</div>
        </div>

        <div class="col-md-2">
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" value="1" name="overwrite" id="overwrite">
                <label class="form-check-label" for="overwrite">
                    {{ __('Overwrite if exists') }}
                </label>
            </div>
        </div>

        <div class="col-md-2">
            <label class="form-label d-block">&nbsp;</label>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fa fa-upload"></i> {{ __('Upload') }}
            </button>
        </div>
    </div>
</form>

        @endcan

        

        {{-- Table --}}
        <div class="table-responsive">
            <table id="custommibs" class="table table-bordered table-condensed">
                <thead>
                    <tr>
                        <th data-column-id="id" data-visible="false" data-identifier="true" data-type="numeric">ID</th>
                        <th data-column-id="filename" data-formatter="text">{{ __('Filename') }}</th>
                        <th data-column-id="model_name" data-formatter="text">{{ __('Model') }}</th>
                        <th data-column-id="uploader" data-formatter="text">{{ __('Uploaded By') }}</th>
                        <th data-column-id="created_at">{{ __('Uploaded At') }}</th>
                        <th data-column-id="actions" data-formatter="actions" data-sortable="false" data-searchable="false">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mibs as $mib)
                        <tr>
                            <td>{{ $mib->id }}</td>
                            <td>{{ $mib->filename }}</td>
                            <td>{{ $mib->model_name }}</td>
                            <td>{{ optional($mib->uploader)->username ?? '—' }}</td>
                            <td>{{ $mib->created_at->format('Y-m-d H:i') }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel>
</div>
@endsection

@section('javascript')
<script type="application/javascript">
    $(document).ready(function () {
        var grid = $("#custommibs");
        grid.bootgrid({
            formatters: {
                text: function (column, row) {
                    let div = document.createElement('div');
                    div.innerText = row[column.id] || '';
                    return div.innerHTML;
                },
                actions: function (column, row) {
                    let downloadUrl = "{{ route('mibs.download', ':id') }}".replace(':id', row['id']);
                    let downloadBtn = '<a href="'+ downloadUrl +'" class="btn btn-sm btn-success" title="{{ __('Download') }}"><i class="fa fa-download"></i></a> ';

                    @can('delete', \App\Models\CustomMib::class)
                        let deleteBtn = '<button type="button" class="btn btn-sm btn-danger" onclick="deleteMib('+ row['id'] +');" title="{{ __('Delete') }}"><i class="fa fa-trash"></i></button>';
                    @else
                        let deleteBtn = '';
                    @endcan

                    return downloadBtn + deleteBtn;
                }
            }
        });

        grid.css('display','table'); // show after bootgrid init
    });

    function deleteMib(id) {
        if (!confirm('{{ __("Are you sure you want to delete this MIB?") }}')) return;

        $.ajax({
            url: '{{ route('mibs.destroy', ':id') }}'.replace(':id', id),
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function (msg) {
                $("#custommibs").bootgrid("remove", [id]);
                toastr.success(msg);
            },
            error: function () {
                toastr.error('{{ __("The MIB could not be deleted") }}');
            }
        });
    }
</script>
@endsection

@section('css')
<style>
    #custommibs form { display:inline; }
</style>
@endsection
