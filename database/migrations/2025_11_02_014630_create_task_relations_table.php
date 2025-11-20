<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('task_id');
            $table->index('related_task_id');
            $table->index('created_by');

            // Unique constraint to prevent duplicate relations
            $table->unique(['task_id', 'related_task_id'], 'unique_task_relation');
        });

        // Add check constraint to prevent self-referencing

        // remove this and do checking in the controller, CONSTRAINT does not work in sqlite
        // DB::statement('ALTER TABLE task_relations ADD CONSTRAINT check_not_self_reference CHECK (task_id != related_task_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_relations');
    }
};
