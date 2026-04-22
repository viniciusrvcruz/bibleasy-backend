<?php

use App\Enums\Support\SupportTypeEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.olie_flow.base_url' => 'https://api.olie-fake.com',
        'services.olie_flow.api_key' => 'fake-api-key',
        'services.olie_flow.step_id' => '100',
        'services.olie_flow.form_id' => '50',
        'services.olie_flow.edge_type_id' => '1',
        'services.olie_flow.edge_description_id' => '2',
        'services.olie_flow.edge_files_id' => '3',
    ]);
});

describe('Send Support', function () {
    it('sends a support request successfully', function () {
        Http::fake([
            '*/api/management/projects/quick-store' => Http::response([
                'response' => true,
                'project' => [
                    'id' => 'fake-project-id',
                    'funnel_steps' => [
                        [
                            'id' => 100,
                            'project_funnel_id' => 42,
                            'name' => 'Test Step',
                        ],
                    ],
                ],
            ]),
            '*/api/management/dynamic-forms/set-form-answers' => Http::response([
                'response' => true,
                'edges' => [],
            ]),
        ]);

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'Something is broken',
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Support request sent successfully.']);

        Http::assertSentCount(2);
    });

    it('sends a support request with files', function () {
        Http::fake([
            '*/api/management/projects/quick-store' => Http::response([
                'response' => true,
                'project' => [
                    'id' => 'fake-project-id',
                    'funnel_steps' => [
                        [
                            'id' => 100,
                            'project_funnel_id' => 42,
                            'name' => 'Test Step',
                        ],
                    ],
                ],
            ]),
            '*/api/management/dynamic-forms/set-form-answers' => Http::response([
                'response' => true,
                'edges' => [],
            ]),
        ]);

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::FEATURE->value,
            'description' => 'I want a new feature',
            'files' => [
                UploadedFile::fake()->create('screenshot.png', 1024),
            ],
        ]);

        $response->assertOk();

        Http::assertSentCount(2);
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

    it('validates email format', function () {
        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'test',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('handles project creation failure', function () {
        Http::fake([
            '*/api/management/projects/quick-store' => Http::response([
                'response' => false,
                'message' => 'error',
            ], 500),
        ]);

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'test',
        ]);

        $response->assertStatus(502);
    });

    it('handles funnel step not found', function () {
        Http::fake([
            '*/api/management/projects/quick-store' => Http::response([
                'response' => true,
                'project' => [
                    'id' => 'fake-project-id',
                    'funnel_steps' => [
                        [
                            'id' => 999,
                            'project_funnel_id' => 42,
                            'name' => 'Wrong Step',
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'test',
        ]);

        $response->assertStatus(422);
    });

    it('sends support without email', function () {
        Http::fake([
            '*/api/management/projects/quick-store' => Http::response([
                'response' => true,
                'project' => [
                    'id' => 'fake-project-id',
                    'funnel_steps' => [
                        [
                            'id' => 100,
                            'project_funnel_id' => 42,
                            'name' => 'Test Step',
                        ],
                    ],
                ],
            ]),
            '*/api/management/dynamic-forms/set-form-answers' => Http::response([
                'response' => true,
                'edges' => [],
            ]),
        ]);

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::QUESTION->value,
            'description' => 'How does this work?',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'set-form-answers')) {
                $answers = collect($request->data()['form_answers']);
                $descAnswer = $answers->firstWhere('id', 2);

                return $descAnswer['answer'] === 'How does this work?';
            }

            return true;
        });
    });

    it('appends email to description when provided', function () {
        Http::fake([
            '*/api/management/projects/quick-store' => Http::response([
                'response' => true,
                'project' => [
                    'id' => 'fake-project-id',
                    'funnel_steps' => [
                        [
                            'id' => 100,
                            'project_funnel_id' => 42,
                            'name' => 'Test Step',
                        ],
                    ],
                ],
            ]),
            '*/api/management/dynamic-forms/set-form-answers' => Http::response([
                'response' => true,
                'edges' => [],
            ]),
        ]);

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::BUG->value,
            'description' => 'Bug report',
            'email' => 'user@test.com',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'set-form-answers')) {
                $answers = collect($request->data()['form_answers']);
                $descAnswer = $answers->firstWhere('id', 2);

                return str_contains($descAnswer['answer'], 'Email: user@test.com');
            }

            return true;
        });
    });
});
