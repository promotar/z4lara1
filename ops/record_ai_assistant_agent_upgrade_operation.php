<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! Schema::hasTable('operation_logs')) {
    echo "operation_logs table missing\n";

    return;
}

$message = 'AI Assistant upgraded with multiline chat, uploads, stronger intent routing, permission-aware site/data tools, and working basic vision analysis.';
$exists = DB::table('operation_logs')
    ->where('operation_type', 'ai_assistant_agent_upgrade')
    ->where('message', $message)
    ->exists();

if (! $exists) {
    DB::table('operation_logs')->insert([
        'operation_type' => 'ai_assistant_agent_upgrade',
        'target_type' => 'plugin',
        'target_slug' => 'ai-assistant',
        'status' => 'completed',
        'message' => $message,
        'context' => json_encode([
            'module' => 'ai-assistant',
            'report' => 'AI-ASSISTANT-AGENT-UPGRADE-REPORT.md',
            'backup' => [
                'ai-assistant-agent-upgrade-backup',
                'ai-vision-worker-basic-analysis-backup',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'started_at' => now(),
        'finished_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

echo "operation recorded\n";
