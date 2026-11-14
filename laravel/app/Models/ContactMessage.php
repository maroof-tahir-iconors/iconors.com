<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One submission of the public contact form.
 *
 * Stored BEFORE any email is attempted, so a mail outage can delay a
 * message but never lose it. `notified_at` records when support was
 * actually emailed; a null there is what the admin "Messages" screen
 * surfaces as "not emailed".
 */
class ContactMessage extends Model
{
    public const TOPICS = [
        'general'     => 'General question',
        'support'     => 'Support request',
        'feedback'    => 'Feedback or suggestion',
        'tools'       => 'Free tools',
        'licensing'   => 'Licensing or billing',
        'contributor' => 'Contributing to Iconors',
        'careers'     => 'Careers',
        'report'      => 'Report a problem or copyright concern',
    ];

    public const STATUSES = ['new', 'read', 'replied', 'spam'];

    protected $fillable = [
        'reference', 'user_id', 'name', 'email', 'topic', 'subject', 'message',
        'context', 'ip_address', 'user_agent', 'status',
        'notified_at', 'confirmed_at', 'mail_error',
    ];

    protected $casts = [
        'notified_at'  => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? 'General question';
    }
}
