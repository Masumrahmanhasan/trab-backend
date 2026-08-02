<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropUnique(['role_id', 'model_id', 'model_type']);
            $table->foreignId('team_id')->nullable()->after('id')->constrained('stores')->nullOnDelete();
            $table->unique(['role_id', 'model_id', 'model_type', 'team_id']);
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['role_id']);   // our re-added FK
            $table->dropForeign(['team_id']);
            $table->dropUnique(['role_id', 'model_id', 'model_type', 'team_id']);
            $table->dropColumn('team_id');
            $table->unique(['role_id', 'model_id', 'model_type']);
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
        });
    }
};
