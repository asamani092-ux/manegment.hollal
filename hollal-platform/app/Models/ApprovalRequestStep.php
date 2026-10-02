<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequestStep extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'approval_request_id', 'step_index', 'definition', 'status',
        'acted_by', 'acted_on_behalf_of', 'acted_at', 'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'acted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
