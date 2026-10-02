<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(PermissionName::cases())
            ->mapWithKeys(fn (PermissionName $permission): array => [
                $permission->value => Permission::findOrCreate($permission->value, $guard),
            ]);

        $rolePermissions = [
            UserRole::Admin->value => PermissionName::cases(),
            UserRole::ProjectManager->value => [
                PermissionName::ProjectsView,
                PermissionName::ProjectsCreate,
                PermissionName::ProjectsUpdate,
                PermissionName::ProjectsArchive,
                PermissionName::ProjectsMembersManage,
                PermissionName::TasksView,
                PermissionName::TasksCreate,
                PermissionName::TasksUpdate,
                PermissionName::TasksAssign,
                PermissionName::TasksStatusChange,
                PermissionName::TasksDelete,
                PermissionName::CommentsCreate,
                PermissionName::CommentsDelete,
                PermissionName::AttachmentsUpload,
                PermissionName::AttachmentsDelete,
                PermissionName::ActivityView,
                PermissionName::DashboardView,
            ],
            UserRole::Member->value => [
                PermissionName::ProjectsView,
                PermissionName::TasksView,
                PermissionName::TasksCreate,
                PermissionName::TasksUpdate,
                PermissionName::TasksAssign,
                PermissionName::TasksStatusChange,
                PermissionName::TasksDelete,
                PermissionName::CommentsCreate,
                PermissionName::CommentsDelete,
                PermissionName::AttachmentsUpload,
                PermissionName::AttachmentsDelete,
                PermissionName::ActivityView,
                PermissionName::DashboardView,
            ],
        ];

        foreach ($rolePermissions as $roleName => $assignedPermissions) {
            $role = Role::findOrCreate($roleName, $guard);

            $role->syncPermissions(
                $permissions->only(array_map(
                    static fn (PermissionName $permission): string => $permission->value,
                    $assignedPermissions,
                )),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
