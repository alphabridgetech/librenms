<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplatePushValue extends Model
{
    protected $fillable = [
        'device_id',
        'template_name',
        'template_folder',
        'field_values',
        'port_mode',
        'pvid',
        'custom_commands',
        'selected_interfaces',
        'user_id',
    ];

    protected $casts = [
        'field_values' => 'array',
        'selected_interfaces' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public static function upsertFor(int $deviceId, string $templateName, ?string $folder, array $attrs): self
    {
        return static::updateOrCreate(
            [
                'device_id' => $deviceId,
                'template_name' => $templateName,
                'template_folder' => $folder ?? '',
            ],
            $attrs
        );
    }
}
