<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('project', 'emailadministrator')) {
            Schema::dropColumns('project', 'emailadministrator');
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('project', 'emailadministrator')) {
            Schema::table('project', function (Blueprint $table) {
                $table->tinyInteger('emailadministrator')->default(1);
            });
        }
    }
};
