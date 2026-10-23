<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
class TaskActivityFactory extends Factory
{
    protected $model = TaskActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['task.created', 'task.updated', 'task.deleted', 'comment.created', 'comment.updated', 'comment.deleted'];
        $fields = ['status', 'priority', 'assignee_id', 'due_date', 'start_date'];

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'activity_type' => $this->faker->randomElement($types),
            'field' => $this->faker->optional()->randomElement($fields),
            'old_value' => $this->faker->optional()->word(),
            'new_value' => $this->faker->optional()->word(),
            'description' => $this->faker->sentence(),
        ];
    }
}
