<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One contributor's request for (and access to) one upload type. */
class ContributorTypeAccess extends Model
{
    protected $table = 'contributor_type_access';

    protected $fillable = [
        'contributor_id',
        'type_key',
        'status',
        'portfolio_url',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
