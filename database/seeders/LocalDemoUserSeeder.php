<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalDemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        foreach ([
            ['name' => 'TaskFlow Admin', 'email' => 'admin@taskflow.test', 'role' => UserRole::Admin],
            ['name' => 'TaskFlow Project Manager', 'email' => 'manager@taskflow.test', 'role' => UserRole::ProjectManager],
            ['name' => 'TaskFlow Member', 'email' => 'member@taskflow.test', 'role' => UserRole::Member],
        ] as $account) {
            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$account['role']->value]);
        }
    }
}
