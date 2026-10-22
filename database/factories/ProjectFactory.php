<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(ProjectStatus::cases());
        $priority = $this->faker->randomElement(ProjectPriority::cases());
        $startDate = $this->faker->boolean(80)
            ? $this->faker->dateTimeBetween('-6 months', 'now')
            : null;
        $endDate = $startDate !== null && $this->faker->boolean(70)
            ? $this->faker->dateTimeBetween($startDate, Carbon::parse($startDate)->addMonths(6))
            : null;

        return [
            'name' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->optional(0.7)->paragraph(),
            'status' => $status,
            'priority' => $priority,
            'start_date' => $startDate?->format('Y-m-d'),
            'end_date' => $endDate?->format('Y-m-d'),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the project is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::ACTIVE,
            'start_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the project is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::COMPLETED,
            'start_date' => $this->faker->dateTimeBetween('-1 year', '-6 months')->format('Y-m-d'),
            'end_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the project is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::ARCHIVED,
        ]);
    }

    /**
     * Indicate that the project has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => ProjectPriority::HIGH,
        ]);
    }
}
