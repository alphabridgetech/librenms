<?php

if (! Auth::user()->hasGlobalAdmin()) {
    require 'includes/html/error-no-perm.inc.php';
    exit;
}

$pagetitle[] = 'Delete device';

if (Auth::user()->isDemo()) {
    demo_account();
} else {
    // One device (id, from the device edit page) or several (ids[], from the list below).
    $delete_ids = [];
    if (isset($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
        $delete_ids = array_values(array_unique(array_filter($_REQUEST['ids'], 'is_numeric')));
    } elseif (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
        $delete_ids = [$_REQUEST['id']];
    }

    if (! empty($delete_ids)) {
        $delete_devices = \App\Models\Device::whereIn('device_id', $delete_ids)->orderBy('hostname')->get();

        echo '
            <div class="row">
            <div class="col-sm-offset-2 col-sm-7">
            ';
        if ($delete_devices->isEmpty()) {
            print_error('No such device.');
        } elseif (! empty($_REQUEST['confirm'])) {
            // Every device is deleted on its own, so one failure doesn't stop the rest.
            set_time_limit(0);
            $deleted = 0;
            $results = [];
            foreach ($delete_devices as $device) {
                try {
                    $ok = $device->delete();
                } catch (\Throwable $e) {
                    report($e);
                    $ok = false;
                }
                $deleted += $ok ? 1 : 0;
                $results[] = [$ok, $device->hostname];
            }

            $failed = count($results) - $deleted;
            if ($failed) {
                print_error("Removed $deleted of " . count($results) . " devices - $failed failed");
            } else {
                print_message('Removed ' . $deleted . ' device' . ($deleted == 1 ? '' : 's'));
            }
            echo '<ul class="list-group">';
            foreach ($results as [$ok, $hostname]) {
                echo '<li class="list-group-item ' . ($ok ? 'list-group-item-success' : 'list-group-item-danger') . '">'
                    . '<i class="fa ' . ($ok ? 'fa-check' : 'fa-times') . ' fa-fw"></i> '
                    . htmlspecialchars($hostname) . ($ok ? ' removed' : ' - failed to remove') . '</li>';
            }
            echo '</ul><a href="' . url('delhost') . '" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Delete Device</a>';
        } else {
            $count = $delete_devices->count();
            print_error($count == 1
                ? 'Are you sure you want to delete device ' . htmlspecialchars($delete_devices->first()->hostname) . '?'
                : "Are you sure you want to delete these $count devices?"); ?>
<br />
<center>
  <i class="fa fa-exclamation-triangle fa-3x text-danger"></i>
  <br>
            <?php if ($count > 1) { ?>
  <ul class="list-group" style="max-width:500px; text-align:left; margin-top:15px; max-height:300px; overflow-y:auto;">
                <?php foreach ($delete_devices as $device) { ?>
    <li class="list-group-item"><?php echo htmlspecialchars($device->hostname) ?>
        <span class="text-muted"><?php echo htmlspecialchars((string) ($device->sysName ?: '')) ?></span></li>
                <?php } ?>
  </ul>
            <?php } ?>
  <form name="form1" method="post" action="<?php echo url('delhost') ?>" class="form-horizontal" role="form">
            <?php echo csrf_field() ?>
    <div class="form-group">
            <?php foreach ($delete_devices as $device) { ?>
      <input type="hidden" name="ids[]" value="<?php echo (int) $device->device_id ?>" />
            <?php } ?>
      <input type="hidden" name="confirm" value="1" />
      <a href="<?php echo url('delhost') ?>" class="btn btn-default">Cancel</a>
      <button type="submit" class="btn btn-danger" onclick="this.disabled=true; this.innerHTML='<i class=&quot;fa fa-spinner fa-spin&quot;></i> Deleting...'; this.form.submit();">
        Confirm deletion of <?php echo $count ?> device<?php echo $count == 1 ? '' : 's' ?>
      </button>
    </div>
  </form>
</center>
            <?php
        }
        echo '
    </div>
  </div>';
    } else {
        $all_devices = \App\Models\Device::with('location')->orderBy('hostname')
            ->get(['device_id', 'hostname', 'sysName', 'hardware', 'os', 'status', 'disabled', 'location_id']);
        $os_text = fn ($os) => (string) \App\Facades\LibrenmsConfig::get("os.$os.text", $os);
        $os_list = $all_devices->pluck('os')->filter()->unique()->mapWithKeys(fn ($os) => [$os => $os_text($os)])->sort();
        $location_list = $all_devices->map(fn ($d) => (string) $d->location?->location)->filter()->unique()->sort()->values(); ?>

<style>
    .delhost-page { max-width: 1200px; margin: 20px auto; }
    .delhost-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .delhost-toolbar select.form-control { width: auto; max-width: 200px; }
    .delhost-toolbar #delhost_search { flex: 1 1 260px; width: auto; min-width: 200px; }
    .delhost-info { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; margin: 10px 0 6px; font-size: 13px; }
    .delhost-scroll { max-height: 60vh; overflow: auto; border: 1px solid #ddd; border-radius: 4px; }
    .delhost-scroll table { margin: 0; }
    .delhost-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f5f5f5; border-bottom: 1px solid #ddd !important; white-space: nowrap; }
    .delhost-scroll th.sortable { cursor: pointer; user-select: none; }
    .delhost-scroll th.sortable .fa { color: #aaa; margin-left: 4px; }
    .delhost-scroll th.sorted .fa { color: #337ab7; }
    .delhost-scroll td { vertical-align: middle !important; }
    .delhost-scroll tbody tr { cursor: pointer; }
    .delhost-scroll td.wrap { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .delhost-footer { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; margin-top: 12px; }
    .delhost-chips .label { display: inline-block; margin: 0 4px 4px 0; font-size: 12px; font-weight: normal; }
    .delhost-confirm-list { max-height: 35vh; overflow-y: auto; margin: 10px 0; }
    .delhost-confirm-list .list-group-item { padding: 5px 10px; }
    .delhost-log { max-height: 30vh; overflow-y: auto; margin-top: 10px; }
</style>

<div class="delhost-page">
  <h2 style="margin-top:0;">Delete Devices</h2>
  <div class="alert alert-danger" style="padding:10px 15px;">
    <i class="fa fa-exclamation-triangle"></i>
    Deleted devices are no longer monitored, and their historical data (<mark>Syslog</mark>, <mark>Eventlog</mark>, <mark>Alert log</mark>) is removed. This cannot be undone.
  </div>

  <form method="post" action="<?php echo url('delhost') ?>" id="delhost_form">
    <?php echo csrf_field() ?>
    <div class="panel panel-default">
      <div class="panel-body">
        <div class="delhost-toolbar">
          <input type="text" id="delhost_search" class="form-control" placeholder="Search devices..." title="Matches hostname, name, hardware, OS and location" autocomplete="off">
          <select id="delhost_status" class="form-control">
            <option value="">All status</option>
            <option value="up">Up</option>
            <option value="down">Down</option>
            <option value="disabled">Disabled</option>
          </select>
          <select id="delhost_os" class="form-control">
            <option value="">All OS</option>
            <?php foreach ($os_list as $os => $text) { ?>
            <option value="<?php echo htmlspecialchars($os) ?>"><?php echo htmlspecialchars($text) ?></option>
            <?php } ?>
          </select>
          <?php if ($location_list->count() > 1) { ?>
          <select id="delhost_location" class="form-control">
            <option value="">All locations</option>
            <?php foreach ($location_list as $loc) { ?>
            <option value="<?php echo htmlspecialchars($loc) ?>"><?php echo htmlspecialchars($loc) ?></option>
            <?php } ?>
          </select>
          <?php } ?>
          <button type="button" id="delhost_only_selected" class="btn btn-default" title="Show only the selected devices">
            <i class="fa fa-check-square-o"></i> Selected only
          </button>
          <button type="button" id="delhost_reset" class="btn btn-link">Reset filters</button>
        </div>

        <div class="delhost-info">
          <span class="text-muted">Showing <strong id="delhost_shown"><?php echo $all_devices->count() ?></strong> of <?php echo $all_devices->count() ?> devices</span>
          <span><strong id="delhost_selected">0</strong> selected
            <a href="#" id="delhost_clear" style="margin-left:6px; display:none;">Clear selection</a></span>
        </div>

        <div class="delhost-scroll">
          <table class="table table-condensed table-hover">
            <thead>
              <tr>
                <th width="34"><input type="checkbox" id="delhost_all" title="Select all shown"></th>
                <th class="sortable sorted" data-sort="hostname">Hostname <i class="fa fa-sort-asc"></i></th>
                <th class="sortable hidden-xs" data-sort="name">Name <i class="fa fa-sort"></i></th>
                <th class="sortable" data-sort="hardware">Hardware <i class="fa fa-sort"></i></th>
                <th class="sortable" data-sort="ostext">OS <i class="fa fa-sort"></i></th>
                <th class="sortable hidden-xs" data-sort="location">Location <i class="fa fa-sort"></i></th>
                <th class="sortable" data-sort="status">Status <i class="fa fa-sort"></i></th>
              </tr>
            </thead>
            <tbody id="delhost_rows">
            <?php foreach ($all_devices as $d) {
                $status = $d->disabled ? 'disabled' : ($d->status ? 'up' : 'down');
                $label = ['up' => 'success', 'down' => 'danger', 'disabled' => 'default'][$status];
                $loc = (string) $d->location?->location; ?>
              <tr data-id="<?php echo (int) $d->device_id ?>"
                  data-hostname="<?php echo htmlspecialchars($d->hostname) ?>"
                  data-name="<?php echo htmlspecialchars((string) $d->sysName) ?>"
                  data-hardware="<?php echo htmlspecialchars((string) $d->hardware) ?>"
                  data-os="<?php echo htmlspecialchars((string) $d->os) ?>"
                  data-ostext="<?php echo htmlspecialchars($os_text($d->os)) ?>"
                  data-location="<?php echo htmlspecialchars($loc) ?>"
                  data-status="<?php echo $status ?>">
                <td><input type="checkbox" name="ids[]" class="delhost-check" value="<?php echo (int) $d->device_id ?>"></td>
                <td><strong><?php echo htmlspecialchars($d->hostname) ?></strong></td>
                <td class="wrap hidden-xs" title="<?php echo htmlspecialchars((string) $d->sysName) ?>"><?php echo htmlspecialchars((string) $d->sysName) ?></td>
                <td class="wrap" title="<?php echo htmlspecialchars((string) $d->hardware) ?>"><?php echo htmlspecialchars((string) $d->hardware) ?></td>
                <td><?php echo htmlspecialchars($os_text($d->os)) ?></td>
                <td class="wrap hidden-xs" title="<?php echo htmlspecialchars($loc) ?>"><?php echo htmlspecialchars($loc ?: '-') ?></td>
                <td><span class="label label-<?php echo $label ?>"><?php echo $status ?></span></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          <div id="delhost_none" class="text-muted text-center" style="display:none; padding:25px;">No device matches the filters</div>
        </div>

        <div class="delhost-footer">
          <span class="text-muted" style="font-size:12px;">Tip: filter, tick the header box to select everything shown, then delete.</span>
          <button id="confirm_delete" type="submit" class="btn btn-danger" disabled>
            <i class="fa fa-trash"></i> Delete selected (<span id="delhost_count">0</span>)
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<div class="modal fade" id="delhost_modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title"><i class="fa fa-exclamation-triangle text-danger"></i> <span id="delhost_modal_title">Delete devices</span></h4>
      </div>
      <div class="modal-body">
        <div id="delhost_step_confirm">
          <p>You are about to delete <strong id="delhost_confirm_count">0</strong> device(s):</p>
          <div class="delhost-chips" id="delhost_confirm_chips"></div>
          <ul class="list-group delhost-confirm-list" id="delhost_confirm_list"></ul>
          <div id="delhost_type_box" class="form-group" style="display:none;">
            <label for="delhost_type" style="font-weight:normal;">Type <strong>DELETE</strong> to confirm:</label>
            <input type="text" id="delhost_type" class="form-control" autocomplete="off">
          </div>
        </div>
        <div id="delhost_step_progress" style="display:none;">
          <p id="delhost_progress_text">Deleting...</p>
          <div class="progress" style="margin-bottom:5px;">
            <div id="delhost_bar" class="progress-bar progress-bar-striped active" role="progressbar" style="width:0%; min-width:2em;">0%</div>
          </div>
          <p class="text-muted" id="delhost_keep_open" style="font-size:12px;">Keep this page open until it finishes.</p>
          <ul class="list-group delhost-log" id="delhost_log"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" id="delhost_cancel" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="delhost_go" disabled><i class="fa fa-trash"></i> Delete</button>
        <button type="button" class="btn btn-primary" id="delhost_done" style="display:none;">Done</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
    const BATCH = <?php echo \App\Http\Controllers\DeviceBulkDeleteController::BATCH ?>;
    const TYPE_CONFIRM_FROM = 10;          // this many or more devices need "DELETE" typed
    const BULK_URL = <?php echo json_encode(url('delhost/bulk')) ?>;

    const tbody   = document.getElementById('delhost_rows');
    const rows    = Array.from(tbody.querySelectorAll('tr'));
    const $       = id => document.getElementById(id);
    const box     = row => row.firstElementChild.firstElementChild;
    const esc     = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const filters = { q: '', status: '', os: '', location: '', onlySelected: false };
    let shownRows = rows.slice();

    // Search text per row, worked out once (fast with thousands of rows).
    rows.forEach(row => {
        const d = row.dataset;
        row._text = [d.hostname, d.name, d.hardware, d.ostext, d.location].join(' ').toLowerCase();
    });

    const selectedRows = () => rows.filter(row => box(row).checked);

    function sync() {
        const n = selectedRows().length;
        $('delhost_count').textContent = n;
        $('delhost_selected').textContent = n;
        $('delhost_clear').style.display = n ? '' : 'none';
        $('confirm_delete').disabled = n === 0;
        const shownChecked = shownRows.filter(row => box(row).checked).length;
        $('delhost_all').checked = shownRows.length > 0 && shownChecked === shownRows.length;
        $('delhost_all').indeterminate = shownChecked > 0 && shownChecked < shownRows.length;
    }

    function applyFilters() {
        const q = filters.q;
        shownRows = rows.filter(row => {
            const d = row.dataset;
            const show = (!q || row._text.includes(q))
                && (!filters.status || d.status === filters.status)
                && (!filters.os || d.os === filters.os)
                && (!filters.location || d.location === filters.location)
                && (!filters.onlySelected || box(row).checked);
            row.style.display = show ? '' : 'none';
            return show;
        });
        $('delhost_shown').textContent = shownRows.length;
        $('delhost_none').style.display = shownRows.length ? 'none' : '';
        sync();
    }

    function markRow(row) {
        row.classList.toggle('danger', box(row).checked);
    }

    // --- filters -----------------------------------------------------------
    let searchTimer = null;
    $('delhost_search').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { filters.q = this.value.trim().toLowerCase(); applyFilters(); }, 150);
    });
    $('delhost_search').addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
    ['status', 'os', 'location'].forEach(key => {
        const el = $('delhost_' + key);
        if (el) el.addEventListener('change', function () { filters[key] = this.value; applyFilters(); });
    });
    $('delhost_only_selected').addEventListener('click', function () {
        filters.onlySelected = !filters.onlySelected;
        this.classList.toggle('active', filters.onlySelected);
        this.classList.toggle('btn-primary', filters.onlySelected);
        applyFilters();
    });
    $('delhost_reset').addEventListener('click', function () {
        $('delhost_search').value = '';
        ['status', 'os', 'location'].forEach(key => { if ($('delhost_' + key)) $('delhost_' + key).value = ''; });
        Object.assign(filters, { q: '', status: '', os: '', location: '', onlySelected: false });
        $('delhost_only_selected').classList.remove('active', 'btn-primary');
        applyFilters();
    });

    // --- selection ---------------------------------------------------------
    $('delhost_all').addEventListener('change', function () {
        shownRows.forEach(row => { box(row).checked = this.checked; markRow(row); });
        sync();
    });
    $('delhost_clear').addEventListener('click', function (e) {
        e.preventDefault();
        rows.forEach(row => { box(row).checked = false; markRow(row); });
        if (filters.onlySelected) applyFilters(); else sync();
    });
    tbody.addEventListener('click', function (e) {
        const row = e.target.closest('tr');
        if (!row) return;
        if (e.target.tagName !== 'INPUT') box(row).checked = !box(row).checked;
        markRow(row);
        sync();
    });

    // --- sorting -----------------------------------------------------------
    let sortKey = 'hostname', sortDir = 1;
    document.querySelectorAll('.delhost-scroll th.sortable').forEach(th => th.addEventListener('click', function () {
        const key = this.dataset.sort;
        sortDir = key === sortKey ? -sortDir : 1;
        sortKey = key;
        document.querySelectorAll('.delhost-scroll th.sortable').forEach(h => {
            h.classList.toggle('sorted', h === this);
            h.querySelector('.fa').className = 'fa ' + (h === this ? (sortDir > 0 ? 'fa-sort-asc' : 'fa-sort-desc') : 'fa-sort');
        });
        const coll = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        rows.sort((a, b) => sortDir * coll.compare(a.dataset[key] || '', b.dataset[key] || ''));
        const frag = document.createDocumentFragment();
        rows.forEach(row => frag.appendChild(row));
        tbody.appendChild(frag);
        shownRows = rows.filter(row => row.style.display !== 'none');
    }));

    // --- confirm + delete in batches --------------------------------------
    let running = false, toDelete = [];

    $('delhost_form').addEventListener('submit', function (e) {
        e.preventDefault();
        toDelete = selectedRows();
        if (!toDelete.length) return;

        const n = toDelete.length;
        $('delhost_modal_title').textContent = 'Delete ' + n + ' device' + (n === 1 ? '' : 's');
        $('delhost_confirm_count').textContent = n;

        // Count per OS, so a wrong filter stands out ("12 x Linux"?).
        const perOs = {};
        toDelete.forEach(row => { perOs[row.dataset.ostext] = (perOs[row.dataset.ostext] || 0) + 1; });
        $('delhost_confirm_chips').innerHTML = Object.entries(perOs).sort((a, b) => b[1] - a[1])
            .map(([os, c]) => `<span class="label label-default">${esc(os)}: ${c}</span>`).join('');

        const hidden = toDelete.filter(row => row.style.display === 'none').length;
        $('delhost_confirm_list').innerHTML = toDelete.map(row =>
            `<li class="list-group-item"><strong>${esc(row.dataset.hostname)}</strong> <span class="text-muted">${esc(row.dataset.name)}</span></li>`).join('')
            + (hidden ? `<li class="list-group-item list-group-item-warning">${hidden} of these are hidden by the current filters</li>` : '');

        const needType = n >= TYPE_CONFIRM_FROM;
        $('delhost_type_box').style.display = needType ? '' : 'none';
        $('delhost_type').value = '';
        $('delhost_go').disabled = needType;
        $('delhost_go').innerHTML = '<i class="fa fa-trash"></i> Delete ' + n + ' device' + (n === 1 ? '' : 's');

        $('delhost_step_confirm').style.display = '';
        $('delhost_step_progress').style.display = 'none';
        $('delhost_cancel').style.display = '';
        $('delhost_go').style.display = '';
        $('delhost_done').style.display = 'none';
        jQuery('#delhost_modal').modal('show');
        if (needType) setTimeout(() => $('delhost_type').focus(), 400);
    });

    $('delhost_type').addEventListener('input', function () {
        $('delhost_go').disabled = this.value.trim() !== 'DELETE';
    });
    $('delhost_type').addEventListener('keydown', e => { if (e.key === 'Enter' && !$('delhost_go').disabled) $('delhost_go').click(); });

    window.addEventListener('beforeunload', function (e) {
        if (running) { e.preventDefault(); e.returnValue = ''; }
    });

    $('delhost_go').addEventListener('click', function () {
        const ids = toDelete.map(row => Number(row.dataset.id));
        const byId = new Map(toDelete.map(row => [Number(row.dataset.id), row]));
        const total = ids.length;
        let done = 0, ok = 0;
        const failed = [];

        running = true;
        $('delhost_step_confirm').style.display = 'none';
        $('delhost_step_progress').style.display = '';
        $('delhost_cancel').style.display = 'none';
        $('delhost_go').style.display = 'none';
        $('delhost_log').innerHTML = '';
        $('delhost_keep_open').style.display = '';
        $('delhost_bar').className = 'progress-bar progress-bar-striped active';

        const progress = () => {
            const pct = Math.round(done * 100 / total);
            $('delhost_bar').style.width = pct + '%';
            $('delhost_bar').textContent = pct + '%';
            $('delhost_progress_text').textContent = `Deleting... ${done} of ${total}`;
        };
        const log = (cls, html) => $('delhost_log').insertAdjacentHTML('beforeend', `<li class="list-group-item ${cls}">${html}</li>`);

        function next(start) {
            if (start >= total) return finish();
            const batch = ids.slice(start, start + BATCH);

            jQuery.ajax({ url: BULK_URL, method: 'POST', contentType: 'application/json', data: JSON.stringify({ ids: batch }), dataType: 'json' })
                .done(res => {
                    (res.results || []).forEach(r => {
                        const row = byId.get(r.id);
                        if (r.ok) {
                            ok++;
                            if (row) { row.remove(); rows.splice(rows.indexOf(row), 1); }
                        } else {
                            failed.push(r);
                            log('list-group-item-danger', `<i class="fa fa-times fa-fw"></i> ${esc(r.hostname || row?.dataset.hostname || r.id)} - ${esc(r.message)}`);
                        }
                    });
                })
                .fail(xhr => {
                    const msg = xhr.responseJSON?.message || ('HTTP ' + xhr.status);
                    batch.forEach(id => {
                        failed.push({ id });
                        log('list-group-item-danger', `<i class="fa fa-times fa-fw"></i> ${esc(byId.get(id)?.dataset.hostname || id)} - ${esc(msg)}`);
                    });
                })
                .always(() => { done += batch.length; progress(); next(start + BATCH); });
        }

        function finish() {
            running = false;
            $('delhost_keep_open').style.display = 'none';
            $('delhost_bar').className = 'progress-bar ' + (failed.length ? 'progress-bar-warning' : 'progress-bar-success');
            $('delhost_progress_text').innerHTML = failed.length
                ? `<strong>Removed ${ok} of ${total}.</strong> ${failed.length} could not be removed - they are still selected.`
                : `<strong>Removed ${ok} device${ok === 1 ? '' : 's'}.</strong>`;
            $('delhost_done').style.display = '';
            shownRows = rows.filter(row => row.style.display !== 'none');
            applyFilters();
        }

        progress();
        next(0);
    });

    $('delhost_done').addEventListener('click', () => jQuery('#delhost_modal').modal('hide'));

    sync();
})();
</script>
        <?php
    }
}
