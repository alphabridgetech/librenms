{{--
    Batch delete for the L3 configuration tables (offline DataTable adapter).

    var batch = L3BatchDelete({
        table:       () => myTable,            // the DataTable (created later)
        tableId:     '#myTable',
        after:       '#btnAdd',                // toolbar is inserted after this element
        url:         '/api/v0/l3/<module>/batchdelete/<ip>',
        headers:     { ... },
        noun:        'static routes',
        canDelete:   row => true,              // rows without a checkbox are never sent
        toItem:      row => ({ ... }),         // fields of one single delete
        describe:    row => 'text',            // one line per row in the confirm prompt
        warning:     'optional extra confirm text',
        onData:      data => render(data),     // response carries the refreshed table
        reload:      () => load('refresh'),    // used when it doesn't
    });

    Add a first column { data: null, orderable: false, searchable: false,
    render: (r, t, full, meta) => batch.checkbox(full, meta) } with an empty
    <th>, and call batch.reset() before giving the table new data.
--}}
<script>
    function L3BatchDelete(o) {
        const selected = new Set();
        const bar = $(`<span class="l3-batch-bar" style="margin-left:10px; white-space:nowrap;">
                <label style="font-weight:normal; margin:0 8px 0 0; cursor:pointer;">
                    <input type="checkbox" class="l3-batch-all"> Select all
                </label>
                <button type="button" class="btn btn-danger l3-batch-btn" disabled>
                    <span class="spinner-border spinner-border-sm" style="display:none;"></span>
                    <i class="fa fa-trash"></i> Delete selected (<span class="l3-batch-count">0</span>)
                </button>
            </span>`);

        $(o.after).after(bar);

        const deletable = () => o.table().rows().data()
            .map((row, i) => (o.canDelete(row) ? i : -1))
            .filter(i => i >= 0);

        function sync() {
            const all = deletable();
            bar.find('.l3-batch-count').text(selected.size);
            bar.find('.l3-batch-btn').prop('disabled', selected.size === 0);
            bar.find('.l3-batch-all').prop('checked', all.length > 0 && all.every(i => selected.has(i)));
        }

        // Bound on the <table>: Bootgrid only adds the <tbody> when the
        // DataTable is created (after this runs). Bootgrid stops *click*
        // propagation at the rows, but the checkbox's change event still
        // bubbles up to the table.
        $(o.tableId).on('change', '.l3-batch-row', function () {
            const i = Number($(this).data('row'));
            if (this.checked) selected.add(i); else selected.delete(i);
            sync();
        });

        bar.find('.l3-batch-all').on('change', function () {
            selected.clear();
            if (this.checked) deletable().forEach(i => selected.add(i));
            o.table().rows().invalidate().draw(false);
            sync();
        });

        bar.find('.l3-batch-btn').on('click', function () {
            const rows = [...selected].sort((a, b) => a - b)
                .map(i => o.table().row(i).data())
                .filter(row => row && o.canDelete(row));

            if (!rows.length) return;

            const list = rows.slice(0, 15).map(row => ' - ' + o.describe(row));
            if (rows.length > 15) list.push(` ... and ${rows.length - 15} more`);

            if (!confirm(`Delete ${rows.length} ${o.noun}?\n\n${list.join('\n')}`
                + (o.warning ? `\n\n${o.warning}` : '')
                + '\n\nEach delete runs on the switch one after another - this can take a while.')) {
                return;
            }

            const btn = $(this);
            btn.prop('disabled', true).find('.spinner-border').show();

            const done = function (res) {
                btn.find('.spinner-border').hide();
                selected.clear();

                const failed = (res.results || []).filter(r => r.status !== 'success')
                    .map(r => ` x ${r.label}: ${r.message}`);
                alert((res.message || 'Batch delete finished') + (failed.length ? `\n\nNot deleted:\n${failed.join('\n')}` : ''));

                if (res.data) o.onData(res.data); else o.reload();
                sync();
            };

            $.ajax({
                url: o.url,
                method: 'POST',
                headers: o.headers,
                contentType: 'application/json',
                data: JSON.stringify({ items: rows.map(o.toItem) }),
                success: done,
                error: xhr => done(xhr.responseJSON || { message: 'Batch delete failed (HTTP ' + xhr.status + ')' })
            });
        });

        return {
            checkbox: (row, meta) => o.canDelete(row)
                ? `<input type="checkbox" class="l3-batch-row" data-row="${meta.row}" ${selected.has(meta.row) ? 'checked' : ''}>`
                : '',
            reset: function () {
                selected.clear();
                sync();
            }
        };
    }
</script>
