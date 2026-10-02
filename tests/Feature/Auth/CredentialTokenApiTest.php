<?php

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('issues a canonical personal access token once and returns the authenticated user safely', function (): void {
    $user = User::factory()->asMember()->create(['email' => 'member@example.test']);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => ' MEMBER@EXAMPLE.TEST ', 'password' => 'password', 'device_name' => 'Pest client',
        'abilities' => ['projects:read', 'tasks:read'],
    ])->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.abilities', ['projects:read', 'tasks:read']);

    $plainToken = $response->json('data.token');
    expect($plainToken)->toBeString()->not->toBeEmpty()
        ->and((string) DB::table('personal_access_tokens')->value('token'))->not->toContain($plainToken);

    $this->withToken($plainToken)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'member@example.test')
        ->assertJsonPath('data.abilities', ['projects:read', 'tasks:read'])
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.token');
});

it('returns generic validation errors for invalid credentials and rejected suspended credentials', function (): void {
    $active = User::factory()->asMember()->create(['email' => 'active@example.test']);
    $suspended = User::factory()->asMember()->suspended()->create(['email' => 'suspended@example.test']);

    foreach ([[$active->email, 'wrong-password'], [$suspended->email, 'password']] as [$email, $password]) {
        $this->postJson('/api/v1/auth/token', [
            'email' => $email, 'password' => $password, 'device_name' => 'Pest client', 'abilities' => ['tasks:read'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
});

it('validates device names and the canonical ability allowlist', function (): void {
    $user = User::factory()->asMember()->create();

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email, 'password' => 'password', 'abilities' => ['not:allowed'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['device_name', 'abilities.0']);
});

it('uses a dedicated credential endpoint rate limit', function (): void {
    $user = User::factory()->asMember()->create(['email' => 'member@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/token', [
            'email' => ' MEMBER@EXAMPLE.TEST ', 'password' => 'wrong', 'device_name' => 'Pest client', 'abilities' => ['tasks:read'],
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'Pest client', 'abilities' => ['tasks:read'],
    ])->assertTooManyRequests();
});

it('requires a valid active token for me and revokes only the current token', function (): void {
    $user = User::factory()->asMember()->create();
    $first = $user->createToken('first', ['tasks:read'])->plainTextToken;
    $second = $user->createToken('second', ['tasks:read'])->plainTextToken;

    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->withToken($first)->deleteJson('/api/v1/auth/token')->assertNoContent();
    expect(PersonalAccessToken::findToken($first))->toBeNull();
    $this->app['auth']->forgetGuards();
    $this->withToken($first)->getJson('/api/v1/me')->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->withToken($second)->getJson('/api/v1/me')->assertOk();
});

it('rejects a token immediately when its actor becomes suspended and respects ability denial', function (): void {
    $user = User::factory()->asMember()->create();
    $token = $user->createToken('limited', ['projects:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/tasks')->assertForbidden();
    $user->forceFill(['status' => AccountStatus::Suspended])->save();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('retires the inherited authenticated token bootstrap endpoints', function (): void {
    $this->getJson('/api/v1/tokens')->assertNotFound();
    $this->postJson('/api/v1/tokens')->assertNotFound();
});
