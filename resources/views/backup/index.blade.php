@extends('layouts.librenmsv1')

@section('title')
    Database Backup
@endsection

@section('content')
    <div class="container">
        <div class="page-header">
            <h1>Manual Database Backup</h1>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h5 class="panel-title">Run Manual Backup</h5>
                    </div>
                    <div class="panel-body">
                        <p>Select the destination where you would like to save the database backup.</p>
                        
                        <form action="{{ route('backup.run') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="destination">Backup Destination:</label>
                                <select name="destination" id="destination" class="form-control" required>
                                    <option value="local">Same Device (storage/app/backups/)</option>
                                    <option value="external">External Hard Drive (/mnt/external/)</option>
                                    <option value="network">Network Drive (/mnt/network/)</option>
                                </select>
                                <span class="help-block">
                                    <strong>Note:</strong> External and Network destinations must be correctly mounted in the Docker container.
                                </span>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg btn-block">
                                    <i class="fa fa-play fa-fw"></i> Start Manual Backup
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h5 class="panel-title">Available Backups (Same Device)</h5>
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
                                            <td>{{ $backup['name'] }}</td>
                                            <td>{{ $backup['size'] }}</td>
                                            <td>{{ $backup['date'] }}</td>
                                            <td>
                                                <a href="{{ route('backup.download', ['filename' => $backup['name']]) }}" class="btn btn-xs btn-success">
                                                    <i class="fa fa-download"></i> Download
                                                </a>
                                                <form action="{{ route('backup.restore', ['filename' => $backup['name']]) }}" method="POST" style="display:inline;" onsubmit="return confirm('WARNING: This will overwrite your current database with the backup data. Are you absolutely sure you want to restore this backup?');">
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
                            <p class="text-muted">No local backups found in <code>storage/app/backups/</code>.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h5 class="panel-title">Recent Backup Activity Logs</h5>
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
                                            <td>{{ $log->filename }}</td>
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

        <div class="row" style="margin-top: 15px;">
            <div class="col-md-12">
                <a href="{{ route('home') }}" class="btn btn-default">Back to Home</a>
            </div>
        </div>
    </div>
@endsection
