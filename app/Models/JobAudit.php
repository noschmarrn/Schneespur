<?php

namespace App\Models;

use Database\Factories\JobAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAudit extends Model
{
    /** @use HasFactory<JobAuditFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'job_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('JobAudit records are insert-only and cannot be updated.');
        }

        return parent::save($options);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
