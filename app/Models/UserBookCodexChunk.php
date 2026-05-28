<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBookCodexChunk extends Model
{
    public $timestamps = false;

    protected $fillable = ['book_id', 'chunk_index', 'chunk_text', 'is_processed'];
}
