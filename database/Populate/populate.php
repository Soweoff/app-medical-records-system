<?php

require __DIR__ . '/../../config/bootstrap.php';

use App\Models\ClinicalCondition;
use Core\Database\Database;
use Database\Populate\ExamTypesPopulate;
use Database\Populate\UsersPopulate;

Database::migrate();

UsersPopulate::populate();

ExamTypesPopulate::populate();

foreach (['Hipertensão', 'Diabetes', 'Asma'] as $conditionName) {
    if (!ClinicalCondition::exists(['name' => $conditionName])) {
        $condition = new ClinicalCondition(['name' => $conditionName]);
        $condition->save();
    }
}

