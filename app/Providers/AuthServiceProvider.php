<?php

namespace App\Providers;

use App\Models\CsvConfiguration;
use App\Models\JsonData;
use App\Policies\CsvConfigurationPolicy;
use App\Policies\JsonDataPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        CsvConfiguration::class => CsvConfigurationPolicy::class,
        JsonData::class => JsonDataPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}