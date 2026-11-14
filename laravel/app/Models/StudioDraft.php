<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One upload session in the Contributor Studio — becomes a pack on Submit. */
class StudioDraft extends Model
{
    protected $table = 'studio_drafts';

    protected $fillable = [
        'contributor_id', 'type_key', 'pack_title', 'description', 'is_premium', 'price',
        'license_id', 'bundle_id', 'category_id', 'preview_asset_ids', 'status', 'pack_id', 'submitted_at',
    ];

    protected $casts = [
        'is_premium' => 'bool',
        'price' => 'decimal:2',
        'preview_asset_ids' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    public function mockups(): HasMany
    {
        return $this->hasMany(PackMockup::class, 'draft_id')->orderBy('sort_order')->orderBy('id');
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(StudioBundle::class, 'bundle_id');
    }

    /** Draft assets uploaded into this session. */
    public function assetsQuery()
    {
        return Asset::where('contributor_id', $this->contributor_id)
            ->where('status', 'draft')
            ->where('metadata->studio_draft_id', $this->id);
    }
}
