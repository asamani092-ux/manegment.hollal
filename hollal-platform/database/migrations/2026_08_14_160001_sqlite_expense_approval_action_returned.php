<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite DBs created before "returned" was added still CHECK action in (approved, rejected).
 * MySQL was handled in 2026_08_13_200001. Time: O(n) rows | Space: O(n).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='expense_approval_logs'");
        $sql = (string) ($row->sql ?? '');
        if ($sql === '' || str_contains($sql, "'returned'")) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        DB::statement('ALTER TABLE expense_approval_logs RENAME TO expense_approval_logs_old');

        DB::statement(<<<'SQL'
CREATE TABLE expense_approval_logs (
    id integer primary key autoincrement not null,
    expense_request_id integer not null,
    stage varchar not null,
    approver_id integer not null,
    action varchar check ("action" in ('approved', 'rejected', 'returned')) not null,
    notes text,
    acted_at datetime not null,
    created_at datetime,
    updated_at datetime,
    foreign key("expense_request_id") references "expense_requests"("id") on delete cascade,
    foreign key("approver_id") references "users"("id") on delete cascade
)
SQL);

        DB::statement(<<<'SQL'
INSERT INTO expense_approval_logs (id, expense_request_id, stage, approver_id, action, notes, acted_at, created_at, updated_at)
SELECT id, expense_request_id, stage, approver_id, action, notes, acted_at, created_at, updated_at
FROM expense_approval_logs_old
SQL);

        DB::statement('DROP TABLE expense_approval_logs_old');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible on SQLite without data loss risk; leave as-is.
    }
};
