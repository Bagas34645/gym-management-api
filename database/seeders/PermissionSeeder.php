<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'memberships.view', 'memberships.create', 'memberships.update', 'memberships.verify',
            'attendance.view', 'attendance.verify',
            'trainers.view', 'trainers.manage',
            'payments.view', 'payments.verify',
            'reports.view',
            'faqs.manage', 'feedback.manage',
            'chat.manage', 'notifications.send',
            'audit.view', 'settings.manage',
        ];

        $permissionIds = [];
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['description' => str_replace('.', ' ', ucfirst($name))]
            );
            $permissionIds[] = $permission->id;
        }

        $adminRole = Role::query()->where('name', 'admin')->first();
        $superAdminRole = Role::query()->where('name', 'super_admin')->first();

        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('name', array_slice($permissions, 0, 16))->pluck('id')
            );
        }

        if ($superAdminRole) {
            $superAdminRole->permissions()->sync($permissionIds);
        }
    }
}
