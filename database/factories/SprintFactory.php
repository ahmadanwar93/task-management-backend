<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sprint>
 */
class SprintFactory extends Factory
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
            'name' => 'Sprint ' . fake()->numberBetween(1, 10),
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'is_eternal' => false,
        ];
    }

    /**
     * Indicate sprint is completed
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
            'end_date' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate sprint is planned (future)
     */
    public function planned(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'planned',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(14),
        ]);
    }

    /**
     * Indicate sprint is eternal (simple kanban mode)
     */
    public function eternal(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Ongoing',
            'is_eternal' => true,
            'start_date' => null,
            'end_date' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Indicate sprint is weekly (7 days)
     */
    public function weekly(): static
    {
        return $this->state(fn(array $attributes) => [
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);
    }

    /**
     * Indicate sprint is biweekly (14 days)
     */
    public function biweekly(): static
    {
        return $this->state(fn(array $attributes) => [
            'start_date' => now(),
            'end_date' => now()->addDays(14),
        ]);
    }

    /**
     * Indicate sprint is active
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }
}
