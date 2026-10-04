<?php

namespace App\Services;

use App\Models\EmployeeDocument;
use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Required-document matrix for one employee.
 * Time: O(types + documents) | Space: O(types).
 */
class DocumentRequirementService
{
    /**
     * @return list<array{code: string, name: string, state: string, color: string, document_id: ?int, reason: ?string}>
     */
    public function matrix(User $user): array
    {
        if (! Schema::hasTable('reference_lists') || ! ReferenceList::query()->where('key', 'document_types')->exists()) {
            return [];
        }

        $types = app(ReferenceListService::class)->activeItems('document_types');
        $documents = EmployeeDocument::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $rows = [];
        foreach ($types as $type) {
            $rows[] = $this->row($type, $documents->first(fn (EmployeeDocument $doc) => $doc->type === $type->name_ar || $doc->type === $type->code));
        }

        return $rows;
    }

    public function attentionCount(User $user): int
    {
        return collect($this->matrix($user))
            ->whereIn('color', ['red', 'yellow'])
            ->count();
    }

    /**
     * @return array{code: string, name: string, state: string, color: string, document_id: ?int, reason: ?string}
     */
    private function row(ReferenceItem $type, ?EmployeeDocument $document): array
    {
        $required = ($type->attributes['required_for'] ?? 'optional') === 'all';
        $notice = (int) ($type->attributes['renewal_notice_days'] ?? 30);
        $base = [
            'code' => $type->code,
            'name' => $type->name_ar,
            'document_id' => $document?->id,
            'reason' => $document?->rejection_reason,
        ];

        if (! $document) {
            return $base + [
                'state' => $required ? 'missing' : 'optional',
                'color' => $required ? 'red' : 'grey',
            ];
        }
        if ($document->status === 'pending_review') {
            return $base + ['state' => 'pending_review', 'color' => 'yellow'];
        }
        if ($document->status === 'rejected') {
            return $base + ['state' => 'rejected', 'color' => 'red'];
        }
        if ($document->status === 'expired' || $document->isExpired()) {
            return $base + ['state' => 'expired', 'color' => 'red'];
        }
        if ($document->isExpiringSoon($notice)) {
            return $base + ['state' => 'expiring', 'color' => 'yellow'];
        }
        if ($document->status === 'approved') {
            return $base + ['state' => 'approved', 'color' => 'green'];
        }

        return $base + [
            'state' => $required ? 'missing' : 'optional',
            'color' => $required ? 'red' : 'grey',
        ];
    }
}
