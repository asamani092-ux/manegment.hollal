<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_POSTED = 'مرحّل';

    /** @var list<string> */
    protected $fillable = [
        'number', 'entry_date', 'description', 'source_type', 'source_id',
        'status', 'is_automatic', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_automatic' => 'boolean',
        ];
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function debitTotal(): float
    {
        return round((float) $this->lines()->sum('debit'), 2);
    }

    public function creditTotal(): float
    {
        return round((float) $this->lines()->sum('credit'), 2);
    }

    public function isBalanced(): bool
    {
        return abs($this->debitTotal() - $this->creditTotal()) < 0.005;
    }
}
