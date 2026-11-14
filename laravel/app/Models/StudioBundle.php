<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A contributor's bundle: a named group of packs of one asset type. */
class StudioBundle extends Model
{
    protected $table = 'studio_bundles';

    protected $fillable = ['contributor_id', 'type_key', 'name'];

    public function packs(): HasMany
    {
        return $this->hasMany(Pack::class, 'bundle_id');
    }
}
