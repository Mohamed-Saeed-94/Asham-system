<?php
namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsRolesAndPermissions;
use Illuminate\Database\Seeder;

/**
 * يضيف الصلاحيات والأدوار المفقودة فقط (firstOrCreate + givePermissionTo للمفقود). لا ينشئ مستخدمين.
 *
 * أول Admin: يُسجَّل من /register عندما يكون جدول المستخدمين فارغًا،
 * ثم يُشغَّل RolesAndPermissionsSeeder الذي يمنح دور admin لأول مستخدم. لا توجد كلمات مرور ثابتة في الكود.
 */
class PermissionsSeeder extends Seeder
{
    use SeedsRolesAndPermissions;

    protected string $guard = 'web';

    public function run(): void
    {
        $this->seedRolesAndPermissions();
    }
}
