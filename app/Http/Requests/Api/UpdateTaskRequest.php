<?php

namespace App\Http\Requests\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user()?->can('update', $task) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::in(array_column(TaskStatus::options(), 'value'))],
            'priority' => ['sometimes', 'required', 'string', Rule::in(array_column(TaskPriority::options(), 'value'))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
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
            if ($this->has('assignee_id') && $this->filled('assignee_id')) {
                $task = $this->route('task');
                $assigneeId = $this->integer('assignee_id');

                if ($task instanceof Task && $assigneeId && ! $task->project->hasMember(User::find($assigneeId))) {
                    $validator->errors()->add('assignee_id', 'Assignee must be a member of the project.');
                }
            }
        });

        return $validator;
    }
}
