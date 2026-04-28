<?php

namespace App\Services\Support;

use App\Exceptions\Support\SupportException;
use App\Services\Support\DTOs\SendSupportDTO;
use App\Services\Support\Interfaces\SupportServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
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
        $projectDetails = $this->getProjectDetails($project['id'], $dto);

        $funnelStep = collect($projectDetails['funnel_steps'])
            ->firstWhere('id', $this->stepId);

        if (! $funnelStep) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Funnel step not found in project response',
                ['step_id' => $this->stepId, 'project_id' => $project['id'] ?? null],
                SupportException::resourceNotFound(
                    "Funnel step with ID {$this->stepId} not found in project response."
                ),
                'warning'
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
            ->post('/projects/quick-store', $payload);

        if ($response->failed()) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Failed to create project',
                [
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ],
                SupportException::externalApiError(
                    "Failed to create project on OlieFlow: {$response->body()}"
                )
            );
        }

        return $response->json('project');
    }

    private function getProjectDetails(string $projectId, SendSupportDTO $dto): array
    {
        $response = $this->httpClient()
            ->get("/projects/{$projectId}");

        if ($response->failed()) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Failed to get project details',
                [
                    'project_id' => $projectId,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ],
                SupportException::externalApiError(
                    "Failed to get project details on OlieFlow: {$response->body()}"
                )
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
                    'answer' => $this->uploadDynamicFormAnswerFiles($dto),
                ],
            ],
        ];

        $response = $this->httpClient()
            ->post('/dynamic-forms/set-form-answers', $payload);

        if ($response->failed()) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Failed to submit form',
                [
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ],
                SupportException::externalApiError(
                    "Failed to submit form on OlieFlow: {$response->body()}"
                )
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function uploadDynamicFormAnswerFiles(SendSupportDTO $dto): array
    {
        $answers = [];

        foreach ($dto->files as $file) {
            $answers[] = $this->uploadDynamicFormAnswerFile($dto, $file);
        }

        return $answers;
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadDynamicFormAnswerFile(SendSupportDTO $dto, UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Could not resolve uploaded file path',
                ['filename' => $file->getClientOriginalName()],
                SupportException::externalApiError(
                    'Could not read uploaded file for OlieFlow.'
                )
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Could not read uploaded file contents',
                ['filename' => $file->getClientOriginalName()],
                SupportException::externalApiError(
                    'Could not read uploaded file contents for OlieFlow.'
                )
            );
        }

        $response = $this->httpClient()
            ->attach('file', $contents, $file->getClientOriginalName())
            ->post('/dynamic-forms-answers-file');

        if ($response->failed()) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Failed to upload dynamic form answer file',
                [
                    'filename' => $file->getClientOriginalName(),
                    'status' => $response->status(),
                    'response' => $response->json(),
                ],
                SupportException::externalApiError(
                    "Failed to upload file on OlieFlow: {$response->body()}"
                )
            );
        }

        $data = $response->json('file');

        if (! is_array($data)) {
            $this->logSupportFailure(
                $dto,
                'OlieFlow: Unexpected response when uploading file',
                ['filename' => $file->getClientOriginalName()],
                SupportException::externalApiError(
                    'Unexpected response when uploading file on OlieFlow.'
                )
            );
        }

        return $data;
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
                'Missing required configuration: ' . self::CONFIG_PREFIX . $key
            );
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function dtoLogContext(SendSupportDTO $dto): array
    {
        return [
            'support_type' => $dto->type->value,
            'description' => $dto->description,
            'ip' => $dto->ip,
            'user_agent' => $dto->userAgent,
            'email' => $dto->email,
            'file_names' => array_map(
                fn (UploadedFile $file) => $file->getClientOriginalName(),
                $dto->files
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logSupportFailure(
        SendSupportDTO $dto,
        string $message,
        array $context,
        SupportException $exception,
        string $level = 'error',
    ): never {
        $logContext = array_merge($this->dtoLogContext($dto), $context);

        match ($level) {
            'warning' => Log::warning($message, $logContext),
            default => Log::error($message, $logContext),
        };

        throw $exception;
    }
}
