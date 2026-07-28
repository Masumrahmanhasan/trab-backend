<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of available plans.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::active()
            ->with('features')
            ->get();

        return $this->ok('Plans retrieved successfully', PlanResource::collection($plans));
    }

    /**
     * Display the specified plan.
     */
    public function show(Plan $plan): JsonResponse
    {
        return $this->ok('Plan retrieved successfully', new PlanResource($plan->load('features')));
    }
}
