<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Modules\Projects\Enums\ProjectMemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectMemberService;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Enums\TaskType;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskWatcherService;

require __DIR__.'/../bootstrap/TestEnvironment.php';

$temporaryRoot = TaskFlowTestEnvironment::temporaryRoot('e2e');
$database = $temporaryRoot.'/database.sqlite';
$environment = [
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'SESSION_DRIVER' => 'file',
    'SESSION_PATH' => $temporaryRoot.'/framework/sessions',
];

TaskFlowTestEnvironment::configure('e2e', $environment);

if (TaskFlowTestEnvironment::temporaryRoot('e2e') !== dirname($database)
    || basename($database) !== 'database.sqlite') {
    throw new RuntimeException('Refusing to prepare an E2E database outside the guarded TaskFlow temporary directory.');
}
if (! is_file($database) && ! touch($database)) {
    throw new RuntimeException('Unable to create the disposable E2E SQLite database.');
}

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$kernel->call('migrate:fresh', ['--force' => true]);
$app->make(RolePermissionSeeder::class)->run();

$admin = User::factory()->asAdmin()->create(['name' => 'E2E Admin', 'email' => 'admin@e2e.test', 'password' => 'browser-password']);
$manager = User::factory()->asProjectManager()->create(['name' => 'E2E Manager', 'email' => 'manager@e2e.test', 'password' => 'browser-password']);
$member = User::factory()->asMember()->create(['name' => 'E2E Member', 'email' => 'member@e2e.test', 'password' => 'browser-password']);
User::factory()->asMember()->create(['name' => 'E2E Candidate', 'email' => 'candidate@e2e.test', 'password' => 'browser-password']);
User::factory()->asMember()->suspended()->create(['name' => 'E2E Suspended', 'email' => 'suspended@e2e.test', 'password' => 'browser-password']);

$project = Project::factory()->active()->create(['name' => 'E2E Project', 'key' => 'E2E', 'owner_id' => $manager->id]);
app(ProjectMemberService::class)->addMember($project, $member, ProjectMemberRole::Member, actor: $manager);
$task = Task::factory()->for($project)->for($member, 'creator')->for($member, 'assignee')->create([
    'title' => 'E2E browser task', 'type' => TaskType::Bug, 'priority' => TaskPriority::High, 'status' => TaskStatus::Todo,
]);
foreach (['desktop', 'mobile'] as $offset => $profile) {
    Task::factory()->for($project)->for($manager, 'creator')->create(['title' => "E2E {$profile} backlog first", 'status' => TaskStatus::Backlog, 'rank' => 1000 + ($offset * 2000)]);
    Task::factory()->for($project)->for($manager, 'creator')->create(['title' => "E2E {$profile} backlog second", 'status' => TaskStatus::Backlog, 'rank' => 2000 + ($offset * 2000)]);
}
Project::factory()->completed()->create(['name' => 'E2E Completed', 'key' => 'CMP', 'owner_id' => $manager->id]);
Project::factory()->archived()->create(['name' => 'E2E Archived', 'key' => 'ARC', 'owner_id' => $manager->id]);
app(TaskWatcherService::class)->watch($task, $manager, $manager);
app(TaskWatcherService::class)->watch($task, $member, $member);

echo "E2E fixture database prepared\n";
