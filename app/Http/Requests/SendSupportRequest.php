<?php

namespace App\Http\Requests;

use App\Enums\Support\SupportTypeEnum;
use App\Services\Support\DTOs\SendSupportDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SendSupportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(SupportTypeEnum::class)],
            'description' => ['required', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', self::supportAttachmentFileRule()],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * Images, short videos, and common documents for support tickets.
     */
    private static function supportAttachmentFileRule(): File
    {    
        return File::types([
            // Images
            'image/jpg',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/heic',
            'image/heif',
            // Videos
            'video/mp4',
            'video/webm',
            'video/quicktime',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'text/csv',
        ])->max(20480);
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
