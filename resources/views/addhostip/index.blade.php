@extends('layouts.librenmsv1')

@section('title', __('Push Configuration to Devices by IP'))

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

            <div class="alert alert-info">
                <strong>{{ __('Instructions:') }}</strong>
                <ul class="mb-0">
                    <li>{{ __('Enter one IP address per line in the textarea') }}</li>
                    <li>{{ __('Load a template/file or upload a new configuration file') }}</li>
                    <li>{{ __('Credentials from the database will be prioritized') }}</li>
                </ul>
            </div>

            <form id="ipUploadForm" method="post" action="{{ route('addhost.ip.save') }}" enctype="multipart/form-data" class="form-horizontal" role="form">
                @csrf

                <div class="row">
                    <div class="col-xs-12 col-sm-6" style="margin-bottom: 10px;">
                        <!-- Load Template Dropdown -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="load_template" class="col-xs-12 col-sm-4 control-label">{{ __('Load Template') }}</label>
                            <div class="col-xs-12 col-sm-8">
                                <select id="load_template" class="form-control" @if(empty($templates)) disabled @endif>
                                    <option value="">{{ empty($templates) ? __('-- No simple templates available --') : __('-- Select Template --') }}</option>
                                    @foreach($templates as $template)
                                        <option value="{{ json_encode($template) }}">{{ $template['name'] }}</option>
                                    @endforeach
                                </select>
                                <span class="help-block" id="noTemplatesHelp" @if(!empty($templates)) style="display: none;" @endif>
                                    {{ __('No saved templates yet. Fill in Config Content below, give it a name, and click "Save as Template".') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    @if(!empty($uploadedFiles))
                    <div class="col-xs-12 col-sm-6" style="margin-bottom: 10px;">
                        <!-- Previously Uploaded Files Dropdown -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="load_file" class="col-xs-12 col-sm-4 control-label">{{ __('Uploaded Files') }}</label>
                            <div class="col-xs-12 col-sm-8">
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
                        <input type="text" name="template_name" id="template_name" class="form-control" placeholder="{{ __('Template Name') }}">
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
                        <textarea name="hostname" id="hostname" class="form-control" rows="8" placeholder="{{ __('Enter one IP address per line:') }}&#10;192.168.1.1&#10;192.168.1.2&#10;192.168.1.3" required></textarea>
                        <span class="help-block">{{ __('Enter one IP per line. Lines starting with # are ignored. Each IP is validated in real-time.') }}</span>
                    </div>
                </div>

                <!-- Real-time IP Validation Preview -->
                <div class="form-group" id="ipValidationPreview" style="display: none;">
                    <label class="col-xs-12 col-sm-3 control-label">{{ __('IP Validation') }}</label>
                    <div class="col-xs-12 col-sm-9">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong>{{ __('IP Address Validation Results') }}</strong>
                                <span style="white-space: nowrap;">
                                    <span id="validCount" class="badge bg-success" style="background-color: #5cb85c;">0</span>
                                    <span id="invalidCount" class="badge bg-danger" style="background-color: #d9534f;">0</span>
                                    <span id="commentCount" class="badge bg-info" style="background-color: #5bc0de;">0</span>
                                </span>
                            </div>
                            <div class="panel-body" style="max-height: 250px; overflow-y: auto; padding: 0;">
                                <div class="table-responsive" id="validationResults"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Config File Upload -->
                <div class="form-group">
                    <label for="config_file" class="col-sm-3 control-label">{{ __('Config File') }} <span class="text-danger">*</span></label>
                    <div class="col-sm-9">
                        <input type="file" name="config_file" id="config_file" class="form-control" accept=".conf,.cfg,.txt,.bin">
                    </div>
                </div>

                <!-- Config Content: editable before push, and can be typed from scratch to save as a new template -->
                <div class="form-group" id="configPreview">
                    <label class="col-sm-3 control-label">{{ __('Config Content') }}</label>
                    <div class="col-sm-9">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong id="configFileName"></strong>
                                <span class="help-block" style="margin: 0; display: inline;">&mdash; {{ __('type/edit commands here; this is what gets pushed - or save it as a reusable template') }}</span>
                            </div>
                            <div class="panel-body" style="padding: 0;">
                                <textarea id="configFileContent" name="direct_commands" class="form-control" style="max-height: 300px; height: 200px; background: #f5f5f5; padding: 10px; font-size: 11px; font-family: monospace; border: 0; border-radius: 0; resize: vertical;"></textarea>
                            </div>
                            <div class="panel-footer">
                                <button type="button" id="saveTemplateBtn" class="btn btn-info btn-sm">
                                    <i class="fa fa-floppy-o"></i> {{ __('Save as Template') }}
                                </button>
                                <span class="help-block" style="display: inline; margin-left: 10px;">{{ __('Uses the Template Name above') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="form-group">
                    <div class="col-xs-12 col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                            <i class="fa fa-plus"></i> {{ __('Process Devices') }}
                        </button>
                        <button type="reset" class="btn btn-default" id="resetBtn">{{ __('Reset') }}</button>
                        <span id="loadingSpinner" style="display: none; margin-left: 10px;">
                            <i class="fa fa-spinner fa-spin"></i> {{ __('Processing...') }}
                        </span>
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
                        results.push({ lineNumber: index + 1, content: originalLine, status: 'empty', message: 'Empty line' });
                    } else if (trimmedLine.startsWith('#')) {
                        commentCount++;
                        results.push({ lineNumber: index + 1, content: originalLine, status: 'comment', message: 'Comment' });
                    } else if (isValidIP(trimmedLine)) {
                        validCount++;
                        valid.push(trimmedLine);
                        results.push({ lineNumber: index + 1, content: originalLine, status: 'valid', message: 'Valid' });
                    } else {
                        invalidCount++;
                        results.push({ lineNumber: index + 1, content: originalLine, status: 'invalid', message: 'Invalid' });
                    }
                });
                
                return { results: results, valid: valid, validCount: validCount, invalidCount: invalidCount, commentCount: commentCount, totalLines: lines.length };
            }

            function displayValidationResults(validation) {
                const previewDiv = $('#ipValidationPreview');
                const resultsDiv = $('#validationResults');
                $('#validCount').text(validation.validCount);
                $('#invalidCount').text(validation.invalidCount);
                $('#commentCount').text(validation.commentCount);
                
                let resultsHtml = '<table class="table table-condensed table-hover" style="margin-bottom: 0;"><tbody>';
                validation.results.forEach(result => {
                    let statusClass = result.status === 'valid' ? 'text-success' : (result.status === 'invalid' ? 'text-danger' : 'text-info');
                    resultsHtml += `<tr class="${statusClass}"><td>Line ${result.lineNumber}</td><td><code>${result.content}</code></td><td>${result.message}</td></tr>`;
                });
                resultsHtml += '</tbody></table>';
                resultsDiv.html(resultsHtml);
                
                if (validation.totalLines > 0) { previewDiv.slideDown(); } else { previewDiv.slideUp(); }
                updateSubmitButton(validation);
            }

            function updateSubmitButton(validation) {
                const hasFile = $('#config_file').val() !== '';
                const useTemplateCommands = $('#use_template_commands').val() === '1';
                const hasEditedContent = $('#configFileContent').val().trim() !== '';
                validIPs = validation.valid;
                $('#submitBtn').prop('disabled', !(validation.validCount > 0 && (hasFile || useTemplateCommands) && hasEditedContent));
            }

            function updateUI() {
                const content = $('#hostname').val();
                const validation = validateAndParseIPs(content);
                displayValidationResults(validation);
            }

            $('#hostname').on('input', updateUI);

            // Handle template selection
            $('#load_template').on('change', function() {
                const val = $(this).val();
                if (val) {
                    const template = JSON.parse(val);
                    $('#template_name').val(template.name);
                    $('#load_file').val('');
                    
                    if (template.commands && template.commands.length > 0) {
                        $('#configFileName').text(template.original_filename || 'Template Commands');
                        $('#configFileContent').val(template.commands.join('\n'));
                        $('#configPreview').slideDown();
                        
                        if ($('#use_template_commands').length === 0) {
                            $('#ipUploadForm').append('<input type="hidden" id="use_template_commands" name="use_template_commands" value="1">');
                            $('#ipUploadForm').append('<input type="hidden" id="loaded_template_name" name="loaded_template_name" value="">');
                            $('#ipUploadForm').append('<input type="hidden" id="template_folder_hidden" name="template_folder" value="">');
                        }
                        $('#use_template_commands').val('1');
                        $('#loaded_template_name').val(template.name);
                        $('#template_folder_hidden').val(template.template_folder || '');
                        $('#loaded_filename').remove();
                        $('#config_file').prop('required', false);
                    }

                    // Trigger UI update AFTER flags are set
                    $('#hostname').val(template.hostname).trigger('input');
                } else {
                    $('#hostname').val('').trigger('input');
                    $('#template_name').val('');
                    $('#configFileName').text('');
                    $('#configFileContent').val('').trigger('input');
                    $('#use_template_commands').remove();
                    $('#loaded_template_name').remove();
                    $('#template_folder_hidden').remove();
                    $('#config_file').prop('required', true);
                }
            });

            // Handle file selection
            $('#load_file').on('change', function() {
                const path = $(this).val();
                if (path) {
                    $('#load_template').val('');
                    $.ajax({
                        url: "{{ route('addhost.ip.file-content') }}",
                        type: 'GET',
                        data: { path: path },
                        success: function(response) {
                            if (response.success) {
                                $('#configFileName').text(response.filename);
                                $('#configFileContent').val(response.content);
                                $('#configPreview').slideDown();
                                
                                if ($('#use_template_commands').length === 0) {
                                    $('#ipUploadForm').append('<input type="hidden" id="use_template_commands" name="use_template_commands" value="1">');
                                }
                                if ($('#loaded_filename').length === 0) {
                                    $('#ipUploadForm').append('<input type="hidden" id="loaded_filename" name="loaded_filename" value="">');
                                }
                                $('#loaded_filename').val(path);
                                $('#loaded_template_name').remove();
                                $('#config_file').prop('required', false);
                                updateUI();
                            }
                        }
                    });
                } else {
                    $('#configFileName').text('');
                    $('#configFileContent').val('');
                    $('#use_template_commands').remove();
                    $('#loaded_filename').remove();
                    $('#config_file').prop('required', true);
                    updateUI();
                }
            });

            $('#config_file').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    $('#configFileName').text(file.name);
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#configFileContent').val(e.target.result);
                        updateUI();
                    };
                    reader.readAsText(file);
                } else {
                    $('#configFileContent').val('');
                    updateUI();
                }
            });

            // Edits to the loaded content are what actually gets submitted
            // (direct_commands takes priority server-side over the file/
            // template it came from), so re-validate as the user types.
            $('#configFileContent').on('input', updateUI);

            // Save the current Config Content as a new simple template,
            // scoped to this page - separate from the form-based templates
            // on Push Configuration by Template.
            $('#saveTemplateBtn').on('click', function() {
                const name = $('#template_name').val().trim();
                const commands = $('#configFileContent').val();

                if (!name) { alert('Enter a Template Name first'); $('#template_name').focus(); return; }
                if (!commands.trim()) { alert('Config Content is empty - nothing to save'); return; }

                const $btn = $(this);
                $btn.prop('disabled', true);

                $.ajax({
                    url: "{{ route('addhost.ip.template.save') }}",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        template_name: name,
                        commands: commands
                    },
                    success: function(response) {
                        if (response.success) {
                            const $select = $('#load_template');
                            $select.prop('disabled', false);
                            $('#noTemplatesHelp').hide();
                            $select.append($('<option>', {
                                value: JSON.stringify(response.template),
                                text: response.template.name
                            }));
                            $select.val(JSON.stringify(response.template));
                            alert('Template "' + response.template.name + '" saved.');
                        } else {
                            alert(response.message || 'Failed to save template');
                        }
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || 'Failed to save template');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            $('#ipUploadForm').on('submit', function(e) {
                e.preventDefault();
                const content = $('#hostname').val();
                const validation = validateAndParseIPs(content);
                if (validation.validCount === 0) { alert('Please enter at least one valid IP address'); return false; }
                
                const useTemplateCommands = $('#use_template_commands').val() === '1';
                if (!$('#config_file').val() && !useTemplateCommands) { alert('Please select a config file'); return false; }
                if ($('#configFileContent').val().trim() === '') { alert('Config content is empty'); return false; }

                if (!confirm(`Process ${validation.validCount} device(s)?`)) return false;
                
                $('#submitBtn').prop('disabled', true);
                $('#loadingSpinner').show();
                $('.alert-success, .alert-warning, .alert-info').remove();
                $('.panel:first').before(`<div class="alert alert-info" id="processingAlert"><i class="fa fa-spinner fa-spin"></i> Processing...</div>`);

                const formData = new FormData(this);
                formData.append('valid_ips', JSON.stringify(validation.valid));

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#processingAlert').remove();
                        const type = response.success ? 'success' : 'warning';
                        const alertHtml = `
                            <div class="alert alert-${type} alert-dismissible" role="alert">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                <strong>${type === 'success' ? 'Success!' : 'Warning!'}</strong> ${response.message}
                                <pre class="mt-2" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap; word-break: break-word;">${JSON.stringify(response.results, null, 2)}</pre>
                            </div>
                        `;
                        $('.panel:first').before(alertHtml);
                        if (response.success) {
                            $('#hostname').val('').trigger('input');
                            $('#config_file').val('');
                            $('#configFileName').text('');
                            $('#configFileContent').val('');
                        }
                    },
                    error: function() { $('#processingAlert').remove(); alert('An error occurred'); },
                    complete: function() { $('#submitBtn').prop('disabled', false); $('#loadingSpinner').hide(); $('html, body').animate({ scrollTop: 0 }, 'slow'); }
                });
            });

            $('#resetBtn').on('click', function() {
                $('.alert-success, .alert-warning, .alert-info').remove();
                $('#ipValidationPreview').hide();
            });
        });
    </script>
@endsection
