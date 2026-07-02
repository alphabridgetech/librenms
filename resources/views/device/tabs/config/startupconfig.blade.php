<style>
    .tq-container {
        font-family: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: #f8fafc;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
        margin-top: 15px;
        margin-bottom: 40px;
    }
    
    .tq-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    
    @media (max-width: 991px) {
        .tq-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .tq-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.7);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        position: relative;
        overflow: hidden;
    }
    
    .tq-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.08);
    }
    
    .tq-card-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 16px;
    }
    
    .tq-card-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .tq-card-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        font-size: 15px;
    }
    
    .icon-primary {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .icon-success {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .icon-info {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
    }
    
    .icon-teal {
        background: rgba(13, 148, 136, 0.1);
        color: #0d9488;
    }
    
    .icon-slate {
        background: rgba(100, 116, 139, 0.1);
        color: #64748b;
    }
    
    .tq-form-group {
        margin-bottom: 16px;
    }
    
    .tq-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 6px;
        display: block;
    }
    
    .tq-input, .tq-select {
        width: 100%;
        padding: 9px 12px;
        font-size: 13.5px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background-color: #f8fafc;
        color: #334155;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    
    .tq-input:focus, .tq-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        background-color: #ffffff;
    }
    
    .tq-btn {
        padding: 10px 18px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
    }
    
    .tq-btn:active {
        transform: scale(0.98);
    }
    
    .tq-btn-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #ffffff;
        box-shadow: 0 4px 8px rgba(37, 99, 235, 0.15);
    }
    
    .tq-btn-primary:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        box-shadow: 0 6px 12px rgba(37, 99, 235, 0.25);
    }
    
    .tq-btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #ffffff;
        box-shadow: 0 4px 8px rgba(5, 150, 105, 0.15);
    }
    
    .tq-btn-success:hover {
        background: linear-gradient(135deg, #059669, #047857);
        box-shadow: 0 6px 12px rgba(5, 150, 105, 0.25);
    }
    
    .tq-btn-info {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: #ffffff;
        box-shadow: 0 4px 8px rgba(124, 58, 237, 0.15);
    }
    
    .tq-btn-info:hover {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
        box-shadow: 0 6px 12px rgba(124, 58, 237, 0.25);
    }
    
    .tq-btn-download {
        background-color: #f1f5f9;
        color: #3b82f6;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        text-decoration: none !important;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    
    .tq-btn-download:hover {
        background-color: #3b82f6;
        color: #ffffff;
    }
    
    .tq-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .tq-table th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 12px;
        padding: 10px 14px;
        border-bottom: 2px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .tq-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 12.5px;
        vertical-align: middle !important;
    }
    
    .tq-table tbody tr {
        transition: background-color 0.15s ease;
    }
    
    .tq-table tbody tr:hover td {
        background-color: #f8fafc;
    }
    
    .tq-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 7px;
        font-size: 10.5px;
        font-weight: 600;
        border-radius: 6px;
        text-transform: uppercase;
    }
    
    .badge-success {
        background-color: rgba(16, 185, 129, 0.1);
        color: #047857;
    }
    
    .badge-error {
        background-color: rgba(239, 68, 68, 0.1);
        color: #b91c1c;
    }
    
    .badge-system {
        background-color: rgba(100, 116, 139, 0.1);
        color: #475569;
    }
    
    .text-danger-bold {
        background-color: rgba(239, 68, 68, 0.05);
        border-left: 4px solid #ef4444;
        padding: 10px 12px;
        border-radius: 0 8px 8px 0;
        color: #b91c1c;
        font-weight: 600;
        font-size: 12.5px;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 15px;
    }
</style>

<div class="tq-container">
    
    <!-- Top Grid for Backup and Upgrade Actions -->
    <div class="tq-grid">
        
        <!-- Export startup-config -->
        <div class="tq-card">
            <div class="tq-card-header">
                <div class="tq-card-icon icon-primary">
                    <i class="fa fa-cloud-upload"></i>
                </div>
                <h3 class="tq-card-title">{{ __('Export Startup-Config') }}</h3>
            </div>
            
            <div class="tq-form-group">
                <label class="tq-label">{{ __('TFTP Server IP') }}</label>
                <input type="text" id="tftpServer_ex" class="tq-input" value="{{ $data['tftp_server_ip'] ?: $data['tftpServer'] }}" placeholder="e.g. 192.168.1.10">
            </div>

            <div class="tq-form-group">
                <label class="tq-label">{{ __('File Name') }}</label>
                <input type="text" class="tq-input" value="startup-config" disabled style="cursor: not-allowed; background-color: #e2e8f0; border-color: #cbd5e1;">
            </div>

            <div style="margin-top: 24px;">
                <button type="button" id="exportBtn" class="tq-btn tq-btn-primary" onclick="exportStartupConfig()">
                    <i class="fa fa-upload"></i> {{ __('Export Startup-Config') }}
                </button>
            </div>
        </div>

        <!-- Import / Upgrade config file -->
        <div class="tq-card">
            <div class="tq-card-header">
                <div class="tq-card-icon icon-success">
                    <i class="fa fa-refresh"></i>
                </div>
                <h3 class="tq-card-title">{{ __('Import / Upgrade Config') }}</h3>
            </div>

            <div class="text-danger-bold">
                <i class="fa fa-exclamation-triangle"></i> {{ __('Reboot is required after importing config file!') }}
            </div>

            <div class="tq-form-group">
                <label class="tq-label">{{ __('TFTP Server') }}</label>
                <input type="text" id="tftpServer" class="tq-input" value="{{ $data['tftpServer'] }}" placeholder="e.g. 192.168.1.10">
            </div>

            <div class="tq-form-group">
                <label class="tq-label">{{ __('File name on the server') }}</label>
                <select id="configFileName" class="tq-select">
                    <option value="startup-config">startup-config</option>
                    <option value="bvss-config">bvss-config</option>
                </select>
            </div>

            <div class="tq-form-group">
                <label class="tq-label">{{ __('Upload BIN File') }}</label>
                <input type="file" id="uploadfile" class="tq-input" style="padding: 5px;">
            </div>

            <div style="margin-top: 24px;">
                <button type="button" id="importBtn" class="tq-btn tq-btn-success" onclick="importStartupConfig()">
                    <i class="fa fa-cloud-download"></i> {{ __('Upgrade') }}
                </button>
            </div>
        </div>
        
    </div>

    <!-- Schedule Daily Backup Time -->
    <div class="tq-card" style="margin-top:20px;">
        <div class="tq-card-header">
            <div class="tq-card-icon icon-info">
                <i class="fa fa-clock-o"></i>
            </div>
            <h3 class="tq-card-title">{{ __('Schedule Daily Backup Settings') }}</h3>
        </div>
        
        <div class="tq-form-group">
            <label class="tq-label">{{ __('Daily Backup Execution Time') }}</label>
            <input type="time" id="backupTimeInput" class="tq-input" value="{{ $data['backup_time'] }}" style="width: auto; max-width: 200px;">
        </div>
        
        <div class="tq-form-group">
            <label class="tq-label">{{ __('TFTP Server IP (Optional)') }}</label>
            <input type="text" id="tftpServerInput" class="tq-input" value="{{ $data['tftp_server_ip'] }}" placeholder="Leave blank to use current Telequill IP: {{ $data['tftpServer'] }}" style="max-width: 400px;">
        </div>
        <div class="tq-form-group">
            <label class="tq-label">{{ __('Backup Retention Period (Days)') }}</label>
            <input type="number" id="backupRetentionInput" class="tq-input" value="{{ $data['backup_retention_days'] }}" placeholder="30" min="1" style="width: auto; max-width: 200px;">
        </div>
        
        <div style="margin-top: 24px;">
            <button type="button" id="saveScheduleBtn" class="tq-btn tq-btn-info" onclick="saveBackupSchedule()">
                <i class="fa fa-floppy-o"></i> {{ __('Save Schedule') }}
            </button>
        </div>
    </div>

    <!-- Backup History / Saved Configurations -->
    <div class="tq-card" style="margin-top:24px;">
        <div class="tq-card-header">
            <div class="tq-card-icon icon-teal">
                <i class="fa fa-history"></i>
            </div>
            <h3 class="tq-card-title">{{ __('Backup History / Saved Configurations') }}</h3>
        </div>
        
        <div style="overflow-x: auto;">
            @if(empty($data['tftp_files']))
                <p class="text-muted" style="margin: 10px 0;">{{ __('No saved configurations found on TFTP server for this device.') }}</p>
            @else
                <!-- Search filter input -->
                <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; max-width: 350px;">
                    <div style="position: relative; width: 100%;">
                        <input type="text" id="tftpSearchInput" class="tq-input" placeholder="Search by date or name..." onkeyup="filterBackupHistory()" style="padding-left: 32px;">
                        <i class="fa fa-search" style="position: absolute; left: 12px; top: 12px; color: #94a3b8; font-size: 13px;"></i>
                    </div>
                </div>

                <table class="tq-table">
                    <thead>
                        <tr>
                            <th>{{ __('File Name') }}</th>
                            <th style="width: 120px; text-align: center;">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['tftp_files'] as $file)
                            <tr class="tq-tftp-row" data-filename="{{ $file }}">
                                <td style="font-family: monospace; font-weight: 600; color: #0f172a;">{{ $file }}</td>
                                <td style="text-align: center;">
                                    <a href="/tftp/download/{{ $file }}" class="tq-btn-download">
                                        <i class="fa fa-download"></i> {{ __('Download') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <!-- Configuration Backup Logs -->
    <div class="tq-card" style="margin-top:24px;">
        <div class="tq-card-header">
            <div class="tq-card-icon icon-slate">
                <i class="fa fa-file-text-o"></i>
            </div>
            <h3 class="tq-card-title">{{ __('Configuration Backup Logs') }}</h3>
        </div>
        
        <div style="overflow-x: auto;">
            @if(empty($data['config_backup_logs']) || $data['config_backup_logs']->isEmpty())
                <p class="text-muted" style="margin: 10px 0;">{{ __('No backup log records found in database.') }}</p>
            @else
                 <table class="tq-table">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Filename') }}</th>
                            <th>{{ __('TFTP Server') }}</th>
                            <th style="width: 90px; text-align: center;">{{ __('Status') }}</th>
                            <th>{{ __('Details / Message') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['config_backup_logs'] as $log)
                            <tr>
                                <td style="white-space: nowrap; color: #64748b; font-weight: 500;">{{ $log->created_at }}</td>
                                <td style="white-space: nowrap; font-weight: 600;">
                                    @if($log->user)
                                        {{ $log->user->username }}
                                    @else
                                        <span class="tq-badge badge-system"><i class="fa fa-android"></i>&nbsp;{{ __('System') }}</span>
                                    @endif
                                </td>
                                <td style="font-family: monospace; color: #334155;">{{ $log->filename }}</td>
                                <td style="font-family: monospace; color: #334155;">{{ $log->tftp_server }}</td>
                                <td style="text-align: center;">
                                    @if($log->status === 'success')
                                        <span class="tq-badge badge-success">{{ __('Success') }}</span>
                                    @else
                                        <span class="tq-badge badge-error">{{ __('Error') }}</span>
                                    @endif
                                </td>
                                <td style="color: #475569;">{{ $log->message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

</div>

<script>
    function importStartupConfig() {

   
    const btn = document.getElementById("importBtn");
    const ip = "{{ $device->hostname }}";
    const apiToken = "{{ $data['api_token'] }}";

    const tftpServer = document.getElementById("tftpServer").value;
    const fileName   = document.getElementById("configFileName").value;
    const fileInput  = document.getElementById("uploadfile");
    const file       = fileInput.files[0];

    if (!tftpServer) {
        alert("TFTP server is required!");
        return;
    }

    // ✅ Use FormData
    const formData = new FormData();
    formData.append("tftp_server", tftpServer);
    formData.append("file", file);
    formData.append("filename", fileName);

  

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border"></span> Importing...';

    fetch(`/api/v0/tftpupload/${ip}`, {
        method: "POST",
        headers: {
            "Authorization": "Bearer " + apiToken,
            "Accept": "application/json"
        },
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === "success") {
            alert("Config imported successfully. reboot now.");
            btn.disabled = false;
            btn.innerHTML = 'Upgrade';

            // devicereboot();
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Upgrade';
            alert(res.message || "Import failed");
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = 'Upgrade';
        alert("Error: " + err);
    });
}

    function exportStartupConfig() {

        const btn = document.getElementById("exportBtn");
        const ip = "{{ $device->hostname }}";
        const apiToken = "{{ $data['api_token'] }}";
        const filename = "startup-config";
        const tftpServer = document.getElementById("tftpServer_ex").value;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Exporting...';

        fetch(`/api/v0/tftpexport/${ip}`, {
                method: "POST",
                headers: {
                    "Authorization": "Bearer " + apiToken,
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    filename: filename,
                    tftp_server: tftpServer               
                })
            })
            .then(res => res.json())
            .then(res => {
                console.log('====================================');
                console.log(res);
                console.log('====================================');
                btn.disabled = false;
                btn.innerHTML = 'Export the current startup-config';

                if (res.status === "success") {
                    alert("Startup-config exported successfully!");
                    window.location.href = res.download_url;
                } else {
                    alert("Export failed!");
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = 'Export the current startup-config';
                alert("Error: " + err);
            });
    }

    function saveBackupSchedule() {
        const btn = document.getElementById("saveScheduleBtn");
        const apiToken = "{{ $data['api_token'] }}";
        const backupTime = document.getElementById("backupTimeInput").value;
        const tftpServerIp = document.getElementById("tftpServerInput").value;
        const backupRetentionDays = document.getElementById("backupRetentionInput").value;

        if (!backupTime) {
            alert("Backup time is required!");
            return;
        }
        if (!backupRetentionDays || backupRetentionDays < 1) {
            alert("Backup retention period must be at least 1 day!");
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border"></span> Saving...';

        fetch(`/api/v0/tftp/schedule`, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + apiToken,
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                backup_time: backupTime,
                tftp_server_ip: tftpServerIp,
                backup_retention_days: backupRetentionDays
            })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = 'Save Schedule';
            if (res.status === "success") {
                alert("Backup schedule saved successfully! Future daily backups will run at: " + backupTime + " using TFTP: " + res.tftp_server_ip + " with a retention of " + res.backup_retention_days + " days.");
            } else {
                alert("Failed to save schedule: " + (res.message || "Unknown error"));
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = 'Save Schedule';
            alert("Error: " + err);
        });
    }

    function filterBackupHistory() {
        const query = document.getElementById("tftpSearchInput").value.toLowerCase();
        const rows = document.querySelectorAll(".tq-tftp-row");
        rows.forEach(row => {
            const filename = row.getAttribute("data-filename").toLowerCase();
            if (filename.includes(query)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }
</script>
