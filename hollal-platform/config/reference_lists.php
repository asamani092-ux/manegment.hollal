<?php

/**
 * Tables that store a specific reference_items.id (exact version row).
 * Keyed by reference list key.
 *
 * @return array{references: array<string, list<array{table: string, column: string}>>}
 */
return [
    'references' => [
        'leave_types' => [
            ['table' => 'leave_requests', 'column' => 'reference_item_id'],
        ],
        'violations' => [
            ['table' => 'violations', 'column' => 'reference_item_id'],
        ],
    ],
];
