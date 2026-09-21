<?php

require_once '/var/data/Telequill_Install/examples/compose/telequill/vendor/autoload.php';
$app = require_once '/var/data/Telequill_Install/examples/compose/telequill/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Facades\LibrenmsConfig;

LibrenmsConfig::persist('snmptrap_forward_host', '127.0.0.1');
LibrenmsConfig::persist('snmptrap_forward_port', 12345);
echo "Configuration updated: 127.0.0.1:12345\n";
