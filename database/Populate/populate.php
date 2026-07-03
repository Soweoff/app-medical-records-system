<?php

require __DIR__ . '/../../config/bootstrap.php';

use Core\Database\Database;
use Database\Populate\ExamTypesPopulate;
use Database\Populate\UsersPopulate;
use Database\Populate\ClinicalConditionsPopulate;

Database::migrate();

UsersPopulate::populate();

ExamTypesPopulate::populate();

ClinicalConditionsPopulate::populate();
