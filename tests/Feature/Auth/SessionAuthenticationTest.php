<?php

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('redirects guests to login and keeps public registration absent', function (): void {
    $this->get(route('home'))->assertRedirect(route('login'));
    $this->get('/register')->assertNotFound();
    $this->get(route('login'))->assertOk()->assertViewIs('auth.login');
});

it('authenticates active users with remember behavior and regenerates the session', function (): void {
    $user = User::factory()->asMember()->create(['email' => 'member@example.test']);
    $oldSessionId = $this->app['session']->getId();

    $this->post(route('login.store'), [
        'email' => ' MEMBER@EXAMPLE.TEST ', 'password' => 'password', 'remember' => true,
    ])->assertRedirect('/')
        ->assertCookie(auth()->getRecallerName());

    $this->assertAuthenticatedAs($user);
    expect($this->app['session']->getId())->not->toBe($oldSessionId);
});

it('uses the same safe credential error for invalid and suspended accounts', function (): void {
    $active = User::factory()->asMember()->create(['email' => 'active@example.test']);
    $suspended = User::factory()->asMember()->suspended()->create(['email' => 'suspended@example.test']);

    $this->from(route('login'))->post(route('login.store'), ['email' => $active->email, 'password' => 'wrong'])
        ->assertRedirect(route('login'))->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    $this->from(route('login'))->post(route('login.store'), ['email' => $suspended->email, 'password' => 'password'])
        ->assertRedirect(route('login'))->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
});

it('throttles a normalized email and IP after five failed attempts', function (): void {
    $user = User::factory()->asMember()->create(['email' => 'member@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->from(route('login'))->post(route('login.store'), ['email' => ' MEMBER@EXAMPLE.TEST ', 'password' => 'wrong'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertTooManyRequests();
    $this->assertGuest();
});

it('rejects a stale suspended web session on every protected route', function (): void {
    $user = User::factory()->asMember()->create();
    $this->actingAs($user);
    $user->forceFill(['status' => AccountStatus::Suspended])->save();

    $this->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('logs out by invalidating the session and regenerating the csrf token', function (): void {
    $user = User::factory()->asMember()->create();
    $this->actingAs($user)->withSession(['_token' => 'before-logout']);

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect($this->app['session']->token())->not->toBe('before-logout');
});
