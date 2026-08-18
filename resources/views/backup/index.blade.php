@extends('layouts.librenmsv1')

@section('title')
    System & Data Backup Management
@endsection

@section('content')
    <div class="container">
        <div class="page-header">
            <h1><i class="fa fa-database text-primary"></i> Backup Management <small>Database & RRD Files</small></h1>
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
                <a href="#backup-logs" data-toggle="tab">
                    <i class="fa fa-history text-muted"></i> <strong>Activity Logs</strong>
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- ================= RRD BACKUP TAB ================= -->
            <div class="tab-pane fade in active" id="rrd-backup">
                <div class="row">
                    <div class="col-md-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-play-circle text-primary"></i> Run Manual RRD Backup</h5>
                            </div>
                            <div class="panel-body">
                                <p>Creates a compressed archive (<code>.tar.gz</code>) of all historical metric graphs in <code>rrd/</code> directory. Pending writes are automatically flushed from <code>rrdcached</code> prior to archiving.</p>
                                
                                <form action="{{ route('backup.rrd.run') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label for="rrd_destination">Backup Destination:</label>
                                        <select name="destination" id="rrd_destination" class="form-control" required>
                                            <option value="local">Same Device (storage/app/backups/rrd/)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fa fa-file-archive-o fa-fw"></i> Start Manual RRD Backup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-clock-o"></i> Daily Automated RRD Backup Schedule</h5>
                            </div>
                            <div class="panel-body">
                                <form action="{{ route('backup.rrd.save-schedule') }}" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <label for="rrd_backup_time">Daily Backup Execution Time:</label>
                                        <input type="time" name="rrd_backup_time" id="rrd_backup_time" class="form-control" value="{{ $rrd_backup_time }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="rrd_backup_destination">Backup Destination:</label>
                                        <select name="rrd_backup_destination" id="rrd_backup_destination" class="form-control" required>
                                            <option value="local" {{ $rrd_backup_destination == 'local' ? 'selected' : '' }}>Same Device (storage/app/backups/rrd/)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="rrd_backup_purge_days">Retention Period (Days):</label>
                                        <input type="number" name="rrd_backup_purge_days" id="rrd_backup_purge_days" class="form-control" value="{{ $rrd_backup_purge_days }}" min="1" required>
                                    </div>
                                    <div class="form-group" style="margin-top: 15px; margin-bottom: 0;">
                                        <button type="submit" class="btn btn-info btn-block">
                                            <i class="fa fa-floppy-o fa-fw"></i> Save RRD Schedule Settings
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
                                <h5 class="panel-title"><i class="fa fa-list"></i> Available RRD Backups</h5>
                            </div>
                            <div class="panel-body">
                                @if (count($rrdBackups) > 0)
                                    <table class="table table-striped table-hover">
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
                                            <option value="local">Same Device (storage/app/backups/)</option>
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
                                <p>Upload a <code>.sql</code> backup file from your local machine.</p>

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
                                <h5 class="panel-title"><i class="fa fa-clock-o"></i> Daily Database Backup Schedule</h5>
                            </div>
                            <div class="panel-body">
                                <form action="{{ route('backup.save-schedule') }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="db_backup_time">Execution Time:</label>
                                                <input type="time" name="db_backup_time" id="db_backup_time" class="form-control" value="{{ $db_backup_time }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="db_backup_destination">Backup Destination:</label>
                                                <select name="db_backup_destination" id="db_backup_destination" class="form-control" required>
                                                    <option value="local" {{ $db_backup_destination == 'local' ? 'selected' : '' }}>Same Device (storage/app/backups/)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
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
                                <h5 class="panel-title"><i class="fa fa-list"></i> Available Database Backups</h5>
                            </div>
                            <div class="panel-body">
                                @if (count($backups) > 0)
                                    <table class="table table-striped table-hover">
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
                                                        <a href="{{ route('backup.download', ['filename' => $backup['name']]) }}" class="btn btn-xs btn-success">
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
                                @else
                                    <p class="text-muted">No database backups found in <code>storage/app/backups/</code>.</p>
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
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="panel-title"><i class="fa fa-history"></i> Backup & Restore Activity Logs</h5>
                            </div>
                            <div class="panel-body">
                                @if ($logs->count() > 0)
                                    <table class="table table-condensed table-striped">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Filename</th>
                                                <th>Destination</th>
                                                <th>Status</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($logs as $log)
                                                <tr>
                                                    <td>{{ $log->user->username ?? 'System' }}</td>
                                                    <td><span class="label label-{{ $log->action == 'delete' ? 'danger' : ($log->action == 'create' ? 'primary' : 'success') }}">{{ strtoupper($log->action) }}</span></td>
                                                    <td><code>{{ $log->filename }}</code></td>
                                                    <td>{{ $log->destination ?? 'N/A' }}</td>
                                                    <td>
                                                        <span class="text-{{ $log->status == 'success' ? 'success' : 'danger' }}">
                                                            <i class="fa fa-{{ $log->status == 'success' ? 'check-circle' : 'exclamation-circle' }}"></i> {{ strtoupper($log->status) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $log->created_at->diffForHumans() }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <p class="text-muted">No activity logs found.</p>
                                @endif
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
@endsection
