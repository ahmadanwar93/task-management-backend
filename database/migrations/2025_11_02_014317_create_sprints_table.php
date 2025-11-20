<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Nullable for eternal sprint
            $table->enum('status', ['planned', 'active', 'completed'])->default('planned');
            $table->boolean('is_eternal')->default(false);
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('status');
            $table->index(['workspace_id', 'status']); // Composite index for common queries that involve both workspace_id and status
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
