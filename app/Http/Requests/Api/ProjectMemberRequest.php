<?php

namespace App\Http\Requests\Api;

use App\Enums\ProjectRole;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('projects.manage_members') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('user') !== null;

        return [
            'user_id' => $isUpdate ? ['sometimes', 'integer', 'exists:users,id'] : ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', Rule::in(array_column(ProjectRole::assignable(), 'value'))],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return;
        }

        // Prevent adding the project owner as a member (they're already owner)
        if ($this->has('user_id') && (int) $this->input('user_id') === $project->owner_id) {
            $this->merge(['user_id' => null]); // Will fail validation
        }
    }
}
