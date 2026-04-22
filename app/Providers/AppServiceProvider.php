<?php

namespace App\Providers;

use App\Services\Support\Interfaces\SupportServiceInterface;
use App\Services\Support\OlieFlowSupportService;
use App\Support\ChapterRateLimit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SupportServiceInterface::class, OlieFlowSupportService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        JsonResource::withoutWrapping();

        ChapterRateLimit::register();
    }
}
