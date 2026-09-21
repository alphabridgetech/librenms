<?php
$no_refresh = true;
?>
<div style="margin-bottom: 10px; text-align: right;">
    <a href="<?php echo route('table.port-statistics.export', ['port_id' => $port['port_id']]); ?>" class="btn btn-primary btn-sm">
        <i class="fa fa-download"></i> Export CSV
    </a>
</div>

<table id="port-statistics" class="table table-condensed table-hover table-striped">
    <thead>
        <tr>
            <th data-column-id="field" data-width="260px" data-sortable="false">Field</th>
            <th data-column-id="value" data-sortable="false">Value</th>
        </tr>
    </thead>
</table>

<script>

var grid = $("#port-statistics").bootgrid({
    ajax: true,
    navigation: false,
    rowCount: [-1],
    post: function ()
    {
        return {
            port_id: "<?php echo $port['port_id']; ?>"
        };
    },
    url: "<?php echo url('/ajax/table/port-statistics'); ?>"
});
</script>
