@extends('layouts.librenmsv1')

@section('title')
    Test Runner
@endsection

@section('content')
<style>
    .jt-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; background:#999; }
    .jt-success { background:#2e9e4f; } .jt-failure { background:#d9363e; } .jt-unstable { background:#e6a100; }
    .jt-running { background:#2b7bd6; animation: jt-pulse 1s infinite; } .jt-aborted, .jt-never { background:#999; }
    @keyframes jt-pulse { 50% { opacity:.3; } }
    .jt-jobs .list-group-item { cursor:pointer; }
    .jt-card { text-align:center; padding:12px 6px; border-radius:6px; border:1px solid rgba(128,128,128,.25); margin-bottom:12px; }
    .jt-card .jt-num { font-size:28px; font-weight:bold; line-height:1.1; }
    .jt-card .jt-lbl { font-size:12px; text-transform:uppercase; opacity:.7; }
    .jt-pass { color:#2e9e4f; } .jt-fail { color:#d9363e; } .jt-skip { color:#e6a100; }
    .jt-bar { display:flex; height:8px; border-radius:4px; overflow:hidden; background:rgba(128,128,128,.2); min-width:80px; }
    .jt-bar span { display:block; height:100%; }
    .jt-builds tr { cursor:pointer; } .jt-builds tr.active td { font-weight:bold; }
    .jt-console { background:#1e1e1e; color:#ddd; font-size:12px; max-height:500px; overflow:auto; white-space:pre-wrap; word-break:break-all; }
    .jt-error { white-space:pre-wrap; font-size:12px; margin:6px 0 0; max-height:250px; overflow:auto; }
    .jt-case { padding:8px 10px; border-bottom:1px solid rgba(128,128,128,.2); }
    .jt-case .jt-group { opacity:.6; font-size:12px; }
    #jtRunBtn { min-width:140px; }
    .jt-empty { padding:40px; text-align:center; opacity:.6; }
</style>

<div class="container-fluid">
    <div class="page-header" style="margin-top:10px;">
        <h1>
            <i class="fa fa-flask text-primary"></i> Test Runner <small>run playbook tests and see what passed or failed</small>
            <a href="{{ $jenkinsUrl }}" target="_blank" rel="noopener" class="btn btn-default btn-sm pull-right" style="margin-top:8px;">
                <i class="fa fa-external-link"></i> Open Jenkins
            </a>
        </h1>
    </div>

    @unless ($configured)
        <div class="alert alert-warning">
            <h4><i class="fa fa-plug"></i> Jenkins is not connected yet</h4>
            Add these lines to <code>.env</code> and reload the page:
<pre style="margin-top:8px;">JENKINS_URL=http://172.17.0.1:8080
JENKINS_USER=kunal
JENKINS_TOKEN=&lt;API token from Jenkins → your name → Security → API Token&gt;</pre>
        </div>
    @endunless

    <div id="jtFlash"></div>

    <div class="row">
        {{-- ---------- Jobs ---------- --}}
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>Test jobs</strong>
                    <a href="#" id="jtReloadJobs" class="pull-right" title="Refresh"><i class="fa fa-refresh"></i></a>
                </div>
                <div class="list-group jt-jobs" id="jtJobs">
                    <div class="list-group-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading…</div>
                </div>
            </div>
        </div>

        {{-- ---------- Selected job ---------- --}}
        <div class="col-md-9">
            <div id="jtNoJob" class="panel panel-default"><div class="jt-empty"><i class="fa fa-hand-o-left fa-2x"></i><br>Select a job on the left</div></div>

            <div id="jtJob" style="display:none;">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <h3 style="margin-top:0;"><span id="jtJobDot" class="jt-dot"></span><span id="jtJobName"></span>
                            <small id="jtQueued" style="display:none;"><i class="fa fa-clock-o"></i> waiting in queue…</small></h3>
                        <form id="jtParams" class="form-inline" style="margin-bottom:10px;"></form>
                        <button id="jtRunBtn" class="btn btn-success btn-lg"><i class="fa fa-play"></i> Run tests</button>
                    </div>
                </div>

                <div class="row" id="jtCards">
                    <div class="col-xs-6 col-sm-3"><div class="jt-card"><div class="jt-num jt-pass" id="jtPassed">–</div><div class="jt-lbl">Passed</div></div></div>
                    <div class="col-xs-6 col-sm-3"><div class="jt-card"><div class="jt-num jt-fail" id="jtFailed">–</div><div class="jt-lbl">Failed</div></div></div>
                    <div class="col-xs-6 col-sm-3"><div class="jt-card"><div class="jt-num jt-skip" id="jtSkipped">–</div><div class="jt-lbl">Skipped</div></div></div>
                    <div class="col-xs-6 col-sm-3"><div class="jt-card"><div class="jt-num" id="jtDuration">–</div><div class="jt-lbl">Duration</div></div></div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Runs</strong> <small class="text-muted">click a run to see its results</small></div>
                    <div class="table-responsive">
                        <table class="table table-hover table-condensed jt-builds" style="margin:0;">
                            <thead><tr><th>#</th><th>Status</th><th>Tests</th><th style="width:25%;">Pass / Fail</th><th>Started</th><th>Duration</th><th></th></tr></thead>
                            <tbody id="jtBuilds"></tbody>
                        </table>
                    </div>
                </div>

                <div class="panel panel-default" id="jtDetail" style="display:none;">
                    <div class="panel-heading">
                        <strong>Run #<span id="jtDetailNum"></span></strong>
                        <ul class="nav nav-pills pull-right" style="margin-top:-6px;">
                            <li class="active"><a href="#jtTabResults" data-toggle="tab">Results</a></li>
                            <li><a href="#jtTabConsole" data-toggle="tab" id="jtConsoleTab">Console</a></li>
                        </ul>
                        <div class="clearfix"></div>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane active" id="jtTabResults">
                            <div class="panel-body" style="padding-bottom:0;">
                                <div class="btn-group btn-group-sm" id="jtFilter">
                                    <button class="btn btn-default active" data-f="all">All</button>
                                    <button class="btn btn-default" data-f="failed"><i class="fa fa-times jt-fail"></i> Failed</button>
                                    <button class="btn btn-default" data-f="passed"><i class="fa fa-check jt-pass"></i> Passed</button>
                                    <button class="btn btn-default" data-f="skipped"><i class="fa fa-minus jt-skip"></i> Skipped</button>
                                </div>
                                <input type="search" id="jtSearch" class="form-control input-sm pull-right" style="width:220px;" placeholder="Search test / playbook…">
                            </div>
                            <div id="jtCases" style="margin-top:10px;"></div>
                        </div>
                        <div class="tab-pane" id="jtTabConsole">
                            <pre class="jt-console" id="jtConsole" style="margin:0; border-radius:0;"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    const base = @json(url('jenkins/api'));
    const state = { job: null, build: null, filter: 'all', cases: [], consoleNext: 0, consoleTimer: null, jobTimer: null };

    const esc = s => $('<div>').text(s == null ? '' : String(s)).html();
    const jobUrl = job => base + '/job/' + encodeURIComponent(job);
    const statusText = { success: 'Passed', failure: 'Failed', unstable: 'Some tests failed', running: 'Running', aborted: 'Stopped', never: 'Never run', unknown: 'Unknown' };
    const statusLabel = { success: 'success', failure: 'danger', unstable: 'warning', running: 'primary', aborted: 'default', never: 'default', unknown: 'default' };

    function flash(type, msg) {
        $('#jtFlash').html('<div class="alert alert-' + type + ' alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + esc(msg) + '</div>');
    }
    function apiError(xhr) { flash('danger', (xhr.responseJSON && xhr.responseJSON.error) || 'Request failed (HTTP ' + xhr.status + ')'); }
    function ago(ts) {
        if (!ts) return '';
        const s = Math.floor(Date.now() / 1000) - ts;
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + ' min ago';
        if (s < 86400) return Math.floor(s / 3600) + ' h ago';
        return new Date(ts * 1000).toLocaleString();
    }
    function dur(sec) { return sec < 60 ? sec + 's' : Math.floor(sec / 60) + 'm ' + (sec % 60) + 's'; }
    function bar(t) {
        if (!t || !t.total) return '<span class="text-muted">–</span>';
        const p = v => (v / t.total * 100) + '%';
        return '<div class="jt-bar" title="' + t.passed + ' passed, ' + t.failed + ' failed, ' + t.skipped + ' skipped">' +
            '<span style="width:' + p(t.passed) + ';background:#2e9e4f"></span>' +
            '<span style="width:' + p(t.failed) + ';background:#d9363e"></span>' +
            '<span style="width:' + p(t.skipped) + ';background:#e6a100"></span></div>';
    }

    // ---------------- Jobs ----------------
    function loadJobs() {
        $.getJSON(base + '/jobs').done(jobs => {
            if (!jobs.length) { $('#jtJobs').html('<div class="list-group-item text-muted">No jobs in Jenkins yet</div>'); return; }
            $('#jtJobs').html(jobs.map(j =>
                '<a class="list-group-item' + (j.name === state.job ? ' active' : '') + '" data-job="' + esc(j.name) + '">' +
                '<span class="jt-dot jt-' + j.status + '"></span>' + esc(j.name) +
                '<br><small class="text-muted" style="margin-left:16px;">' + (j.last_build ? '#' + j.last_build + ' · ' + ago(j.last_time) : 'never run') + '</small></a>'
            ).join(''));
        }).fail(xhr => { $('#jtJobs').html('<div class="list-group-item text-danger">Could not load jobs</div>'); apiError(xhr); });
    }

    $('#jtJobs').on('click', '[data-job]', function () {
        $('#jtJobs .active').removeClass('active'); $(this).addClass('active');
        selectJob($(this).data('job'));
    });
    $('#jtReloadJobs').on('click', e => { e.preventDefault(); loadJobs(); });

    function selectJob(job) {
        state.job = job; state.build = null;
        $('#jtNoJob').hide(); $('#jtJob').show(); $('#jtDetail').hide();
        $('#jtJobName').text(job); $('#jtParams').empty(); $('#jtBuilds').html('<tr><td colspan="7"><i class="fa fa-spinner fa-spin"></i></td></tr>');
        loadJob(true);
    }

    function loadJob(first) {
        const job = state.job;
        clearTimeout(state.jobTimer);
        $.getJSON(jobUrl(job)).done(d => {
            if (job !== state.job) return;
            if (first) renderParams(d.params);
            renderBuilds(d);
            const busy = d.in_queue || d.builds.some(b => b.status === 'running');
            if (busy) state.jobTimer = setTimeout(loadJob, 4000);
            if (first && d.builds.length) openBuild(d.builds[0].number);
        }).fail(apiError);
    }

    function renderParams(params) {
        $('#jtParams').html(params.map(p => {
            const id = 'jtp_' + p.name.replace(/\W/g, '_'), help = p.description ? ' title="' + esc(p.description) + '"' : '';
            if (p.type === 'boolean') {
                return '<div class="checkbox" style="margin-right:15px;"' + help + '><label><input type="checkbox" name="' + esc(p.name) + '"' + (p.default ? ' checked' : '') + '> ' + esc(p.name) + '</label></div>';
            }
            let input;
            if (p.type === 'choice') {
                input = '<select class="form-control input-sm" id="' + id + '" name="' + esc(p.name) + '">' + p.choices.map(c => '<option' + (c === p.default ? ' selected' : '') + '>' + esc(c) + '</option>').join('') + '</select>';
            } else {
                input = '<input class="form-control input-sm" id="' + id + '" name="' + esc(p.name) + '" type="' + (p.type === 'password' ? 'password' : 'text') + '" value="' + esc(p.type === 'password' ? '' : (p.default ?? '')) + '">';
            }
            return '<div class="form-group" style="margin-right:15px;"' + help + '><label for="' + id + '">' + esc(p.name) + '</label> ' + input + '</div>';
        }).join(''));
    }

    function renderBuilds(d) {
        $('#jtQueued').toggle(!!d.in_queue);
        const last = d.builds[0];
        $('#jtJobDot').attr('class', 'jt-dot jt-' + (last ? last.status : 'never'));
        const t = last && last.tests;
        $('#jtPassed').text(t ? t.passed : '–'); $('#jtFailed').text(t ? t.failed : '–'); $('#jtSkipped').text(t ? t.skipped : '–');
        $('#jtDuration').text(last ? dur(last.duration) : '–');
        if (!d.builds.length) { $('#jtBuilds').html('<tr><td colspan="7" class="text-muted">No runs yet - press <b>Run tests</b></td></tr>'); return; }
        $('#jtBuilds').html(d.builds.map(b =>
            '<tr data-build="' + b.number + '"' + (b.number === state.build ? ' class="active"' : '') + '>' +
            '<td>' + b.number + '</td>' +
            '<td><span class="label label-' + statusLabel[b.status] + '">' + (b.status === 'running' ? '<i class="fa fa-spinner fa-spin"></i> ' : '') + statusText[b.status] + '</span></td>' +
            '<td>' + (b.tests ? '<span class="jt-pass">' + b.tests.passed + '</span> / <span class="jt-fail">' + b.tests.failed + '</span>' + (b.tests.skipped ? ' / <span class="jt-skip">' + b.tests.skipped + '</span>' : '') : '<span class="text-muted">–</span>') + '</td>' +
            '<td>' + bar(b.tests) + '</td>' +
            '<td>' + ago(b.time) + '</td>' +
            '<td>' + (b.status === 'running' ? '~' : '') + dur(b.duration) + '</td>' +
            '<td class="text-right">' + (b.status === 'running' ? '<button class="btn btn-xs btn-danger" data-stop="' + b.number + '"><i class="fa fa-stop"></i> Stop</button>' : '') + '</td></tr>'
        ).join(''));
    }

    // ---------------- Run / Stop ----------------
    $('#jtRunBtn').on('click', function () {
        const params = {};
        $('#jtParams').find('input, select').each(function () {
            params[this.name] = this.type === 'checkbox' ? (this.checked ? 'true' : 'false') : $(this).val();
        });
        const $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Starting…');
        $.post(jobUrl(state.job) + '/run', { params: params })
            .done(r => { flash('success', r.message + ' - results will appear below'); setTimeout(() => loadJob(), 1500); setTimeout(loadJobs, 3000); })
            .fail(apiError)
            .always(() => $btn.prop('disabled', false).html('<i class="fa fa-play"></i> Run tests'));
    });

    $('#jtBuilds').on('click', '[data-stop]', function (e) {
        e.stopPropagation();
        const n = $(this).data('stop');
        if (!confirm('Stop run #' + n + '?')) return;
        $.post(jobUrl(state.job) + '/' + n + '/stop').done(r => { flash('info', r.message); setTimeout(() => loadJob(), 1500); }).fail(apiError);
    });

    // ---------------- Build detail ----------------
    $('#jtBuilds').on('click', 'tr[data-build]', function () { openBuild($(this).data('build')); });

    function openBuild(n) {
        state.build = n; state.consoleNext = 0; clearTimeout(state.consoleTimer);
        $('#jtBuilds tr').removeClass('active'); $('#jtBuilds tr[data-build="' + n + '"]').addClass('active');
        $('#jtDetail').show(); $('#jtDetailNum').text(n); $('#jtConsole').text('');
        $('#jtCases').html('<div class="panel-body"><i class="fa fa-spinner fa-spin"></i> Loading results…</div>');
        loadTests(n); loadConsole(n);
    }

    function loadTests(n) {
        $.getJSON(jobUrl(state.job) + '/' + n + '/tests').done(d => {
            if (n !== state.build) return;
            if (!d.available) {
                state.cases = [];
                $('#jtCases').html('<div class="jt-empty">No test report for this run.<br><small>The job has to publish JUnit XML (<code>junit \'reports/*.xml\'</code>). Check the <a href="#" id="jtGoConsole">console</a>.</small></div>');
                return;
            }
            state.cases = d.cases;
            renderCases();
        }).fail(apiError);
    }

    function renderCases() {
        const q = $('#jtSearch').val().toLowerCase();
        const list = state.cases.filter(c => (state.filter === 'all' || c.result === state.filter) &&
            (!q || (c.name + ' ' + c.group).toLowerCase().includes(q)));
        if (!list.length) { $('#jtCases').html('<div class="jt-empty">Nothing to show</div>'); return; }
        const icon = { passed: 'fa-check-circle jt-pass', failed: 'fa-times-circle jt-fail', skipped: 'fa-minus-circle jt-skip' };
        $('#jtCases').html(list.map(c =>
            '<div class="jt-case"><i class="fa fa-lg ' + icon[c.result] + '"></i> <strong>' + esc(c.name) + '</strong> ' +
            '<span class="jt-group">' + esc(c.group) + '</span><span class="pull-right text-muted small">' + c.duration + 's</span>' +
            (c.error ? '<pre class="jt-error">' + esc(c.error) + '</pre>' : '') + '</div>'
        ).join(''));
    }

    $('#jtFilter').on('click', 'button', function () {
        $('#jtFilter .active').removeClass('active'); $(this).addClass('active');
        state.filter = $(this).data('f'); renderCases();
    });
    $('#jtSearch').on('input', renderCases);
    $('#jtCases').on('click', '#jtGoConsole', e => { e.preventDefault(); $('#jtConsoleTab').tab('show'); });

    function loadConsole(n) {
        $.getJSON(jobUrl(state.job) + '/' + n + '/console', { start: state.consoleNext }).done(d => {
            if (n !== state.build) return;
            const $c = $('#jtConsole'), atBottom = $c[0].scrollHeight - $c.scrollTop() - $c.innerHeight() < 40;
            $c.append(document.createTextNode(d.text));
            if (atBottom) $c.scrollTop($c[0].scrollHeight);
            state.consoleNext = d.next;
            if (d.more) {
                state.consoleTimer = setTimeout(() => loadConsole(n), 2000);
            } else if (state.cases.length === 0) {
                loadTests(n); // run just finished - fetch its report
            }
        }).fail(apiError);
    }

    loadJobs();
});
</script>
@endsection
