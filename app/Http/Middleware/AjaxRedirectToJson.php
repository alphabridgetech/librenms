<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controllers in this app signal success/failure via redirect()->with('success'|'error', $message).
 * When the request came in via AJAX, turn that redirect into a small JSON payload instead of
 * following it, so pages built on this convention (e.g. Backup Management) can submit forms
 * without a full page reload/navigation.
 */
class AjaxRedirectToJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof RedirectResponse && $request->ajax()) {
            if ($request->session()->has('success')) {
                return response()->json([
                    'status' => 'success',
                    'message' => $request->session()->get('success'),
                ]);
            }

            if ($request->session()->has('error')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $request->session()->get('error'),
                ]);
            }
        }

        return $response;
    }
}
