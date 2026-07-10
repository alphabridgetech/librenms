@extends('layouts.librenmsv1')

@section('title', __('Template Push Configuration'))

@section('content')
    <div class="container-fluid">
        <x-panel>
            <x-slot name="title">
                <i class="fa fa-file-code-o fa-fw fa-lg"></i> {{ __('Template Push Configuration') }}
            </x-slot>

            @if (session('status'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <i class="fa fa-check-circle fa-fw"></i> {{ session('status') }}
                </div>
            @endif

            <div class="row">
                <!-- LEFT: Template Builder -->
                <div class="col-md-5" id="template_builder_col">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong><i class="fa fa-cube"></i> {{ __('Template Builder') }}</strong>
                            <button type="button" class="btn btn-xs btn-link pull-right" data-toggle="collapse" data-target="#builderHelp" style="padding: 0;">
                                <i class="fa fa-question-circle"></i> {{ __('Help') }}
                            </button>
                        </div>
                        <div id="builderHelp" class="collapse">
                            <div class="panel-body" style="border-bottom: 1px solid #ddd; background: #fafafa; font-size: 12px;">
                                <strong>{{ __('How to build a template:') }}</strong>
                                <ol style="padding-left: 20px; margin-bottom: 0;">
                                    <li>{{ __('Give your template a name above.') }}</li>
                                    <li>{{ __('Click') }} <strong>{{ __('Add Field') }}</strong> {{ __('for each value you want to collect.') }}</li>
                                    <li>
                                        <strong>{{ __('Field Types:') }}</strong>
                                        <ul style="padding-left: 18px;">
                                            <li><strong>{{ __('Text/Number') }}</strong> — {{ __('user types a value, use') }} <code>@{{value}}</code> {{ __('in the command') }}</li>
                                            <li><strong>{{ __('Dropdown') }}</strong> — {{ __('user picks from options, format:') }} <code>val:cmd, val2:cmd2</code></li>
                                            <li><strong>{{ __('Checkbox') }}</strong> — {{ __('if checked, command is pushed as-is') }}</li>
                                            <li><strong>{{ __('Put Only Cmd') }}</strong> — {{ __('no user input, command is always pushed') }}</li>
                                            <li><strong>{{ __('Dynamic Rules List') }}</strong> — {{ __('repeater with custom variables, e.g.') }} <code>@{{from}} @{{to}}</code></li>
                                        </ul>
                                    </li>
                                    <li>
                                        <strong>{{ __('Cross-Field References:') }}</strong>
                                        {{ __('Use') }} <code>@{{field:Label}}</code> {{ __('in any command to pull another field\'s value.') }}
                                        <br><em>{{ __('Example:') }}</em> <code>switchport pvid @{{field:VLAN ID}}</code>
                                    </li>
                                    <li>
                                        <strong>{{ __('Dynamic Interface Context:') }}</strong>
                                        {{ __('Use') }} <code>interface @{{interface}}</code> {{ __('to start an interface block. Subsequent commands up to a') }} <code>!</code> {{ __('will repeat for each selected interface.') }}
                                        <br><em>{{ __('Example:') }}</em>
                                        <pre style="margin-top: 5px; margin-bottom: 5px; padding: 5px; font-size: 11px; display: inline-block; background: #fff; border: 1px solid #ddd; border-radius: 3px;">interface @{{interface}}
switchport pvid @{{value}}
!</pre>
                                    </li>
                                    <li>{{ __('Click') }} <strong>{{ __('Save Template') }}</strong> {{ __('to store it, then load it on the right side.') }}</li>
                                </ol>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="builder_template_name">{{ __('Template Name') }}</label>
                                        <input type="text" id="builder_template_name" class="form-control" placeholder="{{ __('My Template') }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="builder_template_folder">{{ __('Folder') }}</label>
                                        <input type="text" id="builder_template_folder" class="form-control" placeholder="{{ __('general') }}">
                                    </div>
                                </div>
                            </div>

                            <div id="builder_fields_container">
                                <p class="text-muted">{{ __('No fields yet. Click "Add Field" to start building your template.') }}</p>
                            </div>

                            <button type="button" class="btn btn-sm btn-success" id="addFieldBtn">
                                <i class="fa fa-plus"></i> {{ __('Add Field') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="saveBuilderTemplateBtn">
                                <i class="fa fa-save"></i> {{ __('Save Template') }}
                            </button>

                            <div id="builder_preview_section" style="margin-top: 20px;">
                                <hr style="margin: 15px 0;">
                                <h5 style="font-weight: bold; margin-bottom: 12px; color: #333;">
                                    <i class="fa fa-eye"></i> {{ __('Commands Preview') }}
                                </h5>
                                <div id="builder_preview_placeholder" class="text-muted" style="font-size: 12px;">
                                    {{ __('Add fields above to see the generated commands preview.') }}
                                </div>
                                <div id="builder_preview_commands_container" style="display: none;">
                                    <pre id="builder_preview_commands" style="max-height: 150px; overflow-y: auto; background: #fafafa; border: 1px solid #ccc; padding: 8px 12px; font-size: 11px; margin-bottom: 0; font-family: monospace; color: #333;"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Push Interface -->
                <div class="col-md-7" id="push_interface_col">
                    <form id="ipUploadForm" method="post" action="{{ route('addhost.template.save') }}" enctype="multipart/form-data" class="form-horizontal" role="form">
                        @csrf

                        @if(!empty($templates))
                        <div class="form-group">
                            <label for="load_template" class="col-sm-3 control-label">{{ __('Load Template') }}</label>
                            <div class="col-sm-9">
                                <select id="load_template" class="form-control">
                                    <option value="">{{ __('-- Select Template --') }}</option>
                                    @foreach($templates as $template)
                                        @php $typeLabel = strtoupper($template['type'] ?? 'other'); @endphp
                                        <option value="{{ json_encode($template) }}" data-type="{{ $template['type'] ?? 'other' }}">[{{ $typeLabel }}] {{ $template['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endif

                        <div id="template_config_section" style="display: none;">
                            <hr>

                            <div class="form-group">
                                <label for="template_name" class="col-sm-3 control-label">{{ __('Template Name') }}</label>
                                <div class="col-sm-6">
                                    <input type="text" name="template_name" id="template_name" class="form-control" placeholder="{{ __('Template Name') }}">
                                </div>
                                <div class="col-sm-3" style="white-space: nowrap;">
                                    <button type="button" class="btn btn-primary btn-sm" id="editTemplateBtn" style="display: none; margin-right: 5px;">
                                        <i class="fa fa-pencil"></i> {{ __('Edit') }}
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" id="deleteTemplateBtn" style="display: none;">
                                        <i class="fa fa-trash"></i> {{ __('Delete') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Device Selection -->
                            <div class="form-group">
                                <label for="device_select" class="col-sm-3 control-label">{{ __('Select Device') }} <span class="text-danger">*</span></label>
                                <div class="col-sm-9">
                                    <select name="device_id" id="device_select" class="form-control" style="width: 100%;" required>
                                        <option value="">{{ __('-- Select Device --') }}</option>
                                        @foreach($devices as $device)
                                            <option value="{{ $device->device_id }}" data-ip="{{ $device->overwrite_ip ?: $device->hostname }}">
                                                {{ $device->sysName ?: $device->hostname }} ({{ $device->overwrite_ip ?: $device->hostname }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div id="interfaces_dynamic_container"></div>
                            <input type="hidden" name="hostname" id="hostname" value="">

                            <hr>

                            <!-- Port Mode Selection (for access/trunk templates) -->
                            <div id="port_mode_group">
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">{{ __('Port Mode') }} <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <div class="btn-group" data-toggle="buttons">
                                            <label class="btn btn-default active" id="mode_access">
                                                <input type="radio" name="port_mode" value="access" checked> {{ __('Access') }}
                                            </label>
                                            <label class="btn btn-default" id="mode_trunk">
                                                <input type="radio" name="port_mode" value="trunk"> {{ __('Trunk') }}
                                            </label>
                                            <label class="btn btn-default" id="mode_custom">
                                                <input type="radio" name="port_mode" value="custom"> {{ __('Custom') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" id="pvid_group">
                                    <label for="pvid" class="col-sm-3 control-label">{{ __('PVID') }} <span class="text-danger">*</span></label>
                                    <div class="col-sm-3">
                                        <input type="number" name="pvid" id="pvid" class="form-control" value="1" min="1" max="4094">
                                    </div>
                                </div>

                                <div class="form-group" id="custom_commands_group" style="display: none;">
                                    <label for="custom_commands" class="col-sm-3 control-label">{{ __('Custom Commands') }} <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <textarea name="custom_commands" id="custom_commands" class="form-control" rows="6" placeholder="{{ __('Enter one command per line...') }}" style="font-family: monospace;"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic Form Fields (for form type templates) -->
                            <div id="dynamic_form_fields"></div>

                            <!-- Commands Preview -->
                            <div class="form-group" id="commandsPreview">
                                <label class="col-sm-3 control-label">{{ __('Commands to Push') }}</label>
                                <div class="col-sm-9">
                                    <pre id="commandsContent" style="max-height: 150px; overflow-y: auto; background: #f5f5f5; padding: 10px; font-size: 12px;"></pre>
                                </div>
                            </div>

                            <input type="hidden" name="direct_commands" id="direct_commands" value="">
                            <input type="hidden" name="use_template_commands" id="use_template_commands" value="1">

                            <div class="form-group">
                                <div class="col-sm-offset-3 col-sm-9">
                                    <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                                        <i class="fa fa-plus"></i> {{ __('Push to Device') }}
                                    </button>
                                    <button type="reset" class="btn btn-default" id="resetBtn">{{ __('Reset') }}</button>
                                    <div id="loadingSpinner" style="display: none; margin-left: 10px;" class="pull-right">
                                        <i class="fa fa-spinner fa-spin"></i> Processing...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </x-panel>
    </div>
@endsection

@section('scripts')
    @parent
    <script type="text/javascript">
        $(document).ready(function() {
            let validIPs = [];
            let pendingTemplateInterfaces = {};
            let interfaceRequestId = 0;
            let builderFieldCount = 0;

            // Development Mode logic from global settings
            const isDevMode = {{ \App\Facades\LibrenmsConfig::get('development_mode') ? 'true' : 'false' }};

            function applyDevelopmentMode() {
                if (isDevMode) {
                    $('#template_builder_col').show();
                    $('#push_interface_col').removeClass('col-md-12').addClass('col-md-7');
                    
                    const val = $('#load_template').val();
                    if (val) {
                        const template = JSON.parse(val);
                        $('#deleteTemplateBtn').show();
                        if (template.type === 'form') {
                            $('#editTemplateBtn').show();
                        } else {
                            $('#editTemplateBtn').hide();
                        }
                    }
                } else {
                    $('#template_builder_col').hide();
                    $('#push_interface_col').removeClass('col-md-7').addClass('col-md-12');
                    $('#deleteTemplateBtn').hide();
                    $('#editTemplateBtn').hide();
                }
            }

            // Run on load
            applyDevelopmentMode();

            $('#device_select').select2({
                placeholder: '-- Select Device --',
                allowClear: true
            });

            function getTemplateVariables(templateStr) {
                const vars = [];
                const regex = /\{\{([^}]+)\}\}/g;
                let match;
                while ((match = regex.exec(templateStr)) !== null) {
                    const varName = match[1].trim();
                    if (varName !== 'interface' && !varName.startsWith('field:')) {
                        if (!vars.includes(varName)) {
                            vars.push(varName);
                        }
                    }
                }
                return vars;
            }

            // =============================================
            // LEFT: Template Builder
            // =============================================
            $('#addFieldBtn').on('click', function() {
                addBuilderField();
            });

            $('#builder_template_name').on('input', function() {
                updateBuilderPreview();
            });

            function addBuilderField(data) {
                const id = ++builderFieldCount;
                const label = data ? data.label : '';
                const type = data ? data.type : 'text';
                const options = data ? (data.options || '') : '';
                const command = data ? data.command : '';

                const html = `
                    <div class="builder-field well well-sm" data-field-id="${id}">
                        <div class="row">
                            <div class="col-sm-4">
                                <label>{{ __('Label') }}</label>
                                <input type="text" class="form-control input-sm field-label" value="${_.escape(label)}" placeholder="{{ __('Field Label') }}">
                            </div>
                            <div class="col-sm-3">
                                <label>{{ __('Type') }}</label>
                                <select class="form-control input-sm field-type">
                                    <option value="text" ${type === 'text' ? 'selected' : ''}>{{ __('Text') }}</option>
                                    <option value="number" ${type === 'number' ? 'selected' : ''}>{{ __('Number') }}</option>
                                    <option value="dropdown" ${type === 'dropdown' ? 'selected' : ''}>{{ __('Dropdown') }}</option>
                                    <option value="checkbox" ${type === 'checkbox' ? 'selected' : ''}>{{ __('Checkbox') }}</option>
                                    <option value="putonlycmd" ${type === 'putonlycmd' ? 'selected' : ''}>{{ __('Put Only Cmd') }}</option>
                                    <option value="dynamic_list" ${type === 'dynamic_list' ? 'selected' : ''}>{{ __('Dynamic Rules List') }}</option>
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label>{{ __('Dropdown Options') }}</label>
                                <input type="text" class="form-control input-sm field-options" value="${_.escape(options)}" placeholder="{{ __('val:cmd, val2:cmd2') }}">
                                <span class="help-block" style="font-size: 11px; margin-bottom: 0;">{{ __('Use') }} <code>value:command</code> {{ __('for per-option commands, or just') }} <code>value</code> {{ __('to use the template') }}</span>
                            </div>
                            <div class="col-sm-1">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-danger btn-sm remove-field-btn">&times;</button>
                            </div>
                        </div>
                        <div class="row" style="margin-top: 6px;">
                            <div class="col-sm-12">
                                <label>{{ __('Command Template') }}</label>
                                <input type="text" class="form-control input-sm field-command" value="${_.escape(command)}" placeholder="e.g. switchport pvid @{{value}}">
                                <span class="help-block" style="font-size: 11px; margin-bottom: 0;">{{ __('Use') }} <code>@{{value}}</code> {{ __('where the field value should be inserted. You can also use') }} <code>interface @{{interface}}</code> {{ __('to start an interface block.') }}</span>
                            </div>
                        </div>
                    </div>
                `;

                if (data) {
                    if ($('#builder_fields_container p.text-muted').length) {
                        $('#builder_fields_container').empty();
                    }
                    $('#builder_fields_container').append(html);
                } else {
                    if ($('#builder_fields_container p.text-muted').length) {
                        $('#builder_fields_container').empty();
                    }
                    $('#builder_fields_container').append(html);
                }

                const $lastField = $(`#builder_fields_container .builder-field:last`);
                $lastField.find('.field-type').on('change', function() {
                    const val = $(this).val();
                    const $label = $(this).closest('.builder-field').find('.field-label').closest('.col-sm-4');
                    const $options = $(this).closest('.builder-field').find('.field-options').closest('.col-sm-4');
                    const $command = $(this).closest('.builder-field').find('.field-command').closest('.row');
                    const $commandInput = $(this).closest('.builder-field').find('.field-command');
                    const $help = $(this).closest('.builder-field').find('.field-command').siblings('.help-block');

                    if (val === 'dropdown') {
                        $label.show();
                        $options.show();
                        $command.show();
                        $commandInput.attr('placeholder', 'e.g. switchport pvid @{{value}}');
                        $help.html('Use <code>@{{value}}</code> where the field value should be inserted. You can also use <code>interface @{{interface}}</code> to start an interface block.');
                    } else if (val === 'checkbox') {
                        $label.show();
                        $options.hide();
                    } else if (val === 'putonlycmd') {
                        $label.hide();
                        $options.hide();
                        $command.show();
                        $commandInput.attr('placeholder', 'e.g. switchport pvid @{{value}}');
                        $help.html('Use <code>@{{value}}</code> where the field value should be inserted. You can also use <code>interface @{{interface}}</code> to start an interface block.');
                    } else if (val === 'dynamic_list') {
                        $label.show();
                        $options.hide();
                        $command.show();
                        $commandInput.attr('placeholder', 'e.g. switchport dot1q-translating-tunnel mode QinQ translate @{{from}} @{{to}}');
                        $help.html('Use variables like <code>@{{from}}</code>, <code>@{{to}}</code>, etc. A row layout will be generated dynamically for each variable.');
                    } else {
                        $label.show();
                        $options.hide();
                        $command.show();
                        $commandInput.attr('placeholder', 'e.g. switchport pvid @{{value}}');
                        $help.html('Use <code>@{{value}}</code> where the field value should be inserted. You can also use <code>interface @{{interface}}</code> to start an interface block.');
                    }
                });
                $lastField.find('.field-type').trigger('change');

                $lastField.find('.field-label, .field-type, .field-options, .field-command').on('input change', function() {
                    updateBuilderPreview();
                });

                $lastField.find('.remove-field-btn').on('click', function() {
                    $(this).closest('.builder-field').remove();
                    if ($('#builder_fields_container').children().length === 0) {
                        $('#builder_fields_container').html('<p class="text-muted">{{ __('No fields yet. Click "Add Field" to start building your template.') }}</p>');
                    }
                    updateBuilderPreview();
                });

                updateBuilderPreview();
            }

            function collectBuilderFields() {
                const fields = [];
                $('#builder_fields_container .builder-field').each(function() {
                    fields.push({
                        label: $(this).find('.field-label').val(),
                        type: $(this).find('.field-type').val(),
                        options: $(this).find('.field-options').val(),
                        command: $(this).find('.field-command').val()
                    });
                });
                return fields;
            }

            function updateBuilderPreview() {
                const fields = collectBuilderFields();

                if (fields.length === 0) {
                    $('#builder_preview_placeholder').show();
                    $('#builder_preview_commands_container').hide();
                    return;
                }

                $('#builder_preview_placeholder').hide();
                $('#builder_preview_commands_container').show();

                // Generate commands preview (with dummy/placeholder values)
                let coreCommands = [];
                const fieldValues = {};
                fields.forEach(function(field, idx) {
                    let labelText = field.label || '';
                    if (!labelText && field.type !== 'putonlycmd') {
                        labelText = 'Field ' + (idx + 1);
                    }
                    if (labelText) {
                        fieldValues[labelText] = labelText;
                    }
                });

                fields.forEach(function(field, idx) {
                    let labelText = field.label || '';
                    if (!labelText && field.type !== 'putonlycmd') {
                        labelText = 'Field ' + (idx + 1);
                    }
                    let commandTemplate = field.command || '';

                    commandTemplate = commandTemplate.replace(/\{\{field:([^}]+)\}\}/g, function(match, label) {
                        const trimmed = label.trim();
                        return fieldValues[trimmed] || match;
                    });

                    if (field.type === 'checkbox') {
                        if (commandTemplate) {
                            coreCommands.push(commandTemplate);
                        }
                        return;
                    }

                    if (field.type === 'putonlycmd') {
                        if (commandTemplate) {
                            coreCommands.push(commandTemplate);
                        }
                        return;
                    }

                    let val = labelText;
                    let cmd = '';
                    if (field.type === 'dropdown') {
                        if (commandTemplate) {
                            cmd = commandTemplate.replace(/\{\{value\}\}/g, val);
                        } else {
                            const options = (field.options || '').split(',').map(o => o.trim()).filter(o => o);
                            const cmds = [];
                            options.forEach(o => {
                                const parts = o.split(':', 2);
                                if (parts.length === 2) {
                                    cmds.push(parts[1].trim());
                                } else {
                                    cmds.push(parts[0].trim());
                                }
                            });
                            if (cmds.length > 0) {
                                cmd = cmds.join(' | ');
                            }
                        }
                    } else if (field.type === 'dynamic_list') {
                        let cmdTemp = field.command || '';
                        const vars = getTemplateVariables(cmdTemp);
                        if (vars.length === 0) {
                            if (cmdTemp) coreCommands.push(cmdTemp);
                            return;
                        }
                        let rule = cmdTemp;
                        vars.forEach(v => {
                            const varLabel = v.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                            const reg = new RegExp('\\{\\{' + v + '\\}\\}', 'g');
                            rule = rule.replace(reg, varLabel);
                        });
                        coreCommands.push(rule);
                        return;
                    } else {
                        if (commandTemplate) {
                            cmd = commandTemplate.replace(/\{\{value\}\}/g, val);
                        }
                    }

                    if (cmd) coreCommands.push(cmd);
                });

                $('#builder_preview_commands').text(coreCommands.join('\n') || 'No commands templates defined.');
            }

            $('#saveBuilderTemplateBtn').on('click', function() {
                const name = $('#builder_template_name').val();
                if (!name) {
                    alert('Please enter a template name');
                    return;
                }
                const fields = collectBuilderFields();
                if (fields.length === 0) {
                    alert('Please add at least one field');
                    return;
                }

                const data = {
                    _token: '{{ csrf_token() }}',
                    template_name: name,
                    template_folder: $('#builder_template_folder').val() || '',
                    port_mode: 'form',
                    fields: fields
                };

                $.ajax({
                    url: '{{ route("addhost.template.store") }}',
                    type: 'POST',
                    data: JSON.stringify(data),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while saving');
                    }
                });
            });

            // =============================================
            // RIGHT: Load + Use Template
            // =============================================
            function generateCommands() {
                let coreCommands = [];

                // Only generate port mode commands if the port mode section is visible
                if ($('#port_mode_group').is(':visible')) {
                    const mode = $('input[name="port_mode"]:checked').val();
                    const pvid = $('#pvid').val() || '1';

                    if (mode === 'access') {
                        coreCommands.push('switchport mode access');
                        coreCommands.push('switchport pvid ' + pvid);
                    } else if (mode === 'trunk') {
                        coreCommands.push('switchport mode trunk');
                        coreCommands.push('switchport pvid ' + pvid);
                    } else if (mode === 'custom') {
                        const custom = $('#custom_commands').val();
                        coreCommands = custom.split('\n').filter(c => c.trim() !== '');
                    }
                }

                // Collect values from dynamic form fields (for form type templates)
                const fieldValues = {};
                $('#dynamic_form_fields .dynamic-field-row').each(function() {
                    const $field = $(this);
                    const label = $field.data('label') || '';
                    const type = $field.data('type');

                    if (type === 'putonlycmd' || type === 'checkbox') return;

                    const rawValue = $field.find('.dynamic-field-value').val();
                    if (rawValue) {
                        let val = rawValue;
                        if (type === 'dropdown') {
                            const parts = rawValue.split(':', 2);
                            val = parts[0].trim();
                        }
                        if (label) fieldValues[label] = val;
                    } else {
                        if (label) fieldValues[label] = label;
                    }
                });

                $('#dynamic_form_fields .dynamic-field-row').each(function() {
                    const $field = $(this);
                    let commandTemplate = $field.data('command') || '';
                    const type = $field.data('type');
                    const label = $field.data('label') || '';

                    if (type === 'dynamic_list') {
                        const $rulesContainer = $field.find('.dynamic-list-field-container');
                        const $ruleRows = $rulesContainer.find('.dynamic-row-item');
                        $ruleRows.each(function() {
                            let cmd = commandTemplate;
                            $(this).find('.dynamic-row-input').each(function() {
                                const varName = $(this).data('var');
                                const val = $(this).val() || '';
                                const varLabel = varName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                                const displayVal = val.trim() !== '' ? val.trim() : varLabel;
                                const reg = new RegExp('\\{\\{' + varName + '\\}\\}', 'g');
                                cmd = cmd.replace(reg, displayVal);
                            });
                            coreCommands.push(cmd);
                        });
                        return;
                    }

                    commandTemplate = commandTemplate.replace(/\{\{field:([^}]+)\}\}/g, function(match, label) {
                        const trimmed = label.trim();
                        return fieldValues[trimmed] || match;
                    });

                    if (type === 'checkbox') {
                        const checked = $field.find('.dynamic-field-value').is(':checked');
                        if (checked && commandTemplate) {
                            coreCommands.push(commandTemplate);
                        }
                        return;
                    }

                    if (type === 'putonlycmd') {
                        if (commandTemplate) {
                            coreCommands.push(commandTemplate);
                        }
                        return;
                    }

                    const rawValue = $field.find('.dynamic-field-value').val();
                    const isFilled = (rawValue && rawValue.trim() !== '');
                    let val = isFilled ? (type === 'dropdown' ? rawValue.split(':', 2)[0].trim() : rawValue) : ($field.data('label') || 'value');

                    if (isFilled && (label === 'Ingress (1= 64kbps)' || label === 'Egress (1= 64kbps)')) {
                        let valNum = parseFloat(val);
                        if (isNaN(valNum) || valNum < 64) {
                            valNum = 64;
                        }
                        val = Math.floor(valNum / 64).toString();
                    }

                    let cmd = '';
                    if (type === 'dropdown') {
                        if (rawValue) {
                            const parts = rawValue.split(':', 2);
                            if (parts.length === 2) {
                                cmd = parts[1].trim();
                            } else if (commandTemplate) {
                                cmd = commandTemplate.replace(/\{\{value\}\}/g, parts[0].trim());
                            }
                        } else {
                            if (commandTemplate) {
                                cmd = commandTemplate.replace(/\{\{value\}\}/g, val);
                            } else {
                                // Extract commands from options for placeholder preview
                                const optionsStr = $field.data('options') || '';
                                const optionsList = optionsStr.split(',').map(o => o.trim()).filter(o => o);
                                const cmds = [];
                                optionsList.forEach(o => {
                                    const parts = o.split(':', 2);
                                    if (parts.length === 2) {
                                        cmds.push(parts[1].trim());
                                    } else {
                                        cmds.push(parts[0].trim());
                                    }
                                });
                                if (cmds.length > 0) {
                                    cmd = cmds.join(' | ');
                                }
                            }
                        }
                    } else {
                        if (commandTemplate) {
                            cmd = commandTemplate.replace(/\{\{value\}\}/g, val);
                        }
                    }

                    if (cmd) coreCommands.push(cmd);
                });

                // Wrap with interface context
                let finalCommands = [];
                const selectedIfaces = $('.device-interface-select').val();

                if (selectedIfaces && selectedIfaces.length > 0) {
                    const blocks = [];
                    const hasInterfaceVar = coreCommands.some(cmd => cmd.indexOf('@{{interface}}') !== -1);

                    if (!hasInterfaceVar) {
                        // Legacy fallback: wrap everything in virtual interface blocks
                        blocks.push({
                            type: 'interface',
                            commands: ['interface @{{interface}}'].concat(coreCommands)
                        });
                    } else {
                        // Dynamic sequential block parsing
                        let currentBlock = { type: 'global', commands: [] };

                        coreCommands.forEach(function(cmd) {
                            const trimmed = cmd.trim();
                            // If it contains @{{interface}}, it starts a new interface block
                            if (cmd.indexOf('@{{interface}}') !== -1) {
                                if (currentBlock.commands.length > 0) {
                                    blocks.push(currentBlock);
                                }
                                currentBlock = { type: 'interface', commands: [cmd] };
                            }
                            // If it is '!', it resets/exits to a new global block
                            else if (trimmed === '!') {
                                if (currentBlock.commands.length > 0) {
                                    blocks.push(currentBlock);
                                }
                                currentBlock = { type: 'global', commands: [cmd] };
                            }
                            // Otherwise, it continues the current block (global or interface)
                            else {
                                currentBlock.commands.push(cmd);
                            }
                        });
                        if (currentBlock.commands.length > 0) {
                            blocks.push(currentBlock);
                        }
                    }

                    // Generate final commands by iterating through blocks in order
                    blocks.forEach(function(block) {
                        if (block.type === 'global') {
                            block.commands.forEach(function(cmd) {
                                finalCommands.push(cmd);
                            });
                        } else if (block.type === 'interface') {
                            selectedIfaces.forEach(function(iface) {
                                block.commands.forEach(function(cmd) {
                                    finalCommands.push(cmd.replace(/\{\{interface\}\}/g, iface));
                                });
                            });
                        }
                    });
                } else {
                    coreCommands.forEach(function(cmd) {
                        finalCommands.push(cmd.replace(/\{\{interface\}\}/g, ''));
                    });
                }

                $('#commandsContent').text(finalCommands.join('\n'));
                $('#direct_commands').val(finalCommands.join('\n'));
                return finalCommands.length > 0;
            }

            function updateSelectedCount() {
                validIPs = [];
                const selected = $('#device_select').find(':selected');
                const ip = selected.data('ip');
                if (ip) {
                    validIPs.push(ip);
                }

                $('#hostname').val(validIPs.join('\n'));

                const hasCommands = generateCommands();
                
                let hasValidationErrors = false;
                $('.validation-error-msg:visible').each(function() {
                    hasValidationErrors = true;
                });

                $('#submitBtn').prop('disabled', !(validIPs.length > 0 && hasCommands && !hasValidationErrors));
            }

            $('input[name="port_mode"]').on('change', function() {
                const mode = $(this).val();
                if (mode === 'custom') {
                    $('#pvid_group').hide();
                    $('#custom_commands_group').show();
                } else {
                    $('#pvid_group').show();
                    $('#custom_commands_group').hide();
                }
                updateSelectedCount();
            });

            $('#pvid').on('input', function() { updateSelectedCount(); });
            $('#custom_commands').on('input', function() { updateSelectedCount(); });

            $('#device_select').on('change', function() {
                updateSelectedCount();
                fetchInterfacesForDevices();
            });

            $(document).on('change', '.device-interface-select', function() {
                updateSelectedCount();
            });

            function fetchInterfacesForDevices() {
                const selectedDeviceId = $('#device_select').val();
                const $interfaceContainer = $('#interfaces_dynamic_container');
                const requestId = ++interfaceRequestId;

                if (!selectedDeviceId) {
                    $interfaceContainer.empty();
                    return;
                }

                $interfaceContainer.html('<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><i class="fa fa-spinner fa-spin"></i> Loading interfaces...</div></div>');

                $.ajax({
                    url: "{{ route('addhost.ports.fetch') }}",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        device_ids: [selectedDeviceId]
                    },
                    success: function(response) {
                        if (requestId !== interfaceRequestId) return;
                        $interfaceContainer.empty();

                        if (response.success && response.ports.length > 0) {
                            const deviceGroup = response.ports[0];
                            const devId = deviceGroup.device_id;
                            const saved = pendingTemplateInterfaces[devId] || [];
                            const html = `
                                <div class="form-group interface-row" id="group_device_${devId}">
                                    <label class="col-sm-3 control-label">${deviceGroup.text} Interfaces</label>
                                    <div class="col-sm-9">
                                        <select name="selected_interfaces[${devId}][]"
                                                class="form-control device-interface-select"
                                                data-device-id="${devId}"
                                                multiple="multiple" style="width: 100%;">
                                            ${deviceGroup.children.map(p => `<option value="${p.id}">${p.text}</option>`).join('')}
                                        </select>
                                    </div>
                                </div>
                            `;
                            $interfaceContainer.append(html);

                            $(`#group_device_${devId} .device-interface-select`).select2({
                                placeholder: '-- Select Interfaces --',
                                allowClear: true
                            });

                            if (saved.length > 0) {
                                $(`#group_device_${devId} .device-interface-select`).val(saved).trigger('change');
                            }

                            pendingTemplateInterfaces = {};
                        }
                    },
                    error: function() {
                        if (requestId !== interfaceRequestId) return;
                        $interfaceContainer.html('<div class="form-group"><div class="col-sm-offset-3 col-sm-9" class="text-danger">Failed to load interfaces</div></div>');
                    }
                });
            }

            function renderDynamicFormFields(fields) {
                const $container = $('#dynamic_form_fields');
                $container.empty();

                if (!fields || fields.length === 0) {
                    $container.hide();
                    return;
                }

                $container.show();
                fields.forEach(function(field) {
                    let inputHtml = '';
                    if (field.type === 'dropdown') {
                        const options = (field.options || '').split(',').map(function(o) { return o.trim(); }).filter(function(o) { return o; });
                        let opts = '<option value="">-- Select --</option>';
                        options.forEach(function(o) {
                            const parts = o.split(':', 2);
                            const val = parts[0].trim();
                            const display = parts[0].trim();
                            opts += '<option value="' + _.escape(o) + '">' + _.escape(display) + '</option>';
                        });
                        inputHtml = '<select class="form-control dynamic-field-value" style="width: 100%;">' + opts + '</select>';
                    } else if (field.type === 'number') {
                        inputHtml = '<input type="number" class="form-control dynamic-field-value" placeholder="' + _.escape(field.label) + '">';
                    } else if (field.type === 'checkbox') {
                        inputHtml = '<input type="checkbox" class="dynamic-field-value" value="1"> <span>{{ __('Enable') }}</span>';
                    } else if (field.type === 'putonlycmd') {
                        inputHtml = '<input type="hidden" class="dynamic-field-value" value="1"><code class="help-block" style="font-size: 12px; margin-top: 5px;">' + _.escape(field.command || '') + '</code>';
                    } else if (field.type === 'dynamic_list') {
                        inputHtml = `
                            <div class="dynamic-list-field-container" data-label="${_.escape(field.label)}" data-command="${_.escape(field.command || '')}">
                                <div class="dynamic-rules-list"></div>
                                <button type="button" class="btn btn-xs btn-success add-dynamic-rule-btn" style="margin-top: 5px;">
                                    <i class="fa fa-plus"></i> {{ __('Add Row') }}
                                </button>
                            </div>
                        `;
                    } else {
                        if (field.label === 'Ingress (1= 64kbps)' || field.label === 'Egress (1= 64kbps)') {
                            inputHtml = '<input type="number" class="form-control dynamic-field-value" placeholder="' + _.escape(field.label) + '" min="64">';
                        } else {
                            inputHtml = '<input type="text" class="form-control dynamic-field-value" placeholder="' + _.escape(field.label) + '">';
                        }
                    }

                    const row = `
                        <div class="form-group dynamic-field-row" data-label="${_.escape(field.label)}" data-type="${_.escape(field.type)}" data-command="${_.escape(field.command)}" data-options="${_.escape(field.options || '')}">
                            <label class="col-sm-3 control-label">${_.escape(field.label)} <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                ${inputHtml}
                            </div>
                        </div>
                    `;
                    $container.append(row);

                    if (field.type === 'dynamic_list') {
                        const $fieldContainer = $container.find('.dynamic-field-row:last .dynamic-list-field-container');
                        const commandTemplate = field.command || '';
                        const vars = getTemplateVariables(commandTemplate);

                        function addDynamicRuleRow(initialVals) {
                            const $list = $fieldContainer.find('.dynamic-rules-list');
                            let inputsHtml = '';
                            
                            let colWidth = 10;
                            if (vars.length > 0) {
                                colWidth = Math.floor(10 / vars.length);
                                if (colWidth < 2) colWidth = 2;
                            }
                            
                            vars.forEach(v => {
                                const varLabel = v.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                                const val = (initialVals && initialVals[v]) ? initialVals[v] : '';
                                inputsHtml += `
                                    <div class="col-sm-${colWidth}" style="margin-bottom: 5px;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-addon" style="font-size: 11px;">${_.escape(varLabel)}</span>
                                            <input type="text" class="form-control dynamic-row-input" data-var="${_.escape(v)}" placeholder="${_.escape(varLabel)}" value="${_.escape(val)}">
                                        </div>
                                    </div>
                                `;
                            });

                            const deleteColWidth = 12 - (colWidth * vars.length);
                            const actualDeleteColWidth = deleteColWidth > 0 ? deleteColWidth : 2;

                            const ruleRowHtml = `
                                <div class="dynamic-row-item well well-sm" style="margin-bottom: 8px; padding: 10px; background-color: #fbfbfb;">
                                    <div class="row">
                                        ${inputsHtml}
                                        <div class="col-sm-${actualDeleteColWidth} text-right">
                                            <button type="button" class="btn btn-sm btn-danger remove-dynamic-rule-btn" style="padding: 2px 8px;">
                                                <i class="fa fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            $list.append(ruleRowHtml);
                            
                            $list.find('.dynamic-row-item:last input').on('input change', function() {
                                updateSelectedCount();
                            });
                            $list.find('.dynamic-row-item:last .remove-dynamic-rule-btn').on('click', function() {
                                $(this).closest('.dynamic-row-item').remove();
                                updateSelectedCount();
                            });

                            updateSelectedCount();
                        }

                        $fieldContainer.find('.add-dynamic-rule-btn').on('click', function() {
                            addDynamicRuleRow({});
                        });

                        addDynamicRuleRow({});
                    }

                    if (field.type === 'dropdown') {
                        $container.find('.dynamic-field-row:last .dynamic-field-value').select2({
                            placeholder: '-- Select --',
                            allowClear: true
                        });
                    }
                });

                $container.find('.dynamic-field-value').on('change input', function() {
                    const $input = $(this);
                    const $row = $input.closest('.dynamic-field-row');
                    const label = $row.data('label') || '';
                    
                    if (label === 'Ingress (1= 64kbps)' || label === 'Egress (1= 64kbps)') {
                        const val = $input.val();
                        let $errorSpan = $row.find('.validation-error-msg');
                        if ($errorSpan.length === 0) {
                            $errorSpan = $('<span class="text-danger validation-error-msg" style="display: block; font-size: 11px; margin-top: 5px;"></span>');
                            $input.after($errorSpan);
                        }

                        if (val && val.trim() !== '') {
                            const valNum = parseFloat(val);
                            if (isNaN(valNum)) {
                                $errorSpan.text('Value must be a number').show();
                            } else if (valNum < 64) {
                                $errorSpan.text('Value cannot be less than 64').show();
                            } else {
                                $errorSpan.hide();
                            }
                        } else {
                            $errorSpan.hide();
                        }
                    }
                    updateSelectedCount();
                });
                $container.find('input[type="checkbox"].dynamic-field-value').on('change', function() {
                    updateSelectedCount();
                });
            }

            $('#load_template').on('change', function() {
                const val = $(this).val();
                if (val) {
                    const template = JSON.parse(val);
                    const type = template.type || 'other';

                    $('#template_name').val(template.name);
                    $('#template_config_section').show();
                    if (isDevMode) {
                        $('#deleteTemplateBtn').show();
                        if (type === 'form') {
                            $('#editTemplateBtn').show();
                        } else {
                            $('#editTemplateBtn').hide();
                        }
                    } else {
                        $('#deleteTemplateBtn').hide();
                        $('#editTemplateBtn').hide();
                    }

                    if (template.interfaces) {
                        pendingTemplateInterfaces = template.interfaces;
                    } else {
                        pendingTemplateInterfaces = {};
                    }

                    const templateIPs = template.hostname ? template.hostname.split(/\r?\n/) : [];
                    if (templateIPs.length > 0) {
                        const targetIp = templateIPs[0].trim();
                        $('#device_select option').each(function() {
                            const optionIp = $(this).data('ip');
                            if (optionIp === targetIp) {
                                $('#device_select').val($(this).val()).trigger('change');
                            }
                        });
                    }

                    if (type === 'form' && template.fields) {
                        $('#port_mode_group').hide();
                        renderDynamicFormFields(template.fields);
                        updateSelectedCount();
                    } else {
                        $('#dynamic_form_fields').empty().hide();
                        $('#port_mode_group').show();

                        if (type === 'access') {
                            $('input[name="port_mode"][value="access"]').prop('checked', true).trigger('change');
                            $('#mode_access').addClass('active');
                            $('#mode_trunk').removeClass('active');
                            $('#mode_custom').removeClass('active');
                            if (template.pvid) $('#pvid').val(template.pvid);
                            updateSelectedCount();
                        } else if (type === 'trunk') {
                            $('input[name="port_mode"][value="trunk"]').prop('checked', true).trigger('change');
                            $('#mode_trunk').addClass('active');
                            $('#mode_access').removeClass('active');
                            $('#mode_custom').removeClass('active');
                            if (template.pvid) $('#pvid').val(template.pvid);
                            updateSelectedCount();
                        } else if (type === 'custom') {
                            $('input[name="port_mode"][value="custom"]').prop('checked', true).trigger('change');
                            $('#mode_custom').addClass('active');
                            $('#mode_access').removeClass('active');
                            $('#mode_trunk').removeClass('active');
                            if (template.commands && template.commands.length > 0) {
                                $('#custom_commands').val(template.commands.join('\n'));
                            }
                            updateSelectedCount();
                        } else {
                            if (template.commands && template.commands.length > 0) {
                                $('#commandsContent').text(template.commands.join('\n'));
                                $('#direct_commands').val(template.commands.join('\n'));
                            }
                            updateSelectedCount();
                        }
                    }
                } else {
                    $('#template_name').val('');
                    $('#template_config_section').hide();
                    $('#deleteTemplateBtn').hide();
                    $('#editTemplateBtn').hide();
                    $('#dynamic_form_fields').empty().hide();
                    $('#port_mode_group').show();
                    $('#device_select').val('').trigger('change');
                    $('#pvid').val('1');
                    $('input[name="port_mode"][value="access"]').prop('checked', true).trigger('change');
                    $('#mode_access').addClass('active');
                    $('#mode_trunk').removeClass('active');
                    $('#mode_custom').removeClass('active');
                    updateSelectedCount();
                }
            });

            // Edit template handler
            $('#editTemplateBtn').on('click', function() {
                const val = $('#load_template').val();
                if (!val) return;
                const template = JSON.parse(val);

                if (!confirm('Load template "' + template.name + '" into builder for editing? This will overwrite your current unsaved builder progress.')) return;

                $('#builder_template_name').val(template.name);
                $('#builder_template_folder').val(template.template_folder || '');
                $('#builder_fields_container').empty();
                if (template.fields) {
                    template.fields.forEach(function(field) {
                        addBuilderField(field);
                    });
                }
                updateBuilderPreview();
                $('html, body').animate({ scrollTop: $('#builder_template_name').offset().top - 20 }, 'slow');
            });

            // Delete template handler
            $('#deleteTemplateBtn').on('click', function() {
                const name = $('#template_name').val();
                if (!name) return;
                if (!confirm('Delete template "' + name + '"?')) return;

                let folder = '';
                const val = $('#load_template').val();
                if (val) {
                    try {
                        const template = JSON.parse(val);
                        folder = template.template_folder || '';
                    } catch (e) {}
                }

                $.ajax({
                    url: '{{ route("addhost.template.delete") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        template_name: name,
                        template_folder: folder
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Template deleted! Refreshing page...');
                            location.reload();
                        } else {
                            alert('Failed: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Failed to delete template');
                    }
                });
            });

            // Submit handler
            $('#ipUploadForm').on('submit', function(e) {
                e.preventDefault();

                // Validate dynamic fields first
                let missingFields = [];
                $('#dynamic_form_fields .dynamic-field-row').each(function() {
                    const type = $(this).data('type');
                    const label = $(this).data('label') || 'Field';
                    if (type === 'putonlycmd' || type === 'checkbox') return;
                    
                    if (type === 'dynamic_list') {
                        const $rulesContainer = $(this).find('.dynamic-list-field-container');
                        const $ruleRows = $rulesContainer.find('.dynamic-row-item');
                        if ($ruleRows.length === 0) {
                            missingFields.push(label + ' (Please add at least one row)');
                            return;
                        }
                        let hasEmptyField = false;
                        $ruleRows.each(function() {
                            $(this).find('.dynamic-row-input').each(function() {
                                if ($(this).val().trim() === '') {
                                    hasEmptyField = true;
                                }
                            });
                        });
                        if (hasEmptyField) {
                            missingFields.push(label + ' (Please fill in all row values)');
                        }
                        return;
                    }

                    const val = $(this).find('.dynamic-field-value').val();
                    if (!val || val.trim() === '') {
                        missingFields.push(label);
                    } else if (label === 'Ingress (1= 64kbps)' || label === 'Egress (1= 64kbps)') {
                        const valNum = parseFloat(val);
                        if (isNaN(valNum)) {
                            missingFields.push(label + ' (Value must be a number)');
                        } else if (valNum < 64) {
                            missingFields.push(label + ' (Value cannot be less than 64)');
                        }
                    }
                });

                if (missingFields.length > 0) {
                    alert('Please fill in the following required fields:\n- ' + missingFields.join('\n- '));
                    return;
                }

                if (!confirm('Process ' + validIPs.length + ' device(s)?')) return;

                $('#submitBtn').prop('disabled', true);
                $('#loadingSpinner').show();
                $('.alert-success, .alert-warning, .alert-info').remove();

                const processingHtml = '<div class="alert alert-info" id="processingAlert"><i class="fa fa-spinner fa-spin"></i> Processing...</div>';
                $('.panel:first').before(processingHtml);

                const formData = new FormData(this);
                formData.append('valid_ips', JSON.stringify(validIPs));
                if (validIPs.length > 0) {
                    formData.append('hostname', validIPs[0]);
                }

                $('.device-interface-select').each(function() {
                    const devId = $(this).data('device-id');
                    const selected = $(this).val();
                    if (selected && selected.length > 0) {
                        selected.forEach(function(iface) {
                            formData.append('selected_interfaces[' + devId + '][]', iface);
                        });
                    }
                });

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#processingAlert').remove();
                        const type = response.success ? 'success' : 'warning';
                        const alertHtml = '<div class="alert alert-' + type + ' alert-dismissible" role="alert">' +
                            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                            '<strong>' + (type === 'success' ? 'Success!' : 'Warning!') + '</strong> ' + response.message +
                            '<pre class="mt-2" style="max-height: 200px; overflow-y: auto;">' + JSON.stringify(response.results, null, 2) + '</pre>' +
                            '</div>';
                        $('.panel:first').before(alertHtml);
                    },
                    error: function() {
                        $('#processingAlert').remove();
                        alert('An error occurred');
                    },
                    complete: function() {
                        $('#loadingSpinner').hide();
                        $('#submitBtn').prop('disabled', false);
                        $('html, body').animate({ scrollTop: 0 }, 'slow');
                    }
                });
            });

            $('#resetBtn').on('click', function() {
                $('.alert-success, .alert-warning, .alert-info').remove();
                $('#device_select').val('').trigger('change');
                $('#interfaces_dynamic_container').empty();
                $('input[name="port_mode"][value="access"]').prop('checked', true).trigger('change');
                $('#mode_access').addClass('active');
                $('#mode_trunk').removeClass('active');
                $('#mode_custom').removeClass('active');
                $('#pvid').val('1');
                updateSelectedCount();
            });

            updateSelectedCount();
        });
    </script>

#
@endsection