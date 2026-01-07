<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VersionRequest;
use App\Http\Resources\VersionResource;
use App\Models\Version;
use App\Services\Version\Factories\VersionImportDTOFactory;
use App\Services\Version\VersionImportService;
use Illuminate\Http\Response;

class VersionController extends Controller
{
    public function index()
    {
        $versions = Version::query()
            ->when(request('language'), fn($q, $lang) => $q->where('language', $lang))
            ->get();

        return VersionResource::collection($versions);
    }

    public function store(VersionRequest $request, VersionImportService $service)
    {
        $dto = VersionImportDTOFactory::fromRequest($request);

        $version = $service->import($dto);

        return VersionResource::make($version)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(VersionRequest $request, Version $version)
    {
        $version->update($request->validated());

        return VersionResource::make($version);
    }

    public function destroy(Version $version)
    {
        $version->delete();

        return response()->noContent();
    }
}
