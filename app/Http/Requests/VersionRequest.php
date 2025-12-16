<?php

namespace App\Http\Requests;

use App\Enums\VersionLanguageEnum;
use App\Services\Version\Factories\VersionImporterFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VersionRequest extends FormRequest
{
    public function rules(): array
    {
        $isStore = $this->isMethod('post');
        $versionId = $this->route('version');

        return [
            'file' => [
                $isStore ? 'required' : 'prohibited',
                'file'
            ],
            'importer' => [
                $isStore ? 'required' : 'prohibited',
                'string',
                Rule::in(VersionImporterFactory::getAvailableImporters())
            ],
            'name' => [
                'required',
                'string',
                Rule::unique('versions', 'name')->where('language', $this->input('language'))->ignore($versionId)
            ],
            'language' => [
                'required',
                'string',
                Rule::enum(VersionLanguageEnum::class)
            ],
            'copyright' => [
                'nullable',
                'string'
            ],
        ];
    }
}
