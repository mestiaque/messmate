<?php

namespace App\Http\Controllers\Meals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\MealTypeRequest;
use App\Models\MealType;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Meals\MealTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MealTypeController extends Controller
{
    public function __construct(private readonly MealTypeService $types) {}

    public function index(Tenant $tenant, Mess $mess): View
    {
        $this->authorize('manageMealTypes', $mess);

        return view('meal-types.index', [
            'mess' => $mess,
            'types' => $mess->mealTypes()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(MealTypeRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->types->create($mess, $this->payload($request));

        return back()->with('status', 'Meal type added.');
    }

    public function update(MealTypeRequest $request, Tenant $tenant, Mess $mess, MealType $mealType): RedirectResponse
    {
        $this->types->update($mealType, $this->payload($request));

        return back()->with('status', "{$mealType->name} updated. Existing entries keep their recorded factor.");
    }

    private function payload(MealTypeRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => strtolower($request->input('code')),
            'unit_factor' => $request->input('unit_factor'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'default_enabled' => $request->boolean('default_enabled'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
