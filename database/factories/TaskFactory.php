<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Task>
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
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'assignee_id' => null,
            'creator_id' => User::factory(),
            'start_date' => fake()->optional()->date(),
            'due_date' => fake()->optional()->date(),
            'completed_at' => null,
        ];
    }

    /**
     * Set task status to todo.
     */
    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::TODO,
            'completed_at' => null,
        ]);
    }

    /**
     * Set task status to in_progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::IN_PROGRESS,
            'completed_at' => null,
        ]);
    }

    /**
     * Set task status to review.
     */
    public function review(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::REVIEW,
            'completed_at' => null,
        ]);
    }

    /**
     * Set task status to done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::DONE,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Assign task to a specific user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_id' => $user->id,
        ]);
    }

    /**
     * Assign task to a specific project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Set task as overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => Carbon::yesterday(),
            'status' => TaskStatus::TODO,
        ]);
    }
}
