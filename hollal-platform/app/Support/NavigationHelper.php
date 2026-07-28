<?php

namespace App\Support;

class NavigationHelper
{
    /**
     * @return list<array{label: string, route: string, icon: string, permission: string}>
     */
    public static function allItems(): array
    {
        $nav = config('navigation');

        return array_merge(
            $nav['top'] ?? [],
            $nav['primary'] ?? [],
            $nav['secondary'] ?? [],
        );
    }

    /**
     * @return list<string>
     */
    public static function allRoutes(): array
    {
        return collect(self::allItems())->pluck('route')->values()->all();
    }

    /** Supports pipe-separated OR permissions (matches route middleware). */
    public static function userCanSee(string $permission): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (! str_contains($permission, '|')) {
            return $user->can($permission);
        }

        foreach (explode('|', $permission) as $single) {
            if ($user->can(trim($single))) {
                return true;
            }
        }

        return false;
    }
}
