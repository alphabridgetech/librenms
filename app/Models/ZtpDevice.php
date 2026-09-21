<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZtpDevice extends Model
{
    protected $table = 'ztp_devices';

    protected $fillable = [
        'mac_address',
        'device_name',
        'ip_address',
        'subnet_mask',
        'gateway',
        'snmp_community',
        'template_name',
        'template_folder',
        'template_commands',
        'status',
        'last_seen_at',
        'provisioned_at',
        'notes',
    ];

    protected $casts = [
        'last_seen_at'   => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    /**
     * Normalize a MAC address to lowercase colon-separated format.
     */
    public static function normalizeMac(string $mac): string
    {
        // Remove all non-hex characters
        $hex = preg_replace('/[^a-fA-F0-9]/', '', $mac);
        // Format as aa:bb:cc:dd:ee:ff
        return implode(':', str_split(strtolower($hex), 2));
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProvisioned($query)
    {
        return $query->where('status', 'provisioned');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Helpers

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending'     => 'warning',
            'provisioned' => 'success',
            'failed'      => 'danger',
            default       => 'default',
        };
    }
}
