<?php

use App\Actions\Device\ValidateDeviceAndCreate;
use App\Facades\LibrenmsConfig;
use LibreNMS\Enum\PortAssociationMode;
use LibreNMS\Exceptions\HostUnreachableException;
use LibreNMS\Util\IP;
use Symfony\Component\Yaml\Yaml;

$no_refresh = true;

function updateHostsYml($device)
{
    $basePath = "/opt/librenms"; 

    $debugFile = $basePath . "/librenms-ansible-inventory-plugin/debug.log";
    file_put_contents($debugFile, "Function called\n", FILE_APPEND);

    $dir = $basePath . "/librenms-ansible-inventory-plugin/hosts/";

    // Ensure directory exists
    if (!is_dir($dir)) {
        file_put_contents($debugFile, "Directory missing, creating...\n", FILE_APPEND);
        mkdir($dir, 0777, true);
    }

    // Validate hostname
    if (empty($device->hostname)) {
        file_put_contents($debugFile, "ERROR: Hostname empty!\n", FILE_APPEND);
        return;
    }

    $file = $dir . $device->hostname . ".yml";
    file_put_contents($debugFile, "Writing file: $file\n", FILE_APPEND);

    // Correct YAML structure for Ansible inventory
    $data = [
        'all' => [
            'children' => [
                'alphabridge_devices' => [
                    'hosts' => [
                        'bridge1' => [
                            'ansible_host'       => $device->hostname,
                            'ansible_user'       => $device->ssh_user ?? '',
                            'ansible_password'   => $device->ssh_pass ?? '',
                            'ansible_connection' => 'local',
                            'ansible_python_interpreter'=> '/usr/bin/python3',
                            'os'                 => $device->os,
                            'snmpver'            => $device->snmpver,
                            'community'          => $device->community ?? '',
                        ]
                    ]
                ]
            ]
        ]
    ];

    try {
        $yaml = Yaml::dump($data, 10, 2);
        file_put_contents($file, $yaml);

        file_put_contents($debugFile, "YAML written successfully\n", FILE_APPEND);

    } catch (Exception $e) {
        file_put_contents($debugFile, "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

if (! Auth::user()->hasGlobalAdmin()) {
    include 'includes/html/error-no-perm.inc.php';
    exit;
}

// Handle CSV upload
$upload_result = '';
$failed_devices = [];
$success_count = 0;
$failed_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        
        if ($handle !== false) {
            // Get headers
            $headers = fgetcsv($handle);
            
            // Expected headers (case-insensitive)
            $expected_headers = [
                'hostname', 'snmpver', 'community', 'port', 'transport',
                'authlevel', 'authname', 'authpass', 'authalgo', 'cryptopass', 'cryptoalgo',
                'ssh_user', 'ssh_pass', 'os', 'hardware', 'sysname', 'poller_group',
                'port_assoc_mode', 'force_add', 'snmp_disable'
            ];
            
            // Validate headers
            $header_map = [];
            foreach ($headers as $index => $header) {
                // Remove any BOM or special characters
                $header = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header);
                $header_clean = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(trim($header)));
                
                foreach ($expected_headers as $expected) {
                    // Check if the header contains or matches the expected header
                    if (strpos($header_clean, str_replace('_', '', $expected)) !== false || 
                        $header_clean === str_replace('_', '', $expected)) {
                        $header_map[$expected] = $index;
                        break;
                    }
                }
            }
            
            if (empty($header_map)) {
                $upload_result = 'error|Invalid CSV format. Please use the template.';
            } else {
                // Process each row
                $row_number = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $row_number++;
                    
                    try {
                        // Extract hostname (required)
                        $hostname_index = $header_map['hostname'] ?? null;
                        if ($hostname_index === null || empty($row[$hostname_index])) {
                            throw new Exception("Hostname is required");
                        }
                        
                        $hostname = strip_tags(trim($row[$hostname_index]));
                        
                        // Validate hostname
                        if (!\LibreNMS\Util\Validate::hostname($hostname) && !IP::isValid($hostname)) {
                            throw new Exception("Invalid hostname or IP: $hostname");
                        }
                        
                        // Create new device
                        $new_device = new \App\Models\Device(['hostname' => $hostname]);
                        
                        // Set SNMP status
                        $snmp_disable_index = $header_map['snmp_disable'] ?? null;
                        $snmp_disable = ($snmp_disable_index !== null && !empty($row[$snmp_disable_index]) && strtolower($row[$snmp_disable_index]) === 'yes');
                        $new_device->snmp_disable = $snmp_disable ? 1 : 0;
                        
                        // Set basic fields
                        if (isset($header_map['port']) && !empty($row[$header_map['port']])) {
                            $new_device->port = strip_tags($row[$header_map['port']]);
                        }
                        
                        if (isset($header_map['transport']) && !empty($row[$header_map['transport']])) {
                            $new_device->transport = strip_tags($row[$header_map['transport']]);
                        }
                        
                        // SNMP version specific settings
                        if (!$snmp_disable) {
                            $snmpver_index = $header_map['snmpver'] ?? null;
                            if ($snmpver_index !== null && !empty($row[$snmpver_index])) {
                                $new_device->snmpver = strip_tags($row[$snmpver_index]);
                                
                                if (in_array($new_device->snmpver, ['v1', 'v2c'])) {
                                    if (isset($header_map['community']) && !empty($row[$header_map['community']])) {
                                        $new_device->community = $row[$header_map['community']];
                                    }
                                } elseif ($new_device->snmpver === 'v3') {
                                    if (isset($header_map['authlevel']) && !empty($row[$header_map['authlevel']])) {
                                        $new_device->authlevel = strip_tags($row[$header_map['authlevel']]);
                                    }
                                    if (isset($header_map['authname']) && !empty($row[$header_map['authname']])) {
                                        $new_device->authname = $row[$header_map['authname']];
                                    }
                                    if (isset($header_map['authpass']) && !empty($row[$header_map['authpass']])) {
                                        $new_device->authpass = $row[$header_map['authpass']];
                                    }
                                    if (isset($header_map['authalgo']) && !empty($row[$header_map['authalgo']])) {
                                        $new_device->authalgo = strip_tags($row[$header_map['authalgo']]);
                                    }
                                    if (isset($header_map['cryptopass']) && !empty($row[$header_map['cryptopass']])) {
                                        $new_device->cryptopass = $row[$header_map['cryptopass']];
                                    }
                                    if (isset($header_map['cryptoalgo']) && !empty($row[$header_map['cryptoalgo']])) {
                                        $new_device->cryptoalgo = strip_tags($row[$header_map['cryptoalgo']]);
                                    }
                                }
                            }
                        }
                        
                        // SSH settings
                        $new_device->ssh_user = (isset($header_map['ssh_user']) && !empty($row[$header_map['ssh_user']]))
                            ? strip_tags($row[$header_map['ssh_user']])
                            : 'admin';
                        
                        $new_device->ssh_pass = (isset($header_map['ssh_pass']) && !empty($row[$header_map['ssh_pass']]))
                            ? strip_tags($row[$header_map['ssh_pass']])
                            : 'admin';
                        
                        // Optional fields
                        if (isset($header_map['os']) && !empty($row[$header_map['os']])) {
                            $new_device->os = strip_tags($row[$header_map['os']]);
                        }
                        
                        if (isset($header_map['hardware']) && !empty($row[$header_map['hardware']])) {
                            $new_device->hardware = strip_tags($row[$header_map['hardware']]);
                        }
                        
                        if (isset($header_map['sysname']) && !empty($row[$header_map['sysname']])) {
                            $new_device->sysName = strip_tags($row[$header_map['sysname']]);
                        }
                        
                        if (isset($header_map['poller_group']) && !empty($row[$header_map['poller_group']])) {
                            $new_device->poller_group = strip_tags($row[$header_map['poller_group']]);
                        }
                        
                        if (isset($header_map['port_assoc_mode']) && !empty($row[$header_map['port_assoc_mode']])) {
                            $new_device->port_association_mode = PortAssociationMode::getId($row[$header_map['port_assoc_mode']]);
                        }
                        
                        // Force add
                        $force_add = false;
                        if (isset($header_map['force_add']) && !empty($row[$header_map['force_add']])) {
                            $force_add_val = strtolower($row[$header_map['force_add']]);
                            $force_add = ($force_add_val === 'yes' || $force_add_val === 'true' || $force_add_val === '1');
                        }
                        
                        // Add device
                        $result = (new ValidateDeviceAndCreate($new_device, $force_add))->execute();
                        
                        if ($result) {
                            updateHostsYml($new_device);
                            $success_count++;
                        } else {
                            throw new Exception("Failed to add device");
                        }
                        
                    } catch (HostUnreachableException $e) {
                        $failed_count++;
                        $failed_devices[] = [
                            'row' => $row_number,
                            'hostname' => $hostname ?? 'Unknown',
                            'error' => $e->getMessage() . ' - ' . implode(', ', $e->getReasons())
                        ];
                    } catch (Exception $e) {
                        $failed_count++;
                        $failed_devices[] = [
                            'row' => $row_number,
                            'hostname' => $hostname ?? 'Unknown',
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                fclose($handle);
                
                if ($success_count > 0) {
                    $upload_result = "success|Successfully added $success_count device(s)";
                    if ($failed_count > 0) {
                        $upload_result .= " | Failed to add $failed_count device(s)";
                    }
                } else {
                    $upload_result = "error|No devices were added successfully";
                }
            }
        } else {
            $upload_result = 'error|Failed to read CSV file';
        }
    } else {
        $upload_result = 'error|File upload error: ' . $file['error'];
    }
}

// Display page
echo '<div class="row">
        <div class="col-sm-3"></div>
        <div class="col-sm-6">';

// Display upload results
if (!empty($upload_result)) {
    $parts = explode('|', $upload_result);
    $type = $parts[0];
    $message = $parts[1] ?? '';
    $additional = $parts[2] ?? '';
    
    if ($type === 'success') {
        print_message($message);
        if (!empty($additional)) {
            print_warning($additional);
        }
    } else {
        print_error($message);
    }
    
    // Display failed devices if any
    if (!empty($failed_devices)) {
        echo '<div class="panel panel-danger">
                <div class="panel-heading">
                    <h3 class="panel-title">Failed Devices</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-hover table-condensed">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Hostname</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>';
        foreach ($failed_devices as $failed) {
            echo '<tr>
                    <td>' . $failed['row'] . '</td>
                    <td>' . htmlentities($failed['hostname']) . '</td>
                    <td>' . htmlentities($failed['error']) . '</td>
                  </tr>';
        }
        echo '          </tbody>
                    </table>
                </div>
              </div>';
    }
}

?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Bulk Add Devices via CSV</h3>
    </div>
    <div class="panel-body">
        <div class="alert alert-info">
            <strong>Instructions:</strong>
            <ul>
                <li>Download the template CSV file and fill in your device information</li>
                <li>Required field: <strong>hostname</strong></li>
                <li>For SNMPv1/v2c, provide community; for SNMPv3, provide auth/crypto details</li>
                <li>Set snmp_disable to "yes" for non-SNMP devices (ICMP only)</li>
                <li>Set force_add to "yes" to skip reachability checks</li>
                <li>Default SSH username/password: admin/admin (can be overridden)</li>
            </ul>
        </div>
        
        <div class="row">
            <div class="col-sm-6">
                <a href="ajax_csv_template" class="btn btn-primary">
                    <i class="fa fa-download"></i> Download CSV Template
                </a>
            </div>
            <div class="col-sm-6 text-right">
                <a href="addhost" class="btn btn-default">
                    <i class="fa fa-plus"></i> Add Single Device
                </a>
            </div>
        </div>
        
        <hr>
        
        <form name="bulkupload" method="post" action="" enctype="multipart/form-data" class="form-horizontal" role="form">
            <?php echo csrf_field() ?>
            
            <div class="form-group">
                <label for="csv_file" class="col-sm-3 control-label">CSV File</label>
                <div class="col-sm-9">
                    <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.txt" required>
                    <span class="help-block">Upload your CSV file with device information</span>
                </div>
            </div>
            
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="preview_only" id="preview_only"> Preview only (validate without adding)
                        </label>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-success" name="submit">
                        <i class="fa fa-upload"></i> Upload and Add Devices
                    </button>
                    <button type="reset" class="btn btn-default">Reset</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="panel panel-info">
    <div class="panel-heading">
        <h3 class="panel-title">CSV Format Guide</h3>
    </div>
    <div class="panel-body">
        <p><strong>Required columns:</strong> hostname</p>
        <p><strong>Optional columns:</strong> snmpver, community, port, transport, authlevel, authname, authpass, authalgo, cryptopass, cryptoalgo, ssh_user, ssh_pass, os, hardware, sysname, poller_group, port_assoc_mode, force_add, snmp_disable</p>
        
        <h4>Example:</h4>
        <pre>hostname,snmpver,community,ssh_user,ssh_pass,os,force_add
192.168.1.1,v2c,public,admin,admin,ios,
switch.domain.com,v3,,admin,admin,ios,no
firewall.domain.com,,,,,,,yes</pre>
        
        <h4>SNMPv3 Example:</h4>
        <pre>hostname,snmpver,authlevel,authname,authpass,authalgo,cryptopass,cryptoalgo
router.domain.com,v3,authPriv,snmpuser,password123,SHA,encryptpass,AES</pre>
    </div>
</div>

<?php
echo '    </div>
        <div class="col-sm-3">
        </div>
    </div>';

$pagetitle[] = 'Bulk Add Devices';
?>

<script>
$(document).ready(function() {
    // Add any JavaScript functionality here if needed
});
</script>