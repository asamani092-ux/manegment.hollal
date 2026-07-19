<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialDutiesDocument extends Model
{
    /** @var list<string> */
    protected $fillable = ['version', 'file_path', 'published_at', 'published_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The latest published version, if any.
     */
    public static function latestPublished(): ?self
    {
        return static::query()
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
