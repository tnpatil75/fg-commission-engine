<?php

namespace App\Http\Controllers\Web;

use App\Commission\CommissionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordManualDecisionRequest;
use App\Models\Calculation;
use Illuminate\Http\RedirectResponse;

class ManualDecisionController extends Controller
{
    public function store(Calculation $calculation, RecordManualDecisionRequest $request, CommissionService $service): RedirectResponse
    {
        $service->recordManualDecision($calculation->uuid, $request->toInput());

        return redirect()->route('calculations.show', $calculation)
            ->with('status', 'Manual decision recorded. The original outcome is preserved.');
    }
}
