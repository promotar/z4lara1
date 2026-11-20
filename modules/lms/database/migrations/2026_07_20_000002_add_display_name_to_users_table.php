<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // LMS profile names belong to LMS tables and must not extend Core users.
    }

    public function down(): void
    {
        // No Core schema changes are owned by LMS.
    }
};
