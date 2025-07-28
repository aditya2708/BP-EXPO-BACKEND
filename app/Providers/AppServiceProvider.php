<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Controllers\API\Base\BaseService;
use App\Http\Middleware\ApiErrorHandler;
use Illuminate\Contracts\Http\Kernel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register BaseService for dependency injection
        $this->app->bind(BaseService::class, function ($app, $parameters) {
            $modelClass = $parameters['modelClass'] ?? null;
            return new BaseService($modelClass);
        });

        // Register BaseService as singleton for shared instances
        $this->app->singleton('base.service', function ($app) {
            return new BaseService(null);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register API error handling middleware globally for API routes
        $kernel = $this->app->make(Kernel::class);
        
        // Add middleware to API middleware group
        $kernel->appendMiddlewareToGroup('api', ApiErrorHandler::class);
        
        // Register middleware alias for manual usage
        $kernel->appendToMiddlewarePriority(ApiErrorHandler::class);
    }
}