<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Workspace',
            'slug' => Workspace::generateUniqueSlug(),
            'owner_id' => User::factory(),
            'sprint_enabled' => false,
            'sprint_duration' => null,
        ];
    }

    /**
     * Indicate workspace has sprint mode enabled
     */
    public function withSprints(): static
    {
        return $this->state(fn(array $attributes) => [
            'sprint_enabled' => true,
            'sprint_duration' => 'weekly',
        ]);
    }

    /**
     * Indicate workspace has weekly sprints
     */
    public function weekly(): static
    {
        return $this->state(fn(array $attributes) => [
            'sprint_enabled' => true,
            'sprint_duration' => 'weekly',
        ]);
    }

    /**
     * Indicate workspace has biweekly sprints
     */
    public function biweekly(): static
    {
        return $this->state(fn(array $attributes) => [
            'sprint_enabled' => true,
            'sprint_duration' => 'biweekly',
        ]);
    }

    /**
     * Indicate workspace is in simple kanban mode (no sprints)
     */
    public function simpleMode(): static
    {
        return $this->state(fn(array $attributes) => [
            'sprint_enabled' => false,
            'sprint_duration' => null,
        ]);
    }
}
