<?php

namespace App\Http\Middleware;

use App\Facades\Permissions;
use App\Models\Device;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Access rule for the Telequill device configuration API (/api/v0/...):
 *  - admins may configure every device;
 *  - any other user only the devices explicitly granted to them (device or
 *    device-group permissions). Global read is a read-only role and does not
 *    count as permission to change configuration;
 *  - routes without a {hostname} device in the URL stay admin-only.
 */
class TelequillDeviceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        $host = $request->route('hostname');
        if (! $user || $host === null) {
            return $this->deny('Admin access is required for this action.');
        }

        $device = self::findDevice((string) $host);
        if (! $device || ! self::canConfigure($user, $device)) {
            return $this->deny("You don't have permission to configure this device.");
        }

        return $next($request);
    }

    /** Admins: every device. Others: only devices granted to them. */
    public static function canConfigure(User $user, Device $device): bool
    {
        return $user->isAdmin() || Permissions::canAccessDevice($device, $user);
    }

    /** The Telequill routes identify the switch by its hostname / IP. */
    public static function findDevice(string $host): ?Device
    {
        return Device::where('hostname', $host)->first()
            ?? Device::where('overwrite_ip', $host)->first();
    }

    private function deny(string $message): Response
    {
        return response()->json(['status' => 'error', 'message' => $message], 403);
    }
}
