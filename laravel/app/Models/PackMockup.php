<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pack cover / mockup — a static image, or a short video for animations. */
class PackMockup extends Model
{
    protected $table = 'pack_mockups';

    protected $fillable = ['draft_id', 'pack_id', 'disk', 'path', 'mime', 'size_bytes', 'width', 'height', 'sort_order'];

    public function url(): string
    {
        return \Illuminate\Support\Facades\Storage::disk($this->disk)->url($this->path);
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }
}
