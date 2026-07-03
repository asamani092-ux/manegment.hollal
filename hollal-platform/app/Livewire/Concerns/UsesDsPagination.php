<?php

namespace App\Livewire\Concerns;

/**
 * Design-system Livewire pagination view.
 */
trait UsesDsPagination
{
    public function paginationView(): string
    {
        return 'components.pagination.ds';
    }
}
