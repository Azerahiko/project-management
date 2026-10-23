<?php

namespace App\Http\Requests\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user()?->can('tasks.view')) {
            return false;
        }

        $projectId = $this->integer('project_id');

        // Only check project access when project_id is provided;
        // validation handles the required rule otherwise
        if ($projectId) {
            $project = Project::find($projectId);
            if (! $project) {
                return false;
            }

            return $this->user()->can('view', $project);
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::in(array_column(TaskStatus::options(), 'value'))],
            'priority' => ['sometimes', 'required', 'string', Rule::in(array_column(TaskPriority::options(), 'value'))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Default status and priority
        if (! $this->has('status')) {
            $this->merge(['status' => TaskStatus::TODO->value]);
        }
        if (! $this->has('priority')) {
            $this->merge(['priority' => TaskPriority::MEDIUM->value]);
        }

        // Set creator_id to current user
        if ($this->user() && ! $this->has('creator_id')) {
            $this->merge(['creator_id' => $this->user()->id]);
        }
    }

    /**
     * Get the validator instance for the request.
     *
     * @return Validator
     */
    protected function getValidatorInstance()
    {
        $validator = parent::getValidatorInstance();

        $validator->after(function ($validator) {
            if ($this->has('assignee_id') && $this->filled('assignee_id') && $this->filled('project_id')) {
                $project = Project::find($this->integer('project_id'));
                $assigneeId = $this->integer('assignee_id');

                if ($project && $assigneeId && ! $project->hasMember(User::find($assigneeId))) {
                    $validator->errors()->add('assignee_id', 'Assignee must be a member of the project.');
                }
            }
        });

        return $validator;
    }
}
