<?php

namespace Modules\LearningInsights\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\LearningCatalog\Events\LearningEntryPublished;
use Modules\LearningInsights\Listeners\RecordPublishedLearningEntry;
use Modules\LearningInsights\Repositories\Contracts\LearningInsightRepositoryInterface;
use Modules\LearningInsights\Repositories\Eloquent\EloquentLearningInsightRepository;

class LearningInsightsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LearningInsightRepositoryInterface::class, EloquentLearningInsightRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('LearningInsights', 'database/migrations'));

        Event::listen(LearningEntryPublished::class, RecordPublishedLearningEntry::class);
    }
}
