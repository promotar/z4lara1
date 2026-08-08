# تقرير جرد صفحات Art Z

تاريخ الجرد: 2026-08-05. شمل الفحص مسارات Laravel الأساسية، مسارات البلجنات النشطة، Controllers، ملفات Blade وHTML، وجدول `platform_pages` في قاعدة البيانات الحية. لا تشمل القائمة مسارات JSON والتحميل والحفظ والحذف لأنها endpoints وليست صفحات عرض.

## صفحات لوحة الإدارة

| اسم الصفحة | رابط الصفحة | وظيفتها | مكان وجودها وامتدادها |
|---|---|---|---|
| Dashboard | `/dashboard` | لوحة البداية للموظفين | `resources/views/dashboard.blade.php` |
| Documentation | `/admin/documentation` | التوثيق، السجل، المهام، الراوتات والـ hooks | `resources/views/admin/documentation/index.blade.php` |
| Documentation Report | `/admin/documentation/reports/{report}/view` | عرض تقرير Markdown مسجل | ملف ديناميكي من `docs/project-management/implementation-reports/*.md` |
| Backups | `/admin/backups` | إنشاء وإدارة نقاط النسخ الاحتياطي | `resources/views/admin/backups/index.blade.php` |
| Admin Menu | `/admin/menus?location=admin` | إدارة أقسام وعناصر قائمة الإدارة | `resources/views/admin/menus/index.blade.php` |
| Frontend Menus | `/admin/menus?location=frontend` | إدارة قوائم الموقع والأبناء والـ VvvebJs hook | `resources/views/admin/menus/index.blade.php` |
| Media | `/admin/media` | مكتبة الوسائط والرفع وتعديل البيانات | `resources/views/admin/media/index.blade.php` |
| Users | `/admin/users` | إدارة المستخدمين والتحقق والحسابات | `resources/views/admin/users/index.blade.php` |
| Roles | `/admin/roles` | إدارة الأدوار وصلاحياتها | `resources/views/admin/roles/index.blade.php` |
| Permissions | `/admin/permissions` | إدارة ومزامنة الصلاحيات | `resources/views/admin/permissions/index.blade.php` |
| Settings | `/admin/settings` | الإعدادات العامة، SEO، الثيم والوسائط | `resources/views/admin/settings/index.blade.php` |
| Platform Registry | `/admin/platform-registry` | سجل وظائف وراوتات وعمليات المنصة | `resources/views/admin/platform-registry/index.blade.php` |
| Plugins | `/admin/plugins` | عرض وإدارة البلجنات | `resources/views/admin/plugins/index.blade.php` |
| Install Plugin | `/admin/plugins/install` | رفع وتثبيت أو تحديث البلجن | `resources/views/admin/plugins/create.blade.php` |
| Review Plugin Update | `/admin/plugins/update/{token}` | مراجعة حزمة تحديث البلجن قبل اعتمادها | `resources/views/admin/plugins/update.blade.php` |
| VvvebJs Pages | `/admin/pages` | قائمة صفحات وهدر وفوتر وبلوكات VvvebJs | `modules/page-builder/resources/views/pages/index.blade.php` |
| VvvebJs Editor | `/admin/pages/{page}/edit` | محرر VvvebJs الكامل | `modules/page-builder/resources/vvvebjs/editor.html` |
| VvvebJs Canvas | `/admin/pages/{page}/vvveb-canvas` | مستند التصميم داخل iframe المحرر | HTML ديناميكي من `platform_pages.vvvebjs_html` عبر `modules/page-builder/src/Http/Controllers/Admin/PageController.php` |
| Page Preview | `/admin/pages/{page}/preview` | معاينة صفحة قبل/بعد النشر | `modules/page-builder/resources/views/public/show.blade.php` |
| Theme Layout | `/admin/vvveb-layout` | ترتيب الهدرات والفوترات الفعالة | `modules/page-builder/resources/views/pages/layout.blade.php` |
| Admin Theme Settings | `/admin/plugins/admin-theme/settings` | إعداد شكل لوحة الإدارة | `modules/admin-theme/resources/views/settings.blade.php` |

### روابط إدارة توافقية وليست صفحات مستقلة

| اسم الرابط | الرابط | وظيفته | مكانه |
|---|---|---|---|
| Legacy Theme Builder | `/admin/theme-builder` | تحويل إلى قائمة صفحات VvvebJs | `modules/page-builder/routes/admin.php` |
| Page Builder Plugin Alias | `/admin/plugins/page-builder` | فتح نفس صفحة VvvebJs Pages | `modules/page-builder/routes/admin.php` |

## صفحات المستخدم والواجهة الأمامية

| اسم الصفحة | رابط الصفحة | وظيفتها | مكان وجودها وامتدادها |
|---|---|---|---|
| Home | `/` | الصفحة الرئيسية؛ تعرض الصفحة الثابتة المختارة أو Home من قاعدة البيانات أو fallback | `routes/web.php` ثم `modules/page-builder/resources/views/public/show.blade.php` أو `resources/views/frontend/home.blade.php` |
| Public VvvebJs Page | `/pages/{slug}` | عرض أي صفحة VvvebJs منشورة | `modules/page-builder/resources/views/public/show.blade.php` |
| Legacy Public Page | `/page/{slug}` | تحويل دائم إلى `/pages/{slug}` | `modules/page-builder/routes/web.php` |
| Account | `/account` | حساب المستخدم أو تحويله إلى واجهة الطالب عند توفر LMS | `resources/views/frontend/account.blade.php` |
| Profile | `/profile` | تعديل الملف الشخصي وكلمة المرور وحذف الحساب | `resources/views/profile/edit.blade.php` |
| Login | `/login` | تسجيل الدخول | `resources/views/auth/login.blade.php` |
| Register | `/register` | إنشاء حساب | `resources/views/auth/register.blade.php` |
| Forgot Password | `/forgot-password` | طلب رابط استعادة كلمة المرور | `resources/views/auth/forgot-password.blade.php` |
| Reset Password | `/reset-password/{token}` | تعيين كلمة مرور جديدة | `resources/views/auth/reset-password.blade.php` |
| Confirm Password | `/confirm-password` | تأكيد كلمة المرور قبل العمليات الحساسة | `resources/views/auth/confirm-password.blade.php` |
| Verify Email | `/verify-email` | طلب/انتظار توثيق البريد | `resources/views/auth/verify-email.blade.php` |
| Installation: Platform | `/install/platform` | الخطوة الأولى من التثبيت | `resources/views/installation/wizard.blade.php` |
| Installation: Database | `/install/database` | الخطوة الثانية من التثبيت | `resources/views/installation/wizard.blade.php` |
| Installation: Owner | `/install/owner` | الخطوة الثالثة وإنشاء المالك | `resources/views/installation/wizard.blade.php` |

## صفحات VvvebJs المسجلة حاليًا في قاعدة البيانات الحية

| اسم الصفحة | رابط الصفحة | وظيفتها/نوعها | مكان وجودها وامتدادها |
|---|---|---|---|
| Home | `/pages/untitled-page-2026-08-02-2330` | صفحة منشورة | جدول `platform_pages`، السجل `id=3`، والمحتوى HTML داخل `vvvebjs_html` |
| عالم الفن التشكيلي | `/pages/art-world` | صفحة منشورة؛ عنوان المنيو: عالم الفن | جدول `platform_pages`، السجل `id=4`، والمحتوى HTML داخل `vvvebjs_html` |
| Header | لا يملك رابط صفحة عامة مستقلًا | هدر منشور يحقن عبر Theme Layout | جدول `platform_pages`، السجل `id=1`، النوع `header` |
| Header1 | لا يملك رابط صفحة عامة مستقلًا | هدر منشور يحقن عبر Theme Layout | جدول `platform_pages`، السجل `id=5`، النوع `header` |

## ملفات عرض ليست صفحات مستقلة

هذه الملفات أجزاء Layout أو Components وتُستخدم داخل الصفحات أعلاه: `resources/views/layouts/*.blade.php`، `resources/views/components/*.blade.php`، `resources/views/profile/partials/*.blade.php`، `resources/views/layouts/partials/*.blade.php`، وملفات `admin/menus/partials/*.blade.php`.

يوجد ملف قديم غير مربوط بأي route حاليًا: `resources/views/welcome.blade.php`. لم يُسجّل كصفحة لأنه ليس مستخدمًا ولا يملك Controller أو route، وإدخاله في المنيو سينتج رابطًا وهميًا.
