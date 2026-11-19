<?php

namespace App\Http\Requests\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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

        // Use the gate to check update permission - policy handles role-based checks
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
            $task = $this->route('task');
            $user = $this->user();

            if (! $task || ! $user) {
                return;
            }

            // Developer role restrictions
            if ($user->hasRole('developer')) {
                // Developer can only update tasks assigned to them
                if ($task->assignee_id !== $user->id) {
                    $validator->errors()->add('general', 'Role developer can only update tasks assigned to them.');
                }

                // Check for forbidden field updates
                $forbiddenDeveloperFields = ['title', 'description', 'priority', 'start_date', 'due_date', 'assignee_id', 'project_id'];

                foreach (array_keys($this->all()) as $field) {
                    if (in_array($field, $forbiddenDeveloperFields)) {
                        $validator->errors()->add($field, "Role developer cannot update '{$field}'.");
                    }
                }

                // Developer is forbidden from setting status to DONE
                if ($this->has('status') && $this->input('status') === TaskStatus::DONE->value) {
                    $validator->errors()->add('status', 'Role developer cannot set task status to "Done".');
                }
            }

            // Reviewer role restrictions
            if ($user->hasRole('reviewer')) {
                // Reviewer can only update the status field
                $submittedFields = array_keys($this->all());

                // Check if non-status fields were submitted
                $nonStatusFields = array_diff($submittedFields, ['status']);
                foreach ($nonStatusFields as $field) {
                    $validator->errors()->add($field, 'Role reviewer can only update the status field.');
                }

                // Reviewer can only perform valid review status transitions
                if ($this->has('status')) {
                    $currentStatus = $task->status?->value ?? '';
                    $newStatus = $this->input('status');

                    // Valid review transitions
                    $validTransitions = [
                        'review' => ['done', 'in_progress'],
                        'in_progress' => ['review'],
                        'todo' => ['in_progress'],
                    ];

                    $allowedTransitions = $validTransitions[$currentStatus] ?? [];

                    if (! in_array($newStatus, $allowedTransitions)) {
                        $validator->errors()->add('status', "Role reviewer cannot transition from '{$currentStatus}' to '{$newStatus}'.");
                    }
                } else {
                    $validator->errors()->add('status', 'Role reviewer must update the status field.');
                }
            }
        });

        return $validator;
    }
}
