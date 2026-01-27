


<div class="container" style="margin-top:30px;">

    <!-- Export the current startup-config -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <strong>Export the current startup-config</strong>
        </div>

        <div class="panel-body">

           

            <div class="form-group">
                <label class="col-sm-3 control-label">Export the current startup-config</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" value="Switch.bin">
                </div>
            </div>

            <div class="clearfix"></div><br>

            <button class="btn btn-primary">
                Export the current startup-config
            </button>

        </div>
    </div>

    <!-- Import config file -->
    <div class="panel panel-success">
        <div class="panel-heading">
            <strong>Import config file</strong>
        </div>

        <div class="panel-body">

            <p class="text-danger" style="font-weight:bold;">
                Reboot is required after importing config file!
            </p>

          

            <div class="form-group">
                <label class="col-sm-3 control-label">File name on the server</label>
                <div class="col-sm-6">
                    <select class="form-control">
                        @foreach ($data['tftp_files'] as $file)
                    <option value="{{ $file }}">{{ $file }}</option>
                @endforeach
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






<div class="container" style="margin-top:30px;">

    <!-- Export the current startup-config -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <strong>Export the current startup-config</strong>
        </div>

        <div class="panel-body">

           

            <div class="form-group">
                <label class="col-sm-3 control-label">Export the current startup-config</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" value="Switch.bin">
                </div>
            </div>

            <div class="clearfix"></div><br>

            <button class="btn btn-primary">
                Export the current startup-config
            </button>

        </div>
    </div>

    <!-- Import config file -->
    <div class="panel panel-success">
        <div class="panel-heading">
            <strong>Import config file</strong>
        </div>

        <div class="panel-body">

            <p class="text-danger" style="font-weight:bold;">
                Reboot is required after importing config file!
            </p>

          

            <div class="form-group">
                <label class="col-sm-3 control-label">File name on the server</label>
                <div class="col-sm-6">
                    <select class="form-control">
                        @foreach ($data['tftp_files'] as $file)
                    <option value="{{ $file }}">{{ $file }}</option>
                @endforeach
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



