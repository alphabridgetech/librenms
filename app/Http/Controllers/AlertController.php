<?php

namespace App\Http\Controllers;

use App\Facades\LibrenmsConfig;
use App\Models\Alert;
use App\Models\Eventlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LibreNMS\Enum\Severity;

class AlertController extends Controller
{
    public function ack(Request $request, Alert $alert): \Illuminate\Http\JsonResponse
    {
        $this->validate($request, [
            'state' => 'required|int',
            'ack_msg' => 'nullable|string',
            'ack_until_clear' => 'nullable|in:0,1,true,false',
        ]);

        $state = $request->get('state');
        $state_description = '';
        if ($state == 2) {
            $alert->state = 1;
            $state_description = 'UnAck';
            $alert->open = 1;
        } elseif ($state >= 1) {
            $alert->state = 2;
            $state_description = 'Ack';
            $alert->open = 1;
        }

        $info = $alert->info;
        $info['until_clear'] = filter_var($request->get('ack_until_clear'), FILTER_VALIDATE_BOOLEAN);
        $alert->info = $info;

        $timestamp = date(LibrenmsConfig::get('dateformat.long'));
        $username = $request->user()->username;
        $ack_msg = $request->get('ack_msg');
        $alert->note = trim($alert->note . PHP_EOL . "$timestamp - $state_description ($username) " . $ack_msg);

        if ($alert->save()) {
            $rule_name = $alert->rule->name;
            $act = strtolower($state_description) . 'nowledged';
            Eventlog::log("$username {$act} alert $rule_name note: $ack_msg", $alert->device_id, 'alert', Severity::Info, $alert->id);

            return response()->json([
                'message' => "Alert {$state_description}nowledged.",
                'status' => 'ok',
            ]);
        }

        return response()->json([
            'message' => 'Alert has not been acknowledged.',
            'status' => 'error',
        ]);
    }

    public function getAlerts(Request $request): \Illuminate\Http\JsonResponse
    {
        $where = "devices.disabled = 0 and alerts.rule_id > 0 and alerts.state != 0 and alerts.state!=2";

        $query = DB::table('alerts')
            ->leftJoin('devices', 'alerts.device_id', '=', 'devices.device_id')
            ->leftJoin('locations', 'devices.location_id', '=', 'locations.id')
            ->rightJoin('alert_rules', 'alerts.rule_id', '=', 'alert_rules.id')
            ->whereRaw($where);

        // // filter by device
        // if ($request->device_id) {
        //     $query->where('alerts.device_id', $request->device_id);
        // }

        // // filter by rule
        // if ($request->rule_id) {
        //     $query->where('alerts.rule_id', $request->rule_id);
        // }

        // // filter by state
        // if ($request->state !== null) {
        //     $query->where('alerts.state', $request->state);
        // }

        // // filter severity
        // if ($request->min_severity) {
        //     $query->where('alert_rules.severity', '>=', $request->min_severity);
        // }

        // dd($query->toSql(), $query->getBindings());

        $alerts = $query->select(
            'alerts.id',
            'alerts.state',
            'alerts.timestamp',
            'alerts.device_id',
            'devices.hostname',
            'devices.sysName',
            'locations.location',
            'alert_rules.name as rule_name',
            'alert_rules.severity',
            'alert_rules.builder'
        )
            ->orderBy('alerts.timestamp', 'DESC')
            ->limit(3)
            ->get();

        return response()->json([
            'total' => $alerts->count(),
            'alerts' => $alerts
        ]);


    }
}
