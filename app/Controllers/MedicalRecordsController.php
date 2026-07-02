<?php

namespace App\Controllers;

use App\Models\ClinicalCondition;
use App\Models\Doctor;
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
        $title         = 'Novo Prontuário';

        $this->render('medical_record/new', compact('title', 'medicalRecord', 'patientsWithUser', 'clinicalConditions'));
    }

    public function create(Request $request): void
    {
        $doctor = $this->currentUser()->doctor();

        $medicalRecord = new MedicalRecord([
            'patient_id'    => $request->getParam('patient_id'),
            'doctor_id'     => $doctor->id,  // sempre o médico logado
            'appointment_id' => $request->getParam('appointment_id') ?: null,
            'record_date'   => $request->getParam('record_date'),
            'diagnosis'     => $request->getParam('diagnosis'),
            'prescription'  => $request->getParam('prescription') ?: null,
            'notes'         => $request->getParam('notes') ?: null,
        ]);

        if ($medicalRecord->save()) {
            $clinicalConditionIds = $request->getParam('clinical_condition_ids') ?? [];
            if (!is_array($clinicalConditionIds)) {
                $clinicalConditionIds = $clinicalConditionIds ? [$clinicalConditionIds] : [];
            }
            $medicalRecord->syncClinicalConditions($clinicalConditionIds);

            FlashMessage::success('Prontuário criado com sucesso!');
            $this->redirectTo(route('medical_records.show', ['id' => $medicalRecord->id]));
        } else {
            // Validação falhou — reexibe o formulário com os erros
            FlashMessage::danger('Erro ao criar prontuário. Verifique os campos.');
            $patientsWithUser = Patient::allWithUser();
            $clinicalConditions = ClinicalCondition::all();
            $selectedClinicalConditionIds = $request->getParam('clinical_condition_ids') ?? [];
            if (!is_array($selectedClinicalConditionIds)) {
                $selectedClinicalConditionIds = $selectedClinicalConditionIds ? [$selectedClinicalConditionIds] : [];
            }
            $title = 'Novo Prontuário';
            $this->render('medical_record/new', compact('title', 'medicalRecord', 'patientsWithUser', 'clinicalConditions', 'selectedClinicalConditionIds'));
        }
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

        $patientsWithUser = Patient::allWithUser();
        $clinicalConditions = ClinicalCondition::all();
        $title = 'Editar Prontuário #' . $medicalRecord->id;

        $this->render('medical_record/edit', compact('title', 'medicalRecord', 'patientsWithUser', 'clinicalConditions'));
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
            $selectedClinicalConditionIds = $request->getParam('clinical_condition_ids') ?? [];
            if (!is_array($selectedClinicalConditionIds)) {
                $selectedClinicalConditionIds = $selectedClinicalConditionIds ? [$selectedClinicalConditionIds] : [];
            }
            $title = 'Editar Prontuário #' . $medicalRecord->id;
            $this->render('medical_record/edit', compact('title', 'medicalRecord', 'patientsWithUser', 'clinicalConditions', 'selectedClinicalConditionIds'));
            return;
        }

        $medicalRecord->update($data);

        $clinicalConditionIds = $request->getParam('clinical_condition_ids') ?? [];
        if (!is_array($clinicalConditionIds)) {
            $clinicalConditionIds = $clinicalConditionIds ? [$clinicalConditionIds] : [];
        }
        $medicalRecord->syncClinicalConditions($clinicalConditionIds);

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
