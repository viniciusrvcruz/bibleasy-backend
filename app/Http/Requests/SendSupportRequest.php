<?php

namespace App\Http\Requests;

use App\Enums\Support\SupportTypeEnum;
use App\Services\Support\DTOs\SendSupportDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendSupportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(SupportTypeEnum::class)],
            'description' => ['required', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:20480'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function toDTO(): SendSupportDTO
    {
        return new SendSupportDTO(
            type: $this->enum('type', SupportTypeEnum::class),
            description: $this->input('description'),
            files: $this->file('files', []),
            userAgent: $this->userAgent() ?? '',
            ip: $this->ip() ?? '',
            email: $this->input('email'),
        );
    }
}
