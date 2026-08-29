<?php

namespace App\Models;

use App\Models\User;
use App\Support\OrgAdministration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 07-B1 — the reusable template library (نماذج جاهزة).
 */
class DocumentTemplate extends Model
{
    use SoftDeletes;

    public const VISIBILITY_ALL = 'all';

    public const VISIBILITY_DEPARTMENT = 'department';

    /** @var list<string> */
    protected $fillable = ['title', 'category', 'path', 'description', 'visibility', 'uploaded_by'];

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->visibility !== self::VISIBILITY_DEPARTMENT) {
            return true;
        }

        if ((int) $user->id === (int) $this->uploaded_by) {
            return true;
        }

        $uploader = $this->uploader;
        if (! $user->org_unit_id || ! $uploader?->org_unit_id) {
            return false;
        }

        return OrgAdministration::sameRoot((int) $user->org_unit_id, (int) $uploader->org_unit_id);
    }

    /**
     * @param  Builder<DocumentTemplate>  $query
     * @return Builder<DocumentTemplate>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', self::VISIBILITY_ALL)
                ->orWhere('uploaded_by', $user->id)
                ->orWhere(function (Builder $dept) use ($user) {
                    $dept->where('visibility', self::VISIBILITY_DEPARTMENT);
                    if (! $user->org_unit_id) {
                        $dept->whereRaw('0 = 1');

                        return;
                    }
                    $rootId = OrgAdministration::rootId((int) $user->org_unit_id);
                    if ($rootId === null) {
                        $dept->whereRaw('0 = 1');

                        return;
                    }
                    $unitIds = OrgAdministration::unitIdsUnderRoot($rootId);
                    $dept->whereHas('uploader', fn (Builder $u) => $u->whereIn('org_unit_id', $unitIds));
                });
        });
    }
}
