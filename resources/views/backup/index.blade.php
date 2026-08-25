@extends('layouts.librenmsv1')

@section('title')
    System & Data Backup Management
@endsection

@section('content')
    <div class="container">
        <div class="page-header">
            <h1><i class="fa fa-database text-primary"></i> Backup Management <small>Database, RRD & Node Startup-Configs</small></h1>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="backupTabs" style="margin-bottom: 20px;">
            <li class="active">
                <a href="#node-backup" data-toggle="tab">
                    <i class="fa fa-server text-success"></i> <strong>Node Startup-Configs</strong>
                </a>
            </li>
            <li>
                <a href="#rrd-backup" data-toggle="tab">
                    <i class="fa fa-area-chart text-info"></i> <strong>RRD Files Backup</strong>
                </a>
            </li>
            <li>
                <a href="#db-backup" data-toggle="tab">
                    <i class="fa fa-database text-primary"></i> <strong>Database Backup</strong>
                </a>
            </li>
            <li>
                <a href="#alarm-archive" data-toggle="tab">
                    <i class="fa fa-archive text-warning"></i> <strong>Alarm History Archive</strong>
                </a>
            </li>
            <li>
                <a href="#backup-logs" data-toggle="tab">
                    <i class="fa fa-history text-muted"></i> <strong>Activity Logs</strong>
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- ================= NODE / DEVICE STARTUP-CONFIGS TAB ================= -->
            <div class="tab-pane fade in active" id="node-backup">
                <div class="row">
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-play-circle text-success"></i> Run Manual Node Backup</h5>
                            </div>
                            <div class="panel-body">
                                <p>Export startup-config from network devices to central TFTP server (<code>/tftpboot/node/</code>).</p>
                                
                                <form action="{{ route('backup.node.run') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label for="device_id">Target Device / Node:</label>
                                        <select name="device_id" id="device_id" class="form-control input-sm" required>
                                            <option value="all">All Active Devices ({{ count($devices) }} devices)</option>
                                            @foreach ($devices as $dev)
                                                <option value="{{ $dev->device_id }}">
                                                    {{ $dev->hostname }} {{ $dev->sysName ? '('.$dev->sysName.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="tftp_server_ip">TFTP Server IP:</label>
                                        <input type="text" name="tftp_server_ip" id="tftp_server_ip" class="form-control input-sm" value="{{ $node_tftp_server_ip }}" required>
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <button type="submit" class="btn btn-success btn-block btn-sm" onclick="this.disabled=true; this.innerText='Exporting Startup-Config...'; this.form.submit();">
                                            <i class="fa fa-download fa-fw"></i> Export Node Startup-Config
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-upload text-info"></i> Upload Node Config</h5>
                            </div>
                            <div class="panel-body">
                                <p>Upload a node startup-config file manually to <code>/tftpboot/node/</code>.</p>

                                <form action="{{ route('backup.node.upload') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label for="node_backup_file">Select Config File:</label>
                                        <input type="file" name="backup_file" id="node_backup_file" class="form-control input-sm" required>
                                    </div>

                                    <div class="form-group" style="margin-top: 55px; margin-bottom: 0;">
                                        <button type="submit" class="btn btn-info btn-block btn-sm">
                                            <i class="fa fa-upload fa-fw"></i> Upload Node Config
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="panel panel-success">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-clock-o"></i> Automated Schedule</h5>
                            </div>
                            <div class="panel-body">
                                <form action="{{ route('backup.node.save-schedule') }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="node_backup_time">Execution Time:</label>
                                                <input type="time" name="node_backup_time" id="node_backup_time" class="form-control input-sm" value="{{ $node_backup_time }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="node_backup_interval_days">Interval (Days):</label>
                                                <input type="number" name="node_backup_interval_days" id="node_backup_interval_days" class="form-control input-sm" value="{{ $node_backup_interval_days }}" min="1" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="node_tftp_server_ip">TFTP IP:</label>
                                                <input type="text" name="node_tftp_server_ip" id="node_tftp_server_ip" class="form-control input-sm" value="{{ $node_tftp_server_ip }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="node_backup_retention_days">Retention (Days):</label>
                                                <input type="number" name="node_backup_retention_days" id="node_backup_retention_days" class="form-control input-sm" value="{{ $node_backup_retention_days }}" min="1" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin-top: 5px; margin-bottom: 0;">
                                        <button type="submit" class="btn btn-success btn-block btn-sm">
                                            <i class="fa fa-floppy-o fa-fw"></i> Save Schedule
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-file-code-o text-success"></i> Saved Node Startup Configurations (<code>/tftpboot/node</code>)</h5>
                            </div>
                            <div class="panel-body">
                                @if (count($nodeBackups) > 0)
                                    <!-- Controls Bar -->
                                    <div class="row" style="margin-bottom: 12px;">
                                        <div class="col-sm-6 col-md-5">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                                <input type="text" id="nodeSearchInput" class="form-control" placeholder="Search config files by name or date...">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-7 text-right">
                                            <label style="font-weight: normal; margin-bottom: 0; line-height: 30px;">Show:
                                                <select id="nodeEntriesSelect" class="form-control input-sm" style="display: inline-block; width: auto; margin-left: 5px;">
                                                    <option value="10" selected>10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="all">All</option>
                                                </select>
                                            </label>
                                        </div>
                                    </div>

                                    <table class="table table-striped table-hover" id="nodeBackupTable">
                                        <thead>
                                            <tr>
                                                <th>Config Filename</th>
                                                <th>File Size</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($nodeBackups as $backup)
                                                <tr>
                                                    <td><code>{{ $backup['name'] }}</code></td>
                                                    <td><span class="badge bg-green">{{ $backup['size'] }}</span></td>
                                                    <td>{{ $backup['date'] }}</td>
                                                    <td>
                                                        <a href="{{ route('backup.node.download', ['filename' => $backup['name']]) }}" class="btn btn-xs btn-success">
                                                            <i class="fa fa-download"></i> Download
                                                        </a>
                                                        <form action="{{ route('backup.node.restore', ['filename' => $backup['name']]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Initiate restore for startup-config {{ $backup['name'] }} from /tftpboot?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-info">
                                                                <i class="fa fa-undo"></i> Restore
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('backup.node.delete', ['filename' => $backup['name']]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete {{ $backup['name'] }}?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-danger">
                                                                <i class="fa fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <!-- Pagination Footer -->
                                    <div class="row" style="margin-top: 10px;">
                                        <div class="col-sm-6" style="line-height: 30px;">
                                            <span id="nodePaginationInfo" class="text-muted small"></span>
                                        </div>
                                        <div class="col-sm-6 text-right">
                                            <div id="nodePaginationBtns"></div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted">No node startup configuration files found in <code>/tftpboot/</code>.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= RRD BACKUP TAB ================= -->
            <div class="tab-pane fade" id="rrd-backup">
                <div class="row">
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-play-circle text-primary"></i> Run Manual RRD Backup</h5>
                            </div>
                            <div class="panel-body">
                                <p>Creates a compressed archive (<code>.tar.gz</code>) of metrics in <code>/tftpboot/rrd/</code>.</p>
                                
                                <form action="{{ route('backup.rrd.run') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label for="rrd_destination">Backup Destination:</label>
                                        <select name="destination" id="rrd_destination" class="form-control input-sm" required>
                                            <option value="local">Primary (/tftpboot/rrd/)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="margin-top: 45px; margin-bottom: 0;">
                                        <button type="submit" class="btn btn-primary btn-block btn-sm">
                                            <i class="fa fa-file-archive-o fa-fw"></i> Start Manual RRD Backup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-upload text-info"></i> Upload & Restore RRD Backup</h5>
                            </div>
                            <div class="panel-body">
                                <p>Upload an RRD backup archive (<code>.tar.gz</code>) directly to <code>/tftpboot/rrd/</code>.</p>

                                <form action="{{ route('backup.rrd.upload') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label for="rrd_backup_file">Select Archive File:</label>
                                        <input type="file" name="backup_file" id="rrd_backup_file" class="form-control input-sm" accept=".tar.gz,.gz" required>
                                    </div>

                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="restore_immediately" value="1"> 
                                            <strong>Restore immediately after upload?</strong>
                                        </label>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <button type="submit" class="btn btn-info btn-block btn-sm" onclick="return document.querySelector('#rrd_backup_file').form.querySelector('input[name=restore_immediately]').checked ? confirm('WARNING: Restoring will overwrite existing RRD files in rrd/ directory. Proceed?') : true;">
                                            <i class="fa fa-upload fa-fw"></i> Upload RRD Backup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-clock-o"></i> Automated RRD Schedule</h5>
                            </div>
                            <div class="panel-body">
                                <form action="{{ route('backup.rrd.save-schedule') }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="rrd_backup_time">Execution Time:</label>
                                                <input type="time" name="rrd_backup_time" id="rrd_backup_time" class="form-control input-sm" value="{{ $rrd_backup_time }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="rrd_backup_interval_days">Interval (Days):</label>
                                                <input type="number" name="rrd_backup_interval_days" id="rrd_backup_interval_days" class="form-control input-sm" value="{{ $rrd_backup_interval_days }}" min="1" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="rrd_backup_destination">Backup Destination:</label>
                                        <select name="rrd_backup_destination" id="rrd_backup_destination" class="form-control input-sm" required>
                                            <option value="local" {{ $rrd_backup_destination == 'local' ? 'selected' : '' }}>Primary (/tftpboot/rrd/)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="rrd_backup_purge_days">Retention Period (Days):</label>
                                        <input type="number" name="rrd_backup_purge_days" id="rrd_backup_purge_days" class="form-control input-sm" value="{{ $rrd_backup_purge_days }}" min="1" required>
                                    </div>
                                    <div class="form-group" style="margin-top: 5px; margin-bottom: 0;">
                                        <button type="submit" class="btn btn-info btn-block btn-sm">
                                            <i class="fa fa-floppy-o fa-fw"></i> Save Schedule
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-list"></i> Available RRD Backups (<code>/tftpboot/rrd</code>)</h5>
                            </div>
                            <div class="panel-body">
                                @if (count($rrdBackups) > 0)
                                    <!-- Controls Bar -->
                                    <div class="row" style="margin-bottom: 12px;">
                                        <div class="col-sm-6 col-md-5">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                                <input type="text" id="rrdSearchInput" class="form-control" placeholder="Search RRD backups...">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-7 text-right">
                                            <label style="font-weight: normal; margin-bottom: 0; line-height: 30px;">Show:
                                                <select id="rrdEntriesSelect" class="form-control input-sm" style="display: inline-block; width: auto; margin-left: 5px;">
                                                    <option value="10" selected>10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="all">All</option>
                                                </select>
                                            </label>
                                        </div>
                                    </div>

                                    <table class="table table-striped table-hover" id="rrdBackupTable">
                                        <thead>
                                            <tr>
                                                <th>Archive Filename</th>
                                                <th>File Size</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rrdBackups as $backup)
                                                <tr>
                                                    <td><code>{{ $backup['name'] }}</code></td>
                                                    <td><span class="badge bg-blue">{{ $backup['size'] }}</span></td>
                                                    <td>{{ $backup['date'] }}</td>
                                                    <td>
                                                        <a href="{{ route('backup.rrd.download', ['filename' => $backup['name']]) }}" class="btn btn-xs btn-success">
                                                            <i class="fa fa-download"></i> Download
                                                        </a>
                                                        <form action="{{ route('backup.rrd.restore', ['filename' => $backup['name']]) }}" method="POST" style="display:inline;" onsubmit="return confirm('WARNING: This will extract and replace existing RRD files in rrd/ directory. Are you sure you want to restore?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-warning">
                                                                <i class="fa fa-refresh"></i> Restore
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('backup.rrd.delete', ['filename' => $backup['name']]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this RRD backup?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-danger">
                                                                <i class="fa fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <!-- Pagination Footer -->
                                    <div class="row" style="margin-top: 10px;">
                                        <div class="col-sm-6" style="line-height: 30px;">
                                            <span id="rrdPaginationInfo" class="text-muted small"></span>
                                        </div>
                                        <div class="col-sm-6 text-right">
                                            <div id="rrdPaginationBtns"></div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted">No RRD file backups found in <code>storage/app/backups/rrd/</code>.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= DATABASE BACKUP TAB ================= -->
            <div class="tab-pane fade" id="db-backup">
                <div class="row">
                    <div class="col-md-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-play text-primary"></i> Run Manual Database Backup</h5>
                            </div>
                            <div class="panel-body">
                                <p>Select destination where you would like to save the database SQL backup.</p>
                                
                                <form action="{{ route('backup.run') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label for="destination">Backup Destination:</label>
                                        <select name="destination" id="destination" class="form-control" required>
                                            <option value="local">Primary (/tftpboot/database/)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fa fa-database fa-fw"></i> Start Manual Database Backup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-upload"></i> Upload & Restore Database Backup</h5>
                            </div>
                            <div class="panel-body">
                                <p>Upload a <code>.sql</code> backup file directly to <code>/tftpboot/database/</code>.</p>

                                <form action="{{ route('backup.upload') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label for="backup_file">Select SQL File:</label>
                                        <input type="file" name="backup_file" id="backup_file" class="form-control" accept=".sql" required>
                                    </div>

                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="restore_immediately" value="1"> 
                                            <strong>Restore immediately after upload?</strong>
                                        </label>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="btn btn-info btn-block" onclick="return document.querySelector('input[name=restore_immediately]').checked ? confirm('WARNING: Restoring will overwrite current database. Proceed?') : true;">
                                            <i class="fa fa-upload fa-fw"></i> Upload Backup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-12">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-clock-o"></i> Automated Database Backup Schedule</h5>
                            </div>
                            <div class="panel-body">
                                <form action="{{ route('backup.save-schedule') }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="db_backup_time">Execution Time:</label>
                                                <input type="time" name="db_backup_time" id="db_backup_time" class="form-control" value="{{ $db_backup_time }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="db_backup_interval_days">Backup Interval (Days):</label>
                                                <input type="number" name="db_backup_interval_days" id="db_backup_interval_days" class="form-control" value="{{ $db_backup_interval_days }}" min="1" required>
                                                <small class="text-muted">Run every N days (e.g. 7 for 7 days).</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="db_backup_destination">Backup Destination:</label>
                                                <select name="db_backup_destination" id="db_backup_destination" class="form-control" required>
                                                    <option value="local" {{ $db_backup_destination == 'local' ? 'selected' : '' }}>Primary (/tftpboot/database/)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="db_backup_retention_days">Retention Period (Days):</label>
                                                <input type="number" name="db_backup_retention_days" id="db_backup_retention_days" class="form-control" value="{{ $db_backup_retention_days }}" min="1" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin-top: 15px; margin-bottom: 0;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-floppy-o fa-fw"></i> Save Schedule Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-list"></i> Available Database Backups (<code>/tftpboot/database</code>)</h5>
                            </div>
                            <div class="panel-body">
                                @if (count($backups) > 0)
                                    <!-- Controls Bar -->
                                    <div class="row" style="margin-bottom: 12px;">
                                        <div class="col-sm-6 col-md-5">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                                <input type="text" id="dbSearchInput" class="form-control" placeholder="Search database backups...">
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-7 text-right">
                                            <label style="font-weight: normal; margin-bottom: 0; line-height: 30px;">Show:
                                                <select id="dbEntriesSelect" class="form-control input-sm" style="display: inline-block; width: auto; margin-left: 5px;">
                                                    <option value="10" selected>10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="all">All</option>
                                                </select>
                                            </label>
                                        </div>
                                    </div>

                                    <table class="table table-striped table-hover" id="dbBackupTable">
                                        <thead>
                                            <tr>
                                                <th>Filename</th>
                                                <th>Size</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($backups as $backup)
                                                <tr>
                                                    <td><code>{{ $backup['name'] }}</code></td>
                                                    <td><span class="badge bg-green">{{ $backup['size'] }}</span></td>
                                                    <td>{{ $backup['date'] }}</td>
                                                    <td>
                                                        <a href="{{ route('backup.download', ['filename' => $backup['filename'] ?? $backup['name']]) }}" class="btn btn-xs btn-success">
                                                            <i class="fa fa-download"></i> Download
                                                        </a>
                                                        <form action="{{ route('backup.restore', ['filename' => $backup['name']]) }}" method="POST" style="display:inline;" onsubmit="return confirm('WARNING: This will overwrite your current database. Proceed?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-warning">
                                                                <i class="fa fa-refresh"></i> Restore
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('backup.delete', ['filename' => $backup['name']]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-danger">
                                                                <i class="fa fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <!-- Pagination Footer -->
                                    <div class="row" style="margin-top: 10px;">
                                        <div class="col-sm-6" style="line-height: 30px;">
                                            <span id="dbPaginationInfo" class="text-muted small"></span>
                                        </div>
                                        <div class="col-sm-6 text-right">
                                            <div id="dbPaginationBtns"></div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted">No database backups found in <code>/tftpboot/database/</code> or <code>storage/app/backups/</code>.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= ACTIVITY LOGS TAB ================= -->
            <div class="tab-pane fade" id="backup-logs">
                <div class="row">
                    <div class="col-md-12">
                        <!-- Node Config Backup Logs -->
                        <div class="panel panel-success">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-server"></i> Node Startup-Config Backup Activity Logs</h5>
                            </div>
                            <div class="panel-body">
                                @if (isset($nodeLogs) && $nodeLogs->count() > 0)
                                    <!-- Controls Bar -->
                                    <div class="row" style="margin-bottom: 12px;">
                                        <div class="col-sm-5 col-md-4">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                                <input type="text" id="nodeLogsSearchInput" class="form-control" placeholder="Search by device, user, reason, file...">
                                            </div>
                                        </div>
                                        <div class="col-sm-4 col-md-4">
                                            <select id="nodeLogsStatusSelect" class="form-control input-sm">
                                                <option value="all">-- All Statuses --</option>
                                                <option value="success">Success</option>
                                                <option value="error">Error / Failed</option>
                                                <option value="skipped">Skipped</option>
                                            </select>
                                        </div>
                                        <div class="col-sm-3 col-md-4 text-right">
                                            <label style="font-weight: normal; margin-bottom: 0; line-height: 30px;">Show:
                                                <select id="nodeLogsEntriesSelect" class="form-control input-sm" style="display: inline-block; width: auto; margin-left: 5px;">
                                                    <option value="10" selected>10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="100">100</option>
                                                    <option value="all">All</option>
                                                </select>
                                            </label>
                                        </div>
                                    </div>

                                    <table class="table table-condensed table-striped table-hover" id="nodeLogsTable">
                                        <thead>
                                            <tr>
                                                <th>Device / Hostname</th>
                                                <th>User</th>
                                                <th>Filename</th>
                                                <th>TFTP Server</th>
                                                <th>Status</th>
                                                <th>Reason / Message Details</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($nodeLogs as $nlog)
                                                <tr data-status="{{ strtolower($nlog->status) }}">
                                                    <td><strong>{{ $nlog->device->hostname ?? ($nlog->device_id ? 'Device #'.$nlog->device_id : 'All Active Devices') }}</strong></td>
                                                    <td>{{ $nlog->user->username ?? 'System (Automated)' }}</td>
                                                    <td><code>{{ $nlog->filename }}</code></td>
                                                    <td>{{ $nlog->tftp_server ?? 'N/A' }}</td>
                                                    <td>
                                                        @if ($nlog->status == 'success')
                                                            <span class="label label-success"><i class="fa fa-check"></i> SUCCESS</span>
                                                        @elseif ($nlog->status == 'skipped')
                                                            <span class="label label-warning"><i class="fa fa-clock-o"></i> SKIPPED</span>
                                                        @else
                                                            <span class="label label-danger"><i class="fa fa-times"></i> {{ strtoupper($nlog->status) }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($nlog->message)
                                                            <span class="{{ $nlog->status == 'error' ? 'text-danger' : ($nlog->status == 'skipped' ? 'text-warning' : 'text-muted') }}">
                                                                {{ $nlog->message }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td><small>{{ $nlog->created_at ? $nlog->created_at->diffForHumans() : 'N/A' }}</small></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <!-- Pagination Footer -->
                                    <div class="row" style="margin-top: 10px;">
                                        <div class="col-sm-6" style="line-height: 30px;">
                                            <span id="nodeLogsPaginationInfo" class="text-muted small"></span>
                                        </div>
                                        <div class="col-sm-6 text-right">
                                            <div id="nodeLogsPaginationBtns"></div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted">No node config backup activity logs found.</p>
                                @endif
                            </div>
                        </div>

                        <!-- System DB & RRD Backup Logs -->
                        <div class="panel panel-info" style="margin-top: 20px;">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-database"></i> Database & RRD Backup Activity Logs</h5>
                            </div>
                            <div class="panel-body">
                                @if ($logs->count() > 0)
                                    <!-- Controls Bar -->
                                    <div class="row" style="margin-bottom: 12px;">
                                        <div class="col-sm-5 col-md-4">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                                <input type="text" id="sysLogsSearchInput" class="form-control" placeholder="Search by user, action, filename, reason...">
                                            </div>
                                        </div>
                                        <div class="col-sm-4 col-md-4">
                                            <select id="sysLogsStatusSelect" class="form-control input-sm">
                                                <option value="all">-- All Statuses --</option>
                                                <option value="success">Success</option>
                                                <option value="error">Error / Failed</option>
                                                <option value="skipped">Skipped</option>
                                            </select>
                                        </div>
                                        <div class="col-sm-3 col-md-4 text-right">
                                            <label style="font-weight: normal; margin-bottom: 0; line-height: 30px;">Show:
                                                <select id="sysLogsEntriesSelect" class="form-control input-sm" style="display: inline-block; width: auto; margin-left: 5px;">
                                                    <option value="10" selected>10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="100">100</option>
                                                    <option value="all">All</option>
                                                </select>
                                            </label>
                                        </div>
                                    </div>

                                    <table class="table table-condensed table-striped table-hover" id="sysLogsTable">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Filename / Subject</th>
                                                <th>Destination</th>
                                                <th>Status</th>
                                                <th>Reason / Message Details</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($logs as $log)
                                                <tr data-status="{{ strtolower($log->status) }}">
                                                    <td>{{ $log->user->username ?? 'System (Automated)' }}</td>
                                                    <td>
                                                        @php
                                                            $lblClass = 'default';
                                                            if ($log->action == 'delete') $lblClass = 'danger';
                                                            elseif ($log->action == 'create') $lblClass = 'primary';
                                                            elseif ($log->action == 'restore') $lblClass = 'success';
                                                            elseif ($log->action == 'skipped') $lblClass = 'warning';
                                                            elseif ($log->action == 'download') $lblClass = 'info';
                                                        @endphp
                                                        <span class="label label-{{ $lblClass }}">{{ strtoupper($log->action) }}</span>
                                                    </td>
                                                    <td><code>{{ $log->filename }}</code></td>
                                                    <td>{{ $log->destination ?? 'N/A' }}</td>
                                                    <td>
                                                        @if ($log->status == 'success')
                                                            <span class="label label-success"><i class="fa fa-check"></i> SUCCESS</span>
                                                        @elseif ($log->status == 'skipped')
                                                            <span class="label label-warning"><i class="fa fa-clock-o"></i> SKIPPED</span>
                                                        @else
                                                            <span class="label label-danger"><i class="fa fa-times"></i> {{ strtoupper($log->status) }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($log->message)
                                                            <span class="{{ $log->status == 'error' ? 'text-danger' : ($log->status == 'skipped' ? 'text-warning' : 'text-muted') }}">
                                                                {{ $log->message }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td><small>{{ $log->created_at ? $log->created_at->diffForHumans() : 'N/A' }}</small></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <!-- Pagination Footer -->
                                    <div class="row" style="margin-top: 10px;">
                                        <div class="col-sm-6" style="line-height: 30px;">
                                            <span id="sysLogsPaginationInfo" class="text-muted small"></span>
                                        </div>
                                        <div class="col-sm-6 text-right">
                                            <div id="sysLogsPaginationBtns"></div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted">No system backup activity logs found.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= ALARM HISTORY ARCHIVE TAB ================= -->
            <div class="tab-pane fade" id="alarm-archive">
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-8">
                        <h4><i class="fa fa-archive text-warning"></i> Alarm History Archive <small>Export, Buffer & Manage Historical Alert Logs</small></h4>
                    </div>
                    <div class="col-md-4 text-right">
                        <form action="{{ route('alerts.archive.store') }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-archive"></i> Archive Now
                            </button>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <!-- Left Column: Archive Files Table -->
                    <div class="col-md-8">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong><i class="fa fa-file-text-o"></i> Archived Alarm History Files</strong>
                            </div>
                            <div class="panel-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>Filename</th>
                                                <th>Records</th>
                                                <th>File Size</th>
                                                <th>Coverage Window</th>
                                                <th>Created At</th>
                                                <th style="width: 220px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($alarmArchives as $archive)
                                                <tr>
                                                    <td><code>{{ $archive->filename }}</code></td>
                                                    <td><span class="badge bg-blue">{{ number_format($archive->line_count) }} lines</span></td>
                                                    <td><span class="badge bg-green">{{ $archive->file_size }}</span></td>
                                                    <td>
                                                        <small class="text-muted">
                                                            {{ $archive->start_date ? \Carbon\Carbon::parse($archive->start_date)->format('M d, H:i') : 'N/A' }} 
                                                            &rarr; 
                                                            {{ $archive->end_date ? \Carbon\Carbon::parse($archive->end_date)->format('M d, H:i') : 'N/A' }}
                                                        </small>
                                                    </td>
                                                    <td>{{ $archive->created_at->format('Y-m-d H:i') }}</td>
                                                    <td>
                                                        <button type="button" class="btn btn-xs btn-info btn-view-archive" data-id="{{ $archive->id }}" data-filename="{{ $archive->filename }}">
                                                            <i class="fa fa-eye"></i> View/Read
                                                        </button>
                                                        <a href="{{ route('alerts.archive.download', $archive->id) }}" class="btn btn-xs btn-success">
                                                            <i class="fa fa-download"></i> Download
                                                        </a>
                                                        <form action="{{ route('alerts.archive.destroy', $archive->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete archive {{ $archive->filename }}?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-danger">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        <em>No historical alarm archives found. Click "Archive Now" above to generate a new archive file.</em>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if ($alarmArchives->hasPages())
                                    <div class="pull-right">
                                        {{ $alarmArchives->appends(request()->query())->links() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Threshold & Dynamic Schedule Settings -->
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong><i class="fa fa-cogs"></i> Archival Threshold & Schedule Settings</strong>
                            </div>
                            <div class="panel-body">
                                <form action="{{ route('alerts.archive.settings') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label for="max_lines">Max Lines per File</label>
                                        <input type="number" name="max_lines" id="max_lines" class="form-control" value="{{ $alarm_max_lines }}" min="100" max="50000" required>
                                        <span class="help-block small">Buffer line limit before splitting into a new file.</span>
                                    </div>

                                    <div class="form-group">
                                        <label for="max_size_mb">Max File Size (MB)</label>
                                        <input type="number" step="0.5" name="max_size_mb" id="max_size_mb" class="form-control" value="{{ $alarm_max_size_mb }}" min="1" max="500" required>
                                        <span class="help-block small">Maximum file size threshold for archive files.</span>
                                    </div>

                                    <div class="form-group">
                                        <label for="purge_days">Purge Archives Older Than (Days)</label>
                                        <input type="number" name="purge_days" id="purge_days" class="form-control" value="{{ $alarm_purge_days }}" min="1" max="3650" required>
                                        <span class="help-block small">Auto-purge archive files older than specified days.</span>
                                    </div>

                                    <div class="form-group">
                                        <label for="archive_time">Daily Scheduled Archival Time (24h)</label>
                                        <input type="time" name="archive_time" id="archive_time" class="form-control" value="{{ $alarm_archive_time }}" required>
                                        <span class="help-block small">Dynamic schedule time for automated daily alarm log rotation.</span>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-save"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                            <div class="panel-footer small text-muted">
                                <strong>Last Run:</strong> {{ $alarm_last_run }}<br>
                                <strong>Scheduled Daily At:</strong> {{ $alarm_archive_time }}<br>
                                <strong>Storage Path:</strong> <code>/tftpboot/alarms/</code>
                            </div>
                        </div>

                        <!-- Upload Alarm History Archive Panel -->
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <strong><i class="fa fa-upload"></i> Upload Alarm Archive</strong>
                            </div>
                            <div class="panel-body">
                                <form action="{{ route('alerts.archive.upload') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label for="alarm_archive_file">Select Archive (.csv):</label>
                                        <input type="file" name="archive_file" id="alarm_archive_file" class="form-control input-sm" accept=".csv" required>
                                    </div>
                                    <button type="submit" class="btn btn-info btn-block btn-sm">
                                        <i class="fa fa-upload"></i> Upload Archive CSV
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top: 15px;">
            <div class="col-md-12">
                <a href="{{ route('home') }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>
    </div>

    <!-- Modal for RCA Log File Reader View -->
    <div class="modal fade" id="viewArchiveModal" tabindex="-1" role="dialog" aria-labelledby="viewArchiveModalLabel">
        <div class="modal-dialog modal-lg" role="document" style="width: 90%;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="viewArchiveModalLabel">
                        <i class="fa fa-file-text-o text-info"></i> Archive File Reader: <span id="modalFilename"></span>
                    </h4>
                </div>
                <div class="modal-body" style="max-height: 550px; overflow-y: auto;">
                    <div id="modalLoading" class="text-center" style="padding: 30px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i> <p>Loading archive content...</p>
                    </div>
                    <div id="modalContentWrapper" style="display: none;">
                        <div class="alert alert-info small" style="margin-bottom: 10px;">
                            Displaying log records for RCA troubleshooting. Total file records: <strong id="modalTotalLines"></strong> | File Size: <strong id="modalFileSize"></strong>
                        </div>
                        <table class="table table-bordered table-striped table-condensed small" id="archiveContentTable">
                            <thead id="archiveTableHeader"></thead>
                            <tbody id="archiveTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Search, Status Filter & Dynamic Pagination Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function initTablePaginationAndFilter(config) {
                var table = document.getElementById(config.tableId);
                if (!table) return;

                var tbody = table.querySelector('tbody');
                if (!tbody) return;

                var allRows = Array.from(tbody.querySelectorAll('tr'));
                var searchInput = document.getElementById(config.searchInputId);
                var statusSelect = document.getElementById(config.statusSelectId);
                var entriesSelect = document.getElementById(config.entriesSelectId);
                var paginationInfo = document.getElementById(config.paginationInfoId);
                var paginationBtns = document.getElementById(config.paginationBtnsId);

                var currentPage = 1;

                function render() {
                    var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
                    var statusFilter = statusSelect ? statusSelect.value.toLowerCase().trim() : 'all';
                    var pageSize = entriesSelect ? (entriesSelect.value === 'all' ? allRows.length : parseInt(entriesSelect.value)) : 10;

                    // Filter rows
                    var filteredRows = allRows.filter(function(row) {
                        var text = row.textContent.toLowerCase();
                        var matchesSearch = !searchTerm || text.indexOf(searchTerm) > -1;
                        
                        var matchesStatus = true;
                        if (statusFilter && statusFilter !== 'all') {
                            var rowStatus = row.getAttribute('data-status') || text;
                            matchesStatus = rowStatus.indexOf(statusFilter) > -1;
                        }

                        return matchesSearch && matchesStatus;
                    });

                    var totalRows = filteredRows.length;
                    var totalPages = Math.ceil(totalRows / pageSize) || 1;

                    if (currentPage > totalPages) currentPage = totalPages;
                    if (currentPage < 1) currentPage = 1;

                    var startIdx = (currentPage - 1) * pageSize;
                    var endIdx = startIdx + pageSize;

                    // Hide all rows, show active page slice
                    allRows.forEach(function(row) {
                        row.style.display = 'none';
                    });

                    filteredRows.slice(startIdx, endIdx).forEach(function(row) {
                        row.style.display = '';
                    });

                    // Update info text
                    if (paginationInfo) {
                        if (totalRows === 0) {
                            paginationInfo.textContent = 'Showing 0 to 0 of 0 entries';
                        } else {
                            var startDisplay = startIdx + 1;
                            var endDisplay = Math.min(endIdx, totalRows);
                            paginationInfo.textContent = 'Showing ' + startDisplay + ' to ' + endDisplay + ' of ' + totalRows + ' entries';
                        }
                    }

                    // Render pagination buttons
                    if (paginationBtns) {
                        paginationBtns.innerHTML = '';

                        var ul = document.createElement('ul');
                        ul.className = 'pagination pagination-sm';
                        ul.style.margin = '0';

                        // Prev button
                        var prevLi = document.createElement('li');
                        if (currentPage === 1) prevLi.className = 'disabled';
                        var prevA = document.createElement('a');
                        prevA.href = '#';
                        prevA.innerHTML = '&laquo; Prev';
                        prevA.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (currentPage > 1) {
                                currentPage--;
                                render();
                            }
                        });
                        prevLi.appendChild(prevA);
                        ul.appendChild(prevLi);

                        // Page numbers
                        var maxButtons = 5;
                        var startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
                        var endPage = Math.min(totalPages, startPage + maxButtons - 1);
                        if (endPage - startPage < maxButtons - 1) {
                            startPage = Math.max(1, endPage - maxButtons + 1);
                        }

                        for (var i = startPage; i <= endPage; i++) {
                            (function(p) {
                                var li = document.createElement('li');
                                if (p === currentPage) li.className = 'active';
                                var a = document.createElement('a');
                                a.href = '#';
                                a.textContent = p;
                                a.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    currentPage = p;
                                    render();
                                });
                                li.appendChild(a);
                                ul.appendChild(li);
                            })(i);
                        }

                        // Next button
                        var nextLi = document.createElement('li');
                        if (currentPage === totalPages) nextLi.className = 'disabled';
                        var nextA = document.createElement('a');
                        nextA.href = '#';
                        nextA.innerHTML = 'Next &raquo;';
                        nextA.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (currentPage < totalPages) {
                                currentPage++;
                                render();
                            }
                        });
                        nextLi.appendChild(nextA);
                        ul.appendChild(nextLi);

                        paginationBtns.appendChild(ul);
                    }
                }

                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        currentPage = 1;
                        render();
                    });
                }

                if (statusSelect) {
                    statusSelect.addEventListener('change', function() {
                        currentPage = 1;
                        render();
                    });
                }

                if (entriesSelect) {
                    entriesSelect.addEventListener('change', function() {
                        currentPage = 1;
                        render();
                    });
                }

                render();
            }

            // Initialize pagination and filtering for all backup & log tables
            initTablePaginationAndFilter({
                tableId: 'nodeBackupTable',
                searchInputId: 'nodeSearchInput',
                entriesSelectId: 'nodeEntriesSelect',
                paginationInfoId: 'nodePaginationInfo',
                paginationBtnsId: 'nodePaginationBtns'
            });

            initTablePaginationAndFilter({
                tableId: 'nodeLogsTable',
                searchInputId: 'nodeLogsSearchInput',
                statusSelectId: 'nodeLogsStatusSelect',
                entriesSelectId: 'nodeLogsEntriesSelect',
                paginationInfoId: 'nodeLogsPaginationInfo',
                paginationBtnsId: 'nodeLogsPaginationBtns'
            });

            initTablePaginationAndFilter({
                tableId: 'sysLogsTable',
                searchInputId: 'sysLogsSearchInput',
                statusSelectId: 'sysLogsStatusSelect',
                entriesSelectId: 'sysLogsEntriesSelect',
                paginationInfoId: 'sysLogsPaginationInfo',
                paginationBtnsId: 'sysLogsPaginationBtns'
            });

            initTablePaginationAndFilter({
                tableId: 'rrdBackupTable',
                searchInputId: 'rrdSearchInput',
                entriesSelectId: 'rrdEntriesSelect',
                paginationInfoId: 'rrdPaginationInfo',
                paginationBtnsId: 'rrdPaginationBtns'
            });

            initTablePaginationAndFilter({
                tableId: 'dbBackupTable',
                searchInputId: 'dbSearchInput',
                entriesSelectId: 'dbEntriesSelect',
                paginationInfoId: 'dbPaginationInfo',
                paginationBtnsId: 'dbPaginationBtns'
            });

            $(document).on('click', '.btn-view-archive', function() {
                var archiveId = $(this).data('id');
                var filename = $(this).data('filename');

                $('#modalFilename').text(filename);
                $('#modalLoading').show();
                $('#modalContentWrapper').hide();
                $('#viewArchiveModal').modal('show');

                $.ajax({
                    url: '/alerts/archive/view/' + archiveId,
                    type: 'GET',
                    success: function(response) {
                        $('#modalLoading').hide();
                        $('#modalTotalLines').text(response.total_lines);
                        $('#modalFileSize').text(response.file_size);

                        var thead = '';
                        var tbody = '';

                        if (response.data && response.data.length > 0) {
                            var headerRow = response.data[0];
                            thead += '<tr>';
                            $.each(headerRow, function(idx, col) {
                                thead += '<th>' + $('<div>').text(col).html() + '</th>';
                            });
                            thead += '</tr>';

                            for (var i = 1; i < response.data.length; i++) {
                                tbody += '<tr>';
                                $.each(response.data[i], function(idx, col) {
                                    tbody += '<td>' + $('<div>').text(col).html() + '</td>';
                                });
                                tbody += '</tr>';
                            }
                        } else {
                            tbody = '<tr><td colspan="7" class="text-center">No data found in archive file.</td></tr>';
                        }

                        $('#archiveTableHeader').html(thead);
                        $('#archiveTableBody').html(tbody);
                        $('#modalContentWrapper').show();
                    },
                    error: function() {
                        $('#modalLoading').hide();
                        alert('Failed to load archive file contents.');
                    }
                });
            });
        });
    </script>
@endsection
