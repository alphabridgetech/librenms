<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Ajax;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertRuleController;
use App\Http\Controllers\AlertRuleTemplateController;
use App\Http\Controllers\AlertTransportController;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardWidgetController;
use App\Http\Controllers\Device;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceGroupController;
use App\Http\Controllers\GraphController;
use App\Http\Controllers\Install;
use App\Http\Controllers\LegacyController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Maps;
use App\Http\Controllers\Maps\CustomMapBackgroundController;
use App\Http\Controllers\Maps\CustomMapController;
use App\Http\Controllers\Maps\CustomMapDataController;
use App\Http\Controllers\Maps\CustomMapNodeImageController;
use App\Http\Controllers\Maps\DeviceDependencyController;
use App\Http\Controllers\NacController;
use App\Http\Controllers\OuiLookupController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PluginLegacyController;
use App\Http\Controllers\PluginPageController;
use App\Http\Controllers\PluginSettingsController;
use App\Http\Controllers\PollerController;
use App\Http\Controllers\PollerGroupController;
use App\Http\Controllers\PollerSettingsController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\PortGroupController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\Select;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\ServiceTemplateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Table;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\ValidateController;
use App\Http\Controllers\Widgets;
use App\Http\Controllers\WidgetSettingsController;
use App\Http\Controllers\WirelessSensorController;
use App\Http\Middleware\AuthenticateGraph;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Device\Tabs\alphabridgeController;
use App\Http\Controllers\VLANController;
use App\Http\Controllers\MibsUploadController;
use App\Http\Controllers\ChatBotController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\TftpDownloadController;
use App\Http\Controllers\SystemBulkUploadController;
use App\Http\Controllers\LicenceController;
use App\Http\Controllers\TemplatePushController;
use App\Http\Controllers\ZtpController;
use App\Services\LicenseVerifier;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//tftp download
Route::get(
    '/tftp/download/{file}',
    [TftpDownloadController::class, 'download']
)->name('tftp.download');

Route::post('/license/upload', [LicenceController::class, 'licenceUpload'])->name('license.upload');
Route::post('/license/upload-key', [LicenceController::class, 'uploadPublicKey'])->name('license.upload-key');

// ZTP: public endpoint — called by the switch during boot (no auth required)
Route::get('/ztp/config/{mac}', [ZtpController::class, 'serveConfig'])->name('ztp.config');

// Auth
AuthFacade::routes(['register' => false, 'reset' => false, 'verify' => false]);

// Socialite
Route::prefix('auth')->name('socialite.')->group(function () {
    Route::post('{provider}/redirect', [SocialiteController::class, 'redirect'])->name('redirect');
    Route::match(['get', 'post'], '{provider}/callback', [SocialiteController::class, 'callback'])->name('callback');
    Route::get('{provider}/metadata', [SocialiteController::class, 'metadata'])->name('metadata');
});

Route::get('graph/{path?}', GraphController::class)
    ->where('path', '.*')
    ->middleware(['web', AuthenticateGraph::class])->name('graph');

// WebUI
Route::middleware(['auth', 'license'])->group(function () {

    Route::get('/license', function () {
        $verifier = new LicenseVerifier();
        $result = $verifier->verify();
        $license = $result['license'];

        $licenseFilePath = base_path('license.key');
        $licenseFileInfo = null;
        $licenseFileContent = null;
        if (file_exists($licenseFilePath)) {
            $licenseFileInfo = [
                'filename' => 'license.key',
                'path' => $licenseFilePath,
                'size' => filesize($licenseFilePath),
                'last_modified' => date('Y-m-d H:i:s', filemtime($licenseFilePath)),
            ];
            $licenseFileContent = json_decode(file_get_contents($licenseFilePath), true);
        }

        return view('licence.licence', [
            'product' => $license['product'] ?? 'Unknown',
            'expiry' => $license['expiry'] ?? 'N/A',
            'maxUsers' => $license['max_users'] ?? 'Unlimited',
            'domain' => $license['domain'] ?? 'N/A',
            'licenseKey' => $license['license_key'] ?? 'N/A',
            'licenseFileInfo' => $licenseFileInfo,
            'licenseFileContent' => $licenseFileContent,
        ]);
    })->name('license.smenu');

    Route::get('/submenu1-1', [VLANController::class, 'showSubmenu']);

    // Optional: If you want AJAX-based loading:
    Route::get('/tabs/vlan-config', [VLANController::class, 'vlanConfigTab'])->name('vlan-config-page');
    //vlan edit
    Route::get('/vlan/edit/{id}', [VLANController::class, 'editVlan']);
    Route::post('/vlan/update', [VLANController::class, 'updateVlan']);

    //vlan add 
     Route::get('/vlan/add', [VLANController::class, 'addVlan']);
     Route::post('/vlan/store', [VLANController::class, 'storeVlan'])->name('vlan.store');

     //for delete
     Route::post('/vlan/delete-batch', [VLANController::class, 'deleteBatch'])->name('vlan.deleteBatch');



    //     Route::get('/tabs/vlan-batch', function () {
    // return "vgcbvgc";
    //     });


    Route::get('tabs/vlan-batch', [VLANController::class, 'vlanBatchTab'])->name('vlan-batch-page');
    Route::post('/vlan/batch/store', [VLANController::class, 'storeBatchVlan'])->name('vlan.batch.store');







    Route::get('tabs/interface-vlan-attr', [VLANController::class, 'interfaceVlanAttrTab']);

    //voice vlan part
    Route::get('tabs/voice-vlan', [VLANController::class, 'voiceVlanTab']);
    Route::get('/voice/vlan/add', [VLANController::class, 'addVoiceVlan']);
    Route::post('/voice/vlan/store', [VLANController::class, 'storeVoiceVlan'])->name('voice.vlan.store');
    Route::post('/voice/vlan/delete', [VLANController::class, 'deleteVoiceVlan'])->name('voice.vlan.delete');




//interface part
    Route::get('tabs/interface-voice-vlan', [VLANController::class, 'interfaceVoiceVlanTab']);
    Route::get('/vlan/interface/edit', [VLANController::class, 'editVlanInterface']);
    Route::post('/vlan-interface/save', [VLANController::class, 'runVlanAttributeinterface'])->name('vlan.interface.save');






    Route::post('/run-ansible', [alphabridgeController::class, 'runPlaybook'])->name('run.ansible');
    Route::post('/vlan-config', [alphabridgeController::class, 'configureVlan'])->name('vlan.configure');
    Route::get('/ansible-log', function () {
        $logFile = "/opt/librenms/librenms-ansible-inventory-plugin/ansible_log.txt";
    
        if (!file_exists($logFile)) {
            return response()->json(['message' => 'No logs available yet.'], 404);
        }
    
        return response()->json(['log' => file_get_contents($logFile)]);
    });
    











    // pages
    Route::post('alert/{alert}/ack', [AlertController::class, 'ack'])->name('alert.ack');
    Route::resource('device-groups', DeviceGroupController::class);
    Route::any('inventory', App\Http\Controllers\InventoryController::class)->name('inventory');
    Route::get('inventory/purge', [App\Http\Controllers\InventoryController::class, 'purge'])->name('inventory.purge');
    Route::resource('port', PortController::class)->only('update');
    Route::get('vlans', [App\Http\Controllers\VlansController::class, 'index'])->name('vlans.index');
    Route::prefix('poller')->group(function () {
        Route::get('', [PollerController::class, 'pollerTab'])->name('poller.index');
        Route::get('log', [PollerController::class, 'logTab'])->name('poller.log');
        Route::get('groups', [PollerController::class, 'groupsTab'])->name('poller.groups');
        Route::get('settings', [PollerController::class, 'settingsTab'])->name('poller.settings');
        Route::get('performance', [PollerController::class, 'performanceTab'])->name('poller.performance');
        Route::resource('{id}/settings', PollerSettingsController::class, ['as' => 'poller'])->only(['update', 'destroy']);
    });
    Route::prefix('services')->name('services.')->group(function () {
        Route::resource('templates', ServiceTemplateController::class);
        Route::post('templates/applyAll', [ServiceTemplateController::class, 'applyAll'])->name('templates.applyAll');
        Route::post('templates/apply/{template}', [ServiceTemplateController::class, 'apply'])->name('templates.apply');
        Route::post('templates/remove/{template}', [ServiceTemplateController::class, 'remove'])->name('templates.remove');
    });
    Route::get('locations', [LocationController::class, 'index']);
    Route::resource('preferences', UserPreferencesController::class)->only('index', 'store');
    Route::resource('users', UserController::class);
    //added by kunal for mibs upload 
    Route::resource('mibs', MibsUploadController::class);
    Route::get('mibs/download/{id}', [MibsUploadController::class,'download'])->name('mibs.download');

    //added by kunal for system software bulk upload
    Route::resource('syssoftbulk', SystemBulkUploadController::class);
    Route::get('addhost/ip', [SystemBulkUploadController::class, 'addHostIp'])->name('addhost.ip');
    Route::post('addhost/ip/save', [SystemBulkUploadController::class, 'addHostIpsave'])->name('addhost.ip.save');
    Route::get('addhost/template', [TemplatePushController::class, 'addHostTemplate'])->name('addhost.template');
    Route::post('addhost/template/save', [TemplatePushController::class, 'addHostTemplateSave'])->name('addhost.template.save');
    Route::post('addhost/template/store', [TemplatePushController::class, 'storeTemplate'])->name('addhost.template.store');
    Route::post('addhost/template/delete', [TemplatePushController::class, 'destroyTemplate'])->name('addhost.template.delete');
    Route::get('addhost/ip/file-content', [SystemBulkUploadController::class, 'getUploadedFileContent'])->name('addhost.ip.file-content');
    Route::post('addhost/ports/fetch', [TemplatePushController::class, 'getDevicePorts'])->name('addhost.ports.fetch');
    // Add to routes/web.php for testing
    Route::get('/system/bulk-upload', [SystemBulkUploadController::class, 'index'])->name('system.bulk.upload');
    Route::post('/system/bulk-upload/process', [SystemBulkUploadController::class, 'process'])->name('system.bulk.upload.process');

    // ZTP (Zero Touch Provisioning) — authenticated management routes
    Route::resource('ztp', ZtpController::class)->except(['show']);
    Route::post('ztp/{ztp}/reset', [ZtpController::class, 'resetStatus'])->name('ztp.reset');


    //kunal add 
    Route::resource('chatbot', ChatBotController::class);
    Route::post('chatbot/messages', [ChatBotController::class, 'message'])
    ->name('chatbot.message');

    // Backup routes
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index')->middleware('can:admin');
    Route::post('/backup/run', [BackupController::class, 'store'])->name('backup.run')->middleware('can:admin');
    Route::post('/backup/upload', [BackupController::class, 'upload'])->name('backup.upload')->middleware('can:admin');
    Route::get('/backup/download/{filename}', [BackupController::class, 'download'])->name('backup.download')->middleware('can:admin');
    Route::delete('/backup/delete/{filename}', [BackupController::class, 'destroy'])->name('backup.delete')->middleware('can:admin');
    Route::post('/backup/restore/{filename}', [BackupController::class, 'restore'])->name('backup.restore')->middleware('can:admin');
    Route::post('/backup/schedule', [BackupController::class, 'saveSchedule'])->name('backup.save-schedule')->middleware('can:admin');

    // RRD Backup routes
    Route::post('/backup/rrd/run', [BackupController::class, 'storeRrd'])->name('backup.rrd.run')->middleware('can:admin');
    Route::get('/backup/rrd/download/{filename}', [BackupController::class, 'downloadRrd'])->name('backup.rrd.download')->middleware('can:admin');
    Route::delete('/backup/rrd/delete/{filename}', [BackupController::class, 'destroyRrd'])->name('backup.rrd.delete')->middleware('can:admin');
    Route::post('/backup/rrd/restore/{filename}', [BackupController::class, 'restoreRrd'])->name('backup.rrd.restore')->middleware('can:admin');
    Route::post('/backup/rrd/schedule', [BackupController::class, 'saveRrdSchedule'])->name('backup.rrd.save-schedule')->middleware('can:admin');

    // Node / Device Startup-Config Backup routes
    Route::post('/backup/node/run', [BackupController::class, 'storeNode'])->name('backup.node.run')->middleware('can:admin');
    Route::post('/backup/node/schedule', [BackupController::class, 'saveNodeSchedule'])->name('backup.node.save-schedule')->middleware('can:admin');
    Route::get('/backup/node/download/{filename}', [BackupController::class, 'downloadNode'])->name('backup.node.download')->middleware('can:admin');
    Route::delete('/backup/node/delete/{filename}', [BackupController::class, 'destroyNode'])->name('backup.node.delete')->middleware('can:admin');
    Route::post('/backup/node/restore/{filename}', [BackupController::class, 'restoreNode'])->name('backup.node.restore')->middleware('can:admin');

    // Alarm History Archive routes
    Route::get('/alerts/archive', [\App\Http\Controllers\AlarmArchiveController::class, 'index'])->name('alerts.archive.index');
    Route::post('/alerts/archive/run', [\App\Http\Controllers\AlarmArchiveController::class, 'store'])->name('alerts.archive.store')->middleware('can:admin');
    Route::get('/alerts/archive/download/{id}', [\App\Http\Controllers\AlarmArchiveController::class, 'download'])->name('alerts.archive.download')->middleware('can:admin');
    Route::get('/alerts/archive/view/{id}', [\App\Http\Controllers\AlarmArchiveController::class, 'view'])->name('alerts.archive.view');
    Route::delete('/alerts/archive/delete/{id}', [\App\Http\Controllers\AlarmArchiveController::class, 'destroy'])->name('alerts.archive.destroy')->middleware('can:admin');
    Route::post('/alerts/archive/settings', [\App\Http\Controllers\AlarmArchiveController::class, 'saveSettings'])->name('alerts.archive.settings')->middleware('can:admin');

    Route::get('about', [AboutController::class, 'index'])->name('about');
    Route::delete('reporting', [AboutController::class, 'clearReportingData'])->name('reporting.clear');
    Route::get('authlog', [UserController::class, 'authlog']);
    Route::get('overview', [OverviewController::class, 'index'])->name('overview');
    Route::get('/', [OverviewController::class, 'index'])->name('home');
    Route::view('vminfo', 'vminfo');

    Route::get('nac', [NacController::class, 'index']);

    // Device Tabs
    Route::middleware('can:admin')->group(function () {
        Route::get('/device/{device}/edit', [Device\EditDeviceController::class, 'index'])->name('device.edit');
        Route::put('/device/{device}/edit', [Device\EditDeviceController::class, 'update'])->name('device.edit.update');
        Route::post('/device/{device}/rediscover', [DeviceController::class, 'rediscover'])->name('device.rediscover');
    });

    Route::prefix('device/{device}')->name('device.')->group(function () {
        Route::get('popup', \App\Http\Controllers\DevicePopupController::class)->name('popup');
        Route::put('notes', [Device\Tabs\NotesController::class, 'update'])->name('notes.update');
        Route::put('module/{module}', [Device\Tabs\ModuleController::class, 'update'])->name('module.update');
        Route::delete('module/{module}', [Device\Tabs\ModuleController::class, 'delete'])->name('module.delete');
    });

    // fallback device routes
    Route::match(['get', 'post'], 'device/{device}/{tab?}/{vars?}', [DeviceController::class, 'index'])
        ->name('device')->where('vars', '.*');

    // Maps
    Route::get('fullscreenmap', [Maps\FullscreenMapController::class, 'fullscreenMap']);
    Route::get('availability-map', [Maps\AvailabilityMapController::class, 'availabilityMap']);
    Route::get('map/{vars?}', [Maps\NetMapController::class, 'netMap']);
    Route::prefix('maps')->group(function () {
        Route::resource('custom', CustomMapController::class, ['as' => 'maps'])
            ->parameters(['custom' => 'map'])->except('create');
        Route::post('custom/{map}/clone', [CustomMapController::class, 'clone'])->name('maps.custom.clone');
        Route::get('custom/{map}/background', [CustomMapBackgroundController::class, 'get'])->name('maps.custom.background');
        Route::post('custom/{map}/background', [CustomMapBackgroundController::class, 'save'])->name('maps.custom.background.save');
        Route::get('custom/{map}/data', [CustomMapDataController::class, 'get'])->name('maps.custom.data');
        Route::post('custom/{map}/data', [CustomMapDataController::class, 'save'])->name('maps.custom.data.save');
        Route::get('devicedependency', [DeviceDependencyController::class, 'dependencyMap']);
        Route::post('getdevices', [Maps\MapDataController::class, 'getDevices'])->name('maps.getdevices');
        Route::post('getdevicelinks', [Maps\MapDataController::class, 'getDeviceLinks'])->name('maps.getdevicelinks');
        Route::post('getgeolinks', [Maps\MapDataController::class, 'getGeographicLinks'])->name('maps.getgeolinks');
        Route::post('getservices', [Maps\MapDataController::class, 'getServices'])->name('maps.getservices');
        Route::get('nodeimage', [CustomMapNodeImageController::class, 'index'])->name('maps.nodeimage.index');
        Route::post('nodeimage', [CustomMapNodeImageController::class, 'store'])->name('maps.nodeimage.store');
        Route::delete('nodeimage/{image}', [CustomMapNodeImageController::class, 'destroy'])->name('maps.nodeimage.destroy');
        Route::get('nodeimage/{image}', [CustomMapNodeImageController::class, 'show'])->name('maps.nodeimage.show');
        Route::post('nodeimage/{image}', [CustomMapNodeImageController::class, 'update'])->name('maps.nodeimage.update');
    });
    Route::get('maps/devicedependency', [DeviceDependencyController::class, 'dependencyMap']);

    // dashboard
    Route::resource('dashboard', DashboardController::class)->except(['create', 'edit']);
    Route::post('dashboard/{dashboard}/copy', [DashboardController::class, 'copy'])->name('dashboard.copy');
    Route::post('dashboard/{dashboard}/widgets', [DashboardWidgetController::class, 'add'])->name('dashboard.widget.add');
    Route::delete('dashboard/{dashboard}/widgets', [DashboardWidgetController::class, 'clear'])->name('dashboard.widget.clear');
    Route::put('dashboard/{dashboard}/widgets', [DashboardWidgetController::class, 'update'])->name('dashboard.widget.update');
    Route::delete('dashboard/widgets/{widget}', [DashboardWidgetController::class, 'remove'])->name('dashboard.widget.remove');
    Route::put('dashboard/widgets/{widget}', [WidgetSettingsController::class, 'update'])->name('dashboard.widget.settings');

    Route::get('tool/oui-lookup', OuiLookupController::class)->name('tool.oui-lookup');

    // Push notifications
    Route::prefix('push')->group(function () {
        Route::get('token', [PushNotificationController::class, 'token'])->name('push.token');
        Route::get('key', [PushNotificationController::class, 'key'])->name('push.key');
        Route::post('register', [PushNotificationController::class, 'register'])->name('push.register');
        Route::post('unregister', [PushNotificationController::class, 'unregister'])->name('push.unregister');
    });

    // admin pages
    Route::middleware('can:admin')->group(function () {
        Route::get('settings/{tab?}/{section?}', [SettingsController::class, 'index'])->name('settings');
        Route::put('settings/{name}', [SettingsController::class, 'update'])->name('settings.update');
        Route::delete('settings/{name}', [SettingsController::class, 'destroy'])->name('settings.destroy');

        Route::post('alert/transports/{transport}/test', [AlertTransportController::class, 'test'])->name('alert.transports.test');
        Route::resource('alert-rule', AlertRuleController::class)->only(['show', 'store', 'update', 'destroy']);
        Route::put('alert-rule/{alert_rule}/toggle', [AlertRuleController::class, 'toggle'])->name('alert-rule.toggle');
        Route::put('alert-rule/{alert_rule}/toggle-snmp', [AlertRuleController::class, 'toggleSnmp'])->name('alert-rule.toggle-snmp');
        Route::get('alert-rule-from-template/{template_id}', [AlertRuleTemplateController::class, 'template'])->name('alert-rule-template');
        Route::get('alert-rule-from-rule/{alert_rule}', [AlertRuleTemplateController::class, 'rule'])->name('alert-rule-template.rule');

        Route::get('plugin/settings', App\Http\Controllers\PluginAdminController::class)->name('plugin.admin');
        Route::get('plugin/settings/{plugin:plugin_name}', PluginSettingsController::class)->name('plugin.settings');
        Route::post('plugin/settings/{plugin:plugin_name}', [PluginSettingsController::class, 'update'])->name('plugin.update');

        Route::resource('port-groups', PortGroupController::class);
        Route::get('validate', [ValidateController::class, 'index'])->name('validate');
        Route::get('validate/results', [ValidateController::class, 'runValidation'])->name('validate.results');
        Route::post('validate/fix', [ValidateController::class, 'runFixer'])->name('validate.fix');
    });

    Route::get('plugin', [PluginLegacyController::class, 'redirect']);
    Route::redirect('plugin/view=admin', '/plugin/admin');
    Route::get('plugin/p={pluginName}', [PluginLegacyController::class, 'redirect']);
    Route::any('plugin/v1/{plugin:plugin_name}/{other?}', PluginLegacyController::class)->where('other', '(.*)')->name('plugin.legacy');
    Route::get('plugin/{plugin:plugin_name}', PluginPageController::class)->name('plugin.page');

    Route::get('health/{metric?}/{legacyview?}', [SensorController::class, 'index'])->name('sensor.index');
    Route::get('wireless/{metric}/{legacyview?}', [WirelessSensorController::class, 'index'])->name('wireless.index');

    // old route redirects
    Route::permanentRedirect('poll-log', 'poller/log');

    // Two Factor Auth
    Route::prefix('2fa')->group(function () {
        Route::get('', [Auth\TwoFactorController::class, 'showTwoFactorForm'])->name('2fa.form');
        Route::post('', [Auth\TwoFactorController::class, 'verifyTwoFactor'])->name('2fa.verify');
        Route::post('add', [Auth\TwoFactorController::class, 'create'])->name('2fa.add');
        Route::post('cancel', [Auth\TwoFactorController::class, 'cancelAdd'])->name('2fa.cancel');
        Route::post('remove', [Auth\TwoFactorController::class, 'destroy'])->name('2fa.remove');

        Route::post('{user}/unlock', [Auth\TwoFactorManagementController::class, 'unlock'])->name('2fa.unlock');
        Route::delete('{user}', [Auth\TwoFactorManagementController::class, 'destroy'])->name('2fa.delete');
    });

    // Ajax routes
    Route::prefix('ajax')->group(function () {
        //alert
        Route::get('/alerts-api', [AlertController::class, 'getAlerts']);
        // page ajax controllers
        Route::resource('location', LocationController::class)->only('update', 'destroy');
        Route::resource('pollergroup', PollerGroupController::class)->only('destroy');
        // misc ajax controllers
        Route::get('search/bgp', Ajax\BgpSearchController::class);
        Route::get('search/device', Ajax\DeviceSearchController::class);
        Route::get('search/port', Ajax\PortSearchController::class);
        Route::post('set_map_group', [Ajax\AvailabilityMapController::class, 'setGroup']);
        Route::post('set_map_view', [Ajax\AvailabilityMapController::class, 'setView']);
        Route::post('set_resolution', [Ajax\SessionController::class, 'resolution']);
        Route::post('set_style', [Ajax\SessionController::class, 'style']);
        Route::get('netcmd', [Ajax\NetCommand::class, 'run']);
        Route::post('ripe/raw', [Ajax\RipeNccApiController::class, 'raw']);
        Route::get('snmp/capabilities', Ajax\SnmpCapabilities::class)->name('snmp.capabilities');

        Route::get('settings/list', [SettingsController::class, 'listAll'])->name('settings.list');

        // js select2 data controllers
        Route::prefix('select')->group(function () {
            Route::get('application', Select\ApplicationController::class)->name('ajax.select.application');
            Route::get('bill', Select\BillController::class)->name('ajax.select.bill');
            Route::get('custom-map', Select\CustomMapController::class)->name('ajax.select.custom-map');
            Route::get('custom-map-menu-group', Select\CustomMapMenuGroupController::class)->name('ajax.select.custom-map-menu-group');
            Route::get('dashboard', Select\DashboardController::class)->name('ajax.select.dashboard');
            Route::get('device', Select\DeviceController::class)->name('ajax.select.device');
            Route::get('device-field', Select\DeviceFieldController::class)->name('ajax.select.device-field');
            Route::get('device-group', Select\DeviceGroupController::class)->name('ajax.select.device-group');
            Route::get('port-group', Select\PortGroupController::class)->name('ajax.select.port-group');
            Route::get('eventlog', Select\EventlogController::class)->name('ajax.select.eventlog');
            Route::get('graph', Select\GraphController::class)->name('ajax.select.graph');
            Route::get('graph-aggregate', Select\GraphAggregateController::class)->name('ajax.select.graph-aggregate');
            Route::get('graylog-streams', Select\GraylogStreamsController::class)->name('ajax.select.graylog-streams');
            Route::get('inventory', Select\InventoryController::class)->name('ajax.select.inventory');
            Route::get('syslog', Select\SyslogController::class)->name('ajax.select.syslog');
            Route::get('location', Select\LocationController::class)->name('ajax.select.location');
            Route::get('munin', Select\MuninPluginController::class)->name('ajax.select.munin');
            Route::get('role', Select\RoleController::class)->name('ajax.select.role');
            Route::get('service', Select\ServiceController::class)->name('ajax.select.service');
            Route::get('template', Select\ServiceTemplateController::class)->name('ajax.select.template');
            Route::get('poller-group', Select\PollerGroupController::class)->name('ajax.select.poller-group');
            Route::get('port', Select\PortController::class)->name('ajax.select.port');
            Route::get('port-field', Select\PortFieldController::class)->name('ajax.select.port-field');
        });

        // jquery bootgrid data controllers
        Route::prefix('table')->group(function () {
            Route::post('alert-schedule', Table\AlertScheduleController::class);
            Route::post('customers', Table\CustomersController::class);
            Route::post('diskio', Table\DiskioController::class)->name('table.diskio');
            Route::post('device', Table\DeviceController::class)->name('table.device');
            Route::get('device/export', [Table\DeviceController::class, 'export']);
            Route::post('edit-ports', Table\EditPortsController::class);
            Route::post('eventlog', Table\EventlogController::class);
            Route::post('eventlog/forward', [Table\EventlogController::class, 'forward'])->name('ajax.eventlog.forward');
            Route::post('eventlog/forward/test', [Table\EventlogController::class, 'testForward'])->name('ajax.eventlog.forward.test');
            Route::post('eventlog/forward-snmptrap', [Table\EventlogController::class, 'forwardSnmpTrap'])->name('ajax.eventlog.forward-snmptrap');
            Route::post('eventlog/forward-snmptrap/test', [Table\EventlogController::class, 'testForwardSnmpTrap'])->name('ajax.eventlog.forward-snmptrap.test');
            Route::get('eventlog/export', [Table\EventlogController::class, 'export']);
            Route::post('fdb-tables', Table\FdbTablesController::class);
            Route::post('graylog', Table\GraylogController::class);
            Route::post('inventory', Table\InventoryController::class)->name('table.inventory');
            Route::get('inventory/export', [Table\InventoryController::class, 'export']);
            Route::post('location', Table\LocationController::class);
            Route::post('mempools', Table\MempoolsController::class)->name('table.mempools');
            Route::get('mempools/export', [Table\MempoolsController::class, 'export']);
            Route::post('outages', Table\OutagesController::class)->name('table.outages');
            Route::get('outages/export', [Table\OutagesController::class, 'export']);
            Route::post('port-nac', Table\PortNacController::class)->name('table.port-nac');
            Route::post('port-stp', Table\PortStpController::class);
            Route::post('ports', Table\PortsController::class)->name('table.ports');
            Route::get('ports/export', [Table\PortsController::class, 'export']);
            Route::post('processors', Table\ProcessorsController::class)->name('table.processors');
            Route::get('processors/export', [Table\ProcessorsController::class, 'export']);
            Route::post('routes', Table\RoutesTablesController::class);
            Route::post('sensors', Table\SensorsController::class)->name('table.sensors');
            Route::get('sensors/export', [Table\SensorsController::class, 'export']);
            Route::post('storages', Table\StoragesController::class)->name('table.storages');
            Route::get('storages/export', [Table\StoragesController::class, 'export']);
            Route::post('syslog', Table\SyslogController::class);
            Route::post('printer-supply', Table\PrinterSupplyController::class)->name('table.printer-supply');
            Route::post('tnmsne', Table\TnmsneController::class)->name('table.tnmsne');
            Route::post('wireless', Table\WirelessSensorController::class)->name('table.wireless');
            Route::post('vlan-ports', Table\VlanPortsController::class)->name('table.vlan-ports');
            Route::post('vlan-devices', Table\VlanDevicesController::class)->name('table.vlan-devices');
            Route::post('vminfo', Table\VminfoController::class);
        });

        // dashboard widgets
        Route::prefix('dash')->group(function () {
            Route::post('alerts', Widgets\AlertsController::class);
            Route::post('alertlog', Widgets\AlertlogController::class);
            Route::post('alertlog-stats', Widgets\AlertlogStatsController::class);
            Route::post('availability-map', Widgets\AvailabilityMapController::class);
            Route::post('component-status', Widgets\ComponentStatusController::class);
            Route::post('custom-map', Widgets\CustomMapController::class);
            Route::post('device-summary-horiz', Widgets\DeviceSummaryHorizController::class);
            Route::post('device-summary-vert', Widgets\DeviceSummaryVertController::class);
            Route::post('device-types', Widgets\DeviceTypeController::class);
            Route::post('eventlog', Widgets\EventlogController::class);
            Route::post('generic-graph', Widgets\GraphController::class);
            Route::post('generic-image', Widgets\ImageController::class);
            Route::post('globe', Widgets\GlobeController::class);
            Route::post('graylog', Widgets\GraylogController::class);
            Route::post('placeholder', Widgets\PlaceholderController::class);
            Route::post('notes', Widgets\NotesController::class);
            Route::post('server-stats', Widgets\ServerStatsController::class);
            Route::post('syslog', Widgets\SyslogController::class);
            Route::post('top-devices', Widgets\TopDevicesController::class);
            Route::post('top-interfaces', Widgets\TopInterfacesController::class);
            Route::post('top-errors', Widgets\TopErrorsController::class);
            Route::post('worldmap', Widgets\WorldMapController::class)->name('widget.worldmap');
        });
    });

    // demo helper
    Route::permanentRedirect('demo', '/');
});

// routes that don't need authentication
Route::prefix('ajax')->group(function () {
    Route::post('set_timezone', [Ajax\TimezoneController::class, 'set']);
});

// installation routes
Route::prefix('install')->group(function () {
    Route::get('/', [Install\InstallationController::class, 'redirectToFirst'])->name('install');
    Route::get('/checks', [Install\ChecksController::class, 'index'])->name('install.checks');
    Route::get('/database', [Install\DatabaseController::class, 'index'])->name('install.database');
    Route::get('/user', [Install\MakeUserController::class, 'index'])->name('install.user');
    Route::get('/finish', [Install\FinalizeController::class, 'index'])->name('install.finish');

    Route::post('/finish', [Install\FinalizeController::class, 'saveConfig'])->name('install.finish.save');
    Route::post('/user/create', [Install\MakeUserController::class, 'create'])->name('install.action.user');
    Route::post('/database/test', [Install\DatabaseController::class, 'test'])->name('install.acton.test-database');
    Route::get('/ajax/database/migrate', [Install\DatabaseController::class, 'migrate'])->name('install.action.migrate');
    Route::get('/ajax/steps', [Install\InstallationController::class, 'stepsCompleted'])->name('install.action.steps');
    Route::any('{path?}', [Install\InstallationController::class, 'invalid'])->where('path', '.*'); // 404
});

// Legacy routes
Route::any('/dummy_legacy_auth/{path?}', [LegacyController::class, 'dummy'])->middleware('auth');
Route::any('/dummy_legacy_unauth/{path?}', [LegacyController::class, 'dummy']);
Route::any('/{path?}', [LegacyController::class, 'index'])
    ->where('path', '^((?!_debugbar).)*')
    ->middleware('auth');
