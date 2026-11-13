<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'project_id' => Project::factory(),
            'original_name' => Str::slug($name).'.pdf',
            'storage_path' => 'project-files/'.$this->faker->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(1024, 5_000_000),
            'uploaded_by' => User::factory(),
        ];
    }
}
