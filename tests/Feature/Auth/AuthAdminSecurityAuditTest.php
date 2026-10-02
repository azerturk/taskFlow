<?php

use App\Data\UpdateAdminUserData;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AdminUserService;
use App\Services\SecurityAuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Activity\Services\ActivityRecorder;
use Modules\Activity\Support\ActivitySanitizer;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('records account and token events without sensitive payload values', function (): void {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Member', 'email' => 'new-member@example.test', 'password' => 'temporary-password',
        'password_confirmation' => 'temporary-password', 'role' => UserRole::Member->value,
    ])->assertRedirect();

    $tokenResponse = $this->postJson('/api/v1/auth/token', [
        'email' => $admin->email, 'password' => 'password', 'device_name' => 'Audit client', 'abilities' => ['projects:read'],
    ])->assertCreated();
    $plainToken = $tokenResponse->json('data.token');
    $this->withToken($plainToken)->deleteJson('/api/v1/auth/token')->assertNoContent();

    $events = DB::table('activity_log')->pluck('event')->all();
    $payload = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($events)->toContain('user.created', 'api_token.issued', 'api_token.revoked')
        ->and($payload)->not->toContain('temporary-password')
        ->and($payload)->not->toContain($plainToken);
});

it('removes sensitive keys recursively before activity is persisted', function (): void {
    $user = User::factory()->asAdmin()->create();
    $properties = app(SecurityAuditService::class)->sanitize([
        'user_id' => $user->id,
        'password' => 'not-recorded',
        'nested' => ['api_token' => 'not-recorded', 'safe' => 'kept'],
        'authorization_header' => 'not-recorded',
    ]);

    expect($properties)->toBe(['user_id' => $user->id, 'nested' => ['safe' => 'kept']]);
});

it('uses one canonical sanitizer for host security and module Activity callers', function (): void {
    $properties = [
        'task_id' => 10,
        'description_changed' => true,
        'private_path' => '/private/file',
        'nested' => [
            'safe' => 'kept',
            'notification_payload' => ['token' => 'secret'],
            'file_content' => 'secret',
            'binary' => "unsafe\0value",
        ],
    ];
    $canonical = app(ActivitySanitizer::class)->sanitize($properties);

    expect(app(SecurityAuditService::class)->sanitize($properties))->toBe($canonical)
        ->and(app(ActivityRecorder::class)->sanitize($properties))->toBe($canonical)
        ->and($canonical)->toBe([
            'task_id' => 10,
            'description_changed' => true,
            'nested' => ['safe' => 'kept'],
        ]);
});

it('does not allow account-status mass assignment and keeps hidden credentials out of serialization', function (): void {
    $user = User::query()->create([
        'name' => 'Mass Assignment', 'email' => 'mass@example.test', 'password' => 'password', 'status' => AccountStatus::Suspended,
    ]);

    expect($user->fresh()->status)->toBe(AccountStatus::Active)
        ->and($user->toArray())->not->toHaveKey('password')
        ->and($user->toArray())->not->toHaveKey('remember_token');
});

it('keeps final-active-admin protection correct across sequential stale user instances', function (): void {
    $first = User::factory()->asAdmin()->create();
    $second = User::factory()->asAdmin()->create();
    $staleSecond = $second->fresh();
    $service = app(AdminUserService::class);

    $service->update($first, new UpdateAdminUserData($first->name, $first->email, UserRole::Member), $second);

    expect(fn () => $service->update($staleSecond, new UpdateAdminUserData($second->name, $second->email, UserRole::Member), $second))
        ->toThrow(LogicException::class);
});

it('applies account state consistently for every global role at the credential boundary', function (UserRole $role, AccountStatus $status, int $expectedStatus): void {
    $user = User::factory()->withRole($role)->create(['status' => $status]);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'Matrix client', 'abilities' => ['projects:read'],
    ])->assertStatus($expectedStatus);
})->with([
    'active admin' => [UserRole::Admin, AccountStatus::Active, 201],
    'active manager' => [UserRole::ProjectManager, AccountStatus::Active, 201],
    'active member' => [UserRole::Member, AccountStatus::Active, 201],
    'suspended admin' => [UserRole::Admin, AccountStatus::Suspended, 422],
    'suspended manager' => [UserRole::ProjectManager, AccountStatus::Suspended, 422],
    'suspended member' => [UserRole::Member, AccountStatus::Suspended, 422],
]);
