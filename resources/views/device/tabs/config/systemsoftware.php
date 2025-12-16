
<div class="container" style="margin-top:30px;">

    <!-- Backup System -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <strong>Backup System</strong>
        </div>

        <div class="panel-body">

            <p>
                <strong>Current Software Version:</strong>
                Switch.bin, 2.2.0D Build 131046, 2024-08-27 17:23:42 by SYS
            </p>

            <div class="form-group">
                <label class="col-sm-3 control-label">File name on the server</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" value="Switch.bin">
                </div>
            </div>

            <div class="clearfix"></div><br>

            <button class="btn btn-primary">
                Backup System
            </button>

        </div>
    </div>

    <!-- Update System -->
    <div class="panel panel-success">
        <div class="panel-heading">
            <strong>Update System</strong>
        </div>

        <div class="panel-body">

            <p class="text-danger" style="font-weight:bold;">
                Reboot is required after the upgrade of system software!
            </p>

            <div class="checkbox">
                <label>
                    <input type="checkbox"> Reboot the device automatically after upgrade
                </label>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">File name on the server</label>
                <div class="col-sm-6">
                    <select class="form-control">
                        <option>Switch.bin</option>
                        <option>Switch-Update.bin</option>
                    </select>
                </div>
            </div>

            <div class="clearfix"></div><br>

            <div class="form-group">
                <label class="col-sm-3 control-label">Upload BIN File</label>
                <div class="col-sm-6">
                    <input type="file" class="form-control" accept=".bin">
                </div>
            </div>

            <div class="clearfix"></div><br>

            <button class="btn btn-success">
                Upgrade
            </button>

        </div>
    </div>

</div>
