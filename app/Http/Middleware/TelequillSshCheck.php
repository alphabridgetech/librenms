<?php

namespace App\Http\Middleware;

use App\Services\SwitchSsh;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before a Telequill configuration API call runs a playbook, make sure the
 * switch accepts SSH. If not, answer at once with a clear reason (HTTP 503)
 * instead of running a playbook that fails with a long Ansible error.
 */
class TelequillSshCheck
{
    /** Endpoints whose playbooks connect over Telnet, not SSH. */
    private const TELNET_ROUTES = [
        'api/v0/addvlan/{hostname}',
        'api/v0/vlan/batch/{hostname}',
        'api/v0/vlandelete/{hostname}',
        'api/v0/network/interface/config/{hostname}',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->route('hostname');
        if ($host === null || in_array($request->route()->uri(), self::TELNET_ROUTES, true)) {
            return $next($request);
        }

        $check = SwitchSsh::check((string) $host);
        if ($check['ok']) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'code' => 'ssh_unavailable',
            'reason' => $check['reason'],
            'message' => SwitchSsh::message($check),
        ], 503);
    }
}
