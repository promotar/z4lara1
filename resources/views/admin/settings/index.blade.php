<x-app-layout>
    @php
        $siteLanguage = data_get($groups, 'general.fields.site_language.value', 'en');
        $isArabicLanguage = false;
        $translations = [
            'Settings' => 'الإعدادات',
            'General Settings' => 'الإعدادات العامة',
            'SEO Settings' => 'إعدادات السيو',
            'Front Page' => 'الصفحة الرئيسية',
            'Theme Settings' => 'إعدادات الثيم',
            'Site Title' => 'عنوان الموقع',
            'Tagline' => 'وصف الموقع',
            'Site Logo' => 'شعار الموقع',
            'Site Icon' => 'أيقونة الموقع',
            'Application Address URL' => 'رابط التطبيق',
            'Site Address URL' => 'رابط الموقع',
            'Administration Email Address' => 'بريد الإدارة',
            'Membership' => 'العضوية',
            'New User Default Role' => 'الدور الافتراضي للمستخدم الجديد',
            'Site Language' => 'لغة الموقع',
            'Timezone' => 'المنطقة الزمنية',
            'Date Format' => 'تنسيق التاريخ',
            'Custom Date Format' => 'تنسيق تاريخ مخصص',
            'Time Format' => 'تنسيق الوقت',
            'Custom Time Format' => 'تنسيق وقت مخصص',
            'Week Starts On' => 'بداية الأسبوع',
            'Default SEO Title' => 'عنوان السيو الافتراضي',
            'Default Meta Description' => 'وصف الميتا الافتراضي',
            'Default Meta Keywords' => 'كلمات الميتا الافتراضية',
            'Allow Search Engines To Index' => 'السماح لمحركات البحث بالأرشفة',
            'Allow Search Engines To Follow Links' => 'السماح لمحركات البحث بتتبع الروابط',
            'Open Graph Title' => 'عنوان المشاركة',
            'Open Graph Description' => 'وصف المشاركة',
            'Open Graph Image' => 'صورة المشاركة',
            'Homepage Displays' => 'عرض الصفحة الرئيسية',
            'Enabled' => 'مفعل',
            'Save Changes' => 'حفظ التغييرات',
            'Add Media' => 'إضافة صورة',
            'Change Site Logo' => 'تغيير شعار الموقع',
            'Remove Site Logo' => 'حذف شعار الموقع',
            'Change Site Icon' => 'تغيير أيقونة الموقع',
            'Remove Site Icon' => 'حذف أيقونة الموقع',
            'Choose a' => 'اختر',
            'Set as' => 'تعيين كـ',
            'Upload files' => 'رفع الملفات',
            'Media Library' => 'مكتبة الميديا',
            'Drop files to upload' => 'اسحب الملفات هنا للرفع',
            'or' => 'أو',
            'Select Files' => 'اختيار ملفات',
            'Suggested image dimensions: 512 by 512 pixels.' => 'الأبعاد المقترحة للصورة: 512 × 512 بكسل.',
            'Alternative Text' => 'النص البديل',
            'Title' => 'العنوان',
            'Caption' => 'التسمية التوضيحية',
            'Description' => 'الوصف',
            'Save SEO' => 'حفظ بيانات السيو',
            'Set Image' => 'تعيين الصورة',
            'No images are available in the media library yet.' => 'لا توجد صور متاحة في مكتبة الميديا حاليًا.',
            'Upload a PNG, JPG, or WEBP logo image. Recommended width: 240 pixels or larger.' => 'ارفع صورة شعار بصيغة PNG أو JPG أو WEBP. العرض المقترح: 240 بكسل أو أكثر.',
            'Upload a square PNG, JPG, WEBP, or ICO image. Recommended size: 512 x 512 pixels.' => 'ارفع صورة مربعة بصيغة PNG أو JPG أو WEBP أو ICO. المقاس المقترح: 512 × 512 بكسل.',
            'Allow anyone to register.' => 'السماح لأي شخص بالتسجيل.',
            'Default SEO metadata used by public pages when a page-specific value is not available.' => 'بيانات السيو الافتراضية المستخدمة في الصفحات العامة عند عدم وجود قيمة مخصصة للصفحة.',
            'Controls which page is used as the public landing page.' => 'تحديد الصفحة المستخدمة كواجهة رئيسية عامة للموقع.',
            'Recommended image size: 1200 x 630 pixels.' => 'مقاس الصورة المقترح: 1200 × 630 بكسل.',
            'If Front Builder pages exist, published pages will appear here automatically.' => 'إذا كانت صفحات Front Builder موجودة، ستظهر الصفحات المنشورة هنا تلقائيًا.',
            'Published platform pages appear here automatically.' => 'تظهر صفحات المنصة المنشورة هنا تلقائيًا.',
            'Arabic' => 'العربية',
            'English' => 'الإنجليزية',
            'Saturday' => 'السبت',
            'Sunday' => 'الأحد',
            'Monday' => 'الاثنين',
            'Default application home' => 'واجهة التطبيق الافتراضية',
            'A selected page' => 'صفحة محددة',
            'Default storefront home' => 'واجهة المتجر الافتراضية',
            'Custom' => 'مخصص',
            'Light Background' => 'خلفية الوضع النهاري',
            'Light Surface' => 'سطح الوضع النهاري',
            'Light Text' => 'نص الوضع النهاري',
            'Light Muted Text' => 'النص الثانوي النهاري',
            'Dark Background' => 'خلفية الوضع الليلي',
            'Dark Surface' => 'سطح الوضع الليلي',
            'Dark Text' => 'نص الوضع الليلي',
            'Dark Muted Text' => 'النص الثانوي الليلي',
            'Accent Color' => 'لون التمييز',
            'Controls public theme colors for day and night mode.' => 'التحكم بألوان الثيم العامة للوضع النهاري والليلي.',
        ];
        $translate = fn ($text) => $isArabicLanguage ? ($translations[$text] ?? $text) : $text;
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $translate('Settings') }}</h2>
    </x-slot>

    <style>
        .wp-settings {
            background: #f0f0f1;
            color: #1d2327;
        }

        .wp-settings section {
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .wp-settings h3 {
            color: #1d2327;
            font-size: 20px;
            font-weight: 400;
        }

        .wp-settings p,
        .wp-settings label {
            color: #50575e;
        }

        .wp-settings input[type="text"],
        .wp-settings input[type="email"],
        .wp-settings input[type="url"],
        .wp-settings input[type="file"],
        .wp-settings input[type="color"],
        .wp-settings select,
        .wp-settings textarea {
            border-color: #8c8f94;
            color: #2c3338;
            box-shadow: none;
            background-color: #ffffff;
        }

        .wp-settings input[type="text"]:focus,
        .wp-settings input[type="email"]:focus,
        .wp-settings input[type="url"]:focus,
        .wp-settings select:focus,
        .wp-settings textarea:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }

        .wp-settings input[type="checkbox"],
        .wp-settings input[type="radio"] {
            border-color: #8c8f94;
            color: #2271b1;
        }

        .wp-settings .color-field {
            align-items: center;
            display: flex;
            gap: 12px;
        }

        .wp-settings .color-field input[type="color"] {
            height: 38px;
            padding: 2px;
            width: 58px;
        }

        .wp-settings .color-field input[type="text"] {
            max-width: 120px;
        }

        .wp-settings code {
            background: #f0f0f1;
            color: #3c434a;
        }

        .wp-settings .format-options {
            background: transparent;
            border: 0;
            display: grid;
            gap: 10px;
            padding: 0;
        }

        .wp-settings .format-option {
            align-items: center;
            color: #2c3338;
            display: flex;
            gap: 10px;
            min-height: 24px;
        }

        .wp-settings .format-option input[type="radio"] {
            margin: 0;
        }

        .wp-settings .format-example {
            color: #2c3338;
            min-width: 132px;
        }

        .wp-settings .format-code {
            background: #e8e8e8;
            color: #3c434a;
            display: inline-block;
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
            line-height: 1.4;
            min-width: 52px;
            padding: 2px 7px;
            text-align: center;
        }

        .wp-settings .format-custom-input {
            border-color: #8c8f94;
            max-width: 70px;
            min-height: 30px;
            padding: 3px 6px;
        }

        .wp-settings .format-preview {
            color: #1d2327;
            font-size: 14px;
            margin-top: 10px;
        }

        .wp-settings button[type="submit"] {
            background: #3858e9;
            border-color: #3858e9;
            color: #ffffff;
        }

        .wp-settings button[type="submit"]:hover {
            background: #2145e6;
            border-color: #2145e6;
        }

        .wp-settings .settings-panel-header {
            border: 0;
            background: transparent;
            padding-left: 0;
            padding-right: 0;
            padding-bottom: 12px;
        }

        .wp-settings .settings-field-list {
            border-top: 1px solid #dcdcde;
        }

        .wp-settings .settings-field-row {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            column-gap: 32px;
            align-items: start;
            padding-left: 0;
            padding-right: 0;
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .wp-settings .settings-field-row > div {
            grid-column: auto !important;
        }

        .wp-settings .settings-field-row > div:first-child label {
            color: #1d2327;
            font-weight: 600;
        }

        .wp-settings .settings-field-row > div:last-child {
            max-width: 540px;
        }

        .wp-settings .settings-tab-bar {
            border-bottom: 1px solid #c3c4c7;
        }

        .wp-settings .settings-tab {
            border: 1px solid transparent;
            border-bottom: 0;
            color: #2271b1;
            margin-bottom: -1px;
            padding: 9px 14px;
            font-size: 14px;
            line-height: 1.4;
        }

        .wp-settings .settings-tab:hover {
            color: #135e96;
        }

        .wp-settings .settings-tab.is-active {
            background: #f0f0f1;
            border-color: #c3c4c7;
            color: #1d2327;
        }

        .wp-settings [data-settings-panel][hidden] {
            display: none !important;
        }

        .wp-settings [data-media-panel][hidden] {
            display: none !important;
        }

        .wp-settings .media-current {
            background: #ffffff;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            display: inline-flex;
            gap: 12px;
            padding: 10px;
            position: relative;
        }

        .wp-settings .media-current img {
            background: #f6f7f7;
            border-radius: 3px;
            height: 72px;
            object-fit: contain;
            width: 120px;
        }

        .wp-settings .media-remove {
            align-items: center;
            background: #d63638;
            border-radius: 9999px;
            color: #ffffff;
            display: inline-flex;
            font-size: 15px;
            font-weight: 700;
            height: 24px;
            justify-content: center;
            line-height: 1;
            position: absolute;
            right: -9px;
            top: -9px;
            width: 24px;
        }

        .wp-settings .media-button {
            background: #ffffff;
            border: 1px solid #2271b1;
            border-radius: 3px;
            color: #2271b1;
            display: flex;
            font-size: 13px;
            font-weight: 600;
            padding: 7px 12px;
            width: max-content;
        }

        .wp-settings .media-button-primary {
            border-color: #3858e9;
            color: #3858e9;
        }

        .wp-settings .media-button-danger {
            border-color: #d63638;
            color: #b32d2e;
        }

        .wp-settings .site-icon-preview {
            background: linear-gradient(90deg, #d8edf8 0%, #b4dff5 26%, #b8c0c7 57%, #a9adb2 100%);
            border: 1px solid #8c8f94;
            border-radius: 4px;
            display: flex;
            height: 86px;
            overflow: hidden;
            width: 350px;
        }

        .wp-settings .site-icon-large {
            align-items: center;
            display: flex;
            justify-content: center;
            width: 90px;
        }

        .wp-settings .site-icon-large img {
            height: 58px;
            object-fit: contain;
            width: 58px;
        }

        .wp-settings .site-icon-browser {
            align-items: center;
            display: flex;
            flex: 1;
            gap: 12px;
            padding: 16px 10px;
        }

        .wp-settings .site-icon-dots {
            display: flex;
            gap: 8px;
        }

        .wp-settings .site-icon-dots span {
            background: #858a90;
            border-radius: 9999px;
            display: block;
            height: 12px;
            width: 12px;
        }

        .wp-settings .site-icon-tab {
            align-items: center;
            background: #f6f7f7;
            border-radius: 5px;
            display: flex;
            gap: 9px;
            min-width: 160px;
            padding: 10px 12px;
        }

        .wp-settings .site-icon-tab img {
            height: 22px;
            object-fit: contain;
            width: 22px;
        }

        .wp-settings .site-icon-tab-title {
            color: #2c3338;
            flex: 1;
            font-size: 13px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wp-settings .site-icon-tab-close {
            color: #646970;
            font-size: 20px;
            line-height: 1;
        }

        .wp-settings .site-icon-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .wp-settings .site-logo-preview {
            align-items: center;
            background: #ffffff;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            display: flex;
            height: 96px;
            justify-content: center;
            padding: 16px;
            width: 350px;
        }

        .wp-settings .site-logo-preview img {
            max-height: 64px;
            max-width: 300px;
            object-fit: contain;
        }

        .wp-settings .site-logo-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .wp-settings .media-modal[hidden] {
            display: none !important;
        }

        .wp-settings .media-modal {
            align-items: center;
            background: rgba(0, 0, 0, 0.45);
            bottom: 0;
            display: flex;
            justify-content: center;
            left: 0;
            padding: 32px;
            position: fixed;
            right: 0;
            top: 0;
            z-index: 3000;
        }

        .wp-settings .media-modal-panel {
            background: #ffffff;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22);
            height: calc(100vh - 64px);
            max-height: 820px;
            max-width: 1280px;
            min-height: 560px;
            overflow: hidden;
            width: min(1280px, calc(100vw - 64px));
        }

        .wp-settings .media-modal-header {
            align-items: center;
            border-bottom: 1px solid #dcdcde;
            display: flex;
            justify-content: space-between;
            min-height: 68px;
            padding: 18px 22px;
        }

        .wp-settings .media-modal-header h3 {
            font-size: 22px;
            line-height: 1.2;
            margin: 0;
            overflow-wrap: anywhere;
        }

        .wp-settings .media-close {
            align-items: center;
            background: transparent;
            border: 0;
            color: #646970;
            display: inline-flex;
            font-size: 20px;
            height: 32px;
            justify-content: center;
            width: 32px;
        }

        .wp-settings .media-modal-tabs {
            background: #f6f7f7;
            border-bottom: 1px solid #dcdcde;
            display: flex;
            gap: 0;
            padding: 0 20px;
        }

        .wp-settings .media-modal-tabs .settings-tab {
            background: transparent;
            border: 0;
            border-bottom: 3px solid transparent;
            margin: 0;
            padding: 13px 16px 10px;
        }

        .wp-settings .media-modal-tabs .settings-tab.is-active {
            background: #ffffff;
            border: 1px solid #dcdcde;
            border-bottom: 3px solid #2271b1;
            border-top: 0;
            color: #1d2327;
        }

        .wp-settings .media-modal-body {
            flex: 1;
            overflow: auto;
            padding: 22px;
        }

        .wp-settings .media-upload-panel {
            align-items: center;
            border: 0;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            justify-content: center;
            min-height: 420px;
            text-align: center;
        }

        .wp-settings .media-upload-title {
            color: #1d2327;
            font-size: 22px;
            line-height: 1.3;
        }

        .wp-settings .media-modal-footer {
            align-items: center;
            background: #f6f7f7;
            border-top: 1px solid #dcdcde;
            display: flex;
            justify-content: flex-end;
            padding: 14px 16px;
        }

        .wp-settings .media-set-button {
            background: #2271b1;
            border: 1px solid #2271b1;
            border-radius: 3px;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 14px;
        }

        .wp-settings .media-set-button:disabled {
            background: #dcdcde;
            border-color: #dcdcde;
            color: #8c8f94;
            cursor: not-allowed;
        }

        .wp-settings .media-library-grid {
            align-content: start;
            align-items: start;
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            max-height: 420px;
            overflow: auto;
        }

        .wp-settings .media-library-layout {
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(0, 1fr) 340px;
        }

        .wp-settings .media-library-item {
            background: #ffffff;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            height: 132px;
            padding: 8px;
            text-align: left;
        }

        .wp-settings .media-library-item:hover {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }

        .wp-settings .media-library-item.is-selected {
            border-color: #2271b1;
            box-shadow: 0 0 0 2px #2271b1;
        }

        .wp-settings .media-library-item img {
            background: #f6f7f7;
            height: 114px;
            object-fit: contain;
            width: 100%;
        }

        .wp-settings .media-details {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            padding: 14px;
        }

        .wp-settings .media-details[hidden] {
            display: none !important;
        }

        .wp-settings .media-details-preview {
            background: #ffffff;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            margin-bottom: 14px;
            padding: 10px;
        }

        .wp-settings .media-details-preview img {
            max-height: 130px;
            object-fit: contain;
            width: 100%;
        }

        .wp-settings .media-details-field {
            display: block;
            margin-top: 12px;
        }

        .wp-settings .media-details-field span {
            color: #1d2327;
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .wp-settings .media-details-field input,
        .wp-settings .media-details-field textarea {
            width: 100%;
        }

        .wp-settings .media-details-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 14px;
        }

        @media (max-width: 767px) {
            .wp-settings .settings-field-row {
                grid-template-columns: 1fr;
                row-gap: 8px;
            }

            .wp-settings .media-library-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="wp-settings py-8">
        <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="settings-tab-bar flex flex-wrap gap-1">
                @foreach ($groups as $groupKey => $group)
                    <button
                        type="button"
                        class="settings-tab {{ $loop->first ? 'is-active' : '' }}"
                        data-settings-tab="{{ $groupKey }}"
                    >
                        {{ $translate($group['label']) }}
                    </button>
                @endforeach
            </div>

            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                @foreach ($groups as $groupKey => $group)
                    <section
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                        data-settings-panel="{{ $groupKey }}"
                        @unless ($loop->first) hidden @endunless
                    >
                        <div class="settings-panel-header border-b border-gray-200 bg-white px-6 py-5">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $translate($group['label']) }}</h3>
                            @if ($group['description'] !== '')
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">{{ $translate($group['description']) }}</p>
                            @endif
                        </div>

                        <div class="settings-field-list space-y-1">
                            @foreach ($group['fields'] as $fieldKey => $field)
                                @continue($groupKey === 'general' && in_array($fieldKey, ['custom_date_format', 'custom_time_format'], true))
                                <div class="settings-field-row grid gap-4 px-6 py-5 md:grid-cols-12 md:items-start">
                                    <div class="md:col-span-3">
                                        <x-input-label :for="$groupKey.'_'.$fieldKey" :value="$translate($field['label'])" />
                                        @if (! empty($field['help_text']))
                                            <p class="mt-2 text-xs leading-5 text-gray-500">{{ $translate($field['help_text']) }}</p>
                                        @endif
                                    </div>

                                    <div class="max-w-xl md:col-span-9">
                                        @if ($field['type'] === 'textarea')
                                            <textarea
                                                id="{{ $groupKey }}_{{ $fieldKey }}"
                                                name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                rows="4"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >{{ $field['value'] }}</textarea>
                                        @elseif ($field['type'] === 'boolean')
                                            <div class="flex items-center gap-3">
                                                <input type="hidden" name="settings[{{ $groupKey }}][{{ $fieldKey }}]" value="0">
                                                <input
                                                    id="{{ $groupKey }}_{{ $fieldKey }}"
                                                    type="checkbox"
                                                    name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value="1"
                                                    @checked($field['value'])
                                                    class="rounded border-gray-300 text-gray-900 shadow-sm"
                                                >
                                                <label for="{{ $groupKey }}_{{ $fieldKey }}" class="text-sm text-gray-700">
                                                    {{ $translate('Enabled') }}
                                                </label>
                                            </div>
                                        @elseif ($field['type'] === 'select')
                                            <select
                                                id="{{ $groupKey }}_{{ $fieldKey }}"
                                                name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $field['value'] === (string) $optionValue)>
                                                        {{ $translate($optionLabel) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif ($field['type'] === 'color')
                                            <div class="color-field">
                                                <input
                                                    id="{{ $groupKey }}_{{ $fieldKey }}"
                                                    type="color"
                                                    name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value="{{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $field['value']) ? $field['value'] : $field['default'] }}"
                                                    data-color-picker="{{ $groupKey }}_{{ $fieldKey }}"
                                                >
                                                <input
                                                    type="text"
                                                    value="{{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $field['value']) ? $field['value'] : $field['default'] }}"
                                                    class="rounded-md border-gray-300 shadow-sm"
                                                    data-color-text="{{ $groupKey }}_{{ $fieldKey }}"
                                                >
                                            </div>
                                        @elseif ($groupKey === 'general' && in_array($fieldKey, ['date_format', 'time_format'], true))
                                            @php
                                                $now = now();
                                                $isDateFormat = $fieldKey === 'date_format';
                                                $formatOptions = $isDateFormat
                                                    ? [
                                                        'F j, Y' => $now->format('F j, Y'),
                                                        'Y-m-d' => $now->format('Y-m-d'),
                                                        'm/d/Y' => $now->format('m/d/Y'),
                                                        'd/m/Y' => $now->format('d/m/Y'),
                                                        'd.m.Y' => $now->format('d.m.Y'),
                                                    ]
                                                    : [
                                                        'g:i a' => $now->format('g:i a'),
                                                        'g:i A' => $now->format('g:i A'),
                                                        'H:i' => $now->format('H:i'),
                                                    ];
                                                $currentFormat = (string) $field['value'];
                                                $customFormat = $isDateFormat
                                                    ? ($groups['general']['fields']['custom_date_format']['value'] ?? $currentFormat)
                                                    : ($groups['general']['fields']['custom_time_format']['value'] ?? $currentFormat);
                                                $customKey = $isDateFormat ? 'custom_date_format' : 'custom_time_format';
                                                $selectedIsCustom = ! array_key_exists($currentFormat, $formatOptions);
                                                $previewFormat = $selectedIsCustom ? $customFormat : $currentFormat;
                                            @endphp

                                            <div class="format-options" data-format-group="{{ $groupKey }}_{{ $fieldKey }}">
                                                @foreach ($formatOptions as $optionValue => $example)
                                                    <label class="format-option">
                                                        <input
                                                            type="radio"
                                                            name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                            value="{{ $optionValue }}"
                                                            @checked((string) $field['value'] === (string) $optionValue)
                                                            data-format-choice="{{ $groupKey }}_{{ $fieldKey }}"
                                                            data-format-preview="{{ $example }}"
                                                        >
                                                        <span class="format-example">{{ $example }}</span>
                                                        <code class="format-code">{{ $optionValue }}</code>
                                                    </label>
                                                @endforeach

                                                <label class="format-option">
                                                    <input
                                                        type="radio"
                                                        name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                        value="{{ $customFormat }}"
                                                        @checked($selectedIsCustom)
                                                        data-format-custom-radio="{{ $groupKey }}_{{ $fieldKey }}"
                                                        data-format-choice="{{ $groupKey }}_{{ $fieldKey }}"
                                                        data-format-preview="{{ $now->format($customFormat ?: ($isDateFormat ? 'F j, Y' : 'g:i a')) }}"
                                                    >
                                                    <span class="format-example">{{ $translate('Custom') }}:</span>
                                                    <input
                                                        type="text"
                                                        name="settings[{{ $groupKey }}][{{ $customKey }}]"
                                                        value="{{ $customFormat }}"
                                                        class="format-custom-input"
                                                        data-format-custom-input="{{ $groupKey }}_{{ $fieldKey }}"
                                                        data-format-hidden-target="{{ $groupKey }}_{{ $fieldKey }}"
                                                    >
                                                </label>

                                                <p class="format-preview">
                                                    <strong>Preview:</strong>
                                                    <span data-format-preview-output="{{ $groupKey }}_{{ $fieldKey }}">
                                                        {{ $now->format($previewFormat ?: ($isDateFormat ? 'F j, Y' : 'g:i a')) }}
                                                    </span>
                                                </p>
                                            </div>
                                        @elseif ($field['type'] === 'radio')
                                            <div class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                                    <label class="flex items-center gap-3 text-sm text-gray-700">
                                                        <input
                                                            type="radio"
                                                            name="settings[{{ $groupKey }}][{{ $fieldKey }}]"
                                                            value="{{ $optionValue }}"
                                                            @checked((string) $field['value'] === (string) $optionValue)
                                                            class="border-gray-300 text-gray-900 shadow-sm"
                                                        >
                                                        <span>{{ $translate($optionLabel) }}</span>
                                                        <code class="rounded bg-gray-100 px-2 py-1 text-xs">{{ $optionValue }}</code>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($field['type'] === 'file')
                                            <div class="space-y-4">
                                                @php
                                                    $siteDisplayTitle = $groups['general']['fields']['site_title']['value'] ?? 'Z4Rank';
                                                    $isSiteLogoField = $groupKey === 'general' && $fieldKey === 'site_logo';
                                                    $isSiteIconField = $groupKey === 'general' && $fieldKey === 'site_icon';
                                                    $isBrandMediaField = $isSiteLogoField || $isSiteIconField;
                                                    $hasCustomFileValue = (bool) ($field['has_custom_value'] ?? false);
                                                @endphp
                                                <input
                                                    type="hidden"
                                                    name="remove_files[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value="0"
                                                    data-remove-input="{{ $groupKey }}.{{ $fieldKey }}"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="media[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    value=""
                                                    data-media-input="{{ $groupKey }}.{{ $fieldKey }}"
                                                >
                                                @if ($field['value'])
                                                    @if ($isSiteLogoField)
                                                        <div class="site-logo-preview">
                                                            <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                        </div>
                                                    @elseif ($isSiteIconField)
                                                        <div class="site-icon-preview">
                                                            <div class="site-icon-large">
                                                                <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                            </div>
                                                            <div class="site-icon-browser">
                                                                <div class="site-icon-dots">
                                                                    <span></span>
                                                                    <span></span>
                                                                    <span></span>
                                                                </div>
                                                                <div class="site-icon-tab">
                                                                    <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                                    <span class="site-icon-tab-title">{{ $siteDisplayTitle }}</span>
                                                                    <span class="site-icon-tab-close">x</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="media-current">
                                                            <img src="{{ $field['value'] }}" alt="{{ $translate($field['label']) }}">
                                                            @if ($hasCustomFileValue)
                                                                <button
                                                                    type="button"
                                                                    class="media-remove"
                                                                    title="Remove"
                                                                    aria-label="Remove {{ $translate($field['label']) }}"
                                                                    data-remove-media="{{ $groupKey }}.{{ $fieldKey }}"
                                                                >x</button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif

                                                <input
                                                    id="{{ $groupKey }}_{{ $fieldKey }}"
                                                    type="file"
                                                    name="files[{{ $groupKey }}][{{ $fieldKey }}]"
                                                    accept=".png,.jpg,.jpeg,.webp,.ico"
                                                    class="sr-only"
                                                    data-file-input="{{ $groupKey }}.{{ $fieldKey }}"
                                                >
                                                <div class="{{ $isSiteLogoField && $field['value'] ? 'site-logo-actions' : ($isSiteIconField && $field['value'] ? 'site-icon-actions' : '') }}">
                                                    <button
                                                        type="button"
                                                        class="media-button {{ $isBrandMediaField && $field['value'] ? 'media-button-primary' : '' }}"
                                                        data-open-media="{{ $groupKey }}.{{ $fieldKey }}"
                                                        data-open-media-label="{{ $translate($field['label']) }}"
                                                    >
                                                        @if ($isSiteLogoField && $field['value'])
                                                            {{ $translate('Change Site Logo') }}
                                                        @elseif ($isSiteIconField && $field['value'])
                                                            {{ $translate('Change Site Icon') }}
                                                        @else
                                                            {{ $translate('Add Media') }}
                                                        @endif
                                                    </button>
                                                    @if ($isBrandMediaField && $field['value'] && $hasCustomFileValue)
                                                        <button
                                                            type="button"
                                                            class="media-button media-button-danger"
                                                            data-remove-media="{{ $groupKey }}.{{ $fieldKey }}"
                                                        >
                                                            {{ $translate($isSiteLogoField ? 'Remove Site Logo' : 'Remove Site Icon') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <x-text-input
                                                :id="$groupKey.'_'.$fieldKey"
                                                :type="$field['type'] === 'url' ? 'url' : ($field['type'] === 'email' ? 'email' : 'text')"
                                                :name="'settings['.$groupKey.']['.$fieldKey.']'"
                                                :value="$field['value']"
                                                class="block w-full focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="flex justify-end">
                    <x-primary-button>{{ $translate('Save Changes') }}</x-primary-button>
                </div>
            </form>

            <div class="media-modal" data-media-modal hidden>
                <div class="media-modal-panel">
                    <div class="media-modal-header">
                        <h3 data-media-modal-title>{{ $translate('Add Media') }}</h3>
                        <button type="button" class="media-close" data-close-media aria-label="Close">x</button>
                    </div>

                    <div class="media-modal-tabs">
                        <button type="button" class="settings-tab is-active" data-media-tab="upload">{{ $translate('Upload files') }}</button>
                        <button type="button" class="settings-tab" data-media-tab="library">{{ $translate('Media Library') }}</button>
                    </div>

                    <div class="media-modal-body">
                        <div class="media-upload-panel" data-media-panel="upload">
                            <div class="media-upload-title">{{ $translate('Drop files to upload') }}</div>
                            <div class="text-sm text-gray-600">{{ $translate('or') }}</div>
                            <button type="button" class="media-button" data-trigger-upload>{{ $translate('Select Files') }}</button>
                            <p class="text-sm text-gray-600">{{ $translate('Suggested image dimensions: 512 by 512 pixels.') }}</p>
                        </div>

                        <div data-media-panel="library" hidden>
                            @if (! empty($mediaLibrary))
                                <div class="media-library-layout">
                                    <div class="media-library-grid">
                                        @foreach ($mediaLibrary as $media)
                                            <button
                                                type="button"
                                                class="media-library-item"
                                                data-preview-media="{{ $media['url'] }}"
                                                data-media-alt="{{ $media['metadata']['alt_text'] ?? '' }}"
                                                data-media-title="{{ $media['metadata']['title'] ?? '' }}"
                                                data-media-caption="{{ $media['metadata']['caption'] ?? '' }}"
                                                data-media-description="{{ $media['metadata']['description'] ?? '' }}"
                                            >
                                                <img src="{{ $media['url'] }}" alt="{{ $media['metadata']['alt_text'] ?: 'Media image' }}">
                                            </button>
                                        @endforeach
                                    </div>

                                    <form method="POST" action="{{ route('admin.settings.media.update') }}" class="media-details" data-media-details hidden>
                                        @csrf
                                        @method('PATCH')

                                        <input type="hidden" name="media_url" value="" data-media-url-input>

                                        <div class="media-details-preview">
                                            <img src="" alt="" data-media-details-image>
                                        </div>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Alternative Text') }}</span>
                                            <textarea name="alt_text" rows="2" data-media-alt-input></textarea>
                                        </label>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Title') }}</span>
                                            <input type="text" name="title" data-media-title-input>
                                        </label>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Caption') }}</span>
                                            <textarea name="caption" rows="2" data-media-caption-input></textarea>
                                        </label>

                                        <label class="media-details-field">
                                            <span>{{ $translate('Description') }}</span>
                                            <textarea name="description" rows="3" data-media-description-input></textarea>
                                        </label>

                                        <div class="media-details-actions">
                                            <x-primary-button>{{ $translate('Save SEO') }}</x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            @else
                                <p class="text-sm text-gray-600">{{ $translate('No images are available in the media library yet.') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="media-modal-footer">
                        <button type="button" class="media-set-button" data-use-selected-media disabled>{{ $translate('Set Image') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = Array.from(document.querySelectorAll('[data-settings-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-settings-panel]'));
            const settingsForm = document.querySelector('form[action="{{ route('admin.settings.update') }}"]');
            const mediaModal = document.querySelector('[data-media-modal]');
            const mediaModalTitle = document.querySelector('[data-media-modal-title]');
            const mediaSetButton = document.querySelector('[data-use-selected-media]');
            const mediaDetails = document.querySelector('[data-media-details]');
            const mediaUrlInput = document.querySelector('[data-media-url-input]');
            const mediaDetailsImage = document.querySelector('[data-media-details-image]');
            const mediaAltInput = document.querySelector('[data-media-alt-input]');
            const mediaTitleInput = document.querySelector('[data-media-title-input]');
            const mediaCaptionInput = document.querySelector('[data-media-caption-input]');
            const mediaDescriptionInput = document.querySelector('[data-media-description-input]');
            const mediaTabs = Array.from(document.querySelectorAll('[data-media-tab]'));
            const mediaPanels = Array.from(document.querySelectorAll('[data-media-panel]'));
            const mediaCopy = {
                choosePrefix: @json($translate('Choose a')),
                setAsPrefix: @json($translate('Set as')),
            };
            let activeMediaKey = null;
            let selectedMediaUrl = null;

            const activateTab = (activeKey) => {
                tabs.forEach((tab) => {
                    tab.classList.toggle('is-active', tab.dataset.settingsTab === activeKey);
                });

                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.settingsPanel !== activeKey;
                });

                if (settingsForm) {
                    settingsForm.dataset.activeSettingsTab = activeKey;
                    settingsForm.action = settingsForm.action.split('#')[0] + `#settings-${activeKey}`;
                }

                history.replaceState(null, '', `#settings-${activeKey}`);
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => activateTab(tab.dataset.settingsTab));
            });

            const requestedTab = window.location.hash.replace('#settings-', '');
            const hasRequestedTab = tabs.some((tab) => tab.dataset.settingsTab === requestedTab);

            if (hasRequestedTab) {
                activateTab(requestedTab);
            } else if (tabs[0]) {
                activateTab(tabs[0].dataset.settingsTab);
            }

            const pad = (value) => String(value).padStart(2, '0');
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December',
            ];

            const formatPreviewDate = (format) => {
                const date = new Date();
                const hours = date.getHours();
                const hour12 = hours % 12 || 12;
                const replacements = {
                    F: monthNames[date.getMonth()],
                    j: String(date.getDate()),
                    Y: String(date.getFullYear()),
                    m: pad(date.getMonth() + 1),
                    d: pad(date.getDate()),
                    H: pad(hours),
                    g: String(hour12),
                    i: pad(date.getMinutes()),
                    a: hours >= 12 ? 'pm' : 'am',
                    A: hours >= 12 ? 'PM' : 'AM',
                };

                return String(format || '')
                    .split('')
                    .map((token) => replacements[token] ?? token)
                    .join('');
            };

            document.querySelectorAll('[data-format-choice]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    const output = document.querySelector(`[data-format-preview-output="${radio.dataset.formatChoice}"]`);

                    if (output) {
                        output.textContent = radio.dataset.formatPreview || formatPreviewDate(radio.value);
                    }
                });
            });

            document.querySelectorAll('[data-format-custom-input]').forEach((input) => {
                const formatKey = input.dataset.formatCustomInput;
                const customRadio = document.querySelector(`[data-format-custom-radio="${formatKey}"]`);
                const output = document.querySelector(`[data-format-preview-output="${formatKey}"]`);

                const updateCustomFormat = () => {
                    if (customRadio) {
                        customRadio.value = input.value;
                        customRadio.dataset.formatPreview = formatPreviewDate(input.value);
                        customRadio.checked = true;
                    }

                    if (output) {
                        output.textContent = formatPreviewDate(input.value);
                    }
                };

                input.addEventListener('focus', updateCustomFormat);
                input.addEventListener('input', updateCustomFormat);
            });

            document.querySelectorAll('[data-color-picker]').forEach((picker) => {
                const text = document.querySelector(`[data-color-text="${picker.dataset.colorPicker}"]`);

                if (!text) {
                    return;
                }

                picker.addEventListener('input', () => {
                    text.value = picker.value;
                });

                text.addEventListener('input', () => {
                    if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) {
                        picker.value = text.value;
                    }
                });
            });

            const submitSettings = () => {
                if (settingsForm) {
                    const activeTab = settingsForm.dataset.activeSettingsTab || tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.settingsTab || tabs[0]?.dataset.settingsTab;

                    if (activeTab) {
                        settingsForm.action = settingsForm.action.split('#')[0] + `#settings-${activeTab}`;
                    }

                    settingsForm.submit();
                }
            };

            if (settingsForm) {
                settingsForm.addEventListener('submit', () => {
                    const activeTab = settingsForm.dataset.activeSettingsTab || tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.settingsTab || tabs[0]?.dataset.settingsTab;

                    if (activeTab) {
                        settingsForm.action = settingsForm.action.split('#')[0] + `#settings-${activeTab}`;
                    }
                });
            }

            const openMediaModal = (mediaKey, label) => {
                activeMediaKey = mediaKey;
                selectedMediaUrl = null;
                mediaModalTitle.textContent = `${mediaCopy.choosePrefix} ${label}`;
                mediaSetButton.textContent = `${mediaCopy.setAsPrefix} ${label}`;
                mediaSetButton.disabled = true;
                mediaDetails.hidden = true;
                document.querySelectorAll('[data-preview-media]').forEach((button) => button.classList.remove('is-selected'));
                document.body.style.overflow = 'hidden';
                mediaModal.hidden = false;
            };

            const closeMediaModal = () => {
                mediaModal.hidden = true;
                activeMediaKey = null;
                selectedMediaUrl = null;
                document.body.style.overflow = '';
            };

            const activateMediaTab = (activeKey) => {
                mediaTabs.forEach((tab) => {
                    tab.classList.toggle('is-active', tab.dataset.mediaTab === activeKey);
                });

                mediaPanels.forEach((panel) => {
                    panel.hidden = panel.dataset.mediaPanel !== activeKey;
                });
            };

            document.querySelectorAll('[data-open-media]').forEach((button) => {
                button.addEventListener('click', () => openMediaModal(button.dataset.openMedia, button.dataset.openMediaLabel || 'Image'));
            });

            document.querySelectorAll('[data-close-media]').forEach((button) => {
                button.addEventListener('click', closeMediaModal);
            });

            mediaModal.addEventListener('click', (event) => {
                if (event.target === mediaModal) {
                    closeMediaModal();
                }
            });

            mediaTabs.forEach((tab) => {
                tab.addEventListener('click', () => activateMediaTab(tab.dataset.mediaTab));
            });

            document.querySelectorAll('[data-remove-media]').forEach((button) => {
                button.addEventListener('click', () => {
                    const removeInput = document.querySelector(`[data-remove-input="${button.dataset.removeMedia}"]`);

                    if (removeInput) {
                        removeInput.value = '1';
                        submitSettings();
                    }
                });
            });

            document.querySelectorAll('[data-file-input]').forEach((input) => {
                input.addEventListener('change', () => {
                    if (input.files.length > 0) {
                        submitSettings();
                    }
                });
            });

            document.querySelector('[data-trigger-upload]').addEventListener('click', () => {
                const fileInput = document.querySelector(`[data-file-input="${activeMediaKey}"]`);

                if (fileInput) {
                    fileInput.click();
                }
            });

            document.querySelectorAll('[data-preview-media]').forEach((button) => {
                button.addEventListener('click', () => {
                    selectedMediaUrl = button.dataset.previewMedia;

                    document.querySelectorAll('[data-preview-media]').forEach((item) => item.classList.remove('is-selected'));
                    button.classList.add('is-selected');

                    mediaUrlInput.value = selectedMediaUrl;
                    mediaDetailsImage.src = selectedMediaUrl;
                    mediaDetailsImage.alt = button.dataset.mediaAlt || '';
                    mediaAltInput.value = button.dataset.mediaAlt || '';
                    mediaTitleInput.value = button.dataset.mediaTitle || '';
                    mediaCaptionInput.value = button.dataset.mediaCaption || '';
                    mediaDescriptionInput.value = button.dataset.mediaDescription || '';
                    mediaSetButton.disabled = false;
                    mediaDetails.hidden = false;
                });
            });

            mediaSetButton.addEventListener('click', () => {
                const mediaInput = document.querySelector(`[data-media-input="${activeMediaKey}"]`);

                if (mediaInput && selectedMediaUrl) {
                    mediaInput.value = selectedMediaUrl;
                    submitSettings();
                }
            });
        });
    </script>
</x-app-layout>
