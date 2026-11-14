<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A course or guide listed in the contributor Learning Center. */
class LearningCourse extends Model
{
    protected $table = 'learning_courses';

    protected $fillable = ['section', 'title', 'author', 'url', 'thumbnail', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'bool'];
}
