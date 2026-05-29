-- ============================================
-- SCRIPT PEMBUATAN DATABASE SI-PANCONG
-- Sistem Absensi Karyawan - Ck-Ck Coffee
-- Database: Oracle
-- 
-- Cara pakai:
--   1. Copy file ini ke container Docker Oracle
--      docker cp database_oracle.sql <container_name>:/tmp/
--   2. Masuk ke container
--      docker exec -it <container_name> bash
--   3. Jalankan via SQL*Plus
--      sqlplus SYSTEM/password123@//localhost:1521/XEPDB1 @/tmp/database_oracle.sql
-- ============================================

-- Matikan verifikasi agar script jalan tanpa prompt
SET VERIFY OFF
SET FEEDBACK ON
SET ECHO ON

PROMPT ========================================
PROMPT Memulai pembuatan database Si-Pancong...
PROMPT ========================================

-- ============================================
-- HAPUS OBJEK LAMA (JIKA ADA) - OPSIONAL
-- Uncomment bagian ini jika ingin reset database
-- ============================================
/*
BEGIN
    FOR c IN (SELECT table_name FROM user_tables WHERE table_name IN (
        'USER_SESSIONS','JADWAL_KHUSUS','PENGAJUAN_CUTI','TUKAR_SHIFT','JADWAL','ABSENSI','USERS','KARYAWAN','ADMIN'
    )) LOOP
        EXECUTE IMMEDIATE 'DROP TABLE ' || c.table_name || ' CASCADE CONSTRAINTS';
    END LOOP;
    
    FOR c IN (SELECT sequence_name FROM user_sequences WHERE sequence_name IN (
        'SEQ_ADMIN','SEQ_KARYAWAN','SEQ_USERS','SEQ_ABSENSI','SEQ_JADWAL','SEQ_TUKAR_SHIFT','SEQ_JADWAL_KHUSUS','SEQ_PENGAJUAN_CUTI','SEQ_USER_SESSIONS'
    )) LOOP
        EXECUTE IMMEDIATE 'DROP SEQUENCE ' || c.sequence_name;
    END LOOP;
END;
/
*/

-- ============================================
-- 1. SEQUENCES (Auto-increment pengganti)
-- ============================================

PROMPT Membuat sequences...

CREATE SEQUENCE seq_admin START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_karyawan START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_users START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_absensi START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_jadwal START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_tukar_shift START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_jadwal_khusus START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_pengajuan_cuti START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_user_sessions START WITH 1 INCREMENT BY 1 NOCACHE;

-- ============================================
-- 2. TABEL ADMIN
-- Menyimpan akun administrator
-- ============================================

PROMPT Membuat tabel ADMIN...

CREATE TABLE admin (
    id_admin    NUMBER          PRIMARY KEY,
    username    VARCHAR2(100)   NOT NULL UNIQUE,
    password    VARCHAR2(100)   NOT NULL,
    nama        VARCHAR2(200)
);

-- ============================================
-- 3. TABEL KARYAWAN
-- Data master karyawan
-- ============================================

PROMPT Membuat tabel KARYAWAN...

CREATE TABLE karyawan (
    id_karyawan     NUMBER          PRIMARY KEY,
    nama            VARCHAR2(200)   NOT NULL,
    jabatan         VARCHAR2(100)   NOT NULL,
    no_hp           VARCHAR2(20),
    email           VARCHAR2(200),
    alamat          CLOB,
    status          VARCHAR2(20)    DEFAULT 'Aktif',
    gaji_pokok      NUMBER(15,2)    DEFAULT 0,
    shift_default   VARCHAR2(20)
);

-- ============================================
-- 4. TABEL USERS
-- Akun login karyawan (1 karyawan = 1 user)
-- ============================================

PROMPT Membuat tabel USERS...

CREATE TABLE users (
    id_user         NUMBER          PRIMARY KEY,
    username        VARCHAR2(100)   NOT NULL UNIQUE,
    password        VARCHAR2(100)   NOT NULL,
    id_karyawan     NUMBER          NOT NULL,
    is_active       CHAR(1)         DEFAULT 'Y',
    CONSTRAINT fk_users_karyawan 
        FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan)
);

-- ============================================
-- 5. TABEL ABSENSI
-- Pencatatan absensi harian + foto + GPS
-- ============================================

PROMPT Membuat tabel ABSENSI...

CREATE TABLE absensi (
    id_absensi          NUMBER          PRIMARY KEY,
    id_karyawan         NUMBER          NOT NULL,
    tanggal             DATE            NOT NULL,
    jam_masuk           TIMESTAMP,
    jam_keluar          TIMESTAMP,
    status              VARCHAR2(20),
    terlambat           NUMBER          DEFAULT 0,
    foto_masuk          VARCHAR2(500),
    foto_pulang         VARCHAR2(500),
    latitude            NUMBER(15,10),
    longitude           NUMBER(15,10),
    latitude_pulang     NUMBER(15,10),
    longitude_pulang    NUMBER(15,10),
    alamat_masuk        CLOB,
    alamat_pulang       CLOB,
    total_jam           NUMBER(10,2),
    jam_lembur          NUMBER(10,2)    DEFAULT 0,
    keterangan          CLOB,
    CONSTRAINT fk_absensi_karyawan 
        FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan)
);

-- ============================================
-- 6. TABEL JADWAL
-- Jadwal shift kerja (approval oleh admin)
-- ============================================

PROMPT Membuat tabel JADWAL...

CREATE TABLE jadwal (
    id_jadwal       NUMBER          PRIMARY KEY,
    id_karyawan     NUMBER          NOT NULL,
    tanggal         DATE            NOT NULL,
    shift           VARCHAR2(20)    NOT NULL,
    jam_mulai       VARCHAR2(10),
    jam_selesai     VARCHAR2(10),
    keterangan      CLOB,
    status          VARCHAR2(20)    DEFAULT 'Menunggu',
    created_by      NUMBER,
    created_at      TIMESTAMP       DEFAULT SYSDATE,
    CONSTRAINT fk_jadwal_karyawan 
        FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan)
);

-- ============================================
-- 7. TABEL TUKAR_SHIFT
-- Pengajuan tukar shift antar karyawan
-- ============================================

PROMPT Membuat tabel TUKAR_SHIFT...

CREATE TABLE tukar_shift (
    id_tukar                NUMBER          PRIMARY KEY,
    id_karyawan_pengaju     NUMBER          NOT NULL,
    id_karyawan_tujuan      NUMBER          NOT NULL,
    shift_pengaju           VARCHAR2(20),
    shift_tujuan            VARCHAR2(20),
    tanggal                 DATE            NOT NULL,
    alasan                  CLOB,
    status                  VARCHAR2(20)    DEFAULT 'Menunggu',
    created_at              TIMESTAMP       DEFAULT SYSDATE,
    CONSTRAINT fk_tukar_pengaju 
        FOREIGN KEY (id_karyawan_pengaju) REFERENCES karyawan(id_karyawan),
    CONSTRAINT fk_tukar_tujuan 
        FOREIGN KEY (id_karyawan_tujuan) REFERENCES karyawan(id_karyawan)
);

-- ============================================
-- 8. TABEL JADWAL_KHUSUS
-- Jadwal otomatis dari tukar shift yang disetujui
-- ============================================

PROMPT Membuat tabel JADWAL_KHUSUS...

CREATE TABLE jadwal_khusus (
    id_jadwal_khusus    NUMBER          PRIMARY KEY,
    id_karyawan         NUMBER          NOT NULL,
    tanggal             DATE            NOT NULL,
    shift               VARCHAR2(20),
    jenis               VARCHAR2(50),
    id_tukar            NUMBER,
    CONSTRAINT fk_jk_karyawan 
        FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan),
    CONSTRAINT fk_jk_tukar 
        FOREIGN KEY (id_tukar) REFERENCES tukar_shift(id_tukar)
);

-- ============================================
-- 9. TABEL PENGAJUAN_CUTI
-- Pengajuan cuti/izin/sakit karyawan
-- ============================================

PROMPT Membuat tabel PENGAJUAN_CUTI...

CREATE TABLE pengajuan_cuti (
    id_pengajuan    NUMBER          PRIMARY KEY,
    id_karyawan     NUMBER          NOT NULL,
    jenis           VARCHAR2(20)    NOT NULL,
    tanggal_mulai   DATE            NOT NULL,
    tanggal_selesai DATE            NOT NULL,
    alasan          CLOB,
    status          VARCHAR2(20)    DEFAULT 'Menunggu',
    created_at      TIMESTAMP       DEFAULT SYSDATE,
    CONSTRAINT fk_cuti_karyawan 
        FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan)
);

-- ============================================
-- 10. TABEL USER_SESSIONS
-- Manajemen sesi login aktif
-- ============================================

PROMPT Membuat tabel USER_SESSIONS...

CREATE TABLE user_sessions (
    id_session      NUMBER          PRIMARY KEY,
    session_id      VARCHAR2(200),
    user_id         NUMBER          NOT NULL,
    role            VARCHAR2(20),
    username        VARCHAR2(100),
    ip_address      VARCHAR2(50),
    user_agent      VARCHAR2(500),
    login_time      TIMESTAMP       DEFAULT SYSDATE,
    status          VARCHAR2(20)    DEFAULT 'active',
    ended_at        TIMESTAMP
);

-- ============================================
-- 11. TRIGGERS (Auto-increment ID)
-- ============================================

PROMPT Membuat triggers auto-increment...

CREATE OR REPLACE TRIGGER trg_admin_id
BEFORE INSERT ON admin FOR EACH ROW
BEGIN
    IF :NEW.id_admin IS NULL THEN
        SELECT seq_admin.NEXTVAL INTO :NEW.id_admin FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_karyawan_id
BEFORE INSERT ON karyawan FOR EACH ROW
BEGIN
    IF :NEW.id_karyawan IS NULL THEN
        SELECT seq_karyawan.NEXTVAL INTO :NEW.id_karyawan FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_users_id
BEFORE INSERT ON users FOR EACH ROW
BEGIN
    IF :NEW.id_user IS NULL THEN
        SELECT seq_users.NEXTVAL INTO :NEW.id_user FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_absensi_id
BEFORE INSERT ON absensi FOR EACH ROW
BEGIN
    IF :NEW.id_absensi IS NULL THEN
        SELECT seq_absensi.NEXTVAL INTO :NEW.id_absensi FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_jadwal_id
BEFORE INSERT ON jadwal FOR EACH ROW
BEGIN
    IF :NEW.id_jadwal IS NULL THEN
        SELECT seq_jadwal.NEXTVAL INTO :NEW.id_jadwal FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_tukar_shift_id
BEFORE INSERT ON tukar_shift FOR EACH ROW
BEGIN
    IF :NEW.id_tukar IS NULL THEN
        SELECT seq_tukar_shift.NEXTVAL INTO :NEW.id_tukar FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_jadwal_khusus_id
BEFORE INSERT ON jadwal_khusus FOR EACH ROW
BEGIN
    IF :NEW.id_jadwal_khusus IS NULL THEN
        SELECT seq_jadwal_khusus.NEXTVAL INTO :NEW.id_jadwal_khusus FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_pengajuan_cuti_id
BEFORE INSERT ON pengajuan_cuti FOR EACH ROW
BEGIN
    IF :NEW.id_pengajuan IS NULL THEN
        SELECT seq_pengajuan_cuti.NEXTVAL INTO :NEW.id_pengajuan FROM dual;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_user_sessions_id
BEFORE INSERT ON user_sessions FOR EACH ROW
BEGIN
    IF :NEW.id_session IS NULL THEN
        SELECT seq_user_sessions.NEXTVAL INTO :NEW.id_session FROM dual;
    END IF;
END;
/

-- ============================================
-- 12. SEED DATA - Akun Admin Default
-- ============================================

PROMPT Memasukkan data admin default...

-- username: admin | password: admin123 (MD5)
INSERT INTO admin (username, password, nama) 
VALUES ('admin', '0192023a7bbd73250516f069df18b500', 'Administrator');

COMMIT;

-- ============================================
-- 13. VERIFIKASI
-- ============================================

PROMPT
PROMPT ========================================
PROMPT Verifikasi hasil pembuatan database:
PROMPT ========================================

SELECT table_name FROM user_tables ORDER BY table_name;

PROMPT
PROMPT Sequences:
SELECT sequence_name FROM user_sequences ORDER BY sequence_name;

PROMPT
PROMPT Triggers:
SELECT trigger_name, table_name FROM user_triggers ORDER BY table_name;

PROMPT
PROMPT Data admin:
SELECT id_admin, username, nama FROM admin;

PROMPT
PROMPT ========================================
PROMPT Database Si-Pancong berhasil dibuat!
PROMPT 
PROMPT Tabel    : 9 tabel
PROMPT Sequence : 9 sequence
PROMPT Trigger  : 9 trigger
PROMPT Admin    : admin / admin123
PROMPT ========================================

EXIT;
