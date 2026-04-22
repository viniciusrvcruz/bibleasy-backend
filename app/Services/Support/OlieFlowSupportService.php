<?php

namespace App\Services\Support;

use App\Exceptions\Support\SupportException;
use App\Services\Support\DTOs\SendSupportDTO;
use App\Services\Support\Interfaces\SupportServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OlieFlowSupportService implements SupportServiceInterface
{
    private const CONFIG_PREFIX = 'services.olie_flow.';

    private string $baseUrl;
    private string $apiKey;
    private int $stepId;
    private int $formId;
    private int $edgeTypeId;
    private int $edgeDescriptionId;
    private int $edgeFilesId;

    public function __construct()
    {
        $this->baseUrl = $this->getRequiredConfig('base_url');
        $this->apiKey = $this->getRequiredConfig('api_key');
        $this->stepId = (int) $this->getRequiredConfig('step_id');
        $this->formId = (int) $this->getRequiredConfig('form_id');
        $this->edgeTypeId = (int) $this->getRequiredConfig('edge_type_id');
        $this->edgeDescriptionId = (int) $this->getRequiredConfig('edge_description_id');
        $this->edgeFilesId = (int) $this->getRequiredConfig('edge_files_id');
    }

    public function send(SendSupportDTO $dto): bool
    {
        $project = $this->createProject($dto);

        $funnelStep = collect($project['funnel_steps'])
            ->firstWhere('id', $this->stepId);

        if (! $funnelStep) {
            throw SupportException::resourceNotFound(
                "Funnel step with ID {$this->stepId} not found in project response."
            );
        }

        $projectFunnelId = $funnelStep['project_funnel_id'];
        $description = $dto->email
            ? $this->appendEmailToDescription($dto->email, $dto->description)
            : $dto->description;

        $this->submitForm($project['id'], $projectFunnelId, $dto, $description);

        return true;
    }

    private function createProject(SendSupportDTO $dto): array
    {
        $payload = [
            'name' => "[Support] [{$dto->type->value}] {$dto->ip}",
            'funnel_step_id' => $this->stepId,
        ];

        $response = $this->httpClient()
            ->post('/api/management/projects/quick-store', $payload);

        if ($response->failed()) {
            Log::error('OlieFlow: Failed to create project', [
                'payload' => $payload,
                'description' => $dto->description,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw SupportException::externalApiError(
                "Failed to create project on OlieFlow: {$response->body()}"
            );
        }

        return $response->json('project');
    }

    private function submitForm(
        string $projectId,
        int $projectFunnelId,
        SendSupportDTO $dto,
        string $description,
    ): void {
        $payload = [
            'target' => [
                'father_class' => 'project',
                'father_id' => $projectId,
                'pivot_class' => 'projectfunnelassignment',
                'form_id' => $this->formId,
                'project_funnel_id' => $projectFunnelId,
                'project_id' => $projectId,
            ],
            'form_answers' => [
                [
                    'id' => $this->edgeTypeId,
                    'answer' => $dto->type->value,
                ],
                [
                    'id' => $this->edgeDescriptionId,
                    'answer' => $description,
                ],
                [
                    'id' => $this->edgeFilesId,
                    'answer' => array_map(
                        fn ($file) => base64_encode($file->getContent()),
                        $dto->files
                    ),
                ],
            ],
        ];

        $response = $this->httpClient()
            ->post('/api/management/dynamic-forms/set-form-answers', $payload);

        if ($response->failed()) {
            Log::error('OlieFlow: Failed to submit form', [
                'payload' => $payload,
                'description' => $dto->description,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw SupportException::externalApiError(
                "Failed to submit form on OlieFlow: {$response->body()}"
            );
        }
    }

    private function appendEmailToDescription(string $email, string $description): string
    {
        return "{$description}\n\nEmail: {$email}";
    }

    private function httpClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey);
    }

    private function getRequiredConfig(string $key): string
    {
        $value = config(self::CONFIG_PREFIX . $key);

        if (empty($value)) {
            throw SupportException::missingConfiguration(
                "Missing required configuration: " . self::CONFIG_PREFIX . $key
            );
        }

        return $value;
    }
}
