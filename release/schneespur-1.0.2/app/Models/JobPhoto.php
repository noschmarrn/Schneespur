<?php

namespace App\Models;

use Database\Factories\JobPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPhoto extends Model
{
    /** @use HasFactory<JobPhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'job_id',
        'file_path',
        'thumbnail_path',
        'annotated_path',
        'caption',
        'taken_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
