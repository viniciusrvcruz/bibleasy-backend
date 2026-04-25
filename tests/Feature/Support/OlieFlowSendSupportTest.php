<?php

use App\Enums\Support\SupportTypeEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.olie_flow.base_url' => 'https://api.olie-fake.com/api/management',
        'services.olie_flow.api_key' => 'fake-api-key',
        'services.olie_flow.step_id' => '100',
        'services.olie_flow.form_id' => '50',
        'services.olie_flow.edge_type_id' => '1',
        'services.olie_flow.edge_description_id' => '2',
        'services.olie_flow.edge_files_id' => '3',
    ]);
});

describe('Send Support via OlieFlow', function () {
    it('calls OlieFlow when sending a support request successfully', function () {
        Http::fake([
            '*/projects/quick-store' => Http::response([
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
            '*/dynamic-forms/set-form-answers' => Http::response([
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

    it('uploads files and submits form answers to OlieFlow', function () {
        Http::fake([
            '*/projects/quick-store' => Http::response([
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
            '*/dynamic-forms-answers-file' => Http::response([
                'id' => 'fake-file-id',
                'name' => 'screenshot.png',
                'url' => 'https://example.com/file.png',
                'size' => 1024,
                'extension' => 'png',
                'file_id' => 'fake-file-id',
                'created_at' => '2026-04-23T21:40:49.595271Z',
            ]),
            '*/dynamic-forms/set-form-answers' => Http::response([
                'response' => true,
                'edges' => [],
            ]),
        ]);

        $response = $this->postJson('/api/support', [
            'type' => SupportTypeEnum::FEATURE->value,
            'description' => 'I want a new feature',
            'files' => [
                UploadedFile::fake()->create('screenshot.png', str_repeat('.', 1024)),
            ],
        ]);

        $response->assertOk();

        Http::assertSentCount(3);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'dynamic-forms-answers-file')
                && $request->hasFile('file', null, 'screenshot.png');
        });

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'set-form-answers')) {
                return false;
            }

            $answers = collect($request->data()['form_answers'] ?? []);
            $filesAnswer = $answers->firstWhere('id', 3);

            return isset($filesAnswer['answer'][0]['id'], $filesAnswer['answer'][0]['file_id'])
                && $filesAnswer['answer'][0]['id'] === 'fake-file-id';
        });
    });

    it('returns 502 when OlieFlow project creation fails', function () {
        Http::fake([
            '*/projects/quick-store' => Http::response([
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

    it('returns 422 when configured funnel step is missing from OlieFlow project', function () {
        Http::fake([
            '*/projects/quick-store' => Http::response([
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

    it('submits description without email suffix when email is omitted', function () {
        Http::fake([
            '*/projects/quick-store' => Http::response([
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
            '*/dynamic-forms/set-form-answers' => Http::response([
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
            if (! str_contains($request->url(), 'set-form-answers')) {
                return false;
            }

            $answers = collect($request->data()['form_answers'] ?? []);
            $descAnswer = $answers->firstWhere('id', 2);

            return isset($descAnswer['answer']) && $descAnswer['answer'] === 'How does this work?';
        });
    });

    it('appends email to the description field sent to OlieFlow', function () {
        Http::fake([
            '*/projects/quick-store' => Http::response([
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
            '*/dynamic-forms/set-form-answers' => Http::response([
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
            if (! str_contains($request->url(), 'set-form-answers')) {
                return false;
            }

            $answers = collect($request->data()['form_answers'] ?? []);
            $descAnswer = $answers->firstWhere('id', 2);

            return isset($descAnswer['answer'])
                && str_contains($descAnswer['answer'], 'Email: user@test.com');
        });
    });
});
