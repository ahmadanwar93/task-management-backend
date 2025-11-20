<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'sprint_id',
        'title',
        'description',
        'status',
        'due_date',
        'assigned_to',
        'notes',
        'order',
        'created_by',
        'completed_at',
        'calendar_event_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'done';
    }

    public function isInBacklog(): bool
    {
        return $this->status === 'backlog' || $this->sprint_id === null;
    }

    public function isOverdue(): bool
    {
        if (!$this->due_date || $this->status === 'done') {
            return false;
        }

        return now()->gt($this->due_date);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }


    public function markAsIncomplete(): void
    {
        $this->update([
            'completed_at' => null,
        ]);
    }

    public function moveToSprint(?int $sprintId): void
    {
        $this->update(['sprint_id' => $sprintId]);
    }

    public function moveToBacklog(): void
    {
        $this->update([
            'sprint_id' => null,
            'status' => 'backlog',
        ]);
    }

    public function updateStatus(string $status): void
    {
        $updates = ['status' => $status];

        if ($status === 'done' && !$this->completed_at) {
            $updates['completed_at'] = now();
        }

        if ($status !== 'done' && $this->completed_at) {
            $updates['completed_at'] = null;
        }

        $this->update($updates);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
