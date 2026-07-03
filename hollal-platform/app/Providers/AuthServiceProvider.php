<?php

namespace App\Providers;

use App\Models\Partnership;
use App\Models\Project;
use App\Models\Task;
use App\Policies\PartnershipPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        Task::class => TaskPolicy::class,
        Project::class => ProjectPolicy::class,
        Partnership::class => PartnershipPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
