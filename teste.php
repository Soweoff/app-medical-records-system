<?php

require __DIR__ . '/config/bootstrap.php';

use App\Models\ClinicalCondition;

$condition = new ClinicalCondition([
    'name' => 'Diabetes'
]);

echo "Salvou?\n";
var_dump($condition->save());

echo "\nErros:\n";
var_dump($condition->errors());

echo "\nRegistros:\n";
var_dump(ClinicalCondition::all());
