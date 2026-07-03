<?php

namespace App\Controllers;

use App\Models\Doctor;
use App\Models\ClinicalCondition;
use Core\Database\Database;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;
use Lib\Paginator;

class MedicalRecordsController extends Controller
{
    public function index(): void
    {
        $this->redirectTo(route('medical_records.paginate', ['page' => 1]));
    }

    public function paginate(Request $request): void
    {
        $page = max(1, (int) $request->getParam('page'));
        $user = $this->currentUser();

        if ($user->isDoctor()) {
            $doctor = $user->doctor();
            $conditions = ['doctor_id' => $doctor ? $doctor->id : 0, 'deleted_at' => null];
            $subtitle = 'Meus Prontuários';
        } elseif ($user->isPatient()) {
            $patient = $user->patient();
            $conditions = ['patient_id' => $patient ? $patient->id : 0, 'deleted_at' => null];
            $subtitle = 'Meus Prontuários';
        } else {
            FlashMessage::danger('Você não tem permissão para acessar prontuários.');
            $this->redirectTo(route('auth.check'));
            return;
        }

        $paginator = new Paginator(
            MedicalRecord::class,
            $page,
            10,
            MedicalRecord::table(),
            MedicalRecord::columns(),
            $conditions
        );

        $recordsWithUsers = MedicalRecord::withUsers($paginator->registers());

        $title = 'Prontuários Médicos';
        $this->render('medical_record/index', compact('title', 'subtitle', 'paginator', 'recordsWithUsers'));
    }

    public function show(Request $request): void
    {
        $medicalRecord = $this->findRecordOrRedirect($request);
        if (!$medicalRecord) {
            return;
        }


        if (!$this->canAccess($medicalRecord)) {
            FlashMessage::danger('Acesso negado a este prontuário.');
            $this->redirectTo(route('medical_records.index'));
            return;
        }

        $title  = 'Prontuário #' . $medicalRecord->id;
        $patient = $medicalRecord->patient()->get();
        $doctor  = $medicalRecord->doctor()->get();

        $this->render('medical_record/show', compact('title', 'medicalRecord', 'patient', 'doctor'));
    }

        public function new(): void
    {
        $medicalRecord = new MedicalRecord();
        $patientsWithUser = Patient::allWithUser();
        $clinicalConditions = ClinicalCondition::all();



        $title = 'Novo Prontuário';

        $this->render(
            'medical_record/new',
            compact(
                'title',
                'medicalRecord',
                'patientsWithUser',
                'clinicalConditions'
            )
        );
}

    public function create(Request $request): void
  {
      $doctor = $this->currentUser()->doctor();

      if (!$doctor) {
          FlashMessage::danger('Médico não encontrado.');
          $this->redirectTo(route('medical_records.index'));
          return;
      }

      $medicalRecord = new MedicalRecord([
          'patient_id'     => $request->getParam('patient_id'),
          'doctor_id'      => $doctor->id,
          'appointment_id' => $request->getParam('appointment_id') ?: null,
          'record_date'    => $request->getParam('record_date'),
          'diagnosis'      => $request->getParam('diagnosis'),
          'prescription'   => $request->getParam('prescription') ?: null,
          'notes'          => $request->getParam('notes') ?: null,
      ]);

      if (!$medicalRecord->save()) {
          FlashMessage::danger('Erro ao criar prontuário. Verifique os campos.');

          $patientsWithUser = Patient::allWithUser();
          $clinicalConditions = ClinicalCondition::all();
          $title = 'Novo Prontuário';

          $this->render(
              'medical_record/new',
              compact(
                  'title',
                  'medicalRecord',
                  'patientsWithUser',
                  'clinicalConditions'
              )
          );
          return;
      }

      $clinicalConditions = $request->getParam('clinical_conditions') ?? [];

      $pdo = Database::getDatabaseConn();

      $stmt = $pdo->prepare("
          INSERT INTO clinical_condition_medical_records
          (medical_record_id, clinical_condition_id)
          VALUES
          (:medical_record_id, :clinical_condition_id)
      ");

      foreach ($clinicalConditions as $conditionId) {
          $stmt->execute([
              ':medical_record_id' => $medicalRecord->id,
              ':clinical_condition_id' => $conditionId
          ]);
      }

      FlashMessage::success('Prontuário criado com sucesso!');
      $this->redirectTo(route('medical_records.show', ['id' => $medicalRecord->id]));
}

    public function edit(Request $request): void
    {
        $medicalRecord = $this->findRecordOrRedirect($request);
        if (!$medicalRecord) {
            return;
        }

        if (!$this->canEdit($medicalRecord)) {
            FlashMessage::danger('Você não tem permissão para editar este prontuário.');
            $this->redirectTo(route('medical_records.index'));
            return;
        }

          $title = 'Editar Prontuário #' . $medicalRecord->id;
          $patientsWithUser = Patient::allWithUser();
          $clinicalConditions = ClinicalCondition::all();

          $this->render(
              'medical_record/edit',
              compact(
                  'title',
                  'medicalRecord',
                  'patientsWithUser',
                  'clinicalConditions'
              )
          );
}

    public function update(Request $request): void
    {
        $medicalRecord = $this->findRecordOrRedirect($request);
        if (!$medicalRecord) {
            return;
        }

        if (!$this->canEdit($medicalRecord)) {
            FlashMessage::danger('Você não tem permissão para editar este prontuário.');
            $this->redirectTo(route('medical_records.index'));
            return;
        }

        $data = [
            'patient_id'    => $request->getParam('patient_id'),
            'appointment_id' => $request->getParam('appointment_id') ?: null,
            'record_date'   => $request->getParam('record_date'),
            'diagnosis'     => $request->getParam('diagnosis'),
            'prescription'  => $request->getParam('prescription') ?: null,
            'notes'         => $request->getParam('notes') ?: null,
        ];

        foreach ($data as $key => $value) {
            $medicalRecord->$key = $value;
        }

        if (!$medicalRecord->isValid()) {
          $patientsWithUser = Patient::allWithUser();
          $clinicalConditions = ClinicalCondition::all();
          $title = 'Editar Prontuário #' . $medicalRecord->id;

          $this->render(
              'medical_record/edit',
              compact(
                  'title',
                  'medicalRecord',
                  'patientsWithUser',
                  'clinicalConditions'
              )
          );
            return;
        }

        $medicalRecord->update($data);

        $pdo = Database::getDatabaseConn();

        $stmt = $pdo->prepare("
            DELETE FROM clinical_condition_medical_records
            WHERE medical_record_id = :id
        ");

        $stmt->execute([
            ':id' => $medicalRecord->id
        ]);

        $clinicalConditions = $request->getParam('clinical_conditions') ?? [];

        $stmt = $pdo->prepare("
            INSERT INTO clinical_condition_medical_records
            (medical_record_id, clinical_condition_id)
            VALUES
            (:medical_record_id, :clinical_condition_id)
        ");

        foreach ($clinicalConditions as $conditionId) {
            $stmt->execute([
                ':medical_record_id' => $medicalRecord->id,
                ':clinical_condition_id' => $conditionId
            ]);
        }

        FlashMessage::success('Prontuário atualizado com sucesso!');
        $this->redirectTo(route('medical_records.show', ['id' => $medicalRecord->id]));
    }

    public function destroy(Request $request): void
    {
        $medicalRecord = $this->findRecordOrRedirect($request);
        if (!$medicalRecord) {
            return;
        }

        if (!$this->canEdit($medicalRecord)) {
            FlashMessage::danger('Você não tem permissão para excluir este prontuário.');
            $this->redirectTo(route('medical_records.index'));
            return;
        }

        if ($medicalRecord->softDelete()) {
            FlashMessage::success('Prontuário excluído com sucesso!');
        } else {
            FlashMessage::danger('Não foi possível excluir o prontuário.');
        }

        $this->redirectTo(route('medical_records.index'));
    }

    private function findRecordOrRedirect(Request $request): ?MedicalRecord
    {
        $id     = (int) $request->getParam('id');
        $record = MedicalRecord::findActiveById($id);

        if (!$record) {
            FlashMessage::danger('Prontuário não encontrado.');
            $this->redirectTo(route('medical_records.index'));
            return null;
        }

        return $record;
    }

    private function canAccess(MedicalRecord $record): bool
    {
        $user = $this->currentUser();

        if ($user->isDoctor()) {
            $doctor = $user->doctor();
            return $doctor && (int)$record->doctor_id === (int)$doctor->id;
        }

        if ($user->isPatient()) {
            $patient = $user->patient();
            return $patient && (int)$record->patient_id === (int)$patient->id;
        }

        return false;
    }

    private function canEdit(MedicalRecord $record): bool
    {
        $user = $this->currentUser();
        $doctor = $user->doctor();
        return $doctor && (int)$record->doctor_id === $doctor->id;
    }
}
