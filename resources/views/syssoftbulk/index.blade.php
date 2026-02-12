@extends('layouts.librenmsv1')

@section('title', __('System Software Bulk Upload'))

@section('content')
    <div class="container-fluid">
        <x-panel>
            <x-slot name="title">
                <i class="fa fa-upload fa-fw fa-lg"></i> {{ __('System Software Bulk Upload') }}
            </x-slot>

            {{-- Status Messages --}}
            @if (session('status'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="fa fa-check-circle fa-fw"></i> {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="fa fa-exclamation-circle fa-fw"></i> {{ session('error') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="fa fa-exclamation-triangle fa-fw"></i> {{ session('warning') }}
                </div>
            @endif

            @if (session('info'))
                <div class="alert alert-info alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="fa fa-info-circle fa-fw"></i> {{ session('info') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="fa fa-exclamation-circle fa-fw"></i> <strong>{{ __('Validation Errors:') }}</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('system.bulk.upload.process') }}" method="POST" enctype="multipart/form-data" id="bulkUploadForm">
                @csrf
                
                {{-- Model Selection --}}
                <div class="form-group">
                    <label for="model_names" class="control-label">{{ __('Select Hardware Models') }}</label>
                    <select name="model_names[]" id="model_names" class="form-control" multiple size="6">
                        @foreach($deviceFilter as $model)
                            <option value="{{ $model }}" {{ in_array($model, $selectedModels) ? 'selected' : '' }}>
                                {{ $model }}
                            </option>
                        @endforeach
                    </select>
                    <span class="help-block">{{ __('Hold Ctrl to select multiple models') }}</span>
                </div>

                <div class="form-group">
                    <button type="button" id="loadUploadFields" class="btn btn-primary">
                        <i class="fa fa-refresh"></i> {{ __('Load Upload Fields') }}
                    </button>
                </div>

                {{-- Dynamic File Upload Fields --}}
                <div id="uploadFieldsContainer">
                    @if(!empty($selectedModels))
                        <div class="row">
                            @foreach($selectedModels as $index => $model)
                                <div class="col-md-6">
                                    <div class="panel panel-default">
                                        <div class="panel-heading">
                                            <strong>{{ $model }}</strong>
                                        </div>
                                        <div class="panel-body">
                                            <div class="form-group">
                                                <label for="file_{{ $index }}">{{ __('Upload File for') }} {{ $model }}</label>
                                                <input type="file" 
                                                       name="uploads[{{ $model }}]" 
                                                       id="file_{{ $index }}"
                                                       class="form-control file-upload-input"
                                                       accept=".bin,.img,.tar,.tar.gz,.zip,.txt">
                                                <span class="help-block">{{ __('Allowed formats: .bin, .img, .tar, .tar.gz, .zip, .txt (Max: 100MB)') }}</span>
                                            </div>
                                            
                                            @if(isset($uploadedFiles[$model]))
                                                <div class="text-success">
                                                    <i class="fa fa-check"></i> 
                                                    {{ __('Previously uploaded:') }} {{ $uploadedFiles[$model] }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Devices Table --}}
                @if(!empty($selectedModels))
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-6">
                                    <strong><i class="fa fa-table"></i> {{ __('Devices List') }}</strong>
                                </div>
                                <div class="col-xs-6 text-right">
                                    <div class="checkbox" style="margin: 0;">
                                        <label>
                                            <input type="checkbox" id="selectAll"> {{ __('Select All') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">{{ __('Select') }}</th>
                                            <th>{{ __('Device ID') }}</th>
                                            <th>{{ __('Hostname') }}</th>
                                            <th>{{ __('SysName') }}</th>
                                            <th>{{ __('Hardware Model') }}</th>
                                            <th>{{ __('Upload File') }}</th>
                                            <th>{{ __('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($devices as $device)
                                            <tr class="device-row" data-hardware="{{ $device->hardware }}">
                                                <td>
                                                    <div class="checkbox">
                                                        <label>
                                                            <input type="checkbox" 
                                                                   name="selected_devices[]" 
                                                                   value="{{ $device->device_id }}"
                                                                   data-hardware="{{ $device->hardware }}"
                                                                   data-hostname="{{ $device->hostname }}"
                                                                   class="device-checkbox"
                                                                   {{ in_array($device->hardware, $selectedModels) ? '' : 'disabled' }}>
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>{{ $device->device_id }}</td>
                                                <td>{{ $device->hostname }}</td>
                                                <td>{{ $device->sysName ?: '-' }}</td>
                                                <td>
                                                    <span class="label label-info">{{ $device->hardware }}</span>
                                                </td>
                                                <td id="file_display_{{ $device->device_id }}">
                                                    @if(in_array($device->hardware, $selectedModels))
                                                        <span class="text-warning">
                                                            <i class="fa fa-clock-o"></i> {{ __('Pending file selection') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td id="status_{{ $device->device_id }}">
                                                    @if(isset($uploadedFiles[$device->hardware]))
                                                        <span class="label label-success">
                                                            <i class="fa fa-check"></i> {{ __('File Ready') }}
                                                        </span>
                                                    @else
                                                        <span class="label label-warning">
                                                            <i class="fa fa-exclamation-triangle"></i> {{ __('No File') }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">
                                                    <i class="fa fa-info-circle"></i> 
                                                    {{ __('No devices found for selected models.') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="panel-footer">
                            <div class="row">
                                <div class="col-xs-6">
                                    <span class="text-info">
                                        <strong>{{ __('Selected:') }}</strong> <span id="selectedCount">0</span> / <span id="totalDevices">{{ $devices->count() }}</span>
                                    </span>
                                    <span id="validationStatus" class="ml-2"></span>
                                </div>
                                <div class="col-xs-6 text-right">
                                    <button type="submit" class="btn btn-success" id="submitUpload">
                                        <i class="fa fa-upload"></i> {{ __('Upload Selected') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </form>
        </x-panel>
    </div>
@endsection

@section('scripts')
    @parent
    <script type="text/javascript">
        $(document).ready(function() {
            // Initialize tooltips
            if (typeof $('[data-toggle="tooltip"]').tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }

            // Handle "Select All" checkbox
            $('#selectAll').change(function() {
                $('.device-checkbox:visible:enabled').prop('checked', $(this).prop('checked'));
                updateSelectedCount();
                validateUploadReadiness();
            });

            // Update count when individual checkboxes change
            $('.device-checkbox').change(function() {
                updateSelectedCount();
                validateUploadReadiness();
            });

            // Update selected count function
            function updateSelectedCount() {
                var count = $('.device-checkbox:checked').length;
                var total = $('.device-checkbox:visible:enabled').length;
                $('#selectedCount').text(count);
                
                // Update select all checkbox state
                if (count === 0) {
                    $('#selectAll').prop('checked', false).prop('indeterminate', false);
                } else if (count === total) {
                    $('#selectAll').prop('checked', true).prop('indeterminate', false);
                } else {
                    $('#selectAll').prop('indeterminate', true);
                }
            }

            // Validate if all selected devices have files
            function validateUploadReadiness() {
                var missingFiles = [];
                var selectedHardware = [];
                
                $('.device-checkbox:checked').each(function() {
                    var hardware = $(this).data('hardware');
                    selectedHardware.push(hardware);
                });

                var uniqueHardware = [...new Set(selectedHardware)];
                
                $.each(uniqueHardware, function(index, hardware) {
                    var fileInput = $('input[name="uploads[' + hardware + ']"]');
                    if (fileInput.length && fileInput.get(0).files.length === 0) {
                        missingFiles.push(hardware);
                    }
                });

                var $submitBtn = $('#submitUpload');
                var $validationStatus = $('#validationStatus');

                if (missingFiles.length > 0) {
                    $submitBtn.prop('disabled', true);
                    $validationStatus.html('<span class="text-danger"><i class="fa fa-exclamation-circle"></i> Missing files for: ' + missingFiles.join(', ') + '</span>');
                } else {
                    $submitBtn.prop('disabled', false);
                    $validationStatus.html('<span class="text-success"><i class="fa fa-check-circle"></i> Ready to upload</span>');
                }
            }

            // Load upload fields when button is clicked
            $('#loadUploadFields').click(function() {
                var selectedModels = $('#model_names').val();
                
                if (!selectedModels || selectedModels.length === 0) {
                    showNotification('error', 'Please select at least one model');
                    return;
                }

                // Create form and submit to load with selected models
                var form = $('<form action="{{ route("system.bulk.upload") }}" method="GET"></form>');
                
                $.each(selectedModels, function(index, model) {
                    form.append($('<input>').attr({
                        type: 'hidden',
                        name: 'model_names[]',
                        value: model
                    }));
                });
                
                $('body').append(form);
                form.submit();
            });

            // Before form submit, validate all requirements
            $('#bulkUploadForm').submit(function(e) {
                var selectedDevices = $('.device-checkbox:checked').length;
                
                if (selectedDevices === 0) {
                    e.preventDefault();
                    showNotification('error', 'Please select at least one device to upload');
                    return false;
                }

                // Check if all selected devices have corresponding file uploads
                var selectedHardware = [];
                $('.device-checkbox:checked').each(function() {
                    selectedHardware.push($(this).data('hardware'));
                });

                var uniqueHardware = [...new Set(selectedHardware)];
                var missingFiles = [];

                $.each(uniqueHardware, function(index, hardware) {
                    var fileInput = $('input[name="uploads[' + hardware + ']"]');
                    if (fileInput.length && fileInput.get(0).files.length === 0) {
                        missingFiles.push(hardware);
                    }
                });

                if (missingFiles.length > 0) {
                    e.preventDefault();
                    showNotification('error', 'Please upload files for models: ' + missingFiles.join(', '));
                    return false;
                }

                // Show loading state
                var $submitBtn = $(this).find('button[type="submit"]');
                $submitBtn.prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
            });

            // File input change handler with enhanced feedback
            $(document).on('change', 'input[type="file"].file-upload-input', function() {
                var input = $(this);
                var modelName = input.attr('name').match(/\[(.*?)\]/)[1];
                var fileName = input.get(0).files.length > 0 ? input.get(0).files[0].name : '';
                var fileSize = input.get(0).files.length > 0 ? input.get(0).files[0].size : 0;
                
                // Format file size
                var fileSizeFormatted = '';
                if (fileSize > 0) {
                    if (fileSize < 1024) {
                        fileSizeFormatted = fileSize + ' B';
                    } else if (fileSize < 1048576) {
                        fileSizeFormatted = (fileSize / 1024).toFixed(2) + ' KB';
                    } else {
                        fileSizeFormatted = (fileSize / 1048576).toFixed(2) + ' MB';
                    }
                }
                
                // Update all devices with this hardware model
                $('.device-checkbox[data-hardware="' + modelName + '"]').each(function() {
                    var deviceId = $(this).val();
                    var displayCell = $('#file_display_' + deviceId);
                    var statusCell = $('#status_' + deviceId);
                    
                    if (fileName) {
                        displayCell.html('<span class="text-success"><i class="fa fa-check-circle"></i> ' + 
                                       fileName + ' (' + fileSizeFormatted + ')</span>');
                        statusCell.html('<span class="label label-success"><i class="fa fa-check"></i> File Ready</span>');
                        
                        // Auto-select devices when file is uploaded
                        $(this).prop('checked', true);
                    } else {
                        displayCell.html('<span class="text-warning"><i class="fa fa-clock-o"></i> Pending file selection</span>');
                        statusCell.html('<span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> No File</span>');
                    }
                });

                updateSelectedCount();
                validateUploadReadiness();
                
                // Show success notification
                if (fileName) {
                    showNotification('success', 'File "' + fileName + '" selected for ' + modelName);
                }
            });

            // Notification function
            function showNotification(type, message) {
                if (typeof toastr !== 'undefined') {
                    toastr[type](message);
                } else {
                    alert(message);
                }
            }

            // Toastr configuration for Bootstrap 3
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-right',
                    timeOut: 5000,
                    extendedTimeOut: 1000,
                    showEasing: 'swing',
                    hideEasing: 'linear',
                    showMethod: 'fadeIn',
                    hideMethod: 'fadeOut'
                };
            }

            // Initialize validation on page load
            updateSelectedCount();
            validateUploadReadiness();

            // Handle disabled checkboxes
            $('.device-checkbox:disabled').closest('tr').addClass('text-muted');
        });
    </script>
@endsection