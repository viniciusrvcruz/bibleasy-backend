<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendSupportRequest;
use App\Services\Support\Interfaces\SupportServiceInterface;
use Illuminate\Http\JsonResponse;

class SupportController extends Controller
{
    public function __construct(
        private readonly SupportServiceInterface $supportService,
    ) {}

    public function send(SendSupportRequest $request): JsonResponse
    {
        $this->supportService->send($request->toDTO());

        return response()->json(['message' => 'Support request sent successfully.']);
    }
}
