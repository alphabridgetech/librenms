<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v0')->group(function (): void {
    Route::get('ping', fn () => response()->json(['message' => 'pong']))->name('ping');
    Route::get('system', [App\Api\Controllers\LegacyApiController::class, 'server_info'])->name('server_info');
    Route::get('', [App\Api\Controllers\LegacyApiController::class, 'show_endpoints']);

    // Alert Templates
    Route::middleware(['can:viewAny,App\Models\AlertTemplate'])->group(function (): void {
        Route::get('alert_templates/{id}', [App\Api\Controllers\LegacyApiController::class, 'list_alert_templates'])->name('get_alert_template');
        Route::get('alert_templates', [App\Api\Controllers\LegacyApiController::class, 'list_alert_templates'])->name('list_alert_templates');
        Route::post('alert_templates', [App\Api\Controllers\LegacyApiController::class, 'add_edit_alert_template'])->name('add_alert_template')->middleware('can:create,App\Models\AlertTemplate');
        Route::put('alert_templates', [App\Api\Controllers\LegacyApiController::class, 'add_edit_alert_template'])->name('edit_alert_template')->middleware('can:update,App\Models\AlertTemplate');
    });

    // Pollers
    Route::middleware(['can:viewAny,App\Models\Poller'])->group(function (): void {
        Route::get('pollers', [App\Api\Controllers\LegacyApiController::class, 'list_pollers'])->name('list_pollers');
    });

    // Pollers Log
    Route::middleware(['can:viewAny,App\Models\Device'])->group(function (): void {
        Route::get('pollers/log', [App\Api\Controllers\LegacyApiController::class, 'list_poller_log'])->name('list_poller_log');
    });

    // BGP
    Route::middleware(['can:viewAny,App\Models\BgpPeer'])->group(function (): void {
        Route::get('bgp', [App\Api\Controllers\LegacyApiController::class, 'list_bgp'])->name('list_bgp');
        Route::get('bgp/{id}', [App\Api\Controllers\LegacyApiController::class, 'get_bgp'])->name('get_bgp');
        Route::post('bgp/{id}', [App\Api\Controllers\LegacyApiController::class, 'edit_bgp_descr'])->name('edit_bgp_descr')->middleware('can:update,App\Models\BgpPeer');
    });

    // OSPF / Routing
    Route::middleware(['can:viewAny,App\Models\Route'])->group(function (): void {
        Route::get('ospf', [App\Api\Controllers\LegacyApiController::class, 'list_ospf'])->name('list_ospf');
        Route::get('ospf_ports', [App\Api\Controllers\LegacyApiController::class, 'list_ospf_ports'])->name('list_ospf_ports');
        Route::get('ospfv3', [App\Api\Controllers\LegacyApiController::class, 'list_ospfv3'])->name('list_ospfv3');
        Route::get('ospfv3_ports', [App\Api\Controllers\LegacyApiController::class, 'list_ospfv3_ports'])->name('list_ospfv3_ports');
    });

    // Oxidized
    Route::get('oxidized/{hostname?}', [App\Api\Controllers\LegacyApiController::class, 'list_oxidized'])->middleware(['can:viewAny,App\Models\Device'])->name('list_oxidized');
    Route::get('oxidized/config/search/{searchstring}', [App\Api\Controllers\LegacyApiController::class, 'search_oxidized'])->middleware(['can:oxidized.search'])->name('search_oxidized');
    Route::get('oxidized/config/{device_name}', [App\Api\Controllers\LegacyApiController::class, 'get_oxidized_config'])->middleware(['can:viewAny,App\Models\Device'])->name('get_oxidized_config');

    // Device Groups
    Route::middleware(['can:viewAny,App\Models\DeviceGroup'])->group(function (): void {
        Route::get('devicegroups/{name}', [App\Api\Controllers\LegacyApiController::class, 'get_devices_by_group'])->name('get_devices_by_group');
        Route::get('devicegroups', [App\Api\Controllers\LegacyApiController::class, 'get_device_groups'])->name('get_device_groups');
        Route::post('devicegroups', [App\Api\Controllers\LegacyApiController::class, 'add_device_group'])->name('add_device_group')->middleware('can:create,App\Models\DeviceGroup');
        Route::patch('devicegroups/{name}', [App\Api\Controllers\LegacyApiController::class, 'update_device_group'])->name('update_device_group')->middleware('can:update,App\Models\DeviceGroup');
        Route::delete('devicegroups/{name}', [App\Api\Controllers\LegacyApiController::class, 'delete_device_group'])->name('delete_device_group')->middleware('can:delete,App\Models\DeviceGroup');
        Route::post('devicegroups/{name}/devices', [App\Api\Controllers\LegacyApiController::class, 'update_device_group_add_devices'])->name('update_device_group_add_devices')->middleware('can:update,App\Models\DeviceGroup');
        Route::delete('devicegroups/{name}/devices', [App\Api\Controllers\LegacyApiController::class, 'update_device_group_remove_devices'])->name('update_device_group_remove_devices')->middleware('can:update,App\Models\DeviceGroup');
        Route::post('devicegroups/{name}/maintenance', [App\Api\Controllers\LegacyApiController::class, 'maintenance_devicegroup'])->name('maintenance_devicegroup')->middleware('can:update,App\Models\DeviceGroup');
    });

    // Port Groups
    Route::middleware(['can:viewAny,App\Models\PortGroup'])->group(function (): void {
        Route::get('port_groups', [App\Api\Controllers\LegacyApiController::class, 'get_port_groups'])->name('get_port_groups');
        Route::get('port_groups/{name}', [App\Api\Controllers\LegacyApiController::class, 'get_ports_by_group'])->name('get_ports_by_group');
        Route::get('portgroups/multiport/bits/{id}', [App\Api\Controllers\LegacyApiController::class, 'get_graph_by_portgroup'])->name('get_graph_by_portgroup_multiport_bits');
        Route::get('portgroups/{group}', [App\Api\Controllers\LegacyApiController::class, 'get_graph_by_portgroup'])->name('get_graph_by_portgroup');
        Route::post('port_groups', [App\Api\Controllers\LegacyApiController::class, 'add_port_group'])->name('add_port_group')->middleware('can:create,App\Models\PortGroup');
        Route::post('port_groups/{port_group_id}/assign', [App\Api\Controllers\LegacyApiController::class, 'assign_port_group'])->name('assign_port_group')->middleware('can:update,App\Models\PortGroup');
        Route::post('port_groups/{port_group_id}/remove', [App\Api\Controllers\LegacyApiController::class, 'remove_port_group'])->name('remove_port_group')->middleware('can:update,App\Models\PortGroup');
    });

    // Faults
    Route::middleware(['can:viewAny,App\Models\Alert'])->group(function (): void {
        Route::get('faults/{id}', [App\Api\Controllers\LegacyApiController::class, 'list_alerts'])->name('get_alert');
        Route::get('faults', [App\Api\Controllers\LegacyApiController::class, 'list_alerts'])->name('list_alerts');
        Route::put('faults/{id}', [App\Api\Controllers\LegacyApiController::class, 'ack_alert'])->name('ack_alert')->middleware('can:update,App\Models\Alert');
        Route::put('faults/unmute/{id}', [App\Api\Controllers\LegacyApiController::class, 'unmute_alert'])->name('unmute_alert')->middleware('can:update,App\Models\Alert');
        // Legacy aliases
        Route::get('alerts/{id}', [App\Api\Controllers\LegacyApiController::class, 'list_alerts']);
        Route::get('alerts', [App\Api\Controllers\LegacyApiController::class, 'list_alerts']);
        Route::put('alerts/{id}', [App\Api\Controllers\LegacyApiController::class, 'ack_alert'])->middleware('can:update,App\Models\Alert');
        Route::put('alerts/unmute/{id}', [App\Api\Controllers\LegacyApiController::class, 'unmute_alert'])->middleware('can:update,App\Models\Alert');
    });

    // Alert Rules
    Route::middleware(['can:viewAny,App\Models\AlertRule'])->group(function (): void {
        Route::get('rules/{id}', [App\Api\Controllers\LegacyApiController::class, 'list_alert_rules'])->name('get_alert_rule');
        Route::get('rules', [App\Api\Controllers\LegacyApiController::class, 'list_alert_rules'])->name('list_alert_rules');
        Route::post('rules', [App\Api\Controllers\LegacyApiController::class, 'add_edit_rule'])->name('add_rule')->middleware('can:create,App\Models\AlertRule');
        Route::put('rules', [App\Api\Controllers\LegacyApiController::class, 'add_edit_rule'])->name('edit_rule')->middleware('can:update,App\Models\AlertRule');
        Route::delete('rules/{id}', [App\Api\Controllers\LegacyApiController::class, 'delete_rule'])->name('delete_rule')->middleware('can:delete,App\Models\AlertRule');
    });

    // Routing VRF
    Route::middleware(['can:viewAny,App\Models\Vrf'])->group(function (): void {
        Route::get('routing/vrf/{id}', [App\Api\Controllers\LegacyApiController::class, 'get_vrf'])->name('get_vrf');
    });

    // Routing IPSec / Devices / General Device maintenance
    Route::middleware(['can:viewAny,App\Models\Device'])->group(function (): void {
        Route::get('routing/ipsec/data/{hostname}', [App\Api\Controllers\LegacyApiController::class, 'list_ipsec'])->name('list_ipsec');
    });

    // Services
    Route::middleware(['can:viewAny,App\Models\Service'])->group(function (): void {
        Route::get('services', [App\Api\Controllers\LegacyApiController::class, 'list_services'])->name('list_services');
        Route::get('services/{hostname}', [App\Api\Controllers\LegacyApiController::class, 'list_services'])->name('list_services_device');
        Route::post('services/{hostname}', [App\Api\Controllers\LegacyApiController::class, 'add_service_for_host'])->name('add_service_for_host')->middleware('can:create,App\Models\Service');
        Route::delete('services/{id}', [App\Api\Controllers\LegacyApiController::class, 'del_service_from_host'])->name('del_service_from_host')->middleware('can:delete,App\Models\Service');
        Route::patch('services/{id}', [App\Api\Controllers\LegacyApiController::class, 'edit_service_for_host'])->name('edit_service_for_host')->middleware('can:update,App\Models\Service');
    });

    // Syslog Sink (receives syslogs, requires write access on devices / system)
    Route::post('syslogsink', [App\Api\Controllers\LegacyApiController::class, 'post_syslogsink'])->name('post_syslogsink')->middleware('can:update,App\Models\Device');

    // Poller Groups
    Route::get('poller_group/{poller_group_id_or_name?}', [App\Api\Controllers\LegacyApiController::class, 'get_poller_group'])->name('get_poller_group')->middleware('can:viewAny,App\Models\PollerGroup');

    // Device actions / details (restricted by access)
    Route::prefix('devices')->group(function (): void {
        Route::middleware('can:viewAny,App\Models\Device')->group(function (): void {
            Route::get('{hostname}', [App\Api\Controllers\LegacyApiController::class, 'get_device'])->name('get_device');
            Route::get('{hostname}/discover', [App\Api\Controllers\LegacyApiController::class, 'trigger_device_discovery'])->name('trigger_device_discovery');
            Route::get('{hostname}/availability', [App\Api\Controllers\LegacyApiController::class, 'device_availability'])->name('device_availability');
            Route::get('{hostname}/outages', [App\Api\Controllers\LegacyApiController::class, 'device_outages'])->name('device_outages');
            Route::get('{hostname}/graphs/health/{type}/{sensor_id?}', [App\Api\Controllers\LegacyApiController::class, 'get_graph_generic_by_hostname'])->name('get_health_graph');
            Route::get('{hostname}/graphs/wireless/{type}/{sensor_id?}', [App\Api\Controllers\LegacyApiController::class, 'get_graph_generic_by_hostname'])->name('get_wireless_graph');
            Route::get('{hostname}/vlans', [App\Api\Controllers\LegacyApiController::class, 'get_vlans'])->name('get_vlans');
            Route::get('{hostname}/links', [App\Api\Controllers\LegacyApiController::class, 'list_links'])->name('list_links_device');
            Route::get('{hostname}/graphs', [App\Api\Controllers\LegacyApiController::class, 'get_graphs'])->name('get_graphs');
            Route::get('{hostname}/fdb', [App\Api\Controllers\LegacyApiController::class, 'get_fdb'])->name('get_fdb');
            Route::get('{hostname}/nac', [App\Api\Controllers\LegacyApiController::class, 'get_nac'])->name('get_nac');
            Route::get('{hostname}/health/{type?}/{sensor_id?}', [App\Api\Controllers\LegacyApiController::class, 'list_available_health_graphs'])->name('list_available_health_graphs');
            Route::get('{hostname}/wireless/{type?}/{sensor_id?}', [App\Api\Controllers\LegacyApiController::class, 'list_available_wireless_graphs'])->name('list_available_wireless_graphs');
            Route::get('{hostname}/wireless-sensors', [App\Api\Controllers\LegacyApiController::class, 'get_device_wireless_sensors'])->name('get_device_wireless_sensors');
            Route::get('{hostname}/ports', [App\Api\Controllers\LegacyApiController::class, 'get_device_ports'])->name('get_device_ports');
            Route::get('{hostname}/ip', [App\Api\Controllers\LegacyApiController::class, 'get_device_ip_addresses'])->name('get_ip_addresses');
            Route::get('{hostname}/port_stack', [App\Api\Controllers\LegacyApiController::class, 'get_port_stack'])->name('get_port_stack');
            Route::get('{hostname}/transceivers', [App\Api\Controllers\LegacyApiController::class, 'get_transceivers'])->name('get_transceivers');
            Route::get('{hostname}/components', [App\Api\Controllers\LegacyApiController::class, 'get_components'])->name('get_components');
            Route::get('{hostname}/groups', [App\Api\Controllers\LegacyApiController::class, 'get_device_groups'])->name('get_device_groups_device');
            Route::get('{hostname}/maintenance', [App\Api\Controllers\LegacyApiController::class, 'device_under_maintenance'])->name('device_under_maintenance');
            // consumes the route below, but passes to it when detected
            Route::get('{hostname}/ports/{ifname}', [App\Api\Controllers\LegacyApiController::class, 'get_port_stats_by_port_hostname'])->name('get_port_stats_by_port_hostname')->where('ifname', '.*');
            Route::get('{hostname}/ports/{ifname}/{type}', [App\Api\Controllers\LegacyApiController::class, 'get_graph_by_port_hostname'])->name('get_graph_by_port_hostname');
            Route::get('{hostname}/services/{id}/graphs/{datasource}', [App\Api\Controllers\LegacyApiController::class, 'get_graph_by_service'])->name('get_graph_by_service');
            Route::post('{hostname}/eventlog', [App\Api\Controllers\LegacyApiController::class, 'add_eventlog'])->name('add_eventlog');
            Route::get('{hostname}/{type}', [App\Api\Controllers\LegacyApiController::class, 'get_graph_generic_by_hostname'])->name('get_graph_generic_by_hostname');
            Route::get('', [App\Api\Controllers\LegacyApiController::class, 'list_devices'])->name('list_devices');
        });

        Route::middleware('can:create,App\Models\Device')->group(function (): void {
            Route::post('', [App\Api\Controllers\LegacyApiController::class, 'add_device'])->name('add_device');
        });

        Route::middleware('can:delete,App\Models\Device')->group(function (): void {
            Route::delete('{hostname}', [App\Api\Controllers\LegacyApiController::class, 'del_device'])->name('del_device');
        });

        Route::middleware('can:update,App\Models\Device')->group(function (): void {
            Route::patch('{hostname}', [App\Api\Controllers\LegacyApiController::class, 'update_device'])->name('update_device_field');
            Route::patch('{hostname}/rename/{new_hostname}', [App\Api\Controllers\LegacyApiController::class, 'rename_device'])->name('rename_device');
            Route::post('{hostname}/maintenance', [App\Api\Controllers\LegacyApiController::class, 'maintenance_device'])->name('maintenance_device');
            Route::post('{id}/parents', [App\Api\Controllers\LegacyApiController::class, 'add_parents_to_host'])->name('add_parents_to_host');
            Route::delete('{id}/parents', [App\Api\Controllers\LegacyApiController::class, 'del_parents_from_host'])->name('del_parents_from_host');
        });

        Route::middleware('can:update,App\Models\Port')->group(function (): void {
            Route::patch('{hostname}/port/{portid}', [App\Api\Controllers\LegacyApiController::class, 'update_device_port_notes'])->name('update_device_port_notes');
        });

        Route::middleware('can:create,App\Models\Component')->group(function (): void {
            Route::post('{hostname}/components/{type}', [App\Api\Controllers\LegacyApiController::class, 'add_components'])->name('add_components');
        });

        Route::middleware('can:update,App\Models\Component')->group(function (): void {
            Route::put('{hostname}/components', [App\Api\Controllers\LegacyApiController::class, 'edit_components'])->name('edit_components');
        });

        Route::middleware('can:delete,App\Models\Component')->group(function (): void {
            Route::delete('{hostname}/components/{component}', [App\Api\Controllers\LegacyApiController::class, 'delete_components'])->name('delete_components');
        });
    });

    // Logs
    Route::prefix('logs')->group(function (): void {
        Route::get('eventlog/{hostname?}', [App\Api\Controllers\LegacyApiController::class, 'list_logs'])->name('list_eventlog')->middleware('can:viewAny,App\Models\Eventlog');
        Route::get('syslog/{hostname?}', [App\Api\Controllers\LegacyApiController::class, 'list_logs'])->name('list_syslog')->middleware('can:viewAny,App\Models\Syslog');
        Route::get('alertlog/{hostname?}', [App\Api\Controllers\LegacyApiController::class, 'list_logs'])->name('list_alertlog')->middleware('can:viewAny,App\Models\AlertLog');
        Route::get('authlog', [App\Api\Controllers\LegacyApiController::class, 'list_logs'])->name('list_authlog')->middleware('can:viewAny,App\Models\AuthLog');
    });

    // Ports
    Route::prefix('ports')->group(function (): void {
        Route::middleware('can:viewAny,App\Models\Port')->group(function (): void {
            Route::get('{portid}', [App\Api\Controllers\LegacyApiController::class, 'get_port_info'])->name('get_port_info');
            Route::get('{portid}/fdb', [App\Api\Controllers\LegacyApiController::class, 'get_port_fdb'])->name('get_port_fdb');
            Route::get('{portid}/ip', [App\Api\Controllers\LegacyApiController::class, 'get_port_ip_addresses'])->name('get_port_ip_info');
            Route::get('{portid}/transceiver', [App\Api\Controllers\LegacyApiController::class, 'get_port_transceiver'])->name('get_port_transceiver');
            Route::get('search/{field}/{search?}', [App\Api\Controllers\LegacyApiController::class, 'search_ports'])->name('search_ports')->where('search', '.*');
            Route::get('mac/{search}', [App\Api\Controllers\LegacyApiController::class, 'search_by_mac'])->name('search_mac');
            Route::get('', [App\Api\Controllers\LegacyApiController::class, 'get_all_ports'])->name('get_all_ports');
            Route::get('{portid}/description', [App\Api\Controllers\LegacyApiController::class, 'get_port_description'])->name('get_port_description');
        });
        Route::middleware('can:update,App\Models\Port')->group(function (): void {
            Route::patch('{portid}/description', [App\Api\Controllers\LegacyApiController::class, 'update_port_description'])->name('update_port_description');
        });
    });

    // Bills
    Route::prefix('bills')->group(function (): void {
        Route::middleware('can:viewAny,App\Models\Bill')->group(function (): void {
            Route::get('', [App\Api\Controllers\LegacyApiController::class, 'list_bills'])->name('list_bills');
            Route::get('{bill_id}', [App\Api\Controllers\LegacyApiController::class, 'list_bills'])->name('get_bill');
            Route::get('{bill_id}/graphs/{graph_type}', [App\Api\Controllers\LegacyApiController::class, 'get_bill_graph'])->name('get_bill_graph');
            Route::get('{bill_id}/graphdata/{graph_type}', [App\Api\Controllers\LegacyApiController::class, 'get_bill_graphdata'])->name('get_bill_graphdata');
            Route::get('{bill_id}/history', [App\Api\Controllers\LegacyApiController::class, 'get_bill_history'])->name('get_bill_history');
            Route::get('{bill_id}/history/{bill_hist_id}/graphs/{graph_type}', [App\Api\Controllers\LegacyApiController::class, 'get_bill_history_graph'])->name('get_bill_history_graph');
            Route::get('{bill_id}/history/{bill_hist_id}/graphdata/{graph_type}', [App\Api\Controllers\LegacyApiController::class, 'get_bill_history_graphdata'])->name('get_bill_history_graphdata');
        });
        Route::middleware('can:create,App\Models\Bill')->group(function (): void {
            Route::post('', [App\Api\Controllers\LegacyApiController::class, 'create_edit_bill'])->name('create_bill');
        });
        Route::middleware('can:delete,App\Models\Bill')->group(function (): void {
            Route::delete('{bill_id}', [App\Api\Controllers\LegacyApiController::class, 'delete_bill'])->name('delete_bill');
        });
    });

    // Routing
    Route::prefix('routing')->middleware('can:viewAny,App\Models\Route')->group(function (): void {
        Route::get('bgp/cbgp', [App\Api\Controllers\LegacyApiController::class, 'list_cbgp'])->name('list_cbgp');
        Route::get('vrf', [App\Api\Controllers\LegacyApiController::class, 'list_vrf'])->name('list_vrf');
        Route::get('mpls/services', [App\Api\Controllers\LegacyApiController::class, 'list_mpls_services'])->name('list_mpls_services');
        Route::get('mpls/saps', [App\Api\Controllers\LegacyApiController::class, 'list_mpls_saps'])->name('list_mpls_saps');
    });

    // Resources
    Route::prefix('resources')->group(function (): void {
        Route::middleware('can:viewAny,App\Models\Port')->group(function (): void {
            Route::get('fdb', [App\Api\Controllers\LegacyApiController::class, 'list_fdb'])->name('list_fdb');
            Route::get('fdb/{mac}', [App\Api\Controllers\LegacyApiController::class, 'list_fdb'])->name('list_fdb_mac');
            Route::get('fdb/{mac}/detail', [App\Api\Controllers\LegacyApiController::class, 'list_fdb_detail'])->name('list_fdb_detail');
            Route::get('ip/addresses/{address_family?}', [App\Api\Controllers\LegacyApiController::class, 'list_ip_addresses'])->name('list_ip_addresses');
            Route::get('ip/arp/{query}/{cidr?}', [App\Api\Controllers\LegacyApiController::class, 'list_arp'])->name('list_arp');
            Route::get('ip/networks/{address_family?}', [App\Api\Controllers\LegacyApiController::class, 'list_ip_networks'])->name('list_ip_networks');
            Route::get('ip/networks/{id}/ip', [App\Api\Controllers\LegacyApiController::class, 'get_network_ip_addresses'])->name('get_network_ip_addresses');
        });
        Route::middleware('can:viewAny,App\Models\Link')->group(function (): void {
            Route::get('links', [App\Api\Controllers\LegacyApiController::class, 'list_links'])->name('list_links');
            Route::get('links/{id}', [App\Api\Controllers\LegacyApiController::class, 'get_link'])->name('get_link');
        });
        Route::middleware('can:viewAny,App\Models\PortsNac')->group(function (): void {
            Route::get('nac', [App\Api\Controllers\LegacyApiController::class, 'list_nac'])->name('list_nac');
            Route::get('nac/{mac}', [App\Api\Controllers\LegacyApiController::class, 'list_nac'])->name('list_nac_mac');
        });
        Route::middleware('can:viewAny,App\Models\Sensor')->group(function (): void {
            Route::get('sensors', [App\Api\Controllers\LegacyApiController::class, 'list_sensors'])->name('list_sensors');
        });
        Route::middleware('can:viewAny,App\Models\Vlan')->group(function (): void {
            Route::get('vlans', [App\Api\Controllers\LegacyApiController::class, 'list_vlans'])->name('list_vlans');
        });
        Route::middleware('can:viewAny,App\Models\Location')->group(function (): void {
            Route::get('locations', [App\Api\Controllers\LegacyApiController::class, 'list_locations'])->name('list_locations');
        });
    });

    // Inventory
    Route::middleware('can:viewAny,App\Models\Device')->group(function (): void {
        Route::get('inventory/{hostname}', [App\Api\Controllers\LegacyApiController::class, 'get_inventory'])->name('get_inventory');
        Route::get('inventory/{hostname}/all', [App\Api\Controllers\LegacyApiController::class, 'get_inventory_for_device'])->name('get_inventory_for_device');
    });

    // Port Security
    Route::prefix('port_security')->middleware('can:viewAny,App\Models\Port')->group(function (): void {
        Route::get('port/{portid}', [App\Api\Controllers\LegacyApiController::class, 'get_port_security'])->name('get_port_security_by_port');
        Route::get('device/{hostname}', [App\Api\Controllers\LegacyApiController::class, 'get_port_security'])->name('get_port_security_by_hostname');
        Route::get('', [App\Api\Controllers\LegacyApiController::class, 'get_port_security'])->name('get_port_security');
    });

    // Locations
    Route::post('locations', [App\Api\Controllers\LegacyApiController::class, 'add_location'])->name('add_location')->middleware('can:create,App\Models\Location');
    Route::get('location/{location_id_or_name}', [App\Api\Controllers\LegacyApiController::class, 'get_location'])->name('get_location')->middleware('can:viewAny,App\Models\Location');
    Route::patch('locations/{location_id_or_name}', [App\Api\Controllers\LegacyApiController::class, 'edit_location'])->name('edit_location')->middleware('can:update,App\Models\Location');
    Route::delete('locations/{location}', [App\Api\Controllers\LegacyApiController::class, 'del_location'])->name('del_location')->middleware('can:delete,App\Models\Location');
    Route::post('locations/{location}/maintenance', [App\Api\Controllers\LegacyApiController::class, 'maintenance_location'])->name('maintenance_location')->middleware('can:update,App\Models\Location');

    // Telequill: device configuration API (KunalApiController), Template Push and the chatbot.
    // Admin only, as before the upstream merge.
    Route::middleware(['can:admin'])->group(function (): void {
        Route::get('templates', [App\Api\Controllers\TemplatePushApiController::class, 'index'])->name('templates.list');
        Route::post('templates/push', [App\Api\Controllers\TemplatePushApiController::class, 'push'])->name('templates.push');
        Route::get('templates/{name}', [App\Api\Controllers\TemplatePushApiController::class, 'show'])->name('templates.show');
        Route::post('testapi', [App\Api\Controllers\KunalApiController::class, 'testFunction'])->name('testFunction');
        Route::get('systeminfo/{hostname}', [App\Api\Controllers\KunalApiController::class, 'systeminfo'])->name('systeminfo');
        Route::get('gethostname/{hostname}', [App\Api\Controllers\KunalApiController::class, 'gethostname'])->name('gethostname');
        Route::post('changehostname/{hostname}', [App\Api\Controllers\KunalApiController::class, 'changehostname']);
        Route::get('getvlan/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getvlan'])->name('getvlan');
        Route::post('addvlan/{hostname}', [App\Api\Controllers\KunalApiController::class, 'addvlan']);
        Route::get('job-status/{jobId}', [App\Api\Controllers\KunalApiController::class, 'jobStatus']);
        Route::post('devicereboot/{hostname}', [App\Api\Controllers\KunalApiController::class, 'devicereboot']);
        Route::post('vlan/batch/{hostname}', [App\Api\Controllers\KunalApiController::class, 'addvlanbatch']);
        Route::get('vlan/interface/{hostname}', [App\Api\Controllers\KunalApiController::class, 'showvlaninterface']);
        Route::post('changemtu/{hostname}', [App\Api\Controllers\KunalApiController::class, 'changemtu']);
        Route::get('getmtu/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getmtu'])->name('getmtu');
        Route::get('getlldp/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getlldp'])->name('getlldp');
        Route::post('changelldp/{hostname}', [App\Api\Controllers\KunalApiController::class, 'changelldp']);
        Route::get('getlldpinterface/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getlldpinterface'])->name('getlldpinterface');
        Route::post('lldp/interface/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setlldpinterface'])->name('lldp.interface.set');
        Route::get('portconfig/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getportconfiguration'])->name('portconfig.show');
        Route::post('portconfig/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setportconfiguration'])->name('portconfig.set');
        Route::get('ratelimit/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getratelimit'])->name('ratelimit.show');
        Route::post('ratelimit/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setratelimit'])->name('ratelimit.set');
        Route::get('gvrp/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getgvrp'])->name('gvrp.show');
        Route::post('gvrp/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setgvrp'])->name('gvrp.set');
        Route::get('gvrp/global/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getgvrpglobal'])->name('gvrp.global.show');
        Route::post('gvrp/global/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setgvrpglobal'])->name('gvrp.global.set');
        Route::get('pdp/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getpdp'])->name('pdp.show');
        Route::post('pdp/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setpdp'])->name('pdp.set');
        Route::get('pdp/interface/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getpdpinterface'])->name('pdp.interface.show');
        Route::post('pdp/interface/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setpdpinterface'])->name('pdp.interface.set');
        Route::get('mqtt/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getmqtt'])->name('mqtt.show');
        Route::post('mqtt/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setmqtt'])->name('mqtt.set');
        Route::get('ddm/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getddm'])->name('ddm.show');
        Route::post('ddm/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setddm'])->name('ddm.set');
        Route::get('igmp/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getigmpsnooping'])->name('igmp.show');
        Route::post('igmp/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setigmpsnooping'])->name('igmp.set');
        Route::get('igmp/vlan/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getigmpsnoopingvlan'])->name('igmp.vlan.show');
        Route::post('igmp/vlan/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setigmpsnoopingvlan'])->name('igmp.vlan.set');
        Route::get('igmp/multicast/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getigmpmulticastgroups'])->name('igmp.multicast.show');
        Route::post('igmp/multicast/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setigmpmulticastaddress'])->name('igmp.multicast.set');
        Route::get('igmp/filter/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getigmpvlanfilters'])->name('igmp.filter.show');
        Route::post('igmp/filter/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setigmpvlanfilter'])->name('igmp.filter.set');
        Route::post('igmp/filter/delete/{hostname}', [App\Api\Controllers\KunalApiController::class, 'deleteigmpvlanfilter'])->name('igmp.filter.delete');
        Route::get('privatevlan/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getprivatevlan'])->name('privatevlan.show');
        Route::post('privatevlan/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setprivatevlan'])->name('privatevlan.set');
        Route::get('privatevlan/port/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getprivatevlanport'])->name('privatevlan.port.show');
        Route::post('privatevlan/port/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setprivatevlanport'])->name('privatevlan.port.set');
        Route::get('dhcpsnooping/global/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getdhcpglobal'])->name('dhcpsnooping.global.show');
        Route::post('dhcpsnooping/global/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setdhcpglobal'])->name('dhcpsnooping.global.set');
        Route::get('dhcpsnooping/vlan/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getdhcpvlan'])->name('dhcpsnooping.vlan.show');
        Route::post('dhcpsnooping/vlan/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setdhcpvlan'])->name('dhcpsnooping.vlan.set');
        Route::get('dhcpsnooping/interface/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getdhcpinterface'])->name('dhcpsnooping.interface.show');
        Route::post('dhcpsnooping/interface/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setdhcpinterface'])->name('dhcpsnooping.interface.set');
        Route::get('dhcpsnooping/option82/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getdhcpoption82'])->name('dhcpsnooping.option82.show');
        Route::post('dhcpsnooping/option82/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setdhcpoption82'])->name('dhcpsnooping.option82.set');
        Route::get('dhcpsnooping/binding/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getdhcpbinding'])->name('dhcpsnooping.binding.show');
        Route::get('dhcpsnooping/binding/applied/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getdhcpbindingapplied'])->name('dhcpsnooping.binding.applied.show');
        Route::post('dhcpsnooping/binding/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setdhcpbinding'])->name('dhcpsnooping.binding.set');
        Route::get('stp/global/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getstpglobal'])->name('stp.global.show');
        Route::post('stp/global/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setstpglobal'])->name('stp.global.set');
        Route::get('stp/mst/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getstpmst'])->name('stp.mst.show');
        Route::post('stp/mst/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setstpmst'])->name('stp.mst.set');
        Route::get('stp/port/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getstpport'])->name('stp.port.show');
        Route::post('stp/port/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setstpport'])->name('stp.port.set');
        Route::get('stp/portguard/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getstpportguard'])->name('stp.portguard.show');
        Route::post('stp/portguard/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setstpportguard'])->name('stp.portguard.set');
        Route::get('mld/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getmldsnooping'])->name('mld.show');
        Route::post('mld/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setmldsnooping'])->name('mld.set');
        Route::get('erps/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'geterps'])->name('erps.show');
        Route::post('erps/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'seterps'])->name('erps.set');
        Route::get('etherring/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getethernetring'])->name('etherring.show');
        Route::post('etherring/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setethernetring'])->name('etherring.set');
        Route::get('multiplering/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getmultiplering'])->name('multiplering.show');
        Route::post('multiplering/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setmultiplering'])->name('multiplering.set');
        Route::get('vlan/interface/attribute/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getinterfacevlanattribute'])->name('vlan.interface.attribute.show');
        Route::post('vlan/interface/attribute/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setinterfacevlanattribute'])->name('vlan.interface.attribute.set');
        Route::post('vlan/edit/{hostname}', [App\Api\Controllers\KunalApiController::class, 'editvlanconfiguration'])->name('vlan.edit');
        Route::get('vlan/voice/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getvoicevlan'])->name('vlan.voice.show');
        Route::post('vlan/voice/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setvoicevlan'])->name('vlan.voice.set');
        Route::get('vlan/interface/voice/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getinterfacevoicevlan'])->name('vlan.interface.voice.show');
        Route::post('vlan/interface/voice/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setinterfacevoicevlan'])->name('vlan.interface.voice.set');
        Route::post('vlandelete/{hostname}', [App\Api\Controllers\KunalApiController::class, 'vlandelete'])->name('vlandelete');
        Route::get('voicevlanshow/{hostname}', [App\Api\Controllers\KunalApiController::class, 'voicevlanshow'])->name('voicevlanshow');
        Route::post('voicevlan/batch/{hostname}', [App\Api\Controllers\KunalApiController::class, 'voicevlandelete'])->name('voicevlandelete');
        Route::get('arp/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'showbasicarp'])->name('arp.show');
        Route::post('arp/add/{hostname}', [App\Api\Controllers\KunalApiController::class, 'addbasicarp'])->name('arp.add');
        Route::post('arp/edit/{hostname}', [App\Api\Controllers\KunalApiController::class, 'editbasicarp'])->name('arp.edit');
        Route::post('arp/delete/{hostname}', [App\Api\Controllers\KunalApiController::class, 'deletebasicarp'])->name('arp.delete');
        Route::get('backuplink/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getbackuplink'])->name('backuplink.show');
        Route::post('backuplink/set/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setbackuplink'])->name('backuplink.set');
        Route::get('portchannel/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'getportaggregate'])->name('portchannel.show');
        Route::post('portchannel/add/{hostname}', [App\Api\Controllers\KunalApiController::class, 'addportaggregate'])->name('portchannel.add');
        Route::post('portchannel/edit/{hostname}', [App\Api\Controllers\KunalApiController::class, 'editportaggregate'])->name('portchannel.edit');
        Route::post('portchannel/delete/{hostname}', [App\Api\Controllers\KunalApiController::class, 'deleteportaggregate'])->name('portchannel.delete');
        Route::post('portchannel/loadbalance/{hostname}', [App\Api\Controllers\KunalApiController::class, 'setportchannelloadbalance'])->name('portchannel.loadbalance');
        Route::post('tftpupload/{hostname}', [App\Api\Controllers\KunalApiController::class, 'tftpupload'])->name('tftpupload');
        Route::post('tftpexport/{hostname}', [App\Api\Controllers\KunalApiController::class, 'tftpexport'])->name('tftpexport');
        Route::post('tftp/schedule', [App\Api\Controllers\KunalApiController::class, 'saveTftpSchedule'])->name('tftp.schedule');
        Route::post('vlan/configure/{hostname}', [App\Api\Controllers\KunalApiController::class, 'vlanconfigure'])->name('configure.vlan');
        Route::post('vlan/configure-trunk/{hostname}', [App\Api\Controllers\KunalApiController::class, 'vlanconfiguretrunk'])->name('vlan.configure.trunk');
        Route::post('network/interface/config/{hostname}', [App\Api\Controllers\KunalApiController::class, 'network_interface_config'])->name('network.interface.config');
        Route::get('ntp/{hostname}', [App\Api\Controllers\KunalApiController::class, 'ntp'])->name('ntp');
        Route::post('interface/reset/{hostname}', [App\Api\Controllers\KunalApiController::class, 'interfacereset'])->name('interfacereset');
        Route::post('cngportstatus/{hostname}', [App\Api\Controllers\KunalApiController::class, 'cngportstatus'])->name('cngportstatus');
        Route::post('network/cmd/config/{hostname}', [App\Api\Controllers\KunalApiController::class, 'network_cmd_config'])->name('network.cmd.config');
        Route::post('network/interface/show/{hostname}', [App\Api\Controllers\KunalApiController::class, 'network_interface_show'])->name('network.interface.show');
        Route::post('chatbot/message', [App\Http\Controllers\ChatBotController::class, 'message']);
    });

    // Route not found
    Route::any('/{path?}', [App\Api\Controllers\LegacyApiController::class, 'api_not_found'])->where('path', '.*');
});
