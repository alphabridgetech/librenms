<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a saved TeleQuill AI chat. */
class ChatbotMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'role', 'type', 'content'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id');
    }
}
