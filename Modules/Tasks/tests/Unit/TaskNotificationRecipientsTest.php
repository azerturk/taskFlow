<?php

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Support\Collection;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Repositories\Contracts\TaskWatcherRepositoryInterface;
use Modules\Tasks\Services\TaskWatcherNotificationService;

test('notification recipients exclude the actor and de-duplicate eligible watchers', function (): void {
    $actor = (new User)->forceFill(['id' => 1]);
    $first = (new User)->forceFill(['id' => 2]);
    $second = (new User)->forceFill(['id' => 3]);
    $task = (new Task)->forceFill(['id' => 10]);

    $watchers = Mockery::mock(TaskWatcherRepositoryInterface::class);
    $watchers->shouldReceive('eligibleWatchers')->once()->with($task)
        ->andReturn(new Collection([$actor, $first, $first, $second]));
    $notifications = Mockery::mock(NotificationRepositoryInterface::class);

    $recipients = (new TaskWatcherNotificationService($watchers, $notifications))->recipients($task, $actor);

    expect($recipients->pluck('id')->all())->toBe([2, 3]);
});
