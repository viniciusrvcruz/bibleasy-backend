<?php

use App\Enums\Support\SupportTypeEnum;
use App\Services\Support\DTOs\SendSupportDTO;
use App\Services\Support\Interfaces\SupportServiceInterface;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->mock(SupportServiceInterface::class, function ($mock) {
        $mock->shouldReceive('send')->never();
    });
});

describe('Send Support', function () {
    it('sends a support request successfully', function () {
        $this->mock(SupportServiceInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->with(Mockery::on(function (SendSupportDTO $dto) {
                    return $dto->type === SupportTypeEnum::BUG
                        && $dto->description === 'Something is broken'
                        && $dto->email === 'user@example.com'
                        && $dto->files === [];
                }));
        });

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'Something is broken',
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Support request sent successfully.']);
    });

    it('sends a support request with files', function () {
        $this->mock(SupportServiceInterface::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->with(Mockery::on(function (SendSupportDTO $dto) {
                    if ($dto->type !== SupportTypeEnum::FEATURE || count($dto->files) !== 1) {
                        return false;
                    }

                    return $dto->files[0]->getClientOriginalName() === 'screenshot.png';
                }));
        });

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::FEATURE->value,
            'description' => 'I want a new feature',
            'files' => [
                UploadedFile::fake()->create('screenshot.png', str_repeat('.', 1024)),
            ],
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Support request sent successfully.']);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/support', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'description']);
    });

    it('validates type enum', function () {
        $response = $this->postJson('/api/support', [
            'type' => 'invalid_type',
            'description' => 'test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('validates file size', function () {
        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'test',
            'files' => [
                UploadedFile::fake()->create('large.pdf', 21000),
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    });

    it('validates file mime types for support attachments', function () {
        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'test',
            'files' => [
                UploadedFile::fake()->create('script.exe', 100),
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    });

    it('validates email format', function () {
        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'test',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});
