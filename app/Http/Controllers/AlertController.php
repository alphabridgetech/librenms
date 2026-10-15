<?php

namespace App\Http\Controllers;

use App\Facades\LibrenmsConfig;
use App\Models\Alert;
use App\Models\AlertFault;
use App\Models\Eventlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LibreNMS\Alert\AlertRules;
use LibreNMS\Alert\AlertUtil;
use LibreNMS\Enum\AlertState;
use LibreNMS\Enum\Severity;

class AlertController extends Controller
{
    public function ack(Request $request, AlertFault $fault): \Illuminate\Http\JsonResponse
    {
        $alert = Alert::query()->where('rule_id', $fault->rule_id)->where('device_id', $fault->device_id)->first();
        if ($alert) {
            $this->authorize('update', $alert);
        }

        $this->validate($request, [
            'state' => 'required|int',
            'ack_msg' => 'nullable|string',
            'ack_until_clear' => 'nullable|in:0,1,true,false',
        ]);

        $state = $request->input('state');
        $state_description = '';
        $newState = null;
        if ($state == AlertState::ACKNOWLEDGED) {
            $newState = AlertState::ACTIVE;
            $state_description = 'UnAck';
        } elseif ($state >= AlertState::ACTIVE) {
            $newState = AlertState::ACKNOWLEDGED;
            $state_description = 'Ack';
        }

        if ($newState === null) {
            return response()->json([
                'message' => 'Fault has not been acknowledged.',
                'status' => 'error',
            ]);
        }

        $untilClear = filter_var($request->input('ack_until_clear'), FILTER_VALIDATE_BOOLEAN);
        $timestamp = date(LibrenmsConfig::get('dateformat.long'));
        $username = $request->user()->username;
        $ack_msg = $request->input('ack_msg');
        $note_suffix = "$timestamp - $state_description ($username) " . $ack_msg;

        $fault->loadMissing('rule');
        $rule = $fault->rule;
        $openCount = $rule !== null
            ? AlertUtil::openEntityCountForRuleDevice($rule->id, (int) $fault->device_id)
            : 1;
        $targets = ($rule !== null && AlertUtil::shouldNotifyPerEntity($rule, $openCount))
            ? collect([$fault])
            : AlertFault::query()->where('rule_id', $fault->rule_id)->where('device_id', $fault->device_id)->where('open', 1)->get();

        $saved = false;
        foreach ($targets as $target) {
            $target->state = $newState;
            $target->open = 1;
            $info = $target->info ?: [];
            $info['until_clear'] = $untilClear;
            $target->info = $info;
            $target->note = trim($target->note . PHP_EOL . $note_suffix);
            $saved = $target->save() || $saved;
        }

        if ($saved) {
            $fault->loadMissing('rule');
            if ($fault->rule) {
                (new AlertRules($fault->device_id))->syncAlertState($fault->rule);
            }

            $rule_name = $fault->rule?->name;
            $act = strtolower($state_description) . 'nowledged';
            Eventlog::log("$username {$act} alert $rule_name note: $ack_msg", $fault->device_id, 'alert', Severity::Info, $fault->id);

            return response()->json([
                'message' => "Fault {$state_description}nowledged.",
                'status' => 'ok',
            ]);
        }

        return response()->json([
            'message' => 'Fault has not been acknowledged.',
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
