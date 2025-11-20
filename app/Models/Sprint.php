<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'status',
        'start_date',
        'end_date',
        'is_eternal',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_eternal' => 'boolean',
    ];

    /**
     * A sprint belongs to a workspace
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * A sprint has many tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Check if sprint is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if sprint is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if sprint is eternal (simple kanban mode)
     */
    public function isEternal(): bool
    {
        return $this->is_eternal;
    }

    /**
     * Check if sprint is planned
     */
    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    /**
     * Get days remaining in sprint
     */
    public function daysRemaining(): ?int
    {
        if ($this->is_eternal || !$this->end_date) {
            return null;
        }

        $now = Carbon::now();
        $endDate = Carbon::parse($this->end_date);

        if ($now->greaterThan($endDate)) {
            return 0;
        }

        return $now->diffInDays($endDate);
    }

    /**
     * Get days elapsed in sprint
     */
    public function daysElapsed(): ?int
    {
        if ($this->is_eternal || !$this->start_date) {
            return null;
        }

        $now = Carbon::now();
        $startDate = Carbon::parse($this->start_date);

        if ($now->lessThan($startDate)) {
            return 0;
        }

        return $startDate->diffInDays($now);
    }

    /**
     * Get sprint duration in days
     */
    public function duration(): ?int
    {
        if ($this->is_eternal || !$this->start_date || !$this->end_date) {
            return null;
        }

        return Carbon::parse($this->start_date)->diffInDays($this->end_date);
    }

    /**
     * Generate next sprint for a workspace
     *
     * @param Workspace $workspace
     * @param string $status
     * @return Sprint
     */
    public static function generateNext(Workspace $workspace, string $status = 'planned'): Sprint
    {
        $duration = match ($workspace->sprint_duration) {
            'weekly' => 7,
            'biweekly' => 14,
            default => 7,
        };

        // Create new sprint
        return Sprint::create([
            'workspace_id' => $workspace->id,
            'name' => "New Sprint",
            'status' => $status,
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays($duration),
            'is_eternal' => false,
        ]);
    }

    /**
     * Create eternal sprint for simple kanban mode
     *
     * @param Workspace $workspace
     * @param string $status
     * @return Sprint
     */
    public static function createEternal(Workspace $workspace, string $status = 'planned'): Sprint
    {
        return Sprint::create([
            'workspace_id' => $workspace->id,
            'name' => 'Ongoing',
            'status' => $status,
            'start_date' => null,
            'end_date' => null,
            'is_eternal' => true,
        ]);
    }

    /**
     * Complete this sprint and generate next one
     *
     * @return Sprint The next sprint
     */
    public function complete(): Sprint
    {
        $this->update(['status' => 'completed']);

        return self::generateNext($this->workspace);
    }
}
