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
                <div class="col-md-5">
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
                                        </ul>
                                    </li>
                                    <li>
                                        <strong>{{ __('Cross-Field References:') }}</strong>
                                        {{ __('Use') }} <code>@{{field:Label}}</code> {{ __('in any command to pull another field\'s value.') }}
                                        <br><em>{{ __('Example:') }}</em> <code>switchport pvid @{{field:VLAN ID}}</code>
                                    </li>
                                    <li>{{ __('Click') }} <strong>{{ __('Save Template') }}</strong> {{ __('to store it, then load it on the right side.') }}</li>
                                </ol>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label for="builder_template_name">{{ __('Template Name') }}</label>
                                <input type="text" id="builder_template_name" class="form-control" placeholder="{{ __('My Template') }}">
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
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Push Interface -->
                <div class="col-md-7">
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
                                <div class="col-sm-3">
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
                                                {{ $device->hostname }} ({{ $device->overwrite_ip ?: $device->hostname }})
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

            // =============================================
            // LEFT: Template Builder
            // =============================================
            $('#addFieldBtn').on('click', function() {
                addBuilderField();
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
                                <span class="help-block" style="font-size: 11px; margin-bottom: 0;">{{ __('Use') }} <code>@{{value}}</code> {{ __('where the field value should be inserted') }}</span>
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

                $(`#builder_fields_container .builder-field:last .field-type`).on('change', function() {
                    const val = $(this).val();
                    const $label = $(this).closest('.builder-field').find('.field-label').closest('.col-sm-4');
                    const $options = $(this).closest('.builder-field').find('.field-options').closest('.col-sm-4');
                    const $command = $(this).closest('.builder-field').find('.field-command').closest('.row');
                    if (val === 'dropdown') {
                        $label.show();
                        $options.show();
                        $command.show();
                    } else if (val === 'checkbox') {
                        $label.show();
                        $options.hide();
                    } else if (val === 'putonlycmd') {
                        $label.hide();
                        $options.hide();
                        $command.show();
                    } else {
                        $label.show();
                        $options.hide();
                        $command.show();
                    }
                });
                $(`#builder_fields_container .builder-field:last .field-type`).trigger('change');

                $(`#builder_fields_container .builder-field:last .remove-field-btn`).on('click', function() {
                    $(this).closest('.builder-field').remove();
                    if ($('#builder_fields_container').children().length === 0) {
                        $('#builder_fields_container').html('<p class="text-muted">{{ __('No fields yet. Click "Add Field" to start building your template.') }}</p>');
                    }
                });
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
                    }
                });

                $('#dynamic_form_fields .dynamic-field-row').each(function() {
                    const $field = $(this);
                    let commandTemplate = $field.data('command') || '';
                    const type = $field.data('type');

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
                    if (!rawValue) return;

                    let cmd = '';
                    if (type === 'dropdown') {
                        const parts = rawValue.split(':', 2);
                        const val = parts[0].trim();
                        if (parts.length === 2) {
                            cmd = parts[1].trim();
                        } else if (commandTemplate) {
                            cmd = commandTemplate.replace(/\{\{value\}\}/g, val);
                        }
                    } else {
                        if (commandTemplate) {
                            cmd = commandTemplate.replace(/\{\{value\}\}/g, rawValue);
                        }
                    }

                    if (cmd) coreCommands.push(cmd);
                });

                // Wrap with interface context
                let finalCommands = [];
                const selectedIfaces = $('.device-interface-select').val();
                if (selectedIfaces && selectedIfaces.length > 0) {
                    selectedIfaces.forEach(function(iface) {
                        finalCommands.push('interface ' + iface);
                        coreCommands.forEach(function(cmd) {
                            finalCommands.push(cmd);
                        });
                    });
                } else {
                    finalCommands = coreCommands;
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
                $('#submitBtn').prop('disabled', !(validIPs.length > 0 && hasCommands));
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
                    } else {
                        inputHtml = '<input type="text" class="form-control dynamic-field-value" placeholder="' + _.escape(field.label) + '">';
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

                    if (field.type === 'dropdown') {
                        $container.find('.dynamic-field-row:last .dynamic-field-value').select2({
                            placeholder: '-- Select --',
                            allowClear: true
                        });
                    }
                });

                $container.find('.dynamic-field-value').on('change input', function() {
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
                    $('#deleteTemplateBtn').show();

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

            // Delete template handler
            $('#deleteTemplateBtn').on('click', function() {
                const name = $('#template_name').val();
                if (!name) return;
                if (!confirm('Delete template "' + name + '"?')) return;

                $.ajax({
                    url: '{{ route("addhost.template.delete") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        template_name: name
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
@endsection