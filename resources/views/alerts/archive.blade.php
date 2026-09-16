@extends('layouts.librenmsv1')

@section('title')
    Alarm History Archive
@endsection

@section('content')
    <div class="container-fluid" style="padding: 20px;">
        <div class="page-header" style="margin-top: 0;">
            <h1><i class="fa fa-history text-warning"></i> Alarm History Archive <small>Export, Buffer & Manage Historical Alert Logs</small></h1>
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

        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h5 class="panel-title"><i class="fa fa-play-circle text-success"></i> Run Manual Alarm Backup</h5>
                    </div>
                    <div class="panel-body">
                        <p>Archive current alert history log entries into a CSV file immediately, independent of the automated schedule below.</p>

                        <form action="{{ route('alerts.archive.store') }}" method="POST" class="form-inline">
                            @csrf
                            <div class="form-group" style="margin-right: 10px;">
                                <label for="manual_archive_destination" style="margin-right: 5px;">Backup Destination:</label>
                                <select name="destination" id="manual_archive_destination" class="form-control input-sm">
                                    <option value="local" {{ $archive_destination == 'local' ? 'selected' : '' }}>Primary (/tftpboot/alarms/)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fa fa-archive fa-fw"></i> Start Manual Alarm Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Archive Files List -->
            <div class="col-md-9">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-md-6">
                                <strong><i class="fa fa-file-text-o"></i> Archived Alarm History Files</strong>
                            </div>
                            <div class="col-md-6">
                                <form method="GET" action="{{ route('alerts.archive.index') }}" class="form-inline pull-right">
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control input-sm" placeholder="Search archives..." value="{{ request('search') }}">
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-default input-sm"><i class="fa fa-search"></i></button>
                                        </span>
                                    </div>
                                </form>
                            </div>
                        </div>
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
                                    @forelse ($archives as $archive)
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
                        <div class="pull-right">
                            {{ $archives->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings & Info -->
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <strong><i class="fa fa-cogs"></i> Automated Archive Schedule</strong>
                    </div>
                    <div class="panel-body">
                        <form action="{{ route('alerts.archive.settings') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="archive_time">Execution Time:</label>
                                <input type="time" name="archive_time" id="archive_time" class="form-control" value="{{ $archive_time }}" required>
                            </div>

                            <div class="form-group">
                                <label for="archive_interval_days">Backup Interval (Days):</label>
                                <input type="number" name="archive_interval_days" id="archive_interval_days" class="form-control" value="{{ $archive_interval_days }}" min="1" required>
                                <span class="help-block small">Run every N days (e.g. 1 for daily).</span>
                            </div>

                            <div class="form-group">
                                <label for="archive_destination">Backup Destination:</label>
                                <select name="archive_destination" id="archive_destination" class="form-control" required>
                                    <option value="local" {{ $archive_destination == 'local' ? 'selected' : '' }}>Primary (/tftpboot/alarms/)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="purge_days">Retention Period (Days):</label>
                                <input type="number" name="purge_days" id="purge_days" class="form-control" value="{{ $purge_days }}" min="1" max="3650" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-save"></i> Save Schedule Settings
                            </button>
                        </form>
                    </div>
                    <div class="panel-footer small text-muted">
                        <strong>Last Run:</strong> {{ $last_run }}<br>
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
                                <label for="archive_file">Select Archive (.csv):</label>
                                <input type="file" name="archive_file" id="archive_file" class="form-control input-sm" accept=".csv" required>
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

    <script>
        $(document).ready(function() {
            $('.btn-view-archive').on('click', function() {
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
                            // First row is header
                            var headerRow = response.data[0];
                            thead += '<tr>';
                            $.each(headerRow, function(idx, col) {
                                thead += '<th>' + $('<div>').text(col).html() + '</th>';
                            });
                            thead += '</tr>';

                            // Remaining rows are data
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
