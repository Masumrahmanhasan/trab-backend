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
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign(['permission_id']);
            $table->dropUnique(['permission_id', 'model_id', 'model_type']);
            $table->foreignId('team_id')->nullable()->after('id')->constrained('stores')->nullOnDelete();
            $table->unique(['permission_id', 'model_id', 'model_type', 'team_id'], 'model_has_permissions_permission_model_team_unique');
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign(['permission_id']);
            $table->dropForeign(['team_id']);
            $table->dropUnique(['permission_id', 'model_id', 'model_type', 'team_id']);
            $table->dropColumn('team_id');
            $table->unique(['permission_id', 'model_id', 'model_type']);
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();
        });
    }
};
