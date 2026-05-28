<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    protected $fillable = [
        'book_id',
        'title',
        'source_content',
        'target_content',
        'status',
        'chapter_order',
    ];

    public function book()
    {
        return $this->belongsTo(UserBook::class, 'book_id');
    }
}
