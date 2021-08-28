<?php

namespace App\Http\Controllers;

use App\Services\LocalAuthorityService;
use App\Http\Requests\LocalAuthority\StoreRequest;
use App\Http\Requests\LocalAuthority\UpdateRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalAuthoritiesController extends Controller
{
    /**
     * Instance of servcie class for this class
     *
     * @var App\Services\LocalAuthorityService
     */
    protected $service;

    /**
     * @param App\Services\LocalAuthorityService $service
     * @return void
     */
    public function __construct(LocalAuthorityService $service)
    {
        $this->service = $service;
    }

    /**
     * Get listings of local authorities
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->all($request->all()), JsonResponse::HTTP_OK);
    }
    /**
     * Controller - Store a new event
     *
     * @param \App\Http\Requests\LocalAuthority\StoreRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->user()->id;

        if (!$this->service->store($data)) {
            return response()->json(['error' => $request->name .' could not be created.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Local Authority: '. $request->name .' has been created successfully.'
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Controller - Get a specified event
     *
     * @param \App\Models\LocalAuthority $localAuthority
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(LocalAuthority $localAuthority): JsonResponse
    {
        return response()->json($localAuthority, JsonResponse::HTTP_OK);
    }

    /**
     * Controller - Update a specified event
     *
     * @param \App\Http\Requests\LocalAuthority\UpdateRequest $request
     * @param \App\Models\LocalAuthority $localAuthority
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateRequest $request, LocalAuthority $localAuthority): JsonResponse
    {
        $data = $request->validated();
        $data['last_updated_by'] = auth()->user()->id;

        return response()->json([
            'message' => 'Local Authority: '. $localAuthority->name .' has been updated successfully.'
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Controller - Soft delete a specified event
     *
     * @param \App\Models\LocalAuthority $localAuthority
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(LocalAuthority $localAuthority): JsonResponse
    {
        $localAuthority->delete();

        return response()->json([
            'message' => 'Local Authority: '. $localAuthority->name .' has been deleted successfully.'
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Toggles the is_hidden property of a given local authority
     *
     * @param App\Models\LocalAuthority $localAuthority
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleIsHidden(LocalAuthority $localAuthority): JsonResponse
    {
        $this->service->toggleIsHidden($localAuthority);

        return response()->json(['message' => 'Changes saved successfully'], JsonResponse::HTTP_OK);
    }
}
