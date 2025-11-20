<?php

namespace Database\Factories;

use App\Models\Sprint;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'sprint_id' => Sprint::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => 'todo',
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'assigned_to' => null,
            'notes' => fake()->optional()->paragraph(),
            'order' => 0,
            'created_by' => User::factory(),
            'completed_at' => null,
        ];
    }

    public function backlog(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'backlog',
            'sprint_id' => null,
        ]);
    }

    public function todo(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'todo',
        ]);
    }
    public function inProgress(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }
    public function assignedTo(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }
    public function createdBy(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
    public function overdue(): static
    {
        return $this->state(fn(array $attributes) => [
            'due_date' => now()->subDays(2),
            'status' => 'todo', // the status can be in_progress as well
        ]);
    }

    public function withDueDate(): static
    {
        return $this->state(fn(array $attributes) => [
            'due_date' => now()->addDays(7),
        ]);
    }

    public function withNotes(): static
    {
        return $this->state(fn(array $attributes) => [
            'notes' => fake()->paragraphs(2, true),
        ]);
    }
    public function withDescription(): static
    {
        return $this->state(fn(array $attributes) => [
            'description' => fake()->paragraphs(3, true),
        ]);
    }

    // TODO: not sure if this is required 
    public function unscheduled(): static
    {
        return $this->state(fn(array $attributes) => [
            'sprint_id' => null,
        ]);
    }
}
