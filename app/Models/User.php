<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role', 'joined_at');
    }

    public function getRoleInWorkspace(Workspace $workspace): ?string
    {
        // this user might not belong to the workspace hence why the null check
        return $this->workspaces()
            ->where('workspace_id', $workspace->id)
            ->first()
            ?->pivot
            ?->role;
    }

    public function isOwnerOfWorkspace(Workspace $workspace): bool
    {
        return $workspace->owner_id === $this->id;
    }

    public function canAccessWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->where('workspace_id', $workspace->id)->exists();
    }

    public function hasPermissionInWorkspace(string $permission, Workspace $workspace): bool
    {
        return PermissionService::userHasPermission($this, $permission, $workspace);
    }
}
