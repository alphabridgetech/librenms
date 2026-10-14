{{--
    Offline replacement for the DataTables CDN (cdn.datatables.net) on the
    device configuration pages.

    Built on jQuery Bootgrid, which the LibreNMS layout (librenmsv1) already
    loads from html/js, so these tables work without internet access.

    It implements the subset of the DataTables API those pages use:
      $('#table').DataTable({ ajax: { url, type, headers, data, beforeSend,
          complete, dataSrc }, columns: [{ data, render, orderable,
          searchable, type, className, visible, defaultContent }],
          order, lengthMenu, language: { emptyTable } })
      table.ajax.reload(callback, resetPaging)
      table.row(tr | index).data()
      table.rows().invalidate().draw(resetPaging)
      table.columns.adjust()
      $.fn.dataTable.ext.type.order['<type>-asc'] = function (a, b) { ... }
--}}
<script>
    (function ($) {
        if ($.fn.DataTable || !$.fn.bootgrid) {
            return;
        }

        var orderTypes = {};
        $.fn.dataTable = { ext: { type: { order: orderTypes } } };

        function stripHtml(value) {
            var text = value === null || value === undefined ? '' : String(value);
            return text.indexOf('<') === -1 ? text : text.replace(/<[^>]*>/g, ' ');
        }

        function getValue(row, path) {
            if (path === null || path === undefined) {
                return row;
            }
            return String(path).split('.').reduce(function (obj, key) {
                return obj === null || obj === undefined ? undefined : obj[key];
            }, row);
        }

        function naturalCompare(a, b) {
            var x = a === null || a === undefined ? '' : a;
            var y = b === null || b === undefined ? '' : b;
            if (typeof x === 'number' && typeof y === 'number') {
                return x - y;
            }
            return String(x).localeCompare(String(y), undefined, { numeric: true, sensitivity: 'base' });
        }

        // Bootgrid sorts by comparing row[column.id] with < and >, and
        // searches converter.to(row[column.id]). Each cell is stored as a
        // Cell whose valueOf() is its precomputed sort rank and whose text
        // is what search matches against.
        function Cell(rank, text) {
            this.rank = rank;
            this.text = text;
        }
        Cell.prototype.valueOf = function () {
            return this.rank;
        };

        function DataTableApi(table, options) {
            var api = this;

            this.table = table;
            this.options = options;
            this.columnDefs = options.columns || [];
            this.rawRows = [];
            this.emptyLabel = (options.language && options.language.emptyTable) || 'No data available in table';

            var formatters = {};
            var headerRow = table.find('thead > tr').first();
            var order = options.order === undefined ? [[0, 'asc']] : options.order;

            headerRow.children('th').each(function (c) {
                var th = $(this);
                var col = api.columnDefs[c] || {};
                var id = 'c' + c;

                th.attr({ 'data-column-id': id, 'data-formatter': id, 'data-converter': 'tqcell' });
                if (col.orderable === false) th.attr('data-sortable', 'false');
                if (col.searchable === false) th.attr('data-searchable', 'false');
                if (col.visible === false) th.attr('data-visible', 'false');
                if (col.className) th.attr('data-css-class', col.className);
                if (th.attr('width')) th.attr('data-width', th.attr('width'));

                formatters[id] = function (column, row) {
                    return api.renderCell(c, row.__tq);
                };
            });

            // Only the first order entry is used - Bootgrid sorts one column at a time.
            if (order.length && order[0].length === 2) {
                headerRow.children('th').eq(order[0][0]).attr('data-order', String(order[0][1]).toLowerCase());
            }

            // Hidden identifier column so each rendered <tr data-row-id> maps
            // back to its index in rawRows (used by row(tr).data()).
            headerRow.append('<th data-column-id="__tq" data-identifier="true" data-type="numeric" data-visible="false" data-visible-in-selection="false" data-sortable="false" data-searchable="false"></th>');

            if (!table.children('tbody').length) {
                table.append('<tbody></tbody>');
            }

            var lengthMenu = options.lengthMenu || [10, 25, 50, -1];
            if ($.isArray(lengthMenu[0])) {
                lengthMenu = lengthMenu[0];
            }

            table.bootgrid({
                ajax: false,
                caseSensitive: false,
                columnSelection: false,
                rowCount: lengthMenu,
                formatters: formatters,
                converters: {
                    tqcell: {
                        from: function (value) { return value; },
                        to: function (value) {
                            if (value instanceof Cell) return value.text;
                            return value === null || value === undefined ? '' : String(value);
                        }
                    }
                },
                labels: { noResults: 'Loading...' }
            });

            this.grid = table.data('.rs.jquery.bootgrid');

            this.ajax = {
                reload: function (callback, resetPaging) {
                    api.load(callback, resetPaging !== false);
                    return api;
                }
            };
            this.columns = {
                adjust: function () {
                    return api;
                }
            };

            this.load(null, true);
        }

        DataTableApi.prototype.load = function (callback, resetPaging) {
            var api = this;
            var ajax = this.options.ajax;

            if (!ajax) {
                this.setRows(this.options.data || [], resetPaging);
                return;
            }
            if (typeof ajax === 'string') {
                ajax = { url: ajax };
            }

            $.ajax({
                url: ajax.url,
                type: ajax.type || 'GET',
                headers: ajax.headers,
                data: typeof ajax.data === 'function' ? (ajax.data({}) || {}) : ajax.data,
                dataType: 'json',
                beforeSend: ajax.beforeSend,
                complete: ajax.complete,
                success: function (json) {
                    var rows;
                    if (typeof ajax.dataSrc === 'function') {
                        rows = ajax.dataSrc(json);
                    } else if (typeof ajax.dataSrc === 'string') {
                        rows = ajax.dataSrc === '' ? json : getValue(json, ajax.dataSrc);
                    } else {
                        rows = json.data;
                    }
                    api.setRows(rows, resetPaging);
                    if (typeof callback === 'function') {
                        callback(json);
                    }
                },
                error: function (xhr) {
                    console.error('Table data request failed: ' + ajax.url + ' (HTTP ' + xhr.status + ')');
                    api.setRows([], resetPaging);
                }
            });
        };

        DataTableApi.prototype.setRows = function (rows, resetPaging) {
            this.rawRows = $.isArray(rows) ? rows : [];
            this.grid.options.labels.noResults = this.emptyLabel;
            this.draw(resetPaging);
        };

        DataTableApi.prototype.cellValue = function (c, raw, rowIndex, type) {
            var col = this.columnDefs[c] || {};
            var data = getValue(raw, col.data);

            if (typeof col.render === 'function') {
                return col.render(data, type, raw, { row: rowIndex, col: c });
            }
            if (data === null || data === undefined) {
                return col.defaultContent || '';
            }
            return data;
        };

        DataTableApi.prototype.renderCell = function (c, rowIndex) {
            var value = this.cellValue(c, this.rawRows[rowIndex], rowIndex, 'display');
            return value === null || value === undefined ? '' : String(value);
        };

        // Rebuild the Bootgrid rows from rawRows, re-running every column's
        // render (so state the renders read, e.g. LLDP's global status, is
        // picked up), and precompute each column's sort rank.
        DataTableApi.prototype.draw = function (resetPaging) {
            var api = this;
            var grid = this.grid;
            var page = grid.current;
            var gridRows = this.rawRows.map(function (raw, i) {
                return { __tq: i };
            });

            grid.columns.forEach(function (column) {
                if (column.id === '__tq') return;

                var c = parseInt(column.id.substring(1), 10);
                var col = api.columnDefs[c] || {};
                var compare = (col.type && orderTypes[col.type + '-asc']) || naturalCompare;
                var sortValues = api.rawRows.map(function (raw, i) {
                    var value = api.cellValue(c, raw, i, 'sort');
                    return typeof value === 'string' ? stripHtml(value).trim() : value;
                });
                var sorted = sortValues.map(function (v, i) { return i; }).sort(function (a, b) {
                    return compare(sortValues[a], sortValues[b]) || a - b;
                });

                var rank = 0;
                sorted.forEach(function (rowIndex, n) {
                    if (n > 0 && compare(sortValues[sorted[n - 1]], sortValues[rowIndex]) !== 0) {
                        rank = n;
                    }
                    var text = column.searchable ? stripHtml(api.cellValue(c, api.rawRows[rowIndex], rowIndex, 'filter')) : '';
                    gridRows[rowIndex][column.id] = new Cell(rank, text);
                });
            });

            var totalPages = grid.rowCount === -1 ? 1 : Math.max(1, Math.ceil(gridRows.length / grid.rowCount));
            grid.current = resetPaging === false ? Math.min(page, totalPages) : 1;
            grid.rows = [];
            grid.append(gridRows);

            return this;
        };

        DataTableApi.prototype.row = function (selector) {
            var api = this;
            var index;

            if (typeof selector === 'number' || (typeof selector === 'string' && /^\d+$/.test(selector))) {
                index = parseInt(selector, 10);
            } else {
                index = parseInt($(selector).closest('tr').attr('data-row-id'), 10);
            }

            return {
                data: function () {
                    return api.rawRows[index];
                },
                index: function () {
                    return index;
                }
            };
        };

        DataTableApi.prototype.rows = function () {
            var api = this;
            var rowsApi = {
                invalidate: function () {
                    return rowsApi;
                },
                draw: function (resetPaging) {
                    api.draw(resetPaging);
                    return api;
                },
                data: function () {
                    return api.rawRows.slice();
                }
            };
            return rowsApi;
        };

        $.fn.DataTable = function (options) {
            var table = this.first();
            var existing = table.data('tq.datatable');

            if (existing) {
                return existing;
            }

            var api = new DataTableApi(table, options || {});
            table.data('tq.datatable', api);

            return api;
        };
    })(jQuery);
</script>
