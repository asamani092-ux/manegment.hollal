<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 06A-B1 — program file (book, teacher guide, package). Stored on the private
 * disk; served only through a permission-checked download route.
 */
class ProgramFile extends Model
{
    use SoftDeletes;

    public const KIND_BOOK = 'كتاب';

    public const KIND_TEACHER_GUIDE = 'دليل المعلم';

    public const KIND_PACKAGE = 'حقيبة';

    public const KIND_OTHER = 'أخرى';

    /** @var list<string> */
    public const KINDS = [self::KIND_BOOK, self::KIND_TEACHER_GUIDE, self::KIND_PACKAGE, self::KIND_OTHER];

    /** @var list<string> */
    protected $fillable = ['program_id', 'title', 'kind', 'path', 'uploaded_by'];

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
