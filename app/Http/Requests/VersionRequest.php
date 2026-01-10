<?php

namespace App\Http\Requests;

use App\Enums\VersionLanguageEnum;
use App\Services\Version\Factories\VersionAdapterFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VersionRequest extends FormRequest
{
    public function rules(): array
    {
        $isStore = $this->isMethod('post');
        $versionId = $this->route('version');

        return [
            'files' => [
                $isStore ? 'required' : 'prohibited',
                'array',
                'min:1'
            ],
            'files.*' => [
                $isStore ? 'required' : 'prohibited',
                'file'
            ],
            'adapter' => [
                $isStore ? 'required' : 'prohibited',
                'string',
                Rule::in(VersionAdapterFactory::getAvailableAdapterNames())
            ],
            'abbreviation' => [
                'required',
                'string',
                'max:20',
                Rule::unique('versions', 'abbreviation')->where('language', $this->input('language'))->ignore($versionId)
            ],
            'name' => [
                'required',
                'string',
                'max:255'
            ],
            'language' => [
                'required',
                'string',
                Rule::enum(VersionLanguageEnum::class)
            ],
            'copyright' => [
                'nullable',
                'string',
                'max:2000'
            ],
        ];
    }
}
