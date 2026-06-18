@extends('layouts.librenmsv1')

@section('title', __('Push configuration to devices by IP'))

@section('content')
    <div class="container-fluid">
        <x-panel>
            <x-slot name="title">
                <i class="fa fa-plus fa-fw fa-lg"></i> {{ __('Push Configuration to Devices by IP') }}
            </x-slot>

            @if (session('status'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="fa fa-check-circle fa-fw"></i> {{ session('status') }}
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

            <div class="alert alert-info">
                <strong>{{ __('Instructions:') }}</strong>
                <ul class="mb-0">
                    <li>{{ __('Enter one IP address per line in the textarea') }}</li>
                    <li>{{ __('Each line will be validated in real-time') }}</li>
                    <li>{{ __('Lines starting with # will be ignored as comments') }}</li>
                    <li>{{ __('Upload the startup-config file for the devices') }}</li>
                    <li>{{ __('Kindly verify the correctness of the startup-config file before uploading') }}</li>
                    <li>{{ __('After uploading, there is no rollback provision, so please proceed carefully') }}</li>
                </ul>
            </div>

            <form id="ipUploadForm" method="post" action="{{ route('addhost.ip.save') }}" enctype="multipart/form-data" class="form-horizontal" role="form">
                @csrf

                <div class="row">
                    @if(!empty($templates))
                    <div class="col-md-6">
                        <!-- Load Template Dropdown -->
                        <div class="form-group">
                            <label for="load_template" class="col-sm-4 control-label">{{ __('Load Template') }}</label>
                            <div class="col-sm-8">
                                <select id="load_template" class="form-control">
                                    <option value="">{{ __('-- Select Template --') }}</option>
                                    @foreach($templates as $template)
                                        <option value="{{ json_encode($template) }}">{{ $template['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(!empty($uploadedFiles))
                    <div class="col-md-6">
                        <!-- Previously Uploaded Files Dropdown -->
                        <div class="form-group">
                            <label for="load_file" class="col-sm-4 control-label">{{ __('Uploaded Files') }}</label>
                            <div class="col-sm-8">
                                <select id="load_file" class="form-control">
                                    <option value="">{{ __('-- Select File --') }}</option>
                                    @foreach($uploadedFiles as $file)
                                        <option value="{{ $file['path'] }}">{{ $file['display_name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                <hr>

                <!-- Template Name Input -->
                <div class="form-group">
                    <label for="template_name" class="col-sm-3 control-label">{{ __('Template Name') }}</label>
                    <div class="col-sm-9">
                        <input type="text" name="template_name" id="template_name" class="form-control" placeholder="{{ __('Enter a name to save this as a template (optional)') }}">
                        <span class="help-block">{{ __('If provided, this configuration will be saved as a template for future use.') }}</span>
                    </div>
                </div>

                <hr>

                <!-- Credentials Accordion -->
                <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                    <div class="panel panel-default">
                        <div class="panel-heading" role="tab" id="headingCredentials">
                            <h4 class="panel-title">
                                <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseCredentials" aria-expanded="false" aria-controls="collapseCredentials">
                                    <i class="fa fa-key fa-fw"></i> {{ __('Credentials Settings') }} <small>({{ __('Optional Overrides') }})</small>
                                    <i class="fa fa-chevron-down pull-right"></i>
                                </a>
                            </h4>
                        </div>
                        <div id="collapseCredentials" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingCredentials">
                            <div class="panel-body">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> {{ __('Credentials from the database will be prioritized. If not found, these will be used as fallback.') }}
                                </div>

                                <div class="form-group">
                                    <label for="ansible_user" class="col-sm-3 control-label">{{ __('SSH Username') }}</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="ansible_user" id="ansible_user" class="form-control" value="admin">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="ansible_password" class="col-sm-3 control-label">{{ __('SSH Password') }}</label>
                                    <div class="col-sm-9">
                                        <input type="password" name="ansible_password" id="ansible_password" class="form-control" value="admin">
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="snmp_community" class="col-sm-3 control-label">{{ __('SNMP Community') }}</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="snmp_community" id="snmp_community" class="form-control" value="public">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- IP Addresses Input -->
                <div class="form-group">
                    <label for="hostname" class="col-sm-3 control-label">{{ __('IP Addresses') }} <span class="text-danger">*</span></label>
                    <div class="col-sm-9">
                        <textarea name="hostname" id="hostname" class="form-control" rows="8" placeholder="{{ __('Enter one IP address per line:') }}&#10;192.168.1.1&#10;192.168.1.2&#10;192.168.1.3&#10;# This is a comment and will be ignored" required></textarea>
                        <span class="help-block">{{ __('Enter one IP per line. Lines starting with # are ignored. Each IP is validated in real-time.') }}</span>
                    </div>
                </div>

                <!-- Real-time IP Validation Preview -->
                <div class="form-group" id="ipValidationPreview" style="display: none;">
                    <label class="col-sm-3 control-label">{{ __('IP Validation') }}</label>
                    <div class="col-sm-9">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong>{{ __('IP Address Validation Results') }}</strong>
                                <span id="validCount" class="badge bg-success" style="background-color: #5cb85c;">0</span>
                                <span id="invalidCount" class="badge bg-danger" style="background-color: #d9534f;">0</span>
                                <span id="commentCount" class="badge bg-info" style="background-color: #5bc0de;">0</span>
                            </div>
                            <div class="panel-body" style="max-height: 250px; overflow-y: auto;">
                                <div id="validationResults"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Config File Upload -->
                <div class="form-group">
                    <label for="config_file" class="col-sm-3 control-label">{{ __('Config File') }} <span class="text-danger">*</span></label>
                    <div class="col-sm-9">
                        <input type="file" name="config_file" id="config_file" class="form-control" accept=".conf,.cfg,.txt,.bin" required>
                        <span class="help-block">{{ __('Select the startup-config file to upload to devices') }}</span>
                    </div>
                </div>

                <!-- Config File Preview -->
                <div class="form-group" id="configPreview" style="display: none;">
                    <label class="col-sm-3 control-label">{{ __('Config Preview') }}</label>
                    <div class="col-sm-9">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong>{{ __('Uploaded Configuration File') }}</strong>
                                <button type="button" class="btn btn-xs btn-default pull-right" id="toggleConfigView">
                                    <i class="fa fa-eye"></i> {{ __('Show/Hide Content') }}
                                </button>
                            </div>
                            <div class="panel-body">
                                <div class="alert alert-info">
                                    <i class="fa fa-file-text-o"></i> 
                                    <strong id="configFileName"></strong>
                                    <small class="text-muted"> (<span id="configFileSize"></span> bytes)</small>
                                </div>
                                <div id="configContent" style="display: none; margin-top: 10px;">
                                    <pre id="configFileContent" style="max-height: 300px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                            <i class="fa fa-plus"></i> {{ __('Process Devices') }}
                        </button>
                        <button type="reset" class="btn btn-default" id="resetBtn">{{ __('Reset') }}</button>
                        <div id="loadingSpinner" style="display: none; margin-left: 10px;" class="pull-right">
                            <i class="fa fa-spinner fa-spin"></i> Processing...
                        </div>
                    </div>
                </div>
            </form>
        </x-panel>
    </div>
@endsection

@section('scripts')
    @parent
    <script type="text/javascript">
        $(document).ready(function() {
            // Handle template selection
            $('#load_template').on('change', function() {
                const val = $(this).val();
                if (val) {
                    const template = JSON.parse(val);
                    $('#hostname').val(template.hostname).trigger('input');
                    $('#template_name').val(template.name);
                    $('#load_file').val(''); // Clear other dropdown
                    
                    // Show commands in preview
                    if (template.commands && template.commands.length > 0) {
                        $('#configFileName').text(template.original_filename || 'Template Commands');
                        $('#configFileSize').text('N/A');
                        $('#configFileContent').text(template.commands.join('\n'));
                        $('#configPreview').slideDown();
                        $('#configContent').show();
                        
                        // Set a hidden field or flag to indicate we're using template commands
                        if ($('#use_template_commands').length === 0) {
                            $('#ipUploadForm').append('<input type="hidden" id="use_template_commands" name="use_template_commands" value="1">');
                            $('#ipUploadForm').append('<input type="hidden" id="loaded_template_name" name="loaded_template_name" value="">');
                        }
                        $('#loaded_template_name').val(template.name);
                        $('#loaded_filename').remove();
                        
                        // Make config_file not required if we have template commands
                        $('#config_file').prop('required', false);
                        
                        // Re-validate IPs to enable submit button
                        const validation = validateAndParseIPs($('#hostname').val());
                        displayValidationResults(validation);
                    }
                } else {
                    $('#hostname').val('').trigger('input');
                    $('#template_name').val('');
                    $('#configPreview').slideUp();
                    $('#use_template_commands').remove();
                    $('#loaded_template_name').remove();
                    $('#config_file').prop('required', true);
                }
            });

            // Handle file selection from previously uploaded files
            $('#load_file').on('change', function() {
                const path = $(this).val();
                if (path) {
                    $('#load_template').val(''); // Clear other dropdown
                    
                    // Fetch content via AJAX
                    $.ajax({
                        url: "{{ route('addhost.ip.file-content') }}",
                        type: 'GET',
                        data: { path: path },
                        success: function(response) {
                            if (response.success) {
                                $('#configFileName').text(response.filename);
                                $('#configFileSize').text('N/A');
                                $('#configFileContent').text(response.content);
                                $('#configPreview').slideDown();
                                $('#configContent').show();
                                
                                // Set a hidden field to indicate we're using a previously uploaded file
                                if ($('#use_template_commands').length === 0) {
                                    $('#ipUploadForm').append('<input type="hidden" id="use_template_commands" name="use_template_commands" value="1">');
                                }
                                if ($('#loaded_filename').length === 0) {
                                    $('#ipUploadForm').append('<input type="hidden" id="loaded_filename" name="loaded_filename" value="">');
                                }
                                $('#loaded_filename').val(path); // Save the FULL PATH
                                $('#loaded_template_name').remove();
                                
                                // Make config_file not required
                                $('#config_file').prop('required', false);
                                
                                // Re-validate IPs
                                const validation = validateAndParseIPs($('#hostname').val());
                                displayValidationResults(validation);
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while fetching file content');
                        }
                    });
                } else {
                    $('#configPreview').slideUp();
                    $('#use_template_commands').remove();
                    $('#loaded_filename').remove();
                    $('#config_file').prop('required', true);
                }
            });

            let validIPs = [];

            // Function to validate IP address
            function isValidIP(ip) {
                const ipPattern = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
                return ipPattern.test(ip.trim());
            }

            // Function to validate and parse IPs with line-by-line checking
            function validateAndParseIPs(content) {
                const lines = content.split(/\r?\n/);
                const results = [];
                const valid = [];
                let validCount = 0;
                let invalidCount = 0;
                let commentCount = 0;
                
                lines.forEach((line, index) => {
                    const originalLine = line;
                    const trimmedLine = line.trim();
                    
                    if (trimmedLine === '') {
                        results.push({
                            lineNumber: index + 1,
                            content: originalLine,
                            status: 'empty',
                            message: 'Empty line (ignored)'
                        });
                    } else if (trimmedLine.startsWith('#')) {
                        commentCount++;
                        results.push({
                            lineNumber: index + 1,
                            content: originalLine,
                            status: 'comment',
                            message: 'Comment line (ignored)',
                            icon: 'fa-comment'
                        });
                    } else if (isValidIP(trimmedLine)) {
                        validCount++;
                        valid.push(trimmedLine);
                        results.push({
                            lineNumber: index + 1,
                            content: originalLine,
                            status: 'valid',
                            message: 'Valid IP address',
                            icon: 'fa-check-circle',
                            ip: trimmedLine
                        });
                    } else {
                        invalidCount++;
                        results.push({
                            lineNumber: index + 1,
                            content: originalLine,
                            status: 'invalid',
                            message: 'Invalid IP address format',
                            icon: 'fa-times-circle'
                        });
                    }
                });
                
                return {
                    results: results,
                    valid: valid,
                    validCount: validCount,
                    invalidCount: invalidCount,
                    commentCount: commentCount,
                    totalLines: lines.length
                };
            }

            // Function to display validation results
            function displayValidationResults(validation) {
                const previewDiv = $('#ipValidationPreview');
                const resultsDiv = $('#validationResults');
                const validCountSpan = $('#validCount');
                const invalidCountSpan = $('#invalidCount');
                const commentCountSpan = $('#commentCount');
                const submitBtn = $('#submitBtn');
                const configFile = $('#config_file').val();
                
                // Update counters
                validCountSpan.text(validation.validCount);
                invalidCountSpan.text(validation.invalidCount);
                commentCountSpan.text(validation.commentCount);
                
                // Build results HTML
                let resultsHtml = '<table class="table table-condensed table-hover" style="margin-bottom: 0;">';
                resultsHtml += '<thead><tr><th>Line</th><th>Content</th><th>Status</th></tr></thead><tbody>';
                
                validation.results.forEach(result => {
                    let statusClass = '';
                    let statusIcon = '';
                    let statusText = '';
                    
                    switch(result.status) {
                        case 'valid':
                            statusClass = 'text-success';
                            statusIcon = '<i class="fa fa-check-circle text-success"></i>';
                            statusText = 'Valid';
                            break;
                        case 'invalid':
                            statusClass = 'text-danger';
                            statusIcon = '<i class="fa fa-times-circle text-danger"></i>';
                            statusText = 'Invalid';
                            break;
                        case 'comment':
                            statusClass = 'text-info';
                            statusIcon = '<i class="fa fa-comment text-info"></i>';
                            statusText = 'Comment';
                            break;
                        case 'empty':
                            statusClass = 'text-muted';
                            statusIcon = '<i class="fa fa-minus-circle text-muted"></i>';
                            statusText = 'Empty';
                            break;
                    }
                    
                    resultsHtml += `
                        <tr class="${statusClass}">
                            <td style="width: 60px;">${result.lineNumber}</td>
                            <td><code>${escapeHtml(result.content) || '(empty)'}</code></td>
                            <td style="width: 150px;">${statusIcon} ${statusText} - ${result.message}</td>
                        </tr>
                    `;
                });
                
                resultsHtml += '</tbody></table>';
                resultsDiv.html(resultsHtml);
                
                // Show/hide preview
                if (validation.totalLines > 0) {
                    previewDiv.slideDown();
                } else {
                    previewDiv.slideUp();
                }
                
                // Enable/disable submit button
                const useTemplateCommands = $('#use_template_commands').val() === '1';
                if (validation.validCount > 0 && (configFile || useTemplateCommands)) {
                    submitBtn.prop('disabled', false);
                    validIPs = validation.valid;
                } else {
                    submitBtn.prop('disabled', true);
                    validIPs = [];
                }
            }

            // Function to escape HTML
            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            // Real-time validation on input
            $('#hostname').on('input', function(e) {
                const content = $(this).val();
                const validation = validateAndParseIPs(content);
                displayValidationResults(validation);
            });

            // Handle config file selection with preview
            $('#config_file').on('change', function(e) {
                const file = e.target.files[0];
                
                if (file) {
                    // Check file size (max 10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        alert('File size must be less than 10MB');
                        $(this).val('');
                        return;
                    }
                    
                    // Display file info
                    $('#configFileName').text(file.name);
                    $('#configFileSize').text(file.size);
                    $('#configPreview').slideDown();
                    
                    // Read and display file content
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const content = e.target.result;
                        // Limit preview to first 5000 characters
                        let previewContent = content;
                        if (content.length > 5000) {
                            previewContent = content.substring(0, 5000) + '\n\n... (file truncated, showing first 5000 characters)';
                        }
                        $('#configFileContent').text(previewContent);
                    };
                    reader.readAsText(file);
                    
                    // Re-validate IPs
                    const validation = validateAndParseIPs($('#hostname').val());
                    displayValidationResults(validation);
                } else {
                    $('#configPreview').slideUp();
                    $('#submitBtn').prop('disabled', true);
                }
            });

            // Toggle config content view
            $('#toggleConfigView').on('click', function() {
                $('#configContent').slideToggle();
                const icon = $(this).find('i');
                if (icon.hasClass('fa-eye')) {
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                    $(this).html('<i class="fa fa-eye-slash"></i> Hide Content');
                } else {
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                    $(this).html('<i class="fa fa-eye"></i> Show Content');
                }
            });

            // Handle form submission with AJAX
            $('#ipUploadForm').on('submit', function(e) {
                e.preventDefault();
                
                if (validIPs.length === 0) {
                    alert('Please enter at least one valid IP address');
                    return false;
                }
                
                const useTemplateCommands = $('#use_template_commands').val() === '1';
                if (!$('#config_file').val() && !useTemplateCommands) {
                    alert('Please select a config file to upload');
                    return false;
                }
                
                if (!confirm(`Are you sure you want to process ${validIPs.length} device(s)?\n\nValid IPs: ${validIPs.join(', ')}\n`)) {
                    return false;
                }
                
                // Show loading spinner
                $('#submitBtn').prop('disabled', true);
                $('#loadingSpinner').show();

                // Show processing alert
                $('.alert-success, .alert-warning, .alert-info').remove();
                const processingHtml = `
                    <div class="alert alert-info" id="processingAlert">
                        <i class="fa fa-spinner fa-spin fa-fw"></i> 
                        <strong>Processing...</strong> {{ __('Please wait while we configure your devices. This may take a few minutes.') }}
                    </div>
                `;
                $('.panel:first').before(processingHtml);
                $('html, body').animate({ scrollTop: 0 }, 'fast');
                
                // Create FormData object for file upload
                const formData = new FormData(this);
                
                // Add valid IPs list to form data
                formData.append('valid_ips', JSON.stringify(validIPs));
                
                // Submit via AJAX
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Clear any existing alerts
                        $('.alert-success, .alert-warning').remove();
                        $('#processingAlert').remove();

                        if (response.success) {
                            // Show success message
                            const successHtml = `
                                <div class="alert alert-success alert-dismissible fade in" role="alert">
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                    <i class="fa fa-check-circle fa-fw"></i> 
                                    <strong>Success!</strong> ${response.message}
                                    ${response.results ? '<pre class="mt-2" style="max-height: 200px; overflow-y: auto;">' + JSON.stringify(response.results, null, 2) + '</pre>' : ''}
                                </div>
                            `;
                            $('.panel:first').before(successHtml);
                            
                            // Reset form only on full success
                            $('#hostname').val('');
                            $('#config_file').val('');
                            $('#ipValidationPreview').hide();
                            $('#configPreview').hide();
                            validIPs = [];
                        } else {
                            // Show warning message for partial or full failure
                            const warningHtml = `
                                <div class="alert alert-warning alert-dismissible fade in" role="alert">
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                    <i class="fa fa-exclamation-triangle fa-fw"></i> 
                                    <strong>Warning!</strong> ${response.message}
                                    ${response.results ? '<pre class="mt-2" style="max-height: 400px; overflow-y: auto;">' + JSON.stringify(response.results, null, 2) + '</pre>' : ''}
                                </div>
                            `;
                            $('.panel:first').before(warningHtml);
                        }
                        
                        // Scroll to top
                        $('html, body').animate({ scrollTop: 0 }, 'slow');
                    },
                    error: function(xhr) {
                        $('#processingAlert').remove();
                        let errorMsg = 'An error occurred while processing devices';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        alert(errorMsg);
                    },
                    complete: function() {
                        $('#submitBtn').prop('disabled', false);
                        $('#loadingSpinner').hide();
                    }
                });
            });

            // Handle reset button
            $('#resetBtn').on('click', function(e) {
                e.preventDefault();
                $('#hostname').val('');
                $('#config_file').val('');
                $('#ipValidationPreview').hide();
                $('#configPreview').hide();
                $('#submitBtn').prop('disabled', true);
                validIPs = [];
                $('#configContent').hide();
            });
            
            // Initial validation if there's pre-filled content
            if ($('#hostname').val()) {
                const validation = validateAndParseIPs($('#hostname').val());
                displayValidationResults(validation);
            }
        });
    </script>
@endsection