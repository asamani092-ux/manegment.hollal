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
}
