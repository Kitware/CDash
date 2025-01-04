<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('label', function (Blueprint $table) {
            $table->unique(['id', 'text']);
            $table->unique(['text', 'id']);
        });

        $this->add_constraint('label2build', 'labelid', 'label', 'id');
        Schema::table('label2build', function (Blueprint $table) {
            $table->unique(['buildid', 'labelid']);
        });

        $this->add_constraint('label2buildfailure', 'labelid', 'label', 'id');
        $this->add_constraint('label2buildfailure', 'buildfailureid', 'buildfailure', 'id');

        $this->add_constraint('label2dynamicanalysis', 'labelid', 'label', 'id');
        Schema::table('label2dynamicanalysis', function (Blueprint $table) {
            $table->integer('dynamicanalysisid')->nullable(false)->change();
        });
        $this->add_constraint('label2dynamicanalysis', 'dynamicanalysisid', 'dynamicanalysis', 'id');

        $this->add_constraint('label2update', 'labelid', 'label', 'id');
        Schema::table('label2update', function (Blueprint $table) {
            $table->integer('updateid')->nullable(false)->change();
            $table->unique(['updateid', 'labelid']);
        });
        $this->add_constraint('label2update', 'updateid', 'buildupdate', 'id');

        $this->add_constraint('labelemail', 'labelid', 'label', 'id');
        Schema::table('labelemail', function (Blueprint $table) {
            $table->index(['userid', 'projectid', 'labelid']);
            $table->index(['userid', 'labelid', 'projectid']);
            $table->index(['projectid', 'userid', 'labelid']);
            $table->index(['projectid', 'labelid', 'userid']);
            $table->index(['labelid', 'projectid', 'userid']);
            $table->index(['labelid', 'userid', 'projectid']);
        });

        $this->add_constraint('label2coveragefile', 'labelid', 'label', 'id');
        Schema::table('label2coveragefile', function (Blueprint $table) {
            $table->integer('coveragefileid')->nullable(false)->change();
            // Note: primary key index with columns (labelid, buildid, coveragefileid) already exists
            $table->unique(['labelid', 'coveragefileid', 'buildid']);
            $table->unique(['buildid', 'labelid', 'coveragefileid']);
            $table->unique(['buildid', 'coveragefileid', 'labelid']);
            $table->unique(['coveragefileid', 'buildid', 'labelid']);
            $table->unique(['coveragefileid', 'labelid', 'buildid']);
        });
        $this->add_constraint('label2coveragefile', 'coveragefileid', 'coveragefile', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('label2coveragefile', function (Blueprint $table) {
            $table->dropForeign(['labelid']);
            $table->dropForeign(['coveragefileid']);
            $table->dropUnique(['labelid', 'coveragefileid', 'buildid']);
            $table->dropUnique(['buildid', 'labelid', 'coveragefileid']);
            $table->dropUnique(['buildid', 'coveragefileid', 'labelid']);
            $table->dropUnique(['coveragefileid', 'buildid', 'labelid']);
            $table->dropUnique(['coveragefileid', 'labelid', 'buildid']);
        });

        Schema::table('labelemail', function (Blueprint $table) {
            $table->dropForeign(['labelid']);
            $table->dropIndex(['userid', 'projectid', 'labelid']);
            $table->dropIndex(['userid', 'labelid', 'projectid']);
            $table->dropIndex(['projectid', 'userid', 'labelid']);
            $table->dropIndex(['projectid', 'labelid', 'userid']);
            $table->dropIndex(['labelid', 'projectid', 'userid']);
            $table->dropIndex(['labelid', 'userid', 'projectid']);
        });

        Schema::table('label2update', function (Blueprint $table) {
            $table->dropForeign(['labelid']);
            $table->dropForeign(['updateid']);
            $table->dropUnique(['updateid', 'labelid']);
        });

        Schema::table('label2dynamicanalysis', function (Blueprint $table) {
            $table->dropForeign(['labelid']);
            $table->dropForeign(['dynamicanalysisid']);
        });

        Schema::table('label2buildfailure', function (Blueprint $table) {
            $table->dropForeign(['labelid']);
            $table->dropForeign(['buildfailureid']);
        });

        Schema::table('label2build', function (Blueprint $table) {
            $table->dropForeign(['labelid']);
            $table->dropUnique(['buildid', 'labelid']);
        });

        Schema::table('label', function (Blueprint $table) {
            $table->dropUnique(['id', 'text']);
            $table->dropUnique(['text', 'id']);
        });
    }

    /**
     * Deletes invalid rows if they exist, and then adds the specified foreign-key constraint
     */
    private function add_constraint(string $table, string $column, string $related_table, string $related_column): void
    {
        echo "Adding foreign key constraint $table($column)->$related_table($related_column)...";
        $num_deleted = DB::delete("DELETE FROM $table WHERE $column NOT IN (SELECT $related_column FROM $related_table)");
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
        Schema::table($table, function (Blueprint $table) use ($related_table, $related_column, $column) {
            $table->foreign($column)->references($related_column)->on($related_table)->cascadeOnDelete();
        });
    }
};
