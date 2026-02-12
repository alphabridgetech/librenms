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
                                <select name="model_names[]" id="model_names" class="form-control" multiple="multiple"
                                    size="5">
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

                @if (count($selectedModels) > 0)
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="fa fa-file-o"></i> {{ __('Upload Software Files for Selected Models') }}
                                <span class="badge">{{ count($selectedModels) }}</span>
                            </h3>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                @foreach ($selectedModels as $model)
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="sysfile_{{ Str::slug($model) }}">
                                                <i class="fa fa-microchip"></i> {{ $model }}
                                            </label>
                                            <input type="file" name="sysfiles[{{ $model }}]"
                                                id="sysfile_{{ Str::slug($model) }}" class="form-control model-file"
                                                data-model="{{ $model }}" accept=".bin"
                                                data-hardware="{{ $model }}">
                                            <p class="help-block">
                                                <span class="file-status" id="status_{{ Str::slug($model) }}">
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

            @if (count($selectedModels) > 0)
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
                                        <th data-column-id="device_id" data-identifier="true" data-type="numeric"
                                            data-visible="false">
                                            ID
                                        </th>
                                        <th data-column-id="select" data-formatter="select" data-sortable="false"
                                            data-width="50px">
                                            {{ __('Select') }}
                                        </th>
                                        <th data-column-id="hostname">{{ __('Hostname') }}</th>
                                        <th data-column-id="hardware">{{ __('Model') }}</th>
                                        <th data-column-id="sysObjectID">{{ __('sysObjectID') }}</th>
                                        <th data-column-id="model_file" data-formatter="modelFile" data-sortable="false"
                                            data-width="150px">
                                            {{ __('File Status') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($devices as $device)
                                        <tr data-hardware="{{ $device->hardware }}"
                                            data-device-id="{{ $device->device_id }}">
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
                                <button type="button" id="uploadBtn" class="btn btn-success btn-lg"
                                    style="min-width: 200px;">
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
        $(function() {
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
                    "select": function() {
                        return '<input type="checkbox" class="bootgrid-checkbox">';
                    },
                    "modelFile": function(column, row) {
                        var hardware = $(row).attr('data-hardware');
                        var fileInput = $('.model-file[data-model="' + hardware + '"]');
                        var fileName = '';

                        if (fileInput.length > 0 && fileInput[0].files && fileInput[0].files.length >
                            0) {
                            fileName = fileInput[0].files[0].name;
                        }

                        if (fileName) {
                            return '<span class="label label-success"><i class="fa fa-check"></i> ' +
                                truncateString(fileName, 15) + '</span>';
                        } else {
                            return '<span class="label label-danger"><i class="fa fa-times"></i> No file</span>';
                        }
                    }
                }
            }).on("loaded.rs.jquery.bootgrid", function() {
                // Fix for bootgrid selection
                grid.find(".bootgrid-checkbox").on("change", function() {
                    var row = $(this).closest("tr");
                    var rowId = row.find("td:first").text();

                    if ($(this).is(":checked")) {
                        row.addClass("selected");
                        grid.bootgrid("select", [rowId]);
                    } else {
                        row.removeClass("selected");
                        grid.bootgrid("deselect", [rowId]);
                    }
                });
            });

            // Helper function to truncate long filenames
            function truncateString(str, num) {
                if (!str) return '';
                if (str.length <= num) {
                    return str;
                }
                return str.slice(0, num) + '...';
            }

            // Update file status when file is selected
            $('.model-file').on('change', function() {
                var model = $(this).data('model');
                var fileName = '';
                var fileSize = 0;

                if (this.files && this.files.length > 0) {
                    fileName = this.files[0].name;
                    fileSize = this.files[0].size;
                }

                // Update inline status
                var statusEl = $(this).closest('.form-group').find('.file-status');
                if (fileName) {
                    statusEl.removeClass('text-danger').addClass('text-success')
                        .html('<i class="fa fa-check-circle"></i> ' + fileName + ' (' + formatBytes(
                            fileSize) + ')');
                } else {
                    statusEl.removeClass('text-success').addClass('text-danger')
                        .html('<i class="fa fa-times-circle"></i> No file selected');
                }

                // Update table status
                grid.bootgrid('reload');
            });

            // Format bytes
            function formatBytes(bytes, decimals = 2) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const dm = decimals < 0 ? 0 : decimals;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
            }

            // Select all button
            $('#selectAllBtn').on('click', function() {
                $('tr[data-hardware]').each(function() {
                    var rowId = $(this).find('td:first').text();
                    grid.bootgrid('select', [rowId]);
                    $(this).addClass('selected');
                    $(this).find('.bootgrid-checkbox').prop('checked', true);
                });
            });

            // Deselect all button
            $('#deselectAllBtn').on('click', function() {
                grid.bootgrid('deselectAll');
                $('tr').removeClass('selected');
                $('.bootgrid-checkbox').prop('checked', false);
            });

            // Select by model button
            $('#selectByModelBtn').on('click', function() {
                var selectedModels = [];
                $('tr[data-hardware]').each(function() {
                    var hardware = $(this).data('hardware');
                    if ($.inArray(hardware, selectedModels) === -1) {
                        selectedModels.push(hardware);
                    }
                });

                if (selectedModels.length === 0) {
                    alert('No models found in the table');
                    return;
                }

                var modelList = selectedModels.map(function(model) {
                    return '<div class="checkbox"><label><input type="checkbox" class="model-select-checkbox" value="' +
                        model + '"> ' + model + '</label></div>';
                }).join('');

                var modalHtml =
                    '<div class="modal fade" id="modelSelectModal" tabindex="-1" role="dialog">' +
                    '<div class="modal-dialog" role="document">' +
                    '<div class="modal-content">' +
                    '<div class="modal-header">' +
                    '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
                    '<h4 class="modal-title">Select Devices by Model</h4>' +
                    '</div>' +
                    '<div class="modal-body">' +
                    '<p>Select models to select all devices of that model:</p>' +
                    '<div id="modelCheckboxes">' + modelList + '</div>' +
                    '<div class="checkbox" style="margin-top: 10px;">' +
                    '<label><input type="checkbox" id="selectAllModelsCheck"> Select All Models</label>' +
                    '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>' +
                    '<button type="button" class="btn btn-primary" id="applyModelSelection">Apply Selection</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';

                $('#modelSelectModal').remove();
                $('body').append(modalHtml);

                $('#modelSelectModal').modal('show');

                $('#selectAllModelsCheck').on('change', function() {
                    $('.model-select-checkbox').prop('checked', $(this).is(':checked'));
                });

                $('#applyModelSelection').on('click', function() {
                    var selectedModels = [];
                    $('.model-select-checkbox:checked').each(function() {
                        selectedModels.push($(this).val());
                    });

                    if (selectedModels.length === 0) {
                        alert('Please select at least one model');
                        return;
                    }

                    grid.bootgrid('deselectAll');
                    $('tr').removeClass('selected');
                    $('.bootgrid-checkbox').prop('checked', false);

                    $('tr[data-hardware]').each(function() {
                        if ($.inArray($(this).data('hardware'), selectedModels) !== -1) {
                            var rowId = $(this).find('td:first').text();
                            grid.bootgrid('select', [rowId]);
                            $(this).addClass('selected');
                            $(this).find('.bootgrid-checkbox').prop('checked', true);
                        }
                    });

                    $('#modelSelectModal').modal('hide');
                });
            });

            // Create progress modal
            function createProgressModal() {
                var modalHtml =
                    '<div class="modal fade" id="uploadProgressModal" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog">' +
                    '<div class="modal-dialog modal-lg" role="document">' +
                    '<div class="modal-content">' +
                    '<div class="modal-header">' +
                    '<h4 class="modal-title"><i class="fa fa-upload"></i> Bulk Upload Progress</h4>' +
                    '</div>' +
                    '<div class="modal-body">' +
                    '<div class="progress">' +
                    '<div id="uploadProgressBar" class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%">' +
                    '<span id="uploadProgressText">0%</span>' +
                    '</div>' +
                    '</div>' +
                    '<div id="uploadStatus" style="max-height: 300px; overflow-y: auto;">' +
                    '<table class="table table-condensed table-hover">' +
                    '<thead>' +
                    '<tr><th>Device</th><th>Hardware</th><th>Status</th><th>Message</th></tr>' +
                    '</thead>' +
                    '<tbody id="uploadStatusBody"></tbody>' +
                    '</table>' +
                    '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-default" id="closeProgressBtn" disabled>Close</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';

                $('#uploadProgressModal').remove();
                $('body').append(modalHtml);
            }

            // Update progress
            function updateProgress(percent, message) {
                $('#uploadProgressBar').css('width', percent + '%').attr('aria-valuenow', percent);
                $('#uploadProgressText').text(percent + '%');
            }

            // Add status message
            function addStatusMessage(device, hardware, status, message, isSuccess) {
                var statusClass = isSuccess ? 'text-success' : 'text-danger';
                var statusIcon = isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle';

                var row = '<tr>' +
                    '<td>' + device + '</td>' +
                    '<td>' + hardware + '</td>' +
                    '<td class="' + statusClass + '"><i class="fa ' + statusIcon + '"></i> ' + status + '</td>' +
                    '<td>' + (message || '') + '</td>' +
                    '</tr>';

                $('#uploadStatusBody').append(row);

                // Auto scroll to bottom
                var statusDiv = $('#uploadStatus');
                statusDiv.scrollTop(statusDiv[0].scrollHeight);
            }

            // Upload logic - REPLACE the entire $('#uploadBtn').on('click', function() { ... }) section
            // Upload logic - REPLACE the entire $('#uploadBtn').on('click', function() { ... }) section
            $('#uploadBtn').on('click', function() {
                // Get selected rows from bootgrid
                let selectedRows = grid.bootgrid("getSelectedRows");
                let deviceIds = [];

                // Map selected rows to device IDs
                $('tr.selected').each(function() {
                    var deviceId = $(this).find('td:first').text();
                    if (deviceId && !deviceIds.includes(deviceId)) {
                        deviceIds.push(deviceId);
                    }
                });

                // Fallback to bootgrid selection
                if (deviceIds.length === 0 && selectedRows && selectedRows.length > 0) {
                    deviceIds = selectedRows;
                }

                console.log('Selected devices:', deviceIds);

                if (!deviceIds.length) {
                    alert('Please select at least one device');
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

                console.log('Hardware models needed:', Array.from(hardwareSet));

                // Check files for each hardware
                hardwareSet.forEach(function(hardware) {
                    var fileInput = $('.model-file[data-model="' + hardware + '"]');
                    console.log('Checking file for hardware:', hardware, 'Input found:', fileInput
                        .length > 0);

                    if (!fileInput.length) {
                        missingFiles.push(hardware + ' (No file upload field)');
                        allFilesValid = false;
                        return;
                    }

                    var file = fileInput[0].files[0];

                    if (!file) {
                        missingFiles.push(hardware);
                        allFilesValid = false;
                        console.log('No file selected for:', hardware);
                    } else {
                        // Validate file extension
                        var fileName = file.name;
                        var fileExt = fileName.substring(fileName.lastIndexOf('.') + 1)
                            .toLowerCase();

                        if (fileExt !== 'bin') {
                            missingFiles.push(hardware + ' (Invalid file type: .' + fileExt + ')');
                            allFilesValid = false;
                        } else {
                            fileData[hardware] = file;
                            console.log('Valid file for:', hardware, file.name);
                        }
                    }
                });

                if (!allFilesValid) {
                    var errorMsg = 'Missing or invalid files for models:\n\n';
                    errorMsg += missingFiles.join('\n');
                    errorMsg += '\n\nPlease select valid .bin files for all selected models.';
                    alert(errorMsg);
                    return;
                }

                // Create FormData
                let formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');

                // IMPORTANT FIX: Append each file with its model name - ensure proper format
                Object.keys(fileData).forEach(function(hardware) {
                    // Make sure the key format matches what Laravel expects
                    formData.append('sysfiles[' + hardware + ']', fileData[hardware], fileData[
                        hardware].name);
                    console.log('Appending file for hardware:', hardware, fileData[hardware].name);
                });

                // Append device IDs
                deviceIds.forEach(id => formData.append('device_ids[]', id));

                // Debug: Log FormData contents
                console.log('FormData entries:');
                for (let pair of formData.entries()) {
                    if (pair[0].includes('sysfiles')) {
                        console.log(pair[0] + ': [FILE] ' + (pair[1] instanceof File ? pair[1].name :
                            'unknown'));
                    } else {
                        console.log(pair[0] + ': ' + pair[1]);
                    }
                }

                // Create and show progress modal
                createProgressModal();
                $('#uploadProgressModal').modal('show');

                // Clear previous status
                $('#uploadStatusBody').empty();
                updateProgress(0, 'Starting upload...');

                // Show loading state on button
                var $uploadBtn = $(this);
                var originalText = $uploadBtn.html();
                $uploadBtn.prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin"></i> Uploading...');

                // Add initial status
                addStatusMessage('System', '', 'Info', 'Starting upload for ' + deviceIds.length +
                    ' devices...', true);

                $.ajax({
                    url: "{{ route('syssoftbulk.store') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                var percentComplete = Math.round((e.loaded / e.total) *
                                    100);
                                updateProgress(percentComplete, 'Uploading files...');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        updateProgress(100, 'Upload complete');

                        // Show success results
                        if (response.results && response.results.success) {
                            response.results.success.forEach(function(item) {
                                addStatusMessage(item.hostname, item.hardware,
                                    'Success',
                                    'File: ' + item.filename, true);
                            });
                        }

                        // Show failed results
                        if (response.results && response.results.failed) {
                            response.results.failed.forEach(function(item) {
                                addStatusMessage(item.hostname, item.hardware, 'Failed',
                                    item.error, false);
                            });
                        }

                        addStatusMessage('System', '', 'Complete', response.message ||
                            'Upload completed', true);

                        // Enable close button
                        $('#closeProgressBtn')
                            .prop('disabled', false)
                            .off('click')
                            .on('click', function() {
                                $('#uploadProgressModal').modal('hide');
                                // Optionally refresh the page
                                // location.reload();
                            });
                    },
                    error: function(xhr) {
                        console.error('Upload error:', xhr);
                        console.error('Response:', xhr.responseText);

                        updateProgress(0, 'Upload failed');

                        var errorMsg = 'Unknown error occurred';

                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            if (xhr.responseJSON.errors) {
                                errorMsg += '\n' + JSON.stringify(xhr.responseJSON.errors);
                            }
                        } else if (xhr.statusText) {
                            errorMsg = xhr.statusText;
                        }

                        addStatusMessage('System', '', 'Error', errorMsg, false);

                        // Enable close button
                        $('#closeProgressBtn')
                            .prop('disabled', false)
                            .off('click')
                            .on('click', function() {
                                $('#uploadProgressModal').modal('hide');
                            });
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

        tr.selected {
            background-color: #f0f8ff !important;
        }

        .progress {
            height: 30px;
            margin-bottom: 20px;
        }

        .progress-bar {
            line-height: 30px;
            font-size: 14px;
        }

        #uploadStatus {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background-color: #f9f9f9;
        }

        #uploadStatus table {
            margin-bottom: 0;
        }

        #uploadStatus tbody tr:last-child td {
            border-bottom: none;
        }
    </style>
@endsection
