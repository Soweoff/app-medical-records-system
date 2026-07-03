<?php

namespace App\Models;

use App\Models\ClinicalCondition;
use App\Models\Doctor;
use App\Models\Patient;
use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\BelongsToMany;
use Core\Database\ActiveRecord\Model;
use Core\Database\Database;
use Lib\Validations;

/**
 * @property int $id
 * @property int $patient_id
 * @property int $doctor_id
 * @property int|null $appointment_id
 * @property string $record_date
 * @property string $diagnosis
 * @property string|null $prescription
 * @property string|null $notes
 */
class MedicalRecord extends Model
{
    protected static string $table = 'medical_records';

    protected static array $columns = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'record_date',
        'diagnosis',
        'prescription',
        'notes',
    ];

    public function validates(): void
    {
        Validations::notEmpty(
            'patient_id',
            $this,
            'Paciente não pode ser vazio!'
        );

        Validations::notEmpty(
            'doctor_id',
            $this,
            'Médico não pode ser vazio!'
        );

        Validations::notEmpty(
            'record_date',
            $this,
            'Data do prontuário não pode ser vazia!'
        );

        Validations::notEmpty(
            'diagnosis',
            $this,
            'Diagnóstico não pode ser vazio!'
        );
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            Patient::class,
            'patient_id'
        );
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(
            Doctor::class,
            'doctor_id'
        );
    }

    public function clinicalConditions(): BelongsToMany
    {
        return $this->belongsToMany(
            ClinicalCondition::class,
            'clinical_condition_medical_records',
            'medical_record_id',
            'clinical_condition_id'
        );
    }

    public function clinicalConditions(): BelongsToMany
    {
        return $this->belongsToMany(ClinicalCondition::class, 'clinical_condition_medical_records', 'medical_record_id', 'clinical_condition_id');
    }

    public function clinicalConditionIds(): array
    {
        if (empty($this->id)) {
            return [];
        }

        $conditions = $this->clinicalConditions()->get();
        return array_map(fn(ClinicalCondition $condition) => $condition->id, $conditions);
    }

    public function syncClinicalConditions(array $conditionIds): void
    {
        $pdo = Database::getDatabaseConn();
        $pdo->beginTransaction();

        try {
            $delete = $pdo->prepare('DELETE FROM clinical_condition_medical_records WHERE medical_record_id = :medical_record_id');
            $delete->execute(['medical_record_id' => $this->id]);

            $insert = $pdo->prepare('INSERT INTO clinical_condition_medical_records (medical_record_id, clinical_condition_id) VALUES (:medical_record_id, :clinical_condition_id)');
            foreach ($conditionIds as $conditionId) {
                $conditionId = (int) $conditionId;
                if ($conditionId <= 0) {
                    continue;
                }
                $insert->execute([
                    'medical_record_id' => $this->id,
                    'clinical_condition_id' => $conditionId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<MedicalRecord>
     */
    public static function findByPatientId(int $patientId): array
    {
        return self::where([
            'patient_id' => $patientId,
            'deleted_at' => null
        ]);
    }

    /**
     * @return array<MedicalRecord>
     */
    public static function findByDoctorId(int $doctorId): array
    {
        return self::where([
            'doctor_id' => $doctorId,
            'deleted_at' => null
        ]);
    }

    public static function findActiveById(int $id): ?self
    {
        $records = self::where([
            'id' => $id,
            'deleted_at' => null
        ]);

        return $records[0] ?? null;
    }

    /**
     * @param array<int, MedicalRecord> $records
     * @return array<int, array<string,mixed>>
     */
    public static function withUsers(array $records): array
    {
        $items = [];

        foreach ($records as $record) {

            /** @var Patient|null $patient */
            $patient = $record->patient()->get();

            /** @var Doctor|null $doctor */
            $doctor = $record->doctor()->get();

            $items[] = [
                'record'      => $record,
                'patient'     => $patient,
                'doctor'      => $doctor,
                'patientUser' => $patient ? $patient->user()->get() : null,
                'doctorUser'  => $doctor ? $doctor->user()->get() : null,
            ];
        }

        return $items;
    }
}
