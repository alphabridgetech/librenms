<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * API token for Telequill's own pages (device Configuration tabs, Template Push, chatbot),
 * which call /api/v0 from the browser with "Authorization: Bearer <token>".
 *
 * LibreNMS used to keep API tokens in plain text (api_tokens.token_hash) and these pages read the
 * user's token from there. Since the upstream Sanctum migration only a hash is stored, so instead
 * a dedicated Sanctum token is issued per user, kept in their session and reused while it exists.
 */
class TelequillUiToken
{
    private const NAME = 'telequill-ui';
    private const SESSION_KEY = 'telequill_ui_api_token';
    private const PRUNE_AFTER_DAYS = 7;

    public static function forCurrentUser(): ?string
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        // reuse the token from this session while it still exists
        $cached = Session::get(self::SESSION_KEY);
        if (is_string($cached) && str_contains($cached, '|')) {
            [$id] = explode('|', $cached, 2);
            if ($user->tokens()->where('name', self::NAME)->whereKey($id)->exists()) {
                return $cached;
            }
        }

        // drop this user's UI tokens that haven't been used for a while (old sessions)
        $user->tokens()
            ->where('name', self::NAME)
            ->where(fn ($q) => $q->where('last_used_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS))
                ->orWhere(fn ($q) => $q->whereNull('last_used_at')->where('created_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS))))
            ->delete();

        $token = $user->createToken(self::NAME)->plainTextToken;
        Session::put(self::SESSION_KEY, $token);

        return $token;
    }
}
