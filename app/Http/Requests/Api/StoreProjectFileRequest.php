<?php

namespace App\Http\Requests\Api;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreProjectFileRequest extends FormRequest
{
    /**
     * The extensions accepted by the project file upload.
     *
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv',
        'zip',
    ];

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // mimes inspects the file content; extensions checks the client extension.
            'file' => [
                'required',
                File::types(self::ALLOWED_EXTENSIONS)
                    ->extensions(self::ALLOWED_EXTENSIONS)
                    ->max(25 * 1024),
            ],
        ];
    }
}
