# Implementation Task 3 - Build Settings System Report

## Task Title

Implementation Task 3 - Build Settings System

## Current Status

تم تحديث نظام الإعدادات من تخزين JSON خفيف إلى نظام ديناميكي يعتمد على قاعدة البيانات.

الواجهة الحالية في:

```text
admin/settings
```

تعرض الحقول من سجلات قاعدة البيانات، وتحفظ القيم في جدول مخصص.

## Main Objective

بناء صفحة إعدادات شبيهة بفكرة إعدادات WordPress العامة.

مع إضافة:

- إعدادات عامة للموقع.
- إعدادات أساسية للسيو.
- اختيار الصفحة الأمامية.
- دعم رفع صورة.
- تخزين كل القيم في قاعدة البيانات.
- توليد الحقول ديناميكياً من تعريفات مخزنة في قاعدة البيانات.

## Database Storage

القيم لا تخزن حالياً في ملف JSON.

القيم والتعريفات تخزن داخل جدول:

```text
platform_settings
```

ملف migration:

```text
database/migrations/2026_06_23_000001_create_platform_settings_table.php
```

الموديل:

```text
app/Platform/Core/Models/PlatformSetting.php
```

## Table Structure

جدول:

```text
platform_settings
```

يحتوي على الأعمدة التالية:

```text
id
group_key
setting_key
label
type
value
default_value
options
help_text
sort_order
is_public
created_at
updated_at
```

## Why This Is Dynamic

الواجهة لا تعتمد على حقول مكتوبة يدوياً داخل Blade لكل إعداد.

الواجهة تقرأ مجموعات الإعدادات والحقول من:

```php
SettingsRepository::all()
```

ثم تقوم بعرض كل حقل حسب نوعه.

أنواع الحقول المدعومة حالياً:

```text
text
email
url
textarea
boolean
select
radio
file
```

## Main Files

### Repository

```text
app/Platform/Core/Services/SettingsRepository.php
```

هذا هو مركز نظام الإعدادات.

هو المسؤول عن:

- مزامنة تعريفات الحقول مع قاعدة البيانات.
- قراءة القيم من جدول الإعدادات.
- تجهيز الحقول للعرض في الواجهة.
- تنظيف القيم قبل الحفظ.
- حفظ القيم في قاعدة البيانات.
- حفظ ملفات الصور في التخزين العام.
- قراءة خيارات الصفحة الأمامية ديناميكياً إذا وجدت صفحات منشورة.
- قراءة خيارات الأدوار من جدول roles إذا كان موجوداً.

### Model

```text
app/Platform/Core/Models/PlatformSetting.php
```

يمثل سجل واحد داخل جدول:

```text
platform_settings
```

### Controller

```text
app/Http/Controllers/Admin/SettingsController.php
```

هو المسؤول عن:

- عرض صفحة الإعدادات.
- استقبال طلب الحفظ.
- التحقق من الملفات والقيم.
- تمرير البيانات إلى Repository.

### View

```text
resources/views/admin/settings/index.blade.php
```

تعرض الواجهة بشكل ديناميكي حسب نوع كل حقل.

### Frontend Layouts

```text
resources/views/components/frontend-layout.blade.php
resources/views/layouts/frontend.blade.php
```

تقرأ إعدادات السيو والعنوان من قاعدة البيانات.

وتستخدمها في:

```text
title
meta description
meta keywords
meta robots
Open Graph title
Open Graph description
Open Graph image
site icon
```

### Main Web Route

```text
routes/web.php
```

الراوت الرئيسي:

```text
/
```

يقرأ إعداد:

```text
front_page.front_page_mode
front_page.front_page
```

إذا كان الوضع:

```text
static
```

وكانت الصفحة المختارة من Front Builder، يتم التحويل إلى الصفحة المنشورة المختارة.

## Functions Used

### SettingsController

```php
index(SettingsRepository $settings): View
```

تعرض صفحة الإعدادات.

تستخدم:

```php
$settings->all()
```

وترسل البيانات إلى الواجهة داخل:

```text
groups
```

```php
update(Request $request, SettingsRepository $settings): RedirectResponse
```

تحفظ الإعدادات.

تتحقق من:

```text
settings
files
remove_files
```

ثم تستدعي:

```php
$settings->update()
```

### SettingsRepository

```php
all(): array
```

ترجع مجموعات الإعدادات والحقول الجاهزة للعرض.

```php
update(array $input, array $files = [], array $removeFiles = []): void
```

تحفظ القيم داخل قاعدة البيانات.

وتتعامل مع رفع الصور وحذف الصور القديمة.

```php
path(): string
```

ترجع وصف مكان التخزين الحالي:

```text
Database table: platform_settings
```

```php
values(): array
```

ترجع القيم الحالية كمصفوفة مسطحة يمكن استخدامها لاحقاً في الواجهة العامة أو السيو.

```php
syncDefinitions(): void
```

تزامن تعريفات الحقول مع جدول:

```text
platform_settings
```

```php
fieldPayload(PlatformSetting $setting): array
```

تجهز بيانات الحقل للعرض في Blade.

```php
normalizeValue(PlatformSetting $setting, mixed $value): mixed
```

تنظف القيم حسب نوع الحقل.

```php
resolvedOptions(PlatformSetting $setting): array
```

ترجع خيارات الحقول من نوع:

```text
select
radio
```

```php
frontPageOptions(): array
```

ترجع خيارات الصفحة الأمامية.

إذا وجد جدول:

```text
front_builder_pages
```

تظهر الصفحات المنشورة تلقائياً.

```php
roleOptions(): array
```

ترجع أدوار المستخدمين من جدول:

```text
roles
```

إذا كان الجدول موجوداً.

```php
deletePublicFile(string $url): void
```

تحذف ملفات الإعدادات القديمة من:

```text
storage/app/public/settings
```

فقط إذا كان المسار آمناً ويبدأ بـ:

```text
/storage/settings/
```

## Current Settings Groups

### General Settings

المجموعة:

```text
general
```

الحقول:

```text
site_title
tagline
site_icon
wordpress_address_url
site_address_url
admin_email
membership_enabled
default_user_role
site_language
timezone
date_format
custom_date_format
time_format
custom_time_format
week_starts_on
```

### SEO Settings

المجموعة:

```text
seo
```

الحقول:

```text
seo_title
seo_description
seo_keywords
robots_index
robots_follow
open_graph_title
open_graph_description
open_graph_image
```

### Front Page

المجموعة:

```text
front_page
```

الحقول:

```text
front_page_mode
front_page
```

## File Upload Storage

الصور مثل:

```text
site_icon
open_graph_image
```

تخزن في disk:

```text
public
```

داخل:

```text
storage/app/public/settings
```

وتظهر في الواجهة عبر:

```text
/storage/settings/...
```

الرابط:

```text
public/storage
```

موجود على السيرفر ويشير إلى:

```text
storage/app/public
```

## Routes

مسارات الإعدادات:

```text
GET admin/settings
PATCH admin/settings
```

أسماء المسارات:

```text
admin.settings.index
admin.settings.update
```

الحماية:

```text
auth
staff
permission:settings.manage
```

## Save Flow

### Step 1

المستخدم يفتح:

```text
admin/settings
```

### Step 2

Controller يستدعي:

```php
SettingsRepository::all()
```

### Step 3

Repository يتأكد أن تعريفات الحقول موجودة في:

```text
platform_settings
```

### Step 4

Blade يعرض الحقول حسب نوعها.

### Step 5

عند الحفظ، الطلب يذهب إلى:

```text
PATCH admin/settings
```

### Step 6

Controller يتحقق من القيم والملفات.

### Step 7

Repository ينظف القيم ويحفظها في:

```text
platform_settings
```

### Step 8

المستخدم يرجع إلى صفحة الإعدادات مع رسالة نجاح.

## Verification

تم تنفيذ التالي:

```text
php -l
```

للملفات الجديدة والمعدلة.

تم تشغيل migration:

```text
php artisan migrate --force
```

تم إنشاء الجدول:

```text
platform_settings
```

تمت مزامنة تعريفات الإعدادات.

عدد الإعدادات الحالي:

```text
25
```

تم تشغيل:

```text
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

تم التحقق من الصفحة الرئيسية:

```text
HTTP 200
```

وتأكد ظهور:

```text
<title>
meta description
meta keywords
meta robots
```

## Backup

قبل تشغيل migration تم إنشاء checkpoint:

```text
settings.db_upgrade
```

الهدف:

```text
platform:settings
```

## Final Result

صفحة الإعدادات أصبحت ديناميكية وتعتمد على قاعدة البيانات.

تمت إضافة حقول عامة شبيهة بالصورة المطلوبة.

تمت إضافة حقول SEO أساسية.

تمت إضافة إعدادات Front Page.

تم دعم رفع صور للإعدادات.

تم الحفاظ على حماية الصفحة من خلال صلاحيات الإدارة.
