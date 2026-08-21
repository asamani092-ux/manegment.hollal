<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\RevenueCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FIN-ACC-1 — chart of accounts tree management and category bridging.
 * Time: O(n) tree | Space: O(n)
 */
class ChartOfAccountsService
{
    /**
     * @return Collection<int, ChartOfAccount>
     */
    public function tree(): Collection
    {
        return ChartOfAccount::query()
            ->with(['children' => fn ($q) => $q->orderBy('code')->with('children')])
            ->roots()
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  array{code: string, name_ar: string, type: string, nature: string, parent_id?: int|null, is_active?: bool}  $data
     */
    public function create(array $data, ?User $actor = null): ChartOfAccount
    {
        $account = ChartOfAccount::create([
            'code' => trim($data['code']),
            'name_ar' => trim($data['name_ar']),
            'type' => $data['type'],
            'nature' => $data['nature'],
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit($actor, 'chart_of_accounts.created', $account);

        return $account;
    }

    /**
     * @param  array{code?: string, name_ar?: string, type?: string, nature?: string, parent_id?: int|null, is_active?: bool}  $data
     */
    public function update(ChartOfAccount $account, array $data, ?User $actor = null): ChartOfAccount
    {
        $account->fill([
            'code' => array_key_exists('code', $data) ? trim((string) $data['code']) : $account->code,
            'name_ar' => array_key_exists('name_ar', $data) ? trim((string) $data['name_ar']) : $account->name_ar,
            'type' => $data['type'] ?? $account->type,
            'nature' => $data['nature'] ?? $account->nature,
            'parent_id' => array_key_exists('parent_id', $data) ? $data['parent_id'] : $account->parent_id,
            'is_active' => $data['is_active'] ?? $account->is_active,
        ])->save();

        $this->audit($actor, 'chart_of_accounts.updated', $account);

        return $account->fresh();
    }

    public function delete(ChartOfAccount $account, ?User $actor = null): void
    {
        if ($account->hasMovements()) {
            throw new \RuntimeException('لا يُحذف حساب له حركات قيود');
        }

        if ($account->children()->exists()) {
            throw new \RuntimeException('لا يُحذف حساب له حسابات فرعية');
        }

        if ($account->expenseCategories()->exists() || $account->revenueCategories()->exists()) {
            throw new \RuntimeException('لا يُحذف حساب مرتبط ببنود صرف أو إيراد');
        }

        $account->delete();
        $this->audit($actor, 'chart_of_accounts.deleted', $account);
    }

    public function linkExpenseCategory(ExpenseCategory $category, ?int $accountId): void
    {
        $category->forceFill(['account_id' => $accountId])->save();
    }

    public function linkRevenueCategory(RevenueCategory $category, ?int $accountId): void
    {
        $category->forceFill(['account_id' => $accountId])->save();
    }

    private function audit(?User $actor, string $action, ChartOfAccount $account): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_type' => ChartOfAccount::class,
            'target_id' => $account->id,
            'ip_address' => request()->ip(),
            'metadata' => ['code' => $account->code, 'name_ar' => $account->name_ar],
            'created_at' => now(),
        ]);
    }
}
