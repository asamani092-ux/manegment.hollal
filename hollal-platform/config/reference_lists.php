<?php

/**
 * Tables that store a specific reference_items.id (exact version row).
 * Keyed by reference list key.
 *
 * @return array{references: array<string, list<array{table: string, column: string}>>}
 */
return [
    'references' => [
        // Later commands register their columns, e.g. leave_requests.reference_item_id.
    ],
];
