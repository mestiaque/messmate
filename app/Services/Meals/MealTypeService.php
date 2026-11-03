<?php

namespace App\Services\Meals;

use App\Models\MealType;
use App\Models\Mess;
use App\Services\Audit\AuditLogger;

class MealTypeService
{
    public const DEFAULTS = [
        ['name' => 'Breakfast', 'code' => 'breakfast', 'unit_factor' => '0.500000', 'sort_order' => 1],
        ['name' => 'Lunch', 'code' => 'lunch', 'unit_factor' => '1.000000', 'sort_order' => 2],
        ['name' => 'Dinner', 'code' => 'dinner', 'unit_factor' => '1.000000', 'sort_order' => 3],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function createDefaults(Mess $mess): void
    {
        foreach (self::DEFAULTS as $attributes) {
            $this->create($mess, $attributes + ['default_enabled' => true, 'is_active' => true], audit: false);
        }
    }

    public function create(Mess $mess, array $data, bool $audit = true): MealType
    {
        $type = new MealType($data);
        $type->tenant_id = $mess->tenant_id;
        $type->mess_id = $mess->id;
        $type->save();

        if ($audit) {
            $this->audit->log('meal_type.created', $type, new: $type->only(['name', 'code', 'unit_factor']));
        }

        return $type;
    }

    /**
     * Factor changes affect only future entries; existing entries keep their snapshots.
     */
    public function update(MealType $type, array $data): MealType
    {
        $old = $type->only(array_keys($data));
        $type->update($data);
        $this->audit->log('meal_type.updated', $type, $old, $type->only(array_keys($data)));

        return $type;
    }
}
