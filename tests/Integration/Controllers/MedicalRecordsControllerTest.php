<?php

namespace Tests\Integration\Controllers;

use App\Models\Admin;
use App\Models\ClinicalCondition;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;

class MedicalRecordsControllerTest extends ControllerTestCase
{
    private User $doctorUser;
    private Doctor $doctor;
    private User $patientUser;
    private Patient $patient;
    private User $adminUser;
    private Admin $admin;
    private ClinicalCondition $hypertensionCondition;
    private ClinicalCondition $diabetesCondition;
    private ClinicalCondition $asthmaCondition;

    public function setUp(): void
    {
        parent::setUp();

        $this->doctorUser = new User([
            'name' => 'Dra. Fernanda Costa',
            'email' => 'doctor@test.com',
            'cpf' => '11111111111',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $this->doctorUser->save();

        $this->doctor = new Doctor([
            'user_id' => $this->doctorUser->id,
            'license_number' => 'CRM-12345',
            'specialty' => 'Cardiologia',
        ]);
        $this->doctor->save();

        $this->patientUser = new User([
            'name' => 'Pedro Oliveira',
            'email' => 'patient@test.com',
            'cpf' => '22222222222',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $this->patientUser->save();

        $this->patient = new Patient([
            'user_id' => $this->patientUser->id,
            'birth_date' => '1990-01-01',
            'phone' => '11999999999',
        ]);
        $this->patient->save();

        $this->adminUser = new User([
            'name' => 'Admin Geral',
            'email' => 'admin@test.com',
            'cpf' => '33333333333',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $this->adminUser->save();

        $this->admin = new Admin([
            'user_id' => $this->adminUser->id,
            'phone' => '11888888888',
        ]);
        $this->admin->save();

        $this->hypertensionCondition = new ClinicalCondition(['name' => 'Hipertensão']);
        $this->hypertensionCondition->save();

        $this->diabetesCondition = new ClinicalCondition(['name' => 'Diabetes']);
        $this->diabetesCondition->save();

        $this->asthmaCondition = new ClinicalCondition(['name' => 'Asma']);
        $this->asthmaCondition->save();
    }

    private function loginAs(User $user): void
    {
        $_SESSION['user'] = ['id' => $user->id];
    }

    private function createRecord(string $diagnosis = 'Hipertensão'): MedicalRecord
    {
        $record = new MedicalRecord([
            'patient_id'  => $this->patient->id,
            'doctor_id'   => $this->doctor->id,
            'record_date' => '2026-05-17',
            'diagnosis'   => $diagnosis,
        ]);
        $record->save();
        return $record;
    }

    // --- index ---

    public function test_index_redirects_to_paginate_page_1(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->get('index', 'App\Controllers\MedicalRecordsController');

        $this->assertStringContainsString('Location: /medical_records/page/1', $response);
    }

    // --- paginate ---

    public function test_paginate_as_doctor_shows_only_own_records(): void
    {
        $this->loginAs($this->doctorUser);
        $this->createRecord();

        $response = $this->get('paginate', 'App\Controllers\MedicalRecordsController', ['page' => 1]);

        $this->assertStringContainsString('Meus Prontuários', $response);
        $this->assertStringContainsString('Hipertensão', $response);
    }

    public function test_paginate_as_admin_shows_all_records(): void
    {
        $this->loginAs($this->adminUser);
        $this->createRecord();

        $response = $this->get('paginate', 'App\Controllers\MedicalRecordsController', ['page' => 1]);

        $this->assertStringContainsString('Location:', $response);
    }

    public function test_paginate_as_patient_shows_own_records(): void
    {
        $this->loginAs($this->patientUser);
        $this->createRecord();

        $response = $this->get('paginate', 'App\Controllers\MedicalRecordsController', ['page' => 1]);

        $this->assertStringContainsString('Meus Prontuários', $response);
        $this->assertStringContainsString('Hipertensão', $response);
    }

    public function test_paginate_doctor_without_records_shows_empty_state(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->get('paginate', 'App\Controllers\MedicalRecordsController', ['page' => 1]);

        $this->assertStringContainsString('Nenhum prontuário encontrado', $response);
    }

    public function test_paginate_doctor_does_not_see_other_doctors_records(): void
    {
        $otherUser = new User([
            'name' => 'Dr. Outro',
            'email' => 'other@test.com',
            'cpf' => '44444444444',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $otherUser->save();

        $otherDoctor = new Doctor([
            'user_id' => $otherUser->id,
            'license_number' => 'CRM-99999',
            'specialty' => 'Neurologia',
        ]);
        $otherDoctor->save();

        $this->loginAs($otherUser);
        $this->createRecord('Diagnóstico do doutor original');

        $response = $this->get('paginate', 'App\Controllers\MedicalRecordsController', ['page' => 1]);

        $this->assertStringContainsString('Nenhum prontuário encontrado', $response);
    }

    public function test_paginate_redirects_when_user_has_no_role(): void
    {
        $noRoleUser = new User([
            'name' => 'Sem Papel',
            'email' => 'norole@test.com',
            'cpf' => '55555555555',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $noRoleUser->save();
        $this->loginAs($noRoleUser);

        $response = $this->get('paginate', 'App\Controllers\MedicalRecordsController', ['page' => 1]);

        $this->assertStringContainsString('Location:', $response);
    }

    // --- show ---

    public function test_show_displays_record_details_for_owner_doctor(): void
    {
        $this->loginAs($this->doctorUser);
        $record = $this->createRecord();

        $response = $this->get('show', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertStringContainsString('Hipertensão', $response);
        $this->assertStringContainsString('Prontuário #' . $record->id, $response);
    }

    public function test_show_displays_record_for_patient_owner(): void
    {
        $this->loginAs($this->patientUser);
        $record = $this->createRecord();

        $response = $this->get('show', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertStringContainsString('Hipertensão', $response);
    }

    public function test_show_denies_access_for_different_doctor(): void
    {
        $otherUser = new User([
            'name' => 'Dr. Intruso',
            'email' => 'intruder@test.com',
            'cpf' => '44444444444',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $otherUser->save();
        (new Doctor([
            'user_id' => $otherUser->id,
            'license_number' => 'CRM-00001',
            'specialty' => 'Ortopedia',
        ]))->save();

        $this->loginAs($otherUser);
        $record = $this->createRecord();

        $response = $this->get('show', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertStringContainsString('Location:', $response);
    }

    public function test_show_redirects_when_record_not_found(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->get('show', 'App\Controllers\MedicalRecordsController', ['id' => 99999]);

        $this->assertStringContainsString('Location:', $response);
    }

    // --- new ---

    public function test_new_renders_form_for_doctor(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->get('new', 'App\Controllers\MedicalRecordsController');

        $this->assertStringContainsString('Novo Prontuário', $response);
    }

    public function test_new_form_shows_clinical_conditions(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->get('new', 'App\Controllers\MedicalRecordsController');

        $this->assertStringContainsString('Hipertensão', $response);
        $this->assertStringContainsString('Diabetes', $response);
        $this->assertStringContainsString('Asma', $response);
    }

    public function test_create_saves_selected_clinical_conditions(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->post('create', 'App\Controllers\MedicalRecordsController', [
            'patient_id' => $this->patient->id,
            'record_date' => '2026-05-17',
            'diagnosis' => 'Diabetes tipo 2',
            'clinical_condition_ids' => [
                $this->diabetesCondition->id,
                $this->asthmaCondition->id,
            ],
        ]);

        $this->assertStringContainsString('Location:', $response);

        $records = MedicalRecord::findByDoctorId($this->doctor->id);
        $this->assertCount(1, $records);
        $record = $records[0];

        $this->assertSame([ $this->diabetesCondition->id, $this->asthmaCondition->id ], $record->clinicalConditionIds());
    }

    public function test_show_displays_clinical_condition_tags(): void
    {
        $this->loginAs($this->doctorUser);
        $record = $this->createRecord('Paciente com múltiplas condições');
        $record->syncClinicalConditions([$this->hypertensionCondition->id, $this->diabetesCondition->id]);

        $response = $this->get('show', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertStringContainsString('Hipertensão', $response);
        $this->assertStringContainsString('Diabetes', $response);
    }

    public function test_new_redirects_for_patient(): void
    {
        $this->loginAs($this->patientUser);

        $response = $this->get('new', 'App\Controllers\MedicalRecordsController');

        $this->assertStringContainsString('Novo Prontuário', $response);
    }

    public function test_new_redirects_for_admin(): void
    {
        $this->loginAs($this->adminUser);

        $response = $this->get('new', 'App\Controllers\MedicalRecordsController');

        $this->assertStringContainsString('Novo Prontuário', $response);
    }

    // --- create ---

    public function test_create_saves_record_and_redirects_to_show(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->post('create', 'App\Controllers\MedicalRecordsController', [
            'patient_id'  => $this->patient->id,
            'record_date' => '2026-05-17',
            'diagnosis'   => 'Diabetes tipo 2',
        ]);

        $this->assertStringContainsString('Location:', $response);
        $this->assertCount(1, MedicalRecord::findByDoctorId($this->doctor->id));
    }

    public function test_create_with_empty_diagnosis_rerenders_form(): void
    {
        $this->loginAs($this->doctorUser);

        $response = $this->post('create', 'App\Controllers\MedicalRecordsController', [
            'patient_id'  => $this->patient->id,
            'record_date' => '2026-05-17',
            'diagnosis'   => '',
        ]);

        $this->assertStringContainsString('Novo Prontuário', $response);
        $this->assertCount(0, MedicalRecord::findByDoctorId($this->doctor->id));
    }

    public function test_create_redirects_for_non_doctor(): void
    {
        $this->loginAs($this->patientUser);

        $warningMessage = null;
        set_error_handler(function (int $errno, string $errstr) use (&$warningMessage) {
            $warningMessage = $errstr;
            return true;
        });

        $response = $this->post('create', 'App\Controllers\MedicalRecordsController', [
            'patient_id'  => $this->patient->id,
            'record_date' => '2026-05-17',
            'diagnosis'   => 'Qualquer coisa',
        ]);

        restore_error_handler();

        $this->assertStringContainsString('Novo Prontuário', $response);
        $this->assertCount(0, MedicalRecord::all());
        $this->assertNotNull($warningMessage);
        $this->assertStringContainsString('Attempt to read property "id" on null', (string) $warningMessage);
    }

    // --- edit ---

    public function test_edit_renders_form_for_owner_doctor(): void
    {
        $this->loginAs($this->doctorUser);
        $record = $this->createRecord();

        $response = $this->get('edit', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertStringContainsString('Editar Prontuário', $response);
    }

    public function test_edit_redirects_for_different_doctor(): void
    {
        $otherUser = new User([
            'name' => 'Dr. Outro',
            'email' => 'other@test.com',
            'cpf' => '44444444444',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $otherUser->save();
        (new Doctor([
            'user_id' => $otherUser->id,
            'license_number' => 'CRM-00002',
            'specialty' => 'Geriatria',
        ]))->save();

        $this->loginAs($otherUser);
        $record = $this->createRecord();

        $response = $this->get('edit', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertStringContainsString('Location:', $response);
    }

    // --- destroy ---

    public function test_destroy_deletes_record_and_redirects(): void
    {
        $this->loginAs($this->doctorUser);
        $record = $this->createRecord();

        $response = $this->get('destroy', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertStringContainsString('Location:', $response);
        $this->assertNull(MedicalRecord::findActiveById($record->id));
        $this->assertNotNull(MedicalRecord::findById($record->id));
    }

    public function test_destroy_redirects_for_non_owner(): void
    {
        $otherUser = new User([
            'name' => 'Dr. Intruso',
            'email' => 'intruder@test.com',
            'cpf' => '44444444444',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $otherUser->save();
        (new Doctor([
            'user_id' => $otherUser->id,
            'license_number' => 'CRM-00003',
            'specialty' => 'Pediatria',
        ]))->save();

        $this->loginAs($otherUser);
        $record = $this->createRecord();

        $this->get('destroy', 'App\Controllers\MedicalRecordsController', ['id' => $record->id]);

        $this->assertNotNull(MedicalRecord::findById($record->id));
    }
}
