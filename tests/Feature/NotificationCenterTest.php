<?php

use App\Models\User;
use App\Notifications\TaskWatcherNotification;
use App\Services\NotificationCenterService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Enums\ActivityEvent;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskWatcherNotificationService;
use Modules\Tasks\Services\TaskWatcherService;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function notificationCenterContext(): array
{
    $manager = User::factory()->asProjectManager()->create();
    $actor = User::factory()->asMember()->create();
    $watcher = User::factory()->asMember()->create();
    $project = Project::factory()->active()->create(['owner_id' => $manager->id]);
    $members = app(ProjectMemberService::class);
    $members->addMember($project, $actor, ProjectMemberRole::Member, actor: $manager);
    $members->addMember($project, $watcher, ProjectMemberRole::Member, actor: $manager);
    $task = Task::factory()->for($project)->for($actor, 'creator')->create();

    return compact('manager', 'actor', 'watcher', 'project', 'task');
}

test('recipient calculation is centralized and unread badge/read state/pagination are correct', function (): void {
    extract(notificationCenterContext());
    app(TaskWatcherService::class)->watch($task->load('project'), $watcher, $watcher);
    $recipients = app(TaskWatcherNotificationService::class)->recipients($task->fresh()->load('project'), $actor);
    expect($recipients->pluck('id')->all())->toBe([$watcher->id]);
    app(TaskWatcherNotificationService::class)->notify($task->fresh()->load('project'), $actor, ActivityEvent::CommentCreated);
    $center = app(NotificationCenterService::class);
    expect($center->unreadCount($watcher))->toBe(1);
    $this->actingAs($watcher)->get(route('notifications.index'))->assertOk()->assertSee('New task comment')->assertSee($task->number)->assertSee('Open task');
    $notificationId = DB::table('notifications')->where('notifiable_id', $watcher->id)->value('id');
    $this->actingAs($watcher)->patch(route('notifications.read', $notificationId))->assertRedirect();
    expect($center->unreadCount($watcher))->toBe(0);

    foreach (range(1, 21) as $index) {
        $watcher->notify(new TaskWatcherNotification($task, $actor, ActivityEvent::CommentCreated->value));
    }
    expect($center->paginate($watcher, 20)->hasMorePages())->toBeTrue();
    $this->actingAs($watcher)->patch(route('notifications.read-all'))->assertRedirect();
    expect($center->unreadCount($watcher))->toBe(0);
});

test('stale or inaccessible task targets are safely summarized without a link or retained metadata', function (): void {
    extract(notificationCenterContext());
    $watcher->notify(new TaskWatcherNotification($task, $actor, ActivityEvent::CommentCreated->value));
    $task->delete();
    $this->actingAs($watcher)->get(route('notifications.index'))->assertOk()->assertSee('A task update is no longer available.')->assertDontSee($task->number)->assertDontSee('Open task');

    $otherTask = Task::factory()->for($project)->for($actor, 'creator')->create(['title' => 'No longer visible']);
    $watcher->notify(new TaskWatcherNotification($otherTask, $actor, ActivityEvent::CommentCreated->value));
    app(ProjectMemberService::class)->removeMember($project, $watcher, $manager);
    $this->actingAs($watcher)->get(route('notifications.index'))->assertOk()->assertSee('A task update is no longer available.')->assertDontSee('No longer visible')->assertDontSee('Open task');
});
