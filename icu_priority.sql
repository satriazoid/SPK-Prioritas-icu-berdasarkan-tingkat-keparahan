-- ============================================================
-- ICU Priority System – Database Setup
-- Jalankan di phpMyAdmin atau laragon MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS icu_priority
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE icu_priority;

-- ── Tabel utama dataset ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS dataset_akumulasi (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       VARCHAR(20)   NOT NULL UNIQUE,
    age              DECIMAL(5,1)  DEFAULT NULL COMMENT 'Usia pasien (tahun)',
    bmi              DECIMAL(6,2)  DEFAULT NULL COMMENT 'Body Mass Index',
    d1_spo2_min      DECIMAL(6,2)  DEFAULT NULL COMMENT 'SpO2 minimum hari pertama (%)',
    d1_heartrate_min DECIMAL(7,2)  DEFAULT NULL COMMENT 'Heart rate minimum hari pertama (bpm)',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_age     (age),
    INDEX idx_spo2    (d1_spo2_min)
) ENGINE=InnoDB;

-- ── Tabel hasil kalkulasi SAW (opsional, untuk cache) ────────
CREATE TABLE IF NOT EXISTS hasil_saw (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  VARCHAR(20) NOT NULL,
    saw_score   DECIMAL(10,6),
    saw_rank    INT,
    priority    ENUM('KRITIS','TINGGI','SEDANG','RENDAH'),
    calc_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES dataset_akumulasi(patient_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ── Tabel hasil kalkulasi WP (opsional, untuk cache) ─────────
CREATE TABLE IF NOT EXISTS hasil_wp (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  VARCHAR(20) NOT NULL,
    s_value     DECIMAL(20,10),
    wp_score    DECIMAL(20,10),
    wp_rank     INT,
    priority    ENUM('KRITIS','TINGGI','SEDANG','RENDAH'),
    calc_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES dataset_akumulasi(patient_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ── Sample data 10 pasien demo ───────────────────────────────
INSERT IGNORE INTO dataset_akumulasi
    (patient_id, age, bmi, d1_spo2_min, d1_heartrate_min)
VALUES
    ('P001', 72, 18.5, 82,  45),
    ('P002', 55, 29.8, 91,  58),
    ('P003', 43, 22.1, 94,  62),
    ('P004', 68, 35.2, 87,  50),
    ('P005', 31, 24.5, 97,  70),
    ('P006', 80, 17.2, 79,  38),
    ('P007', 48, 31.0, 88,  55),
    ('P008', 60, 26.3, 92,  60),
    ('P009', 75, 19.0, 84,  42),
    ('P010', 52, 28.7, 90,  56);

-- ── Verifikasi ───────────────────────────────────────────────
SELECT 'Database siap ✓' AS status, COUNT(*) AS total_pasien
FROM dataset_akumulasi;