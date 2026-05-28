<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TmGenerationJob extends Model
{
    protected $table = 'tm_generation_jobs';

    protected $fillable = ['book_id', 'status', 'total_blocks', 'processed_blocks', 'error_message'];
}
