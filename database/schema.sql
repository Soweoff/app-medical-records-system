SET foreign_key_checks = 0;

DROP TABLE IF EXISTS medical_records, appointments, admins, doctors, secretaries, patients, users, disease_medical_record, clinical_condition_medical_records, clinical_conditions, exams, exam_types;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(50) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    encrypted_password VARCHAR(255) NOT NULL,
    status BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_cpf (cpf)
) ENGINE=InnoDB;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    phone VARCHAR(11),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    license_number VARCHAR(20) NOT NULL,
    specialty VARCHAR(60) NOT NULL,
    created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_doctors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE secretaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_secretaries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    birth_date DATE NOT NULL,
    phone VARCHAR(11) NOT NULL,
    created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    secretary_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'canceled') NOT NULL DEFAULT 'scheduled',
    observation TEXT NULL,
    created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_appointments_patient_id FOREIGN KEY (patient_id) REFERENCES patients (id),
    CONSTRAINT fk_appointments_doctor_id FOREIGN KEY (doctor_id) REFERENCES doctors (id),
    CONSTRAINT fk_appointments_secretary_id FOREIGN KEY (secretary_id) REFERENCES secretaries (id)
) ENGINE=InnoDB;

CREATE TABLE medical_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT          NOT NULL,
    doctor_id       INT          NOT NULL,
    appointment_id  INT          NULL,
    record_date     DATE         NOT NULL,
    diagnosis       TEXT         NOT NULL,
    prescription    TEXT         NULL,
    notes           TEXT         NULL,
    deleted_at      DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_medical_records_patient
        FOREIGN KEY (patient_id)     REFERENCES patients(id)      ON DELETE CASCADE,
    CONSTRAINT fk_medical_records_doctor
        FOREIGN KEY (doctor_id)      REFERENCES doctors(id)       ON DELETE CASCADE,
    CONSTRAINT fk_medical_records_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE exam_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ai_prompt_template TEXT NOT NULL,
    expected_json_schema JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT NULL,
    upload_by INT NOT NULL, 
    exam_type_id INT NOT NULL,
    exam_date DATE NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    is_verified_by INT NULL, 
    source ENUM('upload', 'whatsapp') NOT NULL DEFAULT 'upload',
    file_path VARCHAR(255) NOT NULL,
    ai_status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    extracted_data_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_exams_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_exams_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    CONSTRAINT fk_exams_exam_type FOREIGN KEY (exam_type_id) REFERENCES exam_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_exams_upload_by FOREIGN KEY (upload_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_exams_verified_by FOREIGN KEY (is_verified_by) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE clinical_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clinical_conditions_name (name)
) ENGINE=InnoDB;

CREATE TABLE clinical_condition_medical_records (
    medical_record_id INT NOT NULL,
    clinical_condition_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (medical_record_id, clinical_condition_id),
    CONSTRAINT fk_ccmr_medical_record FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_ccmr_clinical_condition FOREIGN KEY (clinical_condition_id) REFERENCES clinical_conditions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET foreign_key_checks = 1;
