<?php

namespace App\Http\Controllers\Web;

use App\Commission\CommissionService;
use App\Commission\Data\CalculationResult;
use App\Commission\MasterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateCommissionRequest;
use App\Models\Calculation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CalculationController extends Controller
{
    public function create(MasterData $masters): View
    {
        $resultId = session('calculation_id');
        $calculation = $resultId ? Calculation::with('originalAudit')->where('uuid', $resultId)->first() : null;

        return view('calculations.create', [
            'masters' => $masters->all(),
            'result' => $calculation ? CalculationResult::fromModel($calculation) : null,
        ]);
    }

    public function store(CalculateCommissionRequest $request, CommissionService $service): RedirectResponse
    {
        $result = $service->calculate($request->toInput());

        return redirect()->route('calculate.create')
            ->withInput()
            ->with('calculation_id', $result->calculationId);
    }

    public function index(): View
    {
        return view('calculations.index', [
            'calculations' => Calculation::with(['originalAudit', 'manualDecision'])->latest('id')->paginate(25),
        ]);
    }

    public function show(Calculation $calculation, MasterData $masters): View
    {
        $calculation->load(['auditRecords', 'manualDecision', 'originalAudit']);

        return view('calculations.show', [
            'calculation' => $calculation,
            'result' => CalculationResult::fromModel($calculation),
            'masters' => $masters->all(),
        ]);
    }
}
