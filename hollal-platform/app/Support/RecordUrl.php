<?php

namespace App\Support;

/**
 * Deep links to a record, not the index. Time: O(1) | Space: O(1).
 */
final class RecordUrl
{
    public static function task(int $id): string
    {
        return route('tasks.index', ['open' => $id]);
    }

    public static function expense(int $id): string
    {
        return route('expenses.index', ['open' => $id]);
    }

    public static function custody(int $id): string
    {
        return route('custodies.index', ['open' => $id]);
    }

    public static function leave(int $id): string
    {
        return route('leaves.index', ['open' => $id]);
    }

    public static function partnership(int $id): string
    {
        return route('partnerships.show', $id);
    }
}
