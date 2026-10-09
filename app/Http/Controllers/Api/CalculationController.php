<?php

namespace App\Http\Controllers\Api;

use App\Commission\CommissionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateCommissionRequest;
use App\Http\Resources\CalculationDetailResource;
use App\Models\Calculation;
use Illuminate\Http\JsonResponse;

class CalculationController extends Controller
{
    public function store(CalculateCommissionRequest $request, CommissionService $service): JsonResponse
    {
        $result = $service->calculate($request->toInput());

        return response()->json(['data' => $result->toArray()], 201);
    }

    public function show(Calculation $calculation): CalculationDetailResource
    {
        return new CalculationDetailResource(
            $calculation->load(['auditRecords', 'manualDecision.payer', 'manualDecision.payee', 'originalAudit'])
        );
    }
}
