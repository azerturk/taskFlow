<?php

namespace Modules\LearningCatalog\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed;
use Modules\LearningCatalog\Repositories\Contracts\LearningEntryRepositoryInterface;
use Modules\LearningCatalog\Repositories\Eloquent\EloquentLearningEntryRepository;
use Modules\LearningCatalog\Services\EloquentPublishedLearningEntryFeed;

class LearningCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LearningEntryRepositoryInterface::class, EloquentLearningEntryRepository::class);
        $this->app->bind(PublishedLearningEntryFeed::class, EloquentPublishedLearningEntryFeed::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('LearningCatalog', 'database/migrations'));
    }
}
