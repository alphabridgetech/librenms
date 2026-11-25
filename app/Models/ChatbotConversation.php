<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A saved TeleQuill AI chat. Only its owner can see or change it. */
class ChatbotConversation extends Model
{
    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class, 'conversation_id')->orderBy('id');
    }

    /** Chat title from the first question: one line, at most 60 characters. */
    public static function titleFrom(string $message): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $message));

        return mb_strlen($title) > 60 ? rtrim(mb_substr($title, 0, 57)) . '…' : ($title ?: 'New chat');
    }
}
