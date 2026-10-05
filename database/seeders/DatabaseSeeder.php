<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Accounts\Database\Seeders\AccountsDatabaseSeeder;
use Modules\Lookups\Database\Seeders\LookupsDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            AccountsDatabaseSeeder::class,
            LookupsDatabaseSeeder::class,
            RolesAndPermissionsSeeder::class,
            PermissionsSeeder::class,
        ]);

        // لا يتم إنشاء مستخدمين بكلمات مرور ثابتة هنا (راجع PermissionsSeeder لطريقة إنشاء أول Admin).
    }
}
