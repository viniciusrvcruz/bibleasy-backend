<?php

namespace App\Http\Requests;

use App\Enums\VersionLanguageEnum;
use App\Services\Version\Factories\VersionParserFactory;
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
            'parser' => [
                $isStore ? 'required' : 'prohibited',
                'string',
                Rule::in(VersionParserFactory::getAvailableFormats())
            ],
            'abbreviation' => [
                'required',
                'string',
                Rule::unique('versions', 'abbreviation')->where('language', $this->input('language'))->ignore($versionId)
            ],
            'name' => [
                'required',
                'string'
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
