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
        $items = array_merge($nav['top'] ?? [], $nav['primary'] ?? [], $nav['secondary'] ?? []);

        foreach ($nav['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $items[] = $item;
            }
        }

        return array_values(array_filter($items, fn (array $item) => self::itemIsVisible($item)));
    }

    /** Hide UAT-only entries when the pre-production flag is off. */
    public static function itemIsVisible(array $item): bool
    {
        if (! empty($item['uat_only']) && ! config('uat_tools.enabled')) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function allRoutes(): array
    {
        return collect(self::allItems())->pluck('route')->unique()->values()->all();
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

    /** True if the user can see at least one item in the group. */
    public static function userCanSeeGroup(array $group): bool
    {
        foreach ($group['items'] ?? [] as $item) {
            if (self::userCanSee($item['permission'] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
