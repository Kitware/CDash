<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Users who have logged in with LDAP, OAuth, or SAML can't log in with a CDash password.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD COLUMN password_login_disabled boolean NOT NULL DEFAULT false');
    }

    public function down(): void
    {
    }
};
