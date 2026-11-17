<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo (string) DB::table('ai_assistant_messages')
    ->where('role', 'system')
    ->latest('id')
    ->value('content');
