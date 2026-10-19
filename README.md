# 🏥 SIM Klinik (Sistem Informasi Manajemen Klinik) - API Documentation

Dokumentasi resmi seluruh endpoint API yang tersedia pada sistem backend SIM Klinik berbasis Laravel & MongoDB. Dokumentasi ini disusun untuk mempermudah proses pengujian (testing) menggunakan Postman, Insomnia, Thunder Client, maupun cURL, serta mencakup fitur-fitur pembaruan terbaru seperti **Manajemen Stok Obat FEFO (First Expired, First Out)**, **Cek Kuota Jadwal Dokter Real-Time**, dan **Audit Trail Kepatuhan UU Pelindungan Data Pribadi (UU PDP)** pada rekam medis.

---

## 📋 Daftar Isi

1. [Informasi Umum & Autentikasi](#-informasi-umum--autentikasi)
2. [Daftar Akun Pengujian (Seeder)](#-daftar-akun-pengujian-seeder)
3. [Alur Rekomendasi Alur Pengujian (Workflow)](#-alur-rekomendasi-alur-pengujian-workflow)
4. [Daftar Endpoint API](#-daftar-endpoint-api)
   - [1. Autentikasi & Registrasi](#1-autentikasi--registrasi)
     - `POST /api/register` - Registrasi Pengguna Baru (Pasien / Apoteker)
     - `POST /api/doctors/register` - Registrasi Dokter Baru (Dilindungi Secret Key)
     - `POST /api/login` - Login Pengguna & Peroleh Token Sanctum
   - [2. Pendaftaran Antrean & Rekomendasi Poli/Dokter](#2-pendaftaran-antrean--rekomendasi-polidokter)
    - `POST /api/appointments` - Buat Draft Appointment (Tanggal & Keluhan)
    - `POST /api/appointments/recommend` - Rekomendasi Dokter/Jadwal berdasarkan Draft
    - `GET /api/appointments/available-doctors` - Daftar Sesi Tersedia berdasarkan Draft
    - `POST /api/appointments/{id}/confirm` - Konfirmasi Dokter/Jadwal dan Ambil Nomor Antrean
    - `PATCH /api/appointments/{id}/draft` - Perbarui Tanggal/Keluhan Draft
     - `GET /api/appointments` - Daftar Antrean *(Pasien: milik sendiri | Dokter/Admin: semua)*
     - `PATCH /api/appointments/{id}/status` - Update Status Alur Antrean Klinis *(Dokter & Admin)*
   - [3. Rekam Medis & Audit Trail Kepatuhan UU PDP](#3-rekam-medis--audit-trail-kepatuhan-uu-pdp)
     - `POST /api/medical-records` - Input Rekam Medis SOAP & Resep (Khusus Dokter, Auto Audit Log)
     - `GET /api/medical-records/patient/{patientId}` - Riwayat Rekam Medis Pasien (Terproteksi UU PDP & Auto Audit Log)
     - `GET /api/medical-records/audit-logs` - Log Audit Akses & Perubahan Rekam Medis (Khusus Admin)
   - [4. Farmasi & Inventaris Obat Berbasis Batch FEFO](#4-farmasi--inventaris-obat-berbasis-batch-fefo)
     - `GET /api/medicines` - Daftar Inventaris Obat (Admin, Apoteker, Dokter)
     - `GET /api/medicines/{id}` - Detail Satu Obat (Admin, Apoteker, Dokter)
     - `POST /api/medicines` - Tambah Obat Baru dengan Batch FEFO (Admin & Apoteker)
     - `PUT /api/medicines/{id}` - Update Data & Batch Obat (Admin & Apoteker)
     - `DELETE /api/medicines/{id}` - Hapus Data Obat (**Admin saja**)
   - [5. Kasir & Pembayaran (Billing)](#5-kasir--pembayaran-billing)
     - `GET /api/billings` - Daftar Semua Tagihan (Admin, Apoteker, Pasien)
     - `GET /api/billings/{id}` - Detail Tagihan (Admin, Apoteker, Pasien)
     - `POST /api/billings` - Buat Tagihan Pasien, Otomatisasi Resep & Potong Stok FEFO (Admin & Apoteker)
     - `PUT /api/billings/{id}` - Update Status Pembayaran (Admin & Apoteker)
   - [6. Jadwal Praktik Dokter (Doctor Schedules)](#6-jadwal-praktik-dokter-doctor-schedules)
     - `GET /api/doctor-schedules/{doctorId}` - Lihat Jadwal Aktif Dokter
     - `POST /api/doctor-schedules` - Tambah Jadwal Praktik (Admin & Dokter)
     - `PUT /api/doctor-schedules/{id}` - Update Jadwal (Admin & Dokter)
     - `DELETE /api/doctor-schedules/{id}` - Nonaktifkan Jadwal (soft-delete)

---

## ⚙️ Informasi Umum & Autentikasi

- **Base URL:** `http://127.0.0.1:8000/api`
- **Tipe Data:** `JSON` (`Content-Type: application/json` & `Accept: application/json`)
- **Autentikasi:** Laravel Sanctum Bearer Token
  ```http
  Authorization: Bearer <TOKEN_HASIL_LOGIN>
  ```
- **Role Pengguna yang Didukung:**
  - `admin` (Akses manajerial penuh, jadwal dokter, billing, inventaris obat, serta Audit Trail rekam medis)
  - `doctor` (Input SOAP rekam medis, input & ubah jadwal praktik dokter pribadi)
  - `pharmacist` (Input master obat batch FEFO, pengolahan billing & kasir resep obat)
  - `patient` (Pencarian jadwal & sisa kuota, rekomendasi dokter, booking antrean, riwayat medis milik sendiri)

---

## 👥 Daftar Akun Pengujian (Seeder)

Anda dapat menggunakan akun bawaan dari database seeder untuk menguji hak akses masing-masing endpoint:

| Role | Email | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@simklinik.com` | `password123` | Hak akses penuh manajerial & audit trail |
| **Apoteker** | `apoteker@simklinik.com` | `password123` | Hak akses master obat batch & billing kasir |
| **Dokter** | `dokter@simklinik.com` | `password123` | Hak akses dokter spesialis penyakit dalam & SOAP |
| **Pasien** | `budi@simklinik.com` | `password123` | Hak akses pendaftaran janji temu & antrean |

---

## 🔄 Alur Rekomendasi Alur Pengujian (Workflow)

Untuk menguji fitur secara menyeluruh (end-to-end):

```
[1. Registrasi / Login Akun]
      │
      ▼
[2. Pasien Buat Draft Tanggal/Keluhan] ► POST /api/appointments
  ▼
[3. Cari Rekomendasi Jadwal] ──────► POST /api/appointments/recommend
  │ (atau daftar jadwal) ────────► GET /api/appointments/available-doctors
  ▼
[4. Konfirmasi Dokter & Sesi] ─────► POST /api/appointments/{id}/confirm
      │
      ▼
[5. Monitor Status Antrean] ───────► GET /api/appointments
      │
      ▼
[6. Dokter Input SOAP & Resep] ────► POST /api/medical-records (Auto log audit & antrean: completed)
      │
      ▼
[7. Cek Riwayat Rekam Medis] ──────► GET /api/medical-records/patient/{patientId} (Auto log audit)
      │
      ▼
[8. Kasir Proses Tagihan & FEFO] ──► POST /api/billings
      │
      ▼
[9. Pelunasan Pembayaran] ─────────► PUT /api/billings/{id} (status: paid)
      │
      ▼
[10. Audit Trail Kepatuhan UU PDP] ► GET /api/medical-records/audit-logs (Monitoring Admin)
```

---

## 📡 Daftar Endpoint API

### 1. Autentikasi & Registrasi

#### `POST /register`
Mendaftarkan akun pengguna baru untuk role `patient` (pasien) atau `pharmacist` (apoteker). Setelah registrasi berhasil, sistem langsung mengembalikan token autentikasi Sanctum.

- **Akses:** Publik (Tanpa Token)
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `name` | String | Ya | Nama lengkap pengguna (maks: 255) |
  | `email` | String | Ya | Alamat email unik |
  | `password` | String | Ya | Kata sandi (min: 6 karakter) |
  | `phone` | String | Ya | Nomor telepon/WhatsApp |
  | `address` | String | Ya | Alamat tempat tinggal |
  | `role` | String | Tidak | Pilihan: `patient` atau `pharmacist` (default: `patient`) |
  | `nik` | String | Tidak | NIK 16 digit identitas KTP pasien |
  | `bpjs_number` | String | Tidak | Nomor kartu BPJS Kesehatan pasien (13 digit) |
  | `place_of_birth` | String | Tidak | Tempat lahir |
  | `date_of_birth` | Date | Tidak | Tanggal lahir (YYYY-MM-DD) |
  | `gender` | String | Tidak | Jenis kelamin: `L` atau `P` |
  | `blood_type` | String | Tidak | Golongan darah: `A`, `B`, `AB`, `O` |
  | `allergies` | Array | Tidak | Daftar alergi obat/makanan |
  | `emergency_contact_name` | String | Tidak | Nama kontak darurat keluarga |
  | `emergency_contact_phone` | String | Tidak | Nomor telepon kontak darurat |
  | `emergency_contact_relation` | String | Tidak | Hubungan kontak darurat (`Orang Tua`, `Pasangan`, `Anak`, dll) |

  ```json
  {
    "name": "Dewi Sartika",
    "email": "dewi@gmail.com",
    "password": "password123",
    "phone": "081234567890",
    "address": "Jl. Teuku Umar No. 10, Denpasar",
    "role": "patient",
    "nik": "5171012345670001",
    "bpjs_number": "0001234567890",
    "place_of_birth": "Denpasar",
    "date_of_birth": "1998-05-12",
    "gender": "P",
    "blood_type": "O",
    "emergency_contact_name": "I Made Surya",
    "emergency_contact_phone": "081298765432",
    "emergency_contact_relation": "Pasangan"
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Registrasi patient berhasil",
    "token": "2|hijk9876543210...",
    "user": {
      "id": "6aa60e693fb524a146088499",
      "name": "Dewi Sartika",
      "email": "dewi@gmail.com",
      "role": "patient"
    },
    "profile_data": {
      "user_id": "6aa60e693fb524a146088499",
      "nik": "5171012345670001",
      "bpjs_number": "0001234567890",
      "place_of_birth": "Denpasar",
      "date_of_birth": "1998-05-12",
      "gender": "P",
      "blood_type": "O",
      "emergency_contact_name": "I Made Surya",
      "emergency_contact_phone": "081298765432",
      "emergency_contact_relation": "Pasangan"
    }
  }
  ```
---

#### `POST /doctors/register`
Mendaftarkan akun dokter baru beserta legalitas medisnya (STR & SIP) dan tarif konsultasi default. Endpoint ini dilindungi oleh **Secret Key** manajemen klinik untuk mencegah pendaftaran dokter tanpa otorisasi.

- **Akses:** Publik dengan Secret Header
- **Headers:**
  - `X-Register-Secret: RAHASIA_KLINIK` *(Wajib disesuaikan dengan config `app.doctor_register_secret`)*
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `name` | String | Ya | Nama lengkap dokter beserta gelar |
  | `email` | String | Ya | Email unik akun dokter |
  | `password` | String | Ya | Kata sandi akun (min: 6 karakter) |
  | `str_number` | String | Ya | Nomor Surat Tanda Registrasi (STR) unik |
  | `sip_number` | String | Ya | Nomor Surat Izin Praktik (SIP) unik |
  | `specialization` | String | Ya | Spesialisasi / Poliklinik tempat bertugas |
  | `consultation_fee` | Numeric | Tidak | Tarif dasar konsultasi dokter (default: `50000`) |
  | `gender` | String | Tidak | Jenis kelamin: `L` atau `P` |

  ```json
  {
    "name": "dr. Ratna Dewi, Sp.A",
    "email": "ratna@simklinik.com",
    "password": "password123",
    "str_number": "STR-11223344",
    "sip_number": "SIP.55443.2026",
    "specialization": "Poliklinik Anak",
    "consultation_fee": 85000,
    "gender": "P"
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Data dokter dan legalitas praktiknya berhasil didaftarkan.",
    "data": {
      "user": {
        "id": "6ac3012ba5dd485c230d0ae1",
        "name": "dr. Ratna Dewi, Sp.A",
        "email": "ratna@simklinik.com",
        "role": "doctor"
      },
      "doctor_profile": {
        "id": "6ac3012ba5dd485c230d0ae2",
        "user_id": "6ac3012ba5dd485c230d0ae1",
        "str_number": "STR-11223344",
        "sip_number": "SIP.55443.2026",
        "specialization": "Poliklinik Anak",
        "is_active": true
      }
    }
  }
  ```
- **Response Ditolak (403 Forbidden):**
  ```json
  {
    "status": "error",
    "message": "Unauthorized: Anda tidak memiliki hak akses untuk mendaftarkan dokter."
  }
  ```

---

#### `POST /login`
Masuk ke sistem untuk memperoleh token autentikasi Sanctum Bearer Token.

- **Akses:** Publik (Tanpa Token)
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  ```json
  {
    "email": "dokter@simklinik.com",
    "password": "password123"
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Login Berhasil",
    "token": "1|abcdef1234567890...",
    "user": {
      "id": "6aa60e693fb524a146088433",
      "name": "dr. Andi Wijaya, Sp.PD",
      "email": "dokter@simklinik.com",
      "role": "doctor"
    },
    "profile_data": {
      "user_id": "6aa60e693fb524a146088433",
      "str_number": "STR-98765432",
      "sip_number": "SIP.12345.2026",
      "specialization": "Poliklinik Umum",
      "is_active": true
    }
  }
  ```
- **Response Gagal (401 Unauthorized):**
  ```json
  {
    "message": "Email atau Password salah"
  }
  ```

---

### 2. Pendaftaran Antrean & Rekomendasi Poli/Dokter

#### `GET /appointments/available-doctors`
Mencari jadwal dokter aktif berdasarkan tanggal dan keluhan yang sudah disimpan di draft appointment, lalu menghitung sisa kuota antrean secara real-time.

- **Akses:** Role `patient`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **Query Parameters:**
  | Parameter | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `appointment_id` | String | Ya | ID draft appointment milik pasien; tanggal/keluhan diambil dari draft |

- **Contoh Request:**
  ```http
  GET /api/appointments/available-doctors?appointment_id=<DRAFT_APPOINTMENT_ID>
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "appointment_id": "6ac25f12a5dd485c230d0ad1",
    "date": "2026-10-12",
    "complaint": "demam batuk",
    "specialization": "Poliklinik Umum",
    "data": [
      {
        "id": "6ac1417ba5dd485c230d0ac6",
        "doctor_id": "6ac1417ba5dd485c230d0ac5",
        "schedule_date": "2026-10-12",
        "start_time": "08:00",
        "end_time": "14:00",
        "quota": 20,
        "is_active": true,
        "booked_count": 3,
        "remaining_quota": 17,
        "is_full": false,
        "doctor": {
          "id": "6ac1417ba5dd485c230d0ac5",
          "str_number": "STR-98765432",
          "specialization": "Poliklinik Umum",
          "user": {
            "id": "6ac1417ba5dd485c230d0ac4",
            "name": "dr. Andi Wijaya, Sp.PD",
            "email": "dokter@simklinik.com"
          }
        }
      }
    ]
  }
  ```

---

#### `POST /appointments/recommend`
Merekomendasikan dokter dan sesi praktik berdasarkan keluhan serta tanggal yang sudah disimpan pada draft appointment.

- **Akses:** Role `patient`
- **Headers:**
  - `Authorization: Bearer <TOKEN_PASIEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  ```json
  {
    "appointment_id": "<DRAFT_APPOINTMENT_ID>"
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "appointment_id": "<DRAFT_APPOINTMENT_ID>",
    "date": "2026-10-12",
    "complaint": "Demam dan batuk sejak kemarin",
    "specialization": "Poliklinik Umum",
    "recommendation": {
      "specialization": "Poliklinik Umum",
      "doctor_id": "6ac1417ba5dd485c230d0ac5",
      "doctor_name": "dr. Andi Wijaya, Sp.PD",
      "doctor_schedule_id": "6ac1417ba5dd485c230d0ac6",
      "start_time": "08:00",
      "end_time": "14:00"
    },
    "available_doctors": [
      {
        "id": "6ac1417ba5dd485c230d0ac5",
        "doctor_id": "6ac1417ba5dd485c230d0ac5",
        "schedule_date": "2026-10-12",
        "start_time": "08:00",
        "end_time": "14:00",
        "quota": 20,
        "booked_count": 3,
        "remaining_quota": 17,
        "user": {
          "id": "6ac1417ba5dd485c230d0ac4",
          "name": "dr. Andi Wijaya, Sp.PD",
          "email": "dokter@simklinik.com",
          "role": "doctor"
        }
      }
    ]
  }
  ```
Draft yang tidak ditemukan, bukan milik pemanggil, atau sudah dikonfirmasi tidak dapat digunakan kembali untuk rekomendasi.

---

#### `POST /appointments`
Membuat draft appointment berisi tanggal dan keluhan pasien. Pada tahap ini dokter belum dipilih dan belum ada nomor antrean atau kuota yang digunakan.

- **Akses:** Role `patient`
- **Headers:**
  - `Authorization: Bearer <TOKEN_PASIEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `date` | Date (YYYY-MM-DD) | Ya | Tanggal rencana pemeriksaan |
  | `complaint` | String | Ya | Keluhan utama pasien |
  | `guarantor` | String | Ya | Jenis penjamin (contoh: `Umum`, `BPJS`, `Asuransi`) |

  ```json
  {
    "date": "2026-10-12",
    "complaint": "Demam naik turun dan sakit tenggorokan",
    "guarantor": "BPJS"
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Draft appointment berhasil dibuat. Gunakan appointment_id untuk mendapatkan rekomendasi dokter dan jadwal.",
    "data": {
      "patient_id": "6aa60e693fb524a146088436",
      "date": "2026-10-12",
      "complaint": "Demam naik turun dan sakit tenggorokan",
      "guarantor": "BPJS",
      "status": "draft",
      "payment_status": "unpaid",
      "id": "6ac25f12a5dd485c230d0ad1",
      "created_at": "2026-10-07T08:30:00.000000Z",
      "updated_at": "2026-10-07T08:30:00.000000Z"
    }
  }
  ```

---

#### `PATCH /appointments/{id}/draft`
Memperbarui tanggal, keluhan, dan/atau penjamin pada draft yang sama. Setelah perubahan tanggal atau keluhan, panggil ulang endpoint rekomendasi dengan `appointment_id` yang sama; pilihan dokter dan jam akan dihitung ulang berdasarkan nilai draft terbaru.

- **Akses:** Role `patient`, hanya pemilik draft
- **Headers:** `Authorization: Bearer <TOKEN_PASIEN>`, `Content-Type: application/json`, `Accept: application/json`
- **Path:** `id` = ID draft dari `POST /appointments`
- **Request Body:** minimal satu field; semua field bersifat parsial.

```json
{
  "date": "2026-10-17",
  "complaint": "Sakit gigi"
}
```

Sukses `200`; draft yang sama diperbarui. Tanggal harus hari ini atau mendatang. Draft bukan milik pasien atau sudah dikonfirmasi menghasilkan `404`; body tanpa field untuk diperbarui menghasilkan `422`.

---

#### `POST /appointments/{id}/confirm`
Mengonfirmasi dokter dan sesi praktik yang dipilih dari hasil rekomendasi. Server memvalidasi kembali tanggal, spesialisasi, status aktif, kuota, serta booking duplikat, lalu mengalokasikan nomor antrean.

- **Akses:** Role `patient`, hanya pemilik draft
- **Headers:** `Authorization: Bearer <TOKEN_PASIEN>`, `Content-Type: application/json`, `Accept: application/json`
- **Path:** `id` = ID draft yang dikembalikan `POST /appointments`
- **Body:**

```json
{
  "doctor_id": "<DOCTOR_ID_DARI_REKOMENDASI>",
  "doctor_schedule_id": "<SCHEDULE_ID_DARI_REKOMENDASI>"
}
```

Sukses `200`; draft menjadi `waiting`, mendapatkan `queue_number`, dan menyimpan jam praktik. Jika kuota/sesi/spesialisasi tidak cocok, respons `422` dan draft belum dikonfirmasi.
- **Respons gagal yang dapat diuji:** jadwal tidak cocok, kuota penuh, atau pasien sudah memiliki antrean dokter/tanggal tersebut menghasilkan `422`; draft tetap berstatus `draft` agar pasien dapat memilih sesi lain.

---

#### `GET /appointments`
Melihat seluruh daftar antrean pasien. Pasien hanya melihat antreannya sendiri, sedangkan Dokter dan Admin melihat seluruh antrean.

- **Akses:** Role `patient`, `doctor`, `admin`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Daftar Antrean Berhasil Dimuat",
    "data": [
      {
        "id": "6ac25f12a5dd485c230d0ad1",
        "patient_id": "6aa60e693fb524a146088436",
        "doctor_id": "6ac1417ba5dd485c230d0ac5",
        "doctor_schedule_id": "6ac1417ba5dd485c230d0ac6",
        "practice_start_time": "08:00",
        "practice_end_time": "14:00",
        "queue_number": "A-001",
        "date": "2026-10-12",
        "complaint": "Demam naik turun dan sakit tenggorokan",
        "guarantor": "BPJS",
        "status": "waiting",
        "patient": {
          "id": "6aa60e693fb524a146088436",
          "name": "Budi Santoso",
          "email": "budi@simklinik.com"
        },
        "doctor": {
          "id": "6ac1417ba5dd485c230d0ac5",
          "str_number": "STR-98765432",
          "specialization": "Poliklinik Umum"
        }
      }
    ]
  }
  ```

---

#### `PATCH /appointments/{id}/status`
Memperbarui status alur klinis pasien di ruang periksa (misal memanggil pasien, memulai konsultasi, dll).

- **Akses:** Khusus Role `doctor` atau `admin`
- **Headers:**
  - `Authorization: Bearer <TOKEN_DOKTER_ATAU_ADMIN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID antrean
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `status` | String | Ya | Pilihan: `waiting`, `called`, `in_consultation`, `completed`, `cancelled` |

  ```json
  {
    "status": "called"
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Status antrean berhasil diperbarui menjadi 'called'",
    "data": {
      "id": "6ac25f12a5dd485c230d0ad1",
      "status": "called",
      "payment_status": "unpaid"
    }
  }
  ```

---

### 3. Rekam Medis & Audit Trail Kepatuhan UU PDP

#### `POST /medical-records`
Menyimpan pemeriksaan rekam medis dengan format standar **SOAP** (*Subjective, Objective, Assessment, Plan*) dan resep obat farmasi. Menyimpan SOAP ini secara otomatis mengupdate status antrean menjadi `completed` dan mencatat aktivitas ke sistem **Audit Trail** secara otomatis.

- **Akses:** Khusus Role `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN_DOKTER>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `appointment_id` | String | Ya | ID sesi antrean pasien |
  | `patient_id` | String | Ya | ID Pasien yang diperiksa |
  | `subjective` | String | Ya | Keluhan / Anamnesis dari pasien |
  | `objective` | String | Ya | Hasil pemeriksaan fisik / TTV |
  | `assessment` | String | Ya | Diagnosa kerja dokter |
  | `plan` | String | Ya | Rencana tindakan / edukasi medis |
  | `prescriptions` | Array of Objects | Tidak | Daftar resep obat (sertakan `medicine_id` dan `quantity` agar FEFO bekerja saat billing) |

  ```json
  {
    "appointment_id": "6ac25f12a5dd485c230d0ad1",
    "patient_id": "6aa60e693fb524a146088436",
    "subjective": "Pasien mengeluhkan demam sejak 3 hari lalu, sakit kepala berdenyut, dan batuk kering.",
    "objective": "TD: 120/80 mmHg, Nadi: 82x/mnt, Suhu: 38.6 C, RR: 18x/mnt, Faring hiperemis (+).",
    "assessment": "Febris hari ke-3 ec Faringitis Akut (J02.9)",
    "plan": "Tirah baring, hidrasi cukup 2L/hari, resep obat simptomatik oral.",
    "prescriptions": [
      {
        "medicine_id": "6ac1417ca5dd485c230d0ac9",
        "medicine_name": "Paracetamol 500mg",
        "dosage": "3x1 tablet sesudah makan (prn demam)",
        "quantity": 10
      }
    ]
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Rekam medis SOAP berhasil disimpan. Sesi pasien selesai, silakan lanjut ke pasien berikutnya.",
    "data": {
      "medical_record": {
        "appointment_id": "6ac25f12a5dd485c230d0ad1",
        "patient_id": "6aa60e693fb524a146088436",
        "doctor_id": "6aa60e693fb524a146088433",
        "subjective": "Pasien mengeluhkan demam sejak 3 hari lalu...",
        "objective": "TD: 120/80 mmHg, Nadi: 82x/mnt...",
        "assessment": "Febris hari ke-3 ec Faringitis Akut (J02.9)",
        "plan": "Tirah baring, hidrasi cukup...",
        "prescriptions": [ ... ],
        "examined_at": "2026-10-07T09:00:00.000000Z",
        "id": "6ac2618fa5dd485c230d0ad5"
      },
      "next_queue_status": "ready"
    }
  }
  ```

---

#### `GET /medical-records/patient/{patientId}`
Melihat seluruh riwayat medis lampau seorang pasien. Dilindungi oleh aturan **Kepatuhan UU PDP**: pasien hanya dapat mengakses rekam medis miliknya sendiri. Setiap kali data dibuka, sistem mencatat log audit (siapa yang mengakses, kapan, IP address, dan perangkat).

- **Akses:** Role `patient` (khusus ID miliknya), `doctor`, atau `admin`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **URL Parameter:** `patientId` = ID pasien
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Riwayat rekam medis berhasil dimuat",
    "data": [
      {
        "id": "6ac2618fa5dd485c230d0ad5",
        "appointment_id": "6ac25f12a5dd485c230d0ad1",
        "patient_id": "6aa60e693fb524a146088436",
        "doctor_id": "6aa60e693fb524a146088433",
        "subjective": "Pasien mengeluhkan demam sejak 3 hari lalu...",
        "objective": "TD: 120/80 mmHg, Nadi: 82x/mnt...",
        "assessment": "Febris hari ke-3 ec Faringitis Akut (J02.9)",
        "plan": "Tirah baring, hidrasi cukup...",
        "prescriptions": [ ... ],
        "examined_at": "2026-10-07T09:00:00.000000Z"
      }
    ]
  }
  ```
- **Response Ditolak Jika Pasien Mengakses Pasien Lain (403 Forbidden):**
  ```json
  {
    "status": "error",
    "message": "Akses ditolak: Anda hanya memiliki izin untuk melihat rekam medis milik Anda sendiri."
  }
  ```

---

#### `GET /medical-records/audit-logs`
Menampilkan catatan riwayat log audit (Audit Trail) terkait siapa saja staf/pengguna yang membuka, membuat, atau mencoba mengakses rekam medis pasien untuk kepatuhan regulasi kesehatan & pencegahan kebocoran data.

- **Akses:** Khusus Role `admin`
- **Headers:**
  - `Authorization: Bearer <TOKEN_ADMIN>`
  - `Accept: application/json`
- **Query Parameters (Opsional):**
  - `patient_id`: Filter log berdasarkan pasien tertentu
  - `user_id`: Filter log berdasarkan staf/user tertentu
  - `action`: Filter jenis aksi (`create`, `view_history`, `unauthorized_attempt`)
  - `per_page`: Jumlah data per halaman pagination (default: 20)
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Data audit log rekam medis berhasil dimuat",
    "data": {
      "current_page": 1,
      "data": [
        {
          "id": "6ac3189ea5dd485c230d0af1",
          "user_id": "6aa60e693fb524a146088433",
          "user_name": "dr. Andi Wijaya, Sp.PD",
          "user_role": "doctor",
          "patient_id": "6aa60e693fb524a146088436",
          "action": "create",
          "medical_record_id": "6ac2618fa5dd485c230d0ad5",
          "ip_address": "127.0.0.1",
          "user_agent": "PostmanRuntime/7.39.0",
          "details": {
            "appointment_id": "6ac25f12a5dd485c230d0ad1",
            "diagnosis": "Febris hari ke-3 ec Faringitis Akut (J02.9)",
            "has_prescriptions": true,
            "prescriptions_qty": 1
          },
          "accessed_at": "2026-10-07T09:00:00.000000Z"
        },
        {
          "id": "6ac318a0a5dd485c230d0af2",
          "user_id": "6aa60e693fb524a146088436",
          "user_name": "Budi Santoso",
          "user_role": "patient",
          "patient_id": "6aa60e693fb524a146088436",
          "action": "view_history",
          "ip_address": "127.0.0.1",
          "user_agent": "Mozilla/5.0 ...",
          "details": {
            "records_count": 1
          },
          "accessed_at": "2026-10-07T09:05:00.000000Z"
        }
      ],
      "total": 2
    }
  }
  ```

---

### 4. Farmasi & Inventaris Obat Berbasis Batch FEFO

#### `GET /medicines`
Menampilkan daftar seluruh obat beserta rincian nomor batch, stok per batch, dan tanggal kedaluwarsa.

- **Akses:** Role `admin`, `pharmacist`, `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Daftar inventaris obat berhasil dimuat",
    "data": [
      {
        "id": "6ac1417ca5dd485c230d0ac9",
        "name": "Paracetamol 500mg",
        "category": "Tablet",
        "base_price": 5000,
        "batches": [
          {
            "batch_no": "BATCH-A01",
            "stock": 50,
            "exp_date": "2026-12-01"
          },
          {
            "batch_no": "BATCH-A02",
            "stock": 100,
            "exp_date": "2027-06-15"
          }
        ]
      }
    ]
  }
  ```

---

#### `GET /medicines/{id}`
Menampilkan detail satu data obat berdasarkan ID-nya.

- **Akses:** Role `admin`, `pharmacist`, `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID obat
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Detail obat berhasil dimuat",
    "data": {
      "id": "6ac1417ca5dd485c230d0ac9",
      "name": "Paracetamol 500mg",
      "category": "Tablet",
      "base_price": 5000,
      "batches": [
        {
          "batch_no": "BATCH-A01",
          "stock": 50,
          "exp_date": "2026-12-01"
        }
      ]
    }
  }
  ```
- **Response Gagal (404 Not Found):** `{ "status": "error", "message": "Data obat tidak ditemukan." }`

---

#### `POST /medicines`
Menambahkan data master obat baru ke inventaris farmasi klinik dengan struktur batch FEFO.

- **Akses:** Khusus Role `admin` atau `pharmacist`
- **Headers:**
  - `Authorization: Bearer <TOKEN_APOTEKER_ATAU_ADMIN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `name` | String | Ya | Nama obat & sediaan |
  | `category` | String | Ya | Kategori (misal: `Tablet`, `Sirup`, `Injeksi`, `Salep`) |
  | `base_price` | Numeric | Ya | Harga jual per unit/satuan (min: 0) |
  | `batches` | Array of Objects | Ya | Minimal 1 batch (tiap batch berisi `batch_no`, `stock`, `exp_date`) |

  ```json
  {
    "name": "Amoxicillin 500mg",
    "category": "Tablet",
    "base_price": 4500,
    "batches": [
      {
        "batch_no": "AMX-2026-01",
        "stock": 100,
        "exp_date": "2027-04-10"
      },
      {
        "batch_no": "AMX-2026-02",
        "stock": 200,
        "exp_date": "2027-09-30"
      }
    ]
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Obat baru dengan manajemen batch FEFO berhasil ditambahkan",
    "data": {
      "id": "6ac2635ba5dd485c230d0ad8",
      "name": "Amoxicillin 500mg",
      "category": "Tablet",
      "base_price": 4500,
      "batches": [ ... ]
    }
  }
  ```

---

#### `PUT /medicines/{id}`
Memperbarui data obat atau menambahkan/memodifikasi batch obat yang sudah ada *(partial update)*.

- **Akses:** Khusus Role `admin` atau `pharmacist`
- **Headers:**
  - `Authorization: Bearer <TOKEN_APOTEKER_ATAU_ADMIN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID obat
- **Request Body:**
  ```json
  {
    "base_price": 4800,
    "batches": [
      {
        "batch_no": "AMX-2026-01",
        "stock": 150,
        "exp_date": "2027-04-10"
      }
    ]
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Data obat dan manajemen batch berhasil diperbarui",
    "data": {
      "id": "6ac2635ba5dd485c230d0ad8",
      "base_price": 4800,
      "batches": [ ... ]
    }
  }
  ```

---

#### `DELETE /medicines/{id}`
Menghapus data obat dari inventaris secara permanen. Hanya `admin` yang berhak menghapus.

- **Akses:** Khusus Role `admin`
- **Headers:**
  - `Authorization: Bearer <TOKEN_ADMIN>`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID obat
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Data obat berhasil dihapus dari inventaris"
  }
  ```

---

### 5. Kasir & Pembayaran (Billing)

#### `GET /billings`
Menampilkan seluruh riwayat tagihan pembayaran beserta relasi antrean dan pasiennya.

- **Akses:** Role `admin`, `pharmacist`, `patient`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Daftar tagihan pembayaran berhasil dimuat",
    "data": [
      {
        "id": "6ac265a0a5dd485c230d0adb",
        "appointment_id": "6ac25f12a5dd485c230d0ad1",
        "patient_id": "6aa60e693fb524a146088436",
        "doctor_fee": 75000,
        "medicine_fee": 50000,
        "total_amount": 125000,
        "guarantor": "Umum",
        "payment_status": "unpaid"
      }
    ]
  }
  ```

---

#### `GET /billings/{id}`
Menampilkan detail satu tagihan berdasarkan ID.

- **Akses:** Role `admin`, `pharmacist`, `patient`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID tagihan
- **Response Sukses (200 OK):** `{ "status": "success", "message": "Detail tagihan berhasil dimuat", "data": { ... } }`
- **Response Gagal (404 Not Found):** `{ "status": "error", "message": "Data tagihan tidak ditemukan." }`

---

#### `POST /billings`
Membuat rekapan rincian tagihan pelayanan pasien. 
**Fitur Otomatisasi Terintegrasi:**
1. Sistem otomatis membaca resep obat pada `MedicalRecord` pasien dan mengkalkulasi `medicine_fee`.
2. **Keamanan Stok:** Stok obat **BELUM dipotong** pada tahap ini jika status masih `unpaid` (mencegah pengurangan stok fiktif jika pasien batal menebus resep).
3. Mengambil `patient_id` otomatis dari antrean (`appointment_id`) dan mencegah pembuatan tagihan duplikat untuk antrean yang sama.
4. **Pengecualian BPJS:** Jika `guarantor` adalah `BPJS`, biaya dokter dan obat diset `0`, tagihan diset `covered_by_bpjs`, status pembayaran antrean langsung otomatis `paid`, dan stok obat langsung dipotong otomatis via FEFO.
5. Status alur klinis pasien (`completed`) tetap terjaga dan tidak tertimpa oleh transaksi kasir.

- **Akses:** Khusus Role `admin` atau `pharmacist`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `appointment_id` | String | Ya | ID sesi antrean pasien |
  | `doctor_fee` | Numeric | Tidak | Biaya jasa dokter (Opsional, jika tidak diisi otomatis mengambil tarif `consultation_fee` default dokter) |
  | `guarantor` | String | Ya | Jenis penjamin (`Umum`, `BPJS`, `Asuransi`, dll) |

  ```json
  {
    "appointment_id": "6ac25f12a5dd485c230d0ad1",
    "doctor_fee": 75000,
    "guarantor": "Umum"
  }
  ```
- **Response Sukses Pasien Umum (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Tagihan berhasil dibuat. Stok obat akan terpotong secara otomatis saat pembayaran diselesaikan (paid).",
    "data": {
      "appointment_id": "6ac25f12a5dd485c230d0ad1",
      "patient_id": "6aa60e693fb524a146088436",
      "doctor_fee": 75000,
      "medicine_fee": 50000,
      "total_amount": 125000,
      "guarantor": "Umum",
      "payment_status": "unpaid",
      "stock_deducted": false,
      "id": "6ac265a0a5dd485c230d0adb"
    }
  }
  ```

---

#### `PUT /billings/{id}`
Memperbarui status pembayaran tagihan kasir.
- **Eksekusi FEFO:** Saat status diubah menjadi `paid`, pemotongan stok batch obat FEFO otomatis dieksekusi dan status pembayaran antrean diperbarui menjadi `paid`.
- **Mekanisme Rollback:** Jika tagihan dibatalkan (`cancelled`), sistem otomatis mengembalikan stok obat ke batch jika sebelumnya sudah terpotong.

- **Akses:** Khusus Role `admin` atau `pharmacist`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID tagihan
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `payment_status` | String | Ya | Pilihan: `unpaid`, `paid`, atau `cancelled` |
  | `payment_method` | String | Tidak | Metode: `cash`, `transfer`, `qris`, dll |

  ```json
  {
    "payment_status": "paid",
    "payment_method": "cash"
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Status tagihan berhasil diperbarui",
    "data": {
      "id": "6ac265a0a5dd485c230d0adb",
      "payment_status": "paid",
      "payment_method": "cash"
    }
  }
  ```

---

### 6. Jadwal Praktik Dokter (Doctor Schedules)

#### `GET /doctor-schedules/{doctorId}`
Melihat seluruh jadwal praktik aktif (`is_active: true`) untuk dokter tertentu.

- **Akses:** Role `patient`, `doctor`, `admin`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **URL Parameter:** `doctorId` = ID profil dokter dari koleksi `doctors`
- **Response Sukses (200 OK):**
  ```json
  {
    "message": "Jadwal Praktik Dokter Dimuat",
    "data": [
      {
        "id": "6ac1417ba5dd485c230d0ac6",
        "doctor_id": "6ac1417ba5dd485c230d0ac5",
        "schedule_date": "2026-10-12",
        "start_time": "08:00",
        "end_time": "14:00",
        "quota": 20,
        "is_active": true
      }
    ]
  }
  ```

---

#### `POST /doctor-schedules`
Menambahkan jadwal jam praktik dokter untuk satu tanggal kalender beserta kuota antrean pasien. Jadwal tidak berulang otomatis pada tanggal lain.

- **Akses:** Khusus Role `admin` atau `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN_ADMIN_ATAU_DOKTER>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `doctor_id` | String | Ya | ID dokter dari koleksi `doctors` |
  | `schedule_date` | Date (YYYY-MM-DD) | Ya | Tanggal spesifik dokter praktik; harus hari ini atau mendatang |
  | `start_time` | String (H:i) | Ya | Jam mulai praktik (contoh: `08:00`) |
  | `end_time` | String (H:i) | Ya | Jam selesai (harus setelah `start_time`) |
  | `quota` | Integer | Ya | Kuota kapasitas antrean pasien (min: 1) |

  ```json
  {
    "doctor_id": "6ac1417ba5dd485c230d0ac5",
    "schedule_date": "2026-10-14",
    "start_time": "13:00",
    "end_time": "17:00",
    "quota": 25
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "message": "Jadwal Praktik Berhasil Ditambahkan",
    "data": {
      "id": "6ac3189ea5dd485c230d0aef",
      "doctor_id": "6ac1417ba5dd485c230d0ac5",
      "schedule_date": "2026-10-14",
      "start_time": "13:00",
      "end_time": "17:00",
      "quota": 25,
      "is_active": true
    }
  }
  ```

---

#### `PUT /doctor-schedules/{id}`
Memperbarui data jadwal praktik yang sudah ada (jam, kuota, atau status aktif). Semua field bersifat opsional *(partial update)*.

- **Akses:** Khusus Role `admin` atau `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN_ADMIN_ATAU_DOKTER>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID jadwal
- **Request Body:**
  ```json
  {
    "quota": 30,
    "start_time": "09:00"
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "message": "Jadwal Praktik Berhasil Diperbarui",
    "data": {
      "id": "6ac3189ea5dd485c230d0aef",
      "quota": 30,
      "start_time": "09:00"
    }
  }
  ```

---

#### `DELETE /doctor-schedules/{id}`
Menonaktifkan (*soft-deactivate*) jadwal praktik dengan mengubah `is_active` menjadi `false`. Data jadwal tetap tersimpan untuk keperluan historis antrean.

- **Akses:** Khusus Role `admin` atau `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN_ADMIN_ATAU_DOKTER>`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID jadwal
- **Response Sukses (200 OK):**
  ```json
  {
    "message": "Jadwal praktik berhasil dinonaktifkan"
  }
  ```

---

## 🛑 Kode Status HTTP Umum

| Status Code | Makna | Penyebab Umum |
| :--- | :--- | :--- |
| `200 OK` | Berhasil | Permintaan data berhasil didapatkan |
| `201 Created` | Berhasil Dibuat | Data baru berhasil disimpan ke database |
| `401 Unauthorized` | Belum Login / Token Kadaluwarsa | Header `Authorization: Bearer ...` tidak disertakan atau token salah |
| `403 Forbidden` | Akses Ditolak | Role akun tidak memiliki izin mengakses endpoint ini atau mengakses data pasien lain (UU PDP) |
| `404 Not Found` | Data Tidak Ditemukan | ID sumber daya (misal `appointment_id`, `id` obat) tidak ada di database |
| `422 Unprocessable Entity` | Validasi Gagal | Format payload atau field wajib belum diisi dengan benar |
| `500 Server Error` | Gangguan Sistem | Terjadi kendala internal pada server database |
