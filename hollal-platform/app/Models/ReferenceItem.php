<?php

namespace App\Models;

use App\Models\Concerns\HasReferenceItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferenceItem extends Model
{
    use HasReferenceItem;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_ARCHIVED = 'archived';

    /** @var list<string> */
    protected $fillable = [
        'reference_list_id', 'code', 'name_ar', 'attributes', 'sort_order',
        'status', 'version', 'effective_from', 'effective_to', 'supersedes_id', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'sort_order' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<ReferenceList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ReferenceList::class, 'reference_list_id');
    }

    /** @return BelongsTo<self, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasMany<self, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
