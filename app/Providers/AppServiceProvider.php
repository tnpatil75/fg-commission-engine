<?php

namespace App\Providers;

use App\Commission\Contracts\RuleRepository;
use App\Commission\Persistence\DatabaseRuleRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RuleRepository::class, DatabaseRuleRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
