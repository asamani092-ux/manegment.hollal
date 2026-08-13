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

    public static function project(int $id): string
    {
        return route('projects.show', $id);
    }

    public static function meeting(int $id): string
    {
        return route('meetings.index', ['open' => $id]);
    }

    public static function payrollRun(?int $id = null): string
    {
        return $id === null
            ? route('payroll-runs.index')
            : route('payroll-runs.index', ['open' => $id]);
    }

    public static function contract(int $id): string
    {
        return route('contracts.index', ['open' => $id]);
    }

    public static function document(int $id): string
    {
        return route('documents.index', ['open' => $id]);
    }

    public static function revenue(int $id): string
    {
        return route('revenues.index', ['open' => $id]);
    }
}
