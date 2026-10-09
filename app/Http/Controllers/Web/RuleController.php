<?php

namespace App\Http\Controllers\Web;

use App\Commission\MasterData;
use App\Commission\RuleManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRuleRequest;
use App\Http\Requests\StoreRuleVersionRequest;
use App\Models\RuleCombination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RuleController extends Controller
{
    private const RELATIONS = ['segment', 'region', 'leadCompany', 'saleType', 'activeVersion.payer', 'activeVersion.payee'];

    public function index(): View
    {
        return view('rules.index', [
            'rules' => RuleCombination::with(self::RELATIONS)->withCount('versions')->orderBy('id')->get(),
        ]);
    }

    public function create(MasterData $masters): View
    {
        return view('rules.create', ['masters' => $masters->all()]);
    }

    public function store(StoreRuleRequest $request, RuleManagementService $rules): RedirectResponse
    {
        $data = $request->validated();
        $version = $rules->createRule(
            $data['segment'], $data['region'], $data['lead_company'], $data['sale_type'], $request->toVersionInput(),
        );

        return redirect()->route('rules.show', $version->rule_combination_id)
            ->with('status', "{$version->reference()} created and active.");
    }

    public function show(RuleCombination $rule, MasterData $masters): View
    {
        $rule->load([...self::RELATIONS, 'versions.payer', 'versions.payee'])->loadCount('versions');

        return view('rules.show', ['rule' => $rule, 'masters' => $masters->all()]);
    }

    public function storeVersion(RuleCombination $rule, StoreRuleVersionRequest $request, RuleManagementService $rules): RedirectResponse|JsonResponse
    {
        $version = $rules->createVersion($rule, $request->toVersionInput());
        $status = "{$version->reference()} created and now active. Earlier versions are kept unchanged.";

        // The update form asks for JSON. Every other caller still receives the redirect.
        if ($request->expectsJson()) {
            session()->flash('status', $status);

            return response()->json([
                'message' => $status,
                'redirect' => route('rules.show', $rule),
            ]);
        }

        return redirect()->route('rules.show', $rule)->with('status', $status);
    }

    public function deactivate(RuleCombination $rule, RuleManagementService $rules): RedirectResponse
    {
        $rules->deactivate($rule);

        return redirect()->route('rules.show', $rule)
            ->with('status', 'Rule deactivated. Matching calculations will now need manual review; history is unchanged.');
    }
}
