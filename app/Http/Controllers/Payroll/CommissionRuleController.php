<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreCommissionRuleRequest;
use App\Http\Requests\Payroll\UpdateCommissionRuleRequest;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CommissionRuleController extends Controller
{
    public function index(): Response
    {
        $businessId = Auth::user()->business_id;

        $rules = CommissionRule::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->with(['employee.user', 'service'])
            ->orderByDesc('priority')
            ->orderByDesc('is_active')
            ->paginate(20);

        $employees = Employee::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name ?? "Employee #{$e->id}"])
            ->toArray();

        $services = Service::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->toArray();

        return Inertia::render('Payroll/CommissionRules/Index', [
            'rules' => [
                'data' => $rules->map(fn ($r) => $this->ruleToArray($r)),
                'meta' => [
                    'current_page' => $rules->currentPage(),
                    'last_page' => $rules->lastPage(),
                    'per_page' => $rules->perPage(),
                    'total' => $rules->total(),
                ],
            ],
            'employees' => $employees,
            'services' => $services,
        ]);
    }

    public function store(StoreCommissionRuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['business_id'] = Auth::user()->business_id;
        $data['priority'] = $this->priorityForScope($data['scope_type']);
        unset($data['scope_type']);

        CommissionRule::create($data);

        return back()->with('success', 'Regla de comisión creada.');
    }

    public function update(UpdateCommissionRuleRequest $request, CommissionRule $rule): RedirectResponse
    {
        $data = $request->validated();
        $data['priority'] = $this->priorityForScope($data['scope_type']);
        unset($data['scope_type']);

        $rule->update($data);

        return back()->with('success', 'Regla actualizada.');
    }

    public function destroy(CommissionRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        $action = $rule->is_active ? 'activada' : 'desactivada';

        return back()->with('success', "Regla {$action} correctamente.");
    }

    private function priorityForScope(string $scopeType): int
    {
        return match ($scopeType) {
            'specific' => 4,
            'per_employee' => 3,
            'per_service' => 2,
            default => 1,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleToArray(CommissionRule $rule): array
    {
        $scopeType = match (true) {
            $rule->employee_id !== null && $rule->service_id !== null => 'specific',
            $rule->employee_id !== null => 'per_employee',
            $rule->service_id !== null => 'per_service',
            default => 'global',
        };

        return [
            'id' => $rule->id,
            'scope_type' => $scopeType,
            'employee' => $rule->employee ? [
                'id' => $rule->employee->id,
                'name' => $rule->employee->user?->name,
            ] : null,
            'service' => $rule->service ? [
                'id' => $rule->service->id,
                'name' => $rule->service->name,
            ] : null,
            'type' => $rule->type,
            'value' => (string) $rule->value,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
            'effective_from' => $rule->effective_from?->toDateString(),
            'effective_until' => $rule->effective_until?->toDateString(),
        ];
    }
}
