<?php

namespace App\Http\Requests\Api;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('projects.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::in(array_column(ProjectStatus::options(), 'value'))],
            'priority' => ['sometimes', 'required', 'string', Rule::in(array_column(ProjectPriority::options(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'owner_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
        ];
    }

    public function messages(): array
    {
        return [
            'client_ids.required' => 'Proyek wajib memiliki minimal satu klien.',
            'client_ids.min' => 'Proyek wajib memiliki minimal satu klien.',
            'client_ids.*.exists' => 'Klien yang dipilih tidak valid atau tidak aktif.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $clientIds = (array) $this->input('client_ids', []);
            if (empty($clientIds)) {
                return;
            }

            $validClientCount = User::role(Roles::CLIENT)
                ->whereIn('id', $clientIds)
                ->count();

            if ($validClientCount !== count(array_unique($clientIds))) {
                $v->errors()->add('client_ids', 'Semua pengguna yang dipilih sebagai klien harus memiliki role client.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => ProjectStatus::DRAFT->value]);
        }
        if (! $this->has('priority')) {
            $this->merge(['priority' => ProjectPriority::MEDIUM->value]);
        }
        if (! $this->has('owner_id') && $this->user()?->can('projects.create')) {
            $this->merge(['owner_id' => $this->user()->id]);
        }

        // Accept single client_id or array client_ids
        if ($this->has('client_id') && ! $this->has('client_ids')) {
            $clientId = $this->input('client_id');
            $this->merge([
                'client_ids' => filled($clientId) ? [(int) $clientId] : [],
            ]);
        }
    }
}
