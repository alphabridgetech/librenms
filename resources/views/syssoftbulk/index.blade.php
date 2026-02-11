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
        <form method="GET" action="{{ route('syssoftbulk.index') }}" class="mb-4 card p-3">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label class="control-label">{{ __('Select Model(s)') }}</label>
                        <select name="model_names[]" id="model_names" class="form-control" multiple="multiple" size="5">
                            @foreach ($deviceFilter as $hardware)
                                <option value="{{ $hardware }}" 
                                    {{ in_array($hardware, $selectedModels) ? 'selected' : '' }}>
                                    {{ $hardware }}
                                </option>
                            @endforeach
                        </select>
                        <p class="help-block">
                            <i class="fa fa-info-circle"></i> 
                            {{ __('Hold Ctrl (Windows) or Cmd (Mac) to select multiple models') }}
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group" style="margin-top: 25px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-filter"></i> {{ __('Apply Filter') }}
                        </button>
                        <a href="{{ route('syssoftbulk.index') }}" class="btn btn-default">
                            {{ __('Clear All') }}
                        </a>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="selectAllModels"> {{ __('Select All Models') }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if(count($selectedModels) > 0)
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-file-o"></i> {{ __('Upload Software Files for Selected Models') }}
                    <span class="badge">{{ count($selectedModels) }}</span>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    @foreach($selectedModels as $model)
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sysfile_{{ $loop->index }}">
                                <i class="fa fa-microchip"></i> {{ $model }}
                            </label>
                            <input type="file" 
                                   name="sysfiles[{{ $model }}]" 
                                   id="sysfile_{{ $loop->index }}" 
                                   class="form-control model-file"
                                   data-model="{{ $model }}"
                                   accept=".bin">
                            <p class="help-block">
                                <span class="file-status" id="status_{{ $loop->index }}">
                                    <i class="fa fa-times text-danger"></i> {{ __('No file selected') }}
                                </span>
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        @endcan

        @if(count($selectedModels) > 0)
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-server"></i> {{ __('Devices for Selected Models') }}
                    <span class="badge">{{ $devices->count() }}</span>
                </h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table id="customsysupload" class="table table-bordered table-condensed table-striped">
                        <thead>
                            <tr>
                                <th data-column-id="device_id"
                                    data-identifier="true"
                                    data-type="numeric"
                                    data-visible="false">
                                    ID
                                </th>
                                <th data-column-id="select"
                                    data-formatter="select"
                                    data-sortable="false"
                                    data-width="50px">
                                    {{ __('Select') }}
                                </th>
                                <th data-column-id="hostname">{{ __('Hostname') }}</th>
                                <th data-column-id="hardware">{{ __('Hardware') }}</th>
                                <th data-column-id="sysObjectID">{{ __('sysObjectID') }}</th>
                                <th data-column-id="model_file"
                                    data-formatter="modelFile"
                                    data-sortable="false"
                                    data-width="150px">
                                    {{ __('File Status') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($devices as $device)
                            <tr data-hardware="{{ $device->hardware }}">
                                <td>{{ $device->device_id }}</td>
                                <td></td>
                                <td>{{ $device->hostname }}</td>
                                <td>
                                    <span class="label label-info">{{ $device->hardware }}</span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $device->sysObjectID }}</small>
                                </td>
                                <td></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="btn-group">
                            <button type="button" id="selectAllBtn" class="btn btn-default">
                                <i class="fa fa-check-square-o"></i> {{ __('Select All') }}
                            </button>
                            <button type="button" id="deselectAllBtn" class="btn btn-default">
                                <i class="fa fa-square-o"></i> {{ __('Deselect All') }}
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" id="selectByModelBtn" class="btn btn-default">
                                <i class="fa fa-filter"></i> {{ __('Select by Model') }}
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-right">
                        <button type="button" id="uploadBtn" class="btn btn-success btn-lg" style="min-width: 200px;">
                            <i class="fa fa-upload"></i> {{ __('Upload Selected') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> 
            {{ __('Please select one or more models from the filter above to view devices and upload files.') }}
        </div>
        @endif
    </x-panel>
</div>
@endsection

@section('javascript')
<script>
$(function () {
    // Select all models checkbox
    $('#selectAllModels').on('change', function() {
        if ($(this).is(':checked')) {
            $('#model_names option').prop('selected', true);
        } else {
            $('#model_names option').prop('selected', false);
        }
    });

    let grid = $("#customsysupload").bootgrid({
        rowCount: -1,
        selection: true,
        multiSelect: true,
        keepSelection: true,
        formatters: {
            "select": function () {
                return '<input type="checkbox" class="bootgrid-checkbox">';
            },
            "modelFile": function (column, row) {
                var hardware = $(row).attr('data-hardware');
                var fileInput = $('.model-file[data-model="' + hardware + '"]');
                var fileName = fileInput.val() ? fileInput.val().split('\\').pop() : '';
                
                if (fileInput.val()) {
                    return '<span class="text-success"><i class="fa fa-check-circle"></i> ' + fileName + '</span>';
                } else {
                    return '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> ' + 
                           '{{ __("No file") }}</span>';
                }
            }
        }
    });

    // Update file status when file is selected
    $('.model-file').on('change', function() {
        var model = $(this).data('model');
        var fileName = $(this).val() ? $(this).val().split('\\').pop() : '';
        
        // Update inline status
        if (fileName) {
            $(this).next('.help-block').find('.file-status')
                .removeClass('text-danger')
                .addClass('text-success')
                .html('<i class="fa fa-check-circle"></i> ' + fileName);
        } else {
            $(this).next('.help-block').find('.file-status')
                .removeClass('text-success')
                .addClass('text-danger')
                .html('<i class="fa fa-times-circle"></i> {{ __("No file selected") }}');
        }
        
        // Update table status
        grid.bootgrid('reload');
    });

    // Select all button
    $('#selectAllBtn').on('click', function() {
        grid.bootgrid('selectAll');
    });

    // Deselect all button
    $('#deselectAllBtn').on('click', function() {
        grid.bootgrid('deselectAll');
    });

    // Select by model button (shows modal to select specific models)
    $('#selectByModelBtn').on('click', function() {
        var selectedModels = [];
        $('tr[data-hardware]').each(function() {
            var hardware = $(this).data('hardware');
            if ($.inArray(hardware, selectedModels) === -1) {
                selectedModels.push(hardware);
            }
        });
        
        if (selectedModels.length === 0) {
            alert('{{ __("No models found in the table") }}');
            return;
        }
        
        var modelList = selectedModels.map(function(model) {
            return '<div class="checkbox"><label><input type="checkbox" class="model-select-checkbox" value="' + 
                   model + '"> ' + model + '</label></div>';
        }).join('');
        
        // Create simple modal
        var modalHtml = '<div class="modal fade" id="modelSelectModal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
            '<h4 class="modal-title">{{ __("Select Devices by Model") }}</h4>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p>{{ __("Select models to select all devices of that model:") }}</p>' +
            '<div id="modelCheckboxes">' + modelList + '</div>' +
            '<div class="checkbox" style="margin-top: 10px;">' +
            '<label><input type="checkbox" id="selectAllModelsCheck"> {{ __("Select All Models") }}</label>' +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-default" data-dismiss="modal">{{ __("Cancel") }}</button>' +
            '<button type="button" class="btn btn-primary" id="applyModelSelection">{{ __("Apply Selection") }}</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        // Remove existing modal if any
        $('#modelSelectModal').remove();
        $('body').append(modalHtml);
        
        $('#modelSelectModal').modal('show');
        
        // Select all models in modal
        $('#selectAllModelsCheck').on('change', function() {
            $('.model-select-checkbox').prop('checked', $(this).is(':checked'));
        });
        
        // Apply selection
        $('#applyModelSelection').on('click', function() {
            var selectedModels = [];
            $('.model-select-checkbox:checked').each(function() {
                selectedModels.push($(this).val());
            });
            
            if (selectedModels.length === 0) {
                alert('{{ __("Please select at least one model") }}');
                return;
            }
            
            // Select rows with matching hardware
            grid.bootgrid('deselectAll');
            $('tr[data-hardware]').each(function() {
                if ($.inArray($(this).data('hardware'), selectedModels) !== -1) {
                    var rowId = $(this).find('td:first').text();
                    grid.bootgrid('select', [rowId]);
                }
            });
            
            $('#modelSelectModal').modal('hide');
        });
    });

    // Upload logic
    $('#uploadBtn').on('click', function () {
        let deviceIds = grid.bootgrid("getSelectedRows");
        
        if (!deviceIds.length) {
            alert('{{ __('Please select at least one device') }}');
            return;
        }

        // Check if all selected devices have corresponding files
        let missingFiles = [];
        let fileData = {};
        let allFilesValid = true;
        
        // Get unique hardware from selected devices
        let selectedDevices = $('tr.selected');
        let hardwareSet = new Set();
        
        selectedDevices.each(function() {
            hardwareSet.add($(this).data('hardware'));
        });
        
        // Check files for each hardware
        hardwareSet.forEach(function(hardware) {
            var fileInput = $('.model-file[data-model="' + hardware + '"]');
            var file = fileInput[0].files[0];
            
            if (!file) {
                missingFiles.push(hardware);
                allFilesValid = false;
            } else {
                // Validate file extension
                var fileName = file.name;
                var fileExt = fileName.substring(fileName.lastIndexOf('.') + 1).toLowerCase();
                
                if (fileExt !== 'bin') {
                    missingFiles.push(hardware + ' (Invalid file type: .' + fileExt + ')');
                    allFilesValid = false;
                } else {
                    fileData[hardware] = file;
                }
            }
        });
        
        if (!allFilesValid) {
            var errorMsg = '{{ __("Missing or invalid files for models:") }}\n\n';
            errorMsg += missingFiles.join('\n');
            errorMsg += '\n\n{{ __("Please select valid .bin files for all selected models.") }}';
            alert(errorMsg);
            return;
        }

        // Create FormData and append files
        let formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        
        // Append each file with its model name
        Object.keys(fileData).forEach(function(hardware) {
            formData.append('sysfiles[' + hardware + ']', fileData[hardware]);
        });
        
        // Append device IDs
        deviceIds.forEach(id => formData.append('device_ids[]', id));

        // Show loading
        var $uploadBtn = $(this);
        var originalText = $uploadBtn.html();
        $uploadBtn.prop('disabled', true)
               .html('<i class="fa fa-spinner fa-spin"></i> {{ __("Uploading...") }}');

        $.ajax({
            url: "{{ route('syssoftbulk.store') }}",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                alert('{{ __("Upload successful!") }}');
                // Optionally refresh the page
                // location.reload();
                
                // Or just reset the form
                $('.model-file').val('');
                $('.file-status').removeClass('text-success').addClass('text-danger')
                    .html('<i class="fa fa-times-circle"></i> {{ __("No file selected") }}');
                grid.bootgrid('reload');
            },
            error: function (xhr) {
                var errorMsg = xhr.responseJSON && xhr.responseJSON.message 
                             ? xhr.responseJSON.message 
                             : '{{ __("Unknown error occurred. Please try again.") }}';
                alert('{{ __("Upload failed:") }}\n' + errorMsg);
            },
            complete: function() {
                $uploadBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<style>
#model_names {
    height: auto;
    min-height: 120px;
}
.panel-title .badge {
    margin-left: 10px;
    background-color: #337ab7;
}
.label-info {
    background-color: #5bc0de;
}
.table th {
    background-color: #f5f5f5;
    font-weight: bold;
}
.btn-lg {
    padding: 10px 20px;
    font-size: 16px;
}
.modal-body {
    max-height: 400px;
    overflow-y: auto;
}
.file-status {
    font-weight: bold;
}
</style>
@endsection