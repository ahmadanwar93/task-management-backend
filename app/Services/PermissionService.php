<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class PermissionService
{
    // Middleware and permission might be an overkill at this point
    private const ROLE_PERMISSIONS = [
        'owner' => [
            'manage_workspace',
            'manage_members',
            'manage_sprints',
            'create_tasks',
            'edit_all_tasks',
            'edit_own_tasks',
            'delete_tasks',
            'comment_tasks',
            'view_analytics',
        ],
        'guest' => [
            'edit_own_tasks',
            'comment_tasks',
            'view_analytics',
        ],
    ];

    public static function getPermissionsForRole(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    public static function roleHasPermission(string $role, string $permission): bool
    {
        $permissions = self::getPermissionsForRole($role);
        return in_array($permission, $permissions);
    }

    public static function userHasPermission(User $user, string $permission, Workspace $workspace): bool
    {
        // Get the role first
        $role = $user->getRoleInWorkspace($workspace);

        if (!$role) {
            return false;
        }

        // Check if role has permission
        return self::roleHasPermission($role, $permission);
    }

    // public static function getAllPermissions(): array
    // {
    //     return array_unique(array_merge(
    //         self::ROLE_PERMISSIONS['owner'],
    //         self::ROLE_PERMISSIONS['guest']
    //     ));
    // }

    // public static function getAllRoles(): array
    // {
    //     return array_keys(self::ROLE_PERMISSIONS);
    // }

    // public static function permissionExists(string $permission): bool
    // {
    //     return in_array($permission, self::getAllPermissions());
    // }

    // public static function roleExists(string $role): bool
    // {
    //     return array_key_exists($role, self::ROLE_PERMISSIONS);
    // }
}
