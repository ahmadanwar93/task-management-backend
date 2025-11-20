<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['sprint_enabled' => 'boolean'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        // we have to give an explicit workspace_user because laravel by default named pivot table alphabetically, hence user_workspace
        // that is why table not found
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role', 'joined_at');
    }

    public static function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(10);
        } while (self::where('slug', $slug)->exists());

        return $slug;
    }

    public function addMember(User $user, string $role): void
    {
        $this->users()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    public function removeMember(User $user): void
    {
        $this->users()->detach($user->id);
    }

    public function hasMember(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    public function activeSprint()
    {
        return $this->hasOne(Sprint::class)->where('status', 'active');
    }

    public function initializeSprint(): void
    {
        if ($this->sprint_enabled) {
            Sprint::generateNext($this, 'active');
        } else {
            Sprint::createEternal($this, 'active');
        }
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
