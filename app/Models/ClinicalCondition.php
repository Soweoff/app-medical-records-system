<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsToMany;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * @property int $id
 * @property string $name
 */
class ClinicalCondition extends Model
{
    protected static string $table = 'clinical_conditions';
    protected static array $columns = ['name'];

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
        Validations::uniqueness('name', $this);
    }

    public function medicalRecords(): BelongsToMany
    {
        return $this->belongsToMany(MedicalRecord::class, 'clinical_condition_medical_records', 'clinical_condition_id', 'medical_record_id');
    }
}
