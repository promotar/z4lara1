<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('details')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        $tasks = [
            ['title' => 'بناء PluginManager كامل لتحميل Service Providers للبلجنات النشطة', 'details' => 'الهدف أن البلجنات active فقط تحمل routes, views, migrations, hooks, menus, permissions.'],
            ['title' => 'تشغيل migrations الخاصة بالبلجن عند التثبيت', 'details' => 'بعد فحص ZIP وتسجيل البلجن، يجب تشغيل migrations من modules/{slug}/database/migrations بشكل مضبوط.'],
            ['title' => 'تسجيل permissions و menus و hooks من ملفات البلجن', 'details' => 'قراءة permissions.php و menus.php و hooks.php وربطها بالنظام الأساسي.'],
            ['title' => 'إنشاء صفحة uninstall آمنة للبلجن', 'details' => 'uninstall يكون منفصل عن deactivate ولا يعمل إلا بتأكيد صريح.'],
            ['title' => 'إنشاء Demo Plugin للاختبار', 'details' => 'بلجن بسيط يحتوي module.json و ServiceProvider و route و view لاختبار دورة الحياة.'],
            ['title' => 'تفعيل Reverse Proxy النهائي للدومين', 'details' => 'بعد ربط DNS، يوجه Reverse Proxy إلى عنوان الخدمة المضبوط في بيئة التشغيل.'],
            ['title' => 'إضافة backup قبل install/update/uninstall', 'details' => 'نسخة قاعدة بيانات ونسخة مجلد البلجن قبل أي عملية حساسة.'],
            ['title' => 'تقييد صفحات الإدارة بصلاحيات فعلية', 'details' => 'استخدام middleware مثل permission:plugins.install و users.manage بدل auth فقط.'],
            ['title' => 'إضافة Activity Logs لكل عمليات الإدارة', 'details' => 'تسجيل من رفع بلجن، فعله، عطله، أضاف مستخدم، أو عدل صلاحيات.'],
        ];

        foreach ($tasks as $index => $task) {
            DB::table('documentation_tasks')->insert([
                'title' => $task['title'],
                'details' => $task['details'],
                'sort_order' => ($index + 1) * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_tasks');
    }
};
