<div class="container" style="margin-top:30px;">

    <!-- Tabs -->
    <ul class="nav nav-tabs">
        <li class="active"><a href="#vlan_config" data-toggle="tab">VLAN Configuration</a></li>
        <li><a href="#vlan_batch" data-toggle="tab">VLAN Batch Configuration</a></li>
        <li><a href="#interface_vlan" data-toggle="tab">Interface VLAN Attribute</a></li>
        <li><a href="#voice_vlan" data-toggle="tab">Voice VLAN</a></li>
        <li><a href="#interface_voice_vlan" data-toggle="tab">Interface Voice VLAN</a></li>
    </ul>

    <div class="tab-content">

        <!-- VLAN CONFIG TAB -->
        <div class="tab-pane active" id="vlan_config" style="padding-top:20px;">

            <button class="btn btn-primary">
                <i class="glyphicon glyphicon-plus"></i> Add
            </button>

            <div class="row" style="margin-top:15px;">
                <div class="col-sm-6">
                    <p>No.1 Page / Total 10 Page</p>
                </div>

                <div class="col-sm-6 text-right">
                    <div class="form-inline">
                        <label>Search: </label>
                        <input type="text" class="form-control input-sm" placeholder="Search VLAN">
                    </div>
                </div>
            </div>

            <!-- Table -->
            <table class="table table-bordered table-striped table-hover" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox"></th>
                        <th style="width:100px;">VLAN ID</th>
                        <th>VLAN Name</th>
                        <th style="width:80px;">Operate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>1</td>
                        <td>Default</td>
                        <td><a href="#" class="btn btn-xs btn-info">Edit</a></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>100</td>
                        <td>VLAN0100</td>
                        <td><a href="#" class="btn btn-xs btn-info">Edit</a></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>101</td>
                        <td>VLAN0101</td>
                        <td><a href="#" class="btn btn-xs btn-info">Edit</a></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>102</td>
                        <td>VLAN0102</td>
                        <td><a href="#" class="btn btn-xs btn-info">Edit</a></td>
                    </tr>
                </tbody>
            </table>

            <div class="row">
                <div class="col-sm-6">
                    <label><input type="checkbox"> Select All / Select None</label>
                </div>

                <div class="col-sm-6 text-right">
                    <button class="btn btn-danger">Batch Delete</button>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top:20px;">
                <ul style="margin-bottom:0;">
                    <li>The default VLAN cannot be deleted.</li>
                    <li>Click 'Edit' to browse or reset the VLAN settings.</li>
                    <li>For more than 100 VLANs, use <code>show vlan</code> in CLI.</li>
                </ul>
            </div>
        </div>

        <!-- OTHER TABS (EMPTY FOR NOW) -->
        <div class="tab-pane" id="vlan_batch" style="padding-top:20px;">
            <h4>VLAN Batch Configuration</h4>
            <p>(Your batch config UI will go here)</p>
        </div>

        <div class="tab-pane" id="interface_vlan" style="padding-top:20px;">
            <h4>Interface VLAN Attribute</h4>
            <p>(Your interface attribute configuration UI will go here)</p>
        </div>

        <div class="tab-pane" id="voice_vlan" style="padding-top:20px;">
            <h4>Voice VLAN</h4>
            <p>(Voice VLAN configuration UI)</p>
        </div>

        <div class="tab-pane" id="interface_voice_vlan" style="padding-top:20px;">
            <h4>Interface Voice VLAN</h4>
            <p>(Interface voice VLAN settings UI)</p>
        </div>

    </div>

</div>