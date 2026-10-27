<?php

namespace App\Http\Requests\Api;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $this->user()?->can('projects.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::in(array_column(ProjectStatus::options(), 'value'))],
            'priority' => ['sometimes', 'required', 'string', Rule::in(array_column(ProjectPriority::options(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'owner_id' => [
                'sometimes',
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
                    $ownerId = (int) $value;
                    $owner = User::find($ownerId);

                    if (! $owner) {
                        $fail('Pemilik proyek yang dipilih tidak valid.');

                        return;
                    }

                    // Keep allowing unrelated edits when the current owner is inactive.
                    if ($owner->is_active || ($project instanceof Project && $ownerId === $project->owner_id)) {
                        return;
                    }

                    $fail('Pemilik proyek harus pengguna yang aktif.');
                },
            ],
        ];
    }
}
