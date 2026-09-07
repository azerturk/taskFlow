<?php

use App\Data\ChangeOwnPasswordData;
use App\Data\ResetAdminUserPasswordData;
use App\Data\UpdateAdminUserData;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AdminUserService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Projects\Models\Project;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows only administrators to manage internal accounts', function (): void {
    $member = User::factory()->asMember()->create();
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

it('normalizes email and keeps global role separate from project membership', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $member = User::factory()->asMember()->create(['email' => 'member@example.test']);
    $project = Project::factory()->for($admin, 'owner')->create();
    $project->members()->attach($member, ['member_role' => 'manager', 'joined_at' => now()]);

    $this->actingAs($admin)->put(route('admin.users.update', $member), [
        'name' => 'Member', 'email' => '  MEMBER@EXAMPLE.TEST ', 'role' => UserRole::Member->value,
    ])->assertRedirect();

    expect($member->fresh()->email)->toBe('member@example.test')
        ->and($member->fresh()->hasRole(UserRole::Member->value))->toBeTrue()
        ->and($project->memberships()->where('user_id', $member->id)->first()->member_role->value)->toBe('manager');
});

it('suspends despite open work and revokes tokens and stored sessions', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $member = User::factory()->asMember()->create();
    $project = Project::factory()->active()->for($admin, 'owner')->create();
    $task = Task::factory()->for($project)->for($admin, 'creator')->for($member, 'assignee')->create(['status' => TaskStatus::Todo]);
    $plainToken = $member->createToken('existing token')->plainTextToken;
    DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $member->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp]);

    app(AdminUserService::class)->suspend($member, $admin);

    expect($member->fresh()->status)->toBe(AccountStatus::Suspended)
        ->and($task->fresh()->assignee_id)->toBeNull()
        ->and($member->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $member->id)->count())->toBe(0);

    $this->withToken($plainToken)->getJson('/api/v1/me')->assertUnauthorized();
    $this->post(route('login.store'), ['email' => $member->email, 'password' => 'password'])->assertSessionHasErrors('email');
});

it('does not suspend or demote the final active administrator', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $service = app(AdminUserService::class);

    expect(fn () => $service->suspend($admin, $admin))->toThrow(LogicException::class);
    expect(fn () => $service->update($admin, new UpdateAdminUserData('Admin', $admin->email, UserRole::Member)))->toThrow(LogicException::class);
});

it('resets an account password and revokes every token and session without recording secrets', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $member = User::factory()->asMember()->create();
    $member->createToken('existing token');
    DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $member->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp]);

    app(AdminUserService::class)->resetPassword($member, new ResetAdminUserPasswordData('new-password'), $admin);

    expect(Hash::check('new-password', $member->fresh()->password))->toBeTrue()
        ->and($member->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $member->id)->count())->toBe(0)
        ->and((string) DB::table('activity_log')->latest('id')->value('properties'))->not->toContain('new-password');
});

it('requires the current password for self-service change and preserves only the current session', function (): void {
    $member = User::factory()->asMember()->create();
    $member->createToken('existing token');
    DB::table('sessions')->insert([
        ['id' => 'current-session', 'user_id' => $member->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp],
        ['id' => 'other-session', 'user_id' => $member->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'payload', 'last_activity' => now()->timestamp],
    ]);

    app(AdminUserService::class)->changeOwnPassword($member, new ChangeOwnPasswordData('password', 'new-password'), 'current-session');

    expect(Hash::check('new-password', $member->fresh()->password))->toBeTrue()
        ->and($member->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'current-session')->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-session')->exists())->toBeFalse();

    $this->actingAs($member)->put(route('account.password.update'), [
        'current_password' => 'wrong-password', 'password' => 'another-password', 'password_confirmation' => 'another-password',
    ])->assertSessionHasErrors('current_password');
});
