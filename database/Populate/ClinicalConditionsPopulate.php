<?php

namespace Database\Populate;

use App\Models\ClinicalCondition;

class ClinicalConditionsPopulate
{
    public static function populate(): void
    {        $conditions = [
            'Hipertensão',
            'Diabetes',
            'Asma'
        ];

        foreach ($conditions as $name) {
            if (!ClinicalCondition::exists(['name' => $name])) {
                (new ClinicalCondition([
                    'name' => $name
                ]))->save();
            }
        }
    }
}
