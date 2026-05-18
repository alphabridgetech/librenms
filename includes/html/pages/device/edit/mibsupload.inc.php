<?php
// html/pages/mibsupload.inc.php
use LibreNMS\Config;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::user()->hasGlobalAdmin()) {
    $errors = [];
    $success = null;

    if (!isset($_FILES['mibfile']) || $_FILES['mibfile']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'MIB file upload failed';
    } else {
        $deviceId = isset($_POST['device_id']) && is_numeric($_POST['device_id'])
            ? (int)$_POST['device_id']
            : null;

        $filename = basename($_FILES['mibfile']['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, ['mib','txt'])) {
            $errors[] = 'Only .mib or .txt files are allowed';
        } else {
            // validate syntax quickly (snmptranslate -Pu etc. must exist inside container)
            $tmp = $_FILES['mibfile']['tmp_name'];
           
            exec("snmptranslate -Pu -M+{$tmp} 2>&1", $out, $ret);
            if ($ret !== 0) {
                $errors[] = 'MIB syntax validation failed: '.htmlspecialchars(implode("\n", $out));
            } else {
                // target dir (device specific or global custom)
                $targetDir = Config::get('install_dir').'/mibs/custom';
                if ($deviceId) {
                    $targetDir .= "/device_$deviceId";
                }
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }
                if (!move_uploaded_file($tmp, "$targetDir/$filename")) {
                    $errors[] = 'Could not move file to '.$targetDir;
                } else {
                    $success = "MIB uploaded successfully to $targetDir/$filename";
                }
            }
        }
    }

    if ($success) {
        toast()->success($success);
    }
    if ($errors) {
        foreach ($errors as $err) {
            toast()->error($err, options:['timeOut'=>30000]);
        }
    }
}
?>

<div class="container">
  <h2>Upload Custom MIB</h2>
  <form method="post" enctype="multipart/form-data" class="form-horizontal">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="col-sm-2 control-label">MIB File (.txt/.mib)</label>
      <div class="col-sm-6">
        <input type="file" name="mibfile" class="form-control" required>
      </div>
    </div>
    <div class="form-group">
      <label class="col-sm-2 control-label">Device ID (optional)</label>
      <div class="col-sm-2">
        <input type="number" name="device_id" class="form-control" placeholder="e.g. 42">
      </div>
    </div>
    <div class="row">
      <div class="col-md-1 col-md-offset-2">
        <button type="submit" class="btn btn-success">
          <i class="fa fa-upload"></i> Upload & Validate
        </button>
      </div>
    </div>
  </form>
</div>
