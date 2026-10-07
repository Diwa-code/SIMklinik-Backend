# 🏥 SIM Klinik (Sistem Informasi Manajemen Klinik) - API Documentation

Dokumentasi resmi seluruh endpoint API yang tersedia pada sistem backend SIM Klinik berbasis Laravel & MongoDB. Dokumentasi ini disusun untuk mempermudah proses pengujian (testing) menggunakan Postman, Insomnia, Thunder Client, maupun cURL.

> **Catatan:** Endpoint registrasi dokter (`POST /api/doctors/register`) tidak disertakan dalam dokumen ini karena telah melalui proses pengujian sebelumnya.

---

## 📋 Daftar Isi

1. [Informasi Umum & Autentikasi](#-informasi-umum--autentikasi)
2. [Daftar Akun Pengujian (Seeder)](#-daftar-akun-pengujian-seeder)
3. [Alur Rekomendasi Alur Pengujian (Workflow)](#-alur-rekomendasi-alur-pengujian-workflow)
4. [Daftar Endpoint API](#-daftar-endpoint-api)
   - [1. Autentikasi](#1-autentikasi)
     - `POST /api/login` - Login User
   - [2. Pendaftaran Antrean & Rekomendasi Poli/Dokter](#2-pendaftaran-antrean--rekomendasi-polidokter)
     - `POST /api/appointments/recommend` - Rekomendasi Dokter Berdasarkan Keluhan
     - `POST /api/appointments` - Pendaftaran Antrean Pasien
     - `GET /api/appointments` - Daftar Antrean *(Pasien: milik sendiri | Dokter/Admin: semua)*
   - [3. Rekam Medis (Medical Records)](#3-rekam-medis-medical-records)
     - `POST /api/medical-records` - Input Rekam Medis SOAP (Khusus Dokter)
     - `GET /api/medical-records/patient/{patientId}` - Riwayat Rekam Medis Pasien
   - [4. Farmasi & Inventaris Obat (Pharmacy)](#4-farmasi--inventaris-obat-pharmacy)
     - `GET /api/medicines` - Daftar Inventaris Obat (Semua Role)
     - `GET /api/medicines/{id}` - Detail Satu Obat (Semua Role)
     - `POST /api/medicines` - Tambah Obat Baru (Admin & Apoteker)
     - `PUT /api/medicines/{id}` - Update Data/Stok Obat (Admin & Apoteker)
     - `DELETE /api/medicines/{id}` - Hapus Data Obat (**Admin saja**)
   - [5. Kasir & Pembayaran (Billing)](#5-kasir--pembayaran-billing)
     - `GET /api/billings` - Daftar Semua Tagihan
     - `GET /api/billings/{id}` - Detail Tagihan
     - `POST /api/billings` - Buat Tagihan Baru
     - `PUT /api/billings/{id}` - Update Status Pembayaran (unpaid → paid)
   - [6. Jadwal Praktik Dokter (Doctor Schedules)](#6-jadwal-praktik-dokter-doctor-schedules)
     - `GET /api/doctor-schedules/{doctorId}` - Lihat Jadwal Aktif Dokter
     - `POST /api/doctor-schedules` - Tambah Jadwal Praktik (Admin & Dokter)
     - `PUT /api/doctor-schedules/{id}` - Update Jadwal (jam/kuota/status)
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
  - `admin` (Akses manajemen umum, jadwal dokter, billing, inventaris obat)
  - `doctor` (Input SOAP rekam medis, input jadwal praktik dokter)
  - `pharmacist` (Input data obat baru apotek)
  - `patient` (Pendaftaran antrean, melihat rekomendasi dokter & riwayat medis sendiri)

---

## 👥 Daftar Akun Pengujian (Seeder)

Anda dapat menggunakan akun bawaan dari database seeder untuk menguji hak akses masing-masing endpoint:

| Role | Email | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@simklinik.com` | `password123` | Hak akses penuh manajerial |
| **Apoteker** | `apoteker@simklinik.com` | `password123` | Hak akses input master obat |
| **Dokter** | `dokter@simklinik.com` | `password123` | Hak akses dokter spesialis penyakit dalam & SOAP |
| **Pasien** | `budi@simklinik.com` | `password123` | Hak akses pendaftaran janji temu & antrean |

---

## 🔄 Alur Rekomendasi Alur Pengujian (Workflow)

Untuk menguji fitur secara end-to-end secara logis:

```
[1. Login Akun]
      │
      ▼
[2. Pasien Konsultasi Keluhan] ──► POST /api/appointments/recommend
      │
      ▼
[3. Cek Jadwal Praktik Dokter] ──► GET /api/doctor-schedules/{doctorId}
      │
      ▼
[4. Daftar Antrean / Booking] ───► POST /api/appointments
      │
      ▼
[5. Monitor Daftar Antrean] ─────► GET /api/appointments
      │
      ▼
[6. Dokter Input SOAP & Resep] ──► POST /api/medical-records (Status antrean otomatis: completed)
      │
      ▼
[7. Cek Riwayat Rekam Medis] ────► GET /api/medical-records/patient/{patientId}
      │
      ▼
[8. Apotek Input Stok Obat] ─────► POST /api/medicines & GET /api/medicines
      │
      ▼
[9. Kasir Buat Tagihan Pasien] ──► POST /api/billings
```

---

## 📡 Daftar Endpoint API

### 1. Autentikasi

#### `POST /login`
Masuk ke sistem untuk memperoleh token otentikasi Sanctum Bearer Token.

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
      "specialization": "Poliklinik Penyakit Dalam",
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

#### `POST /appointments/recommend`
Menganalisis keluhan pasien secara cerdas menggunakan pencocokan kata kunci dan merekomendasikan dokter spesialis yang paling sesuai beserta daftar dokter yang sedang aktif.

- **Akses:** Semua Pengguna Terotentikasi (Bearer Token)
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  ```json
  {
    "complaint": "Saya mengalami demam tinggi, batuk berdahak, dan badan terasa lemas"
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "recommendation": {
      "specialization": "Poliklinik Penyakit Dalam",
      "doctor_id": "6ac1417ba5dd485c230d0ac5",
      "doctor_name": "dr. Andi Wijaya, Sp.PD"
    },
    "available_doctors": [
      {
        "id": "6ac1417ba5dd485c230d0ac5",
        "user_id": "6ac1417ba5dd485c230d0ac4",
        "str_number": "STR-98765432",
        "sip_number": "SIP.12345.2026",
        "specialization": "Poliklinik Penyakit Dalam",
        "is_active": true,
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

---

#### `POST /appointments`
Mendaftarkan antrean pasien baru ke dokter yang dipilih. Nomor antrean otomatis dihasilkan berurutan (misal: `A-001`, `A-002`, dst).

- **Akses:** Semua Pengguna Terotentikasi (Umumnya Pasien)
- **Headers:**
  - `Authorization: Bearer <TOKEN_PASIEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `doctor_id` | String | Ya | ID dokter tujuan (merujuk ke collection doctors) |
  | `date` | Date (YYYY-MM-DD) | Ya | Tanggal rencana pemeriksaan |
  | `complaint` | String | Ya | Keluhan utama pasien |
  | `guarantor` | String | Ya | Jenis penjamin (contoh: `Umum`, `BPJS`, `Asuransi`) |

  ```json
  {
    "doctor_id": "6ac1417ba5dd485c230d0ac5",
    "date": "2026-10-05",
    "complaint": "Demam naik turun dan sakit tenggorokan",
    "guarantor": "BPJS"
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Pendaftaran antrean berhasil dibuat",
    "data": {
      "patient_id": "6aa60e693fb524a146088436",
      "doctor_id": "6ac1417ba5dd485c230d0ac5",
      "queue_number": "A-001",
      "date": "2026-10-05",
      "complaint": "Demam naik turun dan sakit tenggorokan",
      "guarantor": "BPJS",
      "status": "waiting",
      "payment_status": "unpaid",
      "id": "6ac25f12a5dd485c230d0ad1",
      "created_at": "2026-10-04T13:30:00.000000Z",
      "updated_at": "2026-10-04T13:30:00.000000Z"
    }
  }
  ```

---

#### `GET /appointments`
Melihat seluruh daftar antrean pasien yang terdaftar di sistem beserta relasi profil pasien dan data dokternya.

- **Akses:** Semua Pengguna Terotentikasi (Bearer Token)
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
        "queue_number": "A-001",
        "date": "2026-10-05",
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
          "specialization": "Poliklinik Penyakit Dalam"
        }
      }
    ]
  }
  ```

---

### 3. Rekam Medis (Medical Records)

#### `POST /medical-records`
Menyimpan pemeriksaan rekam medis dengan format standar **SOAP** (*Subjective, Objective, Assessment, Plan*) dan resep obat. Menyimpan SOAP ini secara otomatis mengupdate status antrean pasien menjadi `completed` sehingga dokter dapat memanggil pasien berikutnya.

- **Akses:** Khusus Role `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN_DOKTER>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `appointment_id` | String | Ya | ID antrean sesi periksa saat ini |
  | `patient_id` | String | Ya | ID Pasien yang diperiksa |
  | `subjective` | String | Ya | Keluhan / Anamnesis dari pasien |
  | `objective` | String | Ya | Hasil pemeriksaan fisik / TTV |
  | `assessment` | String | Ya | Diagnosa kerja dokter |
  | `plan` | String | Ya | Rencana tindakan / edukasi medis |
  | `prescriptions` | Array of Objects | Tidak | Daftar resep obat untuk instalasi farmasi |

  ```json
  {
    "appointment_id": "6ac25f12a5dd485c230d0ad1",
    "patient_id": "6aa60e693fb524a146088436",
    "subjective": "Pasien mengeluhkan demam sejak 3 hari lalu, sakit kepala berdenyut, dan batuk kering.",
    "objective": "TD: 120/80 mmHg, Nadi: 82x/mnt, Suhu: 38.6 C, RR: 18x/mnt, Faring hiperemis (+).",
    "assessment": "Febris hari ke-3 ec Faringitis Akut (J02.9)",
    "plan": "Tirah baring, hidrasi cukup 2L/hari, resep obat simptomatik dan antibiotik oral.",
    "prescriptions": [
      {
        "medicine_name": "Paracetamol 500mg",
        "dosage": "3x1 tablet sesudah makan (prn demam)",
        "quantity": 10
      },
      {
        "medicine_name": "Amoxicillin 500mg",
        "dosage": "3x1 tablet habiskan",
        "quantity": 15
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
        "doctor_id": "6ac1417ba5dd485c230d0ac4",
        "subjective": "Pasien mengeluhkan demam sejak 3 hari lalu...",
        "objective": "TD: 120/80 mmHg, Nadi: 82x/mnt...",
        "assessment": "Febris hari ke-3 ec Faringitis Akut (J02.9)",
        "plan": "Tirah baring, hidrasi cukup...",
        "prescriptions": [ ... ],
        "examined_at": "2026-10-04T13:35:00.000000Z",
        "id": "6ac2618fa5dd485c230d0ad5"
      },
      "next_queue_status": "ready"
    }
  }
  ```

---

#### `GET /medical-records/patient/{patientId}`
Melihat seluruh riwayat medis lampau seorang pasien yang diurutkan dari pemeriksaan terbaru.

- **Akses:** Role `patient`, `doctor`, atau `admin`
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **URL Parameter:**
  - `patientId`: ID pasien
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
        "doctor_id": "6ac1417ba5dd485c230d0ac4",
        "subjective": "Pasien mengeluhkan demam sejak 3 hari lalu...",
        "objective": "TD: 120/80 mmHg, Nadi: 82x/mnt...",
        "assessment": "Febris hari ke-3 ec Faringitis Akut (J02.9)",
        "plan": "Tirah baring, hidrasi cukup...",
        "prescriptions": [ ... ],
        "examined_at": "2026-10-04T13:35:00.000000Z"
      }
    ]
  }
  ```

---

### 4. Farmasi & Inventaris Obat (Pharmacy)

#### `GET /medicines`
Menampilkan seluruh daftar inventaris obat di apotek klinik beserta jumlah stok dan harga.

- **Akses:** Semua Pengguna Terotentikasi (Bearer Token)
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
        "stock": 350,
        "unit_price": 5000,
        "expired_date": "2026-12-01"
      }
    ]
  }
  ```

---

#### `GET /medicines/{id}`
Menampilkan detail data satu obat berdasarkan ID-nya.

- **Akses:** Semua Pengguna Terotentikasi (Bearer Token)
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
      "stock": 350,
      "unit_price": 5000,
      "expired_date": "2026-12-01"
    }
  }
  ```
- **Response Gagal (404):** `{ "status": "error", "message": "Data obat tidak ditemukan." }`

---

#### `POST /medicines`
Menambahkan data obat baru ke master inventaris farmasi klinik.

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
  | `stock` | Integer | Ya | Jumlah stok awal (min: 0) |
  | `unit_price` | Numeric | Ya | Harga jual per unit/satuan |
  | `expired_date` | Date (YYYY-MM-DD) | Ya | Tanggal kedaluwarsa |

  ```json
  {
    "name": "Amoxicillin 500mg",
    "category": "Tablet",
    "stock": 200,
    "unit_price": 4500,
    "expired_date": "2027-08-30"
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Obat baru berhasil ditambahkan ke inventaris apotek",
    "data": { "id": "6ac2635ba5dd485c230d0ad8", "name": "Amoxicillin 500mg", "stock": 200, ... }
  }
  ```

---

#### `PUT /medicines/{id}`
Memperbarui data atau stok obat yang sudah ada. Hanya field yang dikirim yang akan diubah *(partial update)*.

- **Akses:** Khusus Role `admin` atau `pharmacist`
- **Headers:**
  - `Authorization: Bearer <TOKEN_APOTEKER_ATAU_ADMIN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID obat
- **Request Body** *(semua field opsional — kirim hanya yang ingin diubah)*:
  ```json
  {
    "stock": 350,
    "unit_price": 5500
  }
  ```
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Data obat berhasil diperbarui",
    "data": { "id": "6ac1417ca5dd485c230d0ac9", "stock": 350, "unit_price": 5500, ... }
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
  { "status": "success", "message": "Data obat berhasil dihapus dari inventaris" }
  ```
- **Response Ditolak (403 - role pharmacist):**
  ```json
  { "message": "Akses Ditolak. Anda tidak memiliki hak akses ke modul ini." }
  ```

---

### 5. Kasir & Pembayaran (Billing)

#### `GET /billings`
Menampilkan seluruh riwayat tagihan pembayaran yang ada di sistem beserta relasi data antrean dan pasien.

- **Akses:** Semua Pengguna Terotentikasi (Bearer Token)
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **Response Sukses (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Daftar tagihan pembayaran berhasil dimuat",
    "data": [ { "id": "...", "patient_id": "...", "total_amount": 120000, "payment_status": "unpaid", ... } ]
  }
  ```

---

#### `GET /billings/{id}`
Menampilkan detail satu tagihan berdasarkan ID-nya.

- **Akses:** Semua Pengguna Terotentikasi (Bearer Token)
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID tagihan
- **Response Sukses (200 OK):** `{ "status": "success", "message": "Detail tagihan berhasil dimuat", "data": { ... } }`
- **Response Gagal (404):** `{ "status": "error", "message": "Data tagihan tidak ditemukan." }`

---

#### `POST /billings`
Membuat rekapan rincian tagihan pelayanan pasien. Sistem otomatis menjumlahkan total tagihan dan memperbarui status antrean menjadi `pending_payment`. Juga memvalidasi bahwa `appointment_id` benar-benar ada.

- **Akses:** Semua Pengguna Terotentikasi (Kasir / Admin)
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `appointment_id` | String | Ya | ID sesi antrean pasien |
  | `patient_id` | String | Ya | ID pasien yang ditagihkan |
  | `doctor_fee` | Numeric | Ya | Biaya jasa konsultasi dokter (min: 0) |
  | `medicine_fee` | Numeric | Ya | Total biaya obat yang ditebus (min: 0) |
  | `guarantor` | String | Ya | Penjamin (contoh: `Umum`, `BPJS`, `Asuransi`) |

  ```json
  {
    "appointment_id": "6ac25f12a5dd485c230d0ad1",
    "patient_id": "6aa60e693fb524a146088436",
    "doctor_fee": 75000,
    "medicine_fee": 45000,
    "guarantor": "Umum"
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Tagihan pembayaran pasien berhasil dibuat",
    "data": {
      "appointment_id": "6ac25f12a5dd485c230d0ad1",
      "patient_id": "6aa60e693fb524a146088436",
      "doctor_fee": 75000,
      "medicine_fee": 45000,
      "total_amount": 120000,
      "guarantor": "Umum",
      "payment_status": "unpaid",
      "id": "6ac265a0a5dd485c230d0adb"
    }
  }
  ```

---

#### `PUT /billings/{id}`
Memperbarui status pembayaran tagihan. Jika di-set `paid`, status antrean terkait ikut diperbarui menjadi `paid` secara otomatis.

- **Akses:** Semua Pengguna Terotentikasi (Kasir / Admin)
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID tagihan
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `payment_status` | String | Ya | Status baru: `unpaid`, `paid`, atau `cancelled` |
  | `payment_method` | String | Tidak | Metode bayar: `cash`, `transfer`, `bpjs`, dll |

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
    "data": { "payment_status": "paid", "payment_method": "cash", ... }
  }
  ```

---

### 6. Jadwal Praktik Dokter (Doctor Schedules)

#### `GET /doctor-schedules/{doctorId}`
Melihat seluruh jadwal praktik yang statusnya `is_active: true` untuk seorang dokter.

- **Akses:** Semua Pengguna Terotentikasi (Bearer Token)
- **Headers:**
  - `Authorization: Bearer <TOKEN>`
  - `Accept: application/json`
- **URL Parameter:** `doctorId` = ID profil dokter dari koleksi doctors
- **Response Sukses (200 OK):**
  ```json
  {
    "message": "Jadwal Praktik Dokter Dimuat",
    "data": [
      { "id": "6ac1417ba5dd485c230d0ac6", "day_of_week": "Senin", "start_time": "08:00", "end_time": "14:00", "quota": 30, "is_active": true }
    ]
  }
  ```

---

#### `POST /doctor-schedules`
Menambahkan jadwal jam praktik dokter per hari beserta kuota antrean.

- **Akses:** Khusus Role `admin` atau `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN_ADMIN_ATAU_DOKTER>`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body:**
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `doctor_id` | String | Ya | ID dokter dari koleksi doctors |
  | `day_of_week` | String | Ya | Hari praktik (misal: `Senin`, `Selasa`, dst.) |
  | `start_time` | String (H:i) | Ya | Jam mulai praktik (contoh: `08:00`) |
  | `end_time` | String (H:i) | Ya | Jam selesai (harus setelah `start_time`) |
  | `quota` | Integer | Ya | Kuota kapasitas antrean pasien (min: 1) |

  ```json
  {
    "doctor_id": "6ac1417ba5dd485c230d0ac5",
    "day_of_week": "Rabu",
    "start_time": "13:00",
    "end_time": "17:00",
    "quota": 25
  }
  ```
- **Response Sukses (201 Created):**
  ```json
  { "message": "Jadwal Praktik Berhasil Ditambahkan", "data": { "id": "...", "is_active": true, ... } }
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
- **Request Body** *(kirim hanya yang ingin diubah)*:
  | Field | Tipe | Wajib | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `day_of_week` | String | Tidak | Ubah hari praktik |
  | `start_time` | String (H:i) | Tidak | Ubah jam mulai |
  | `end_time` | String (H:i) | Tidak | Ubah jam selesai |
  | `quota` | Integer | Tidak | Ubah kuota antrean |
  | `is_active` | Boolean | Tidak | Aktifkan/nonaktifkan jadwal |

  ```json
  { "quota": 30, "start_time": "09:00" }
  ```
- **Response Sukses (200 OK):**
  ```json
  { "message": "Jadwal Praktik Berhasil Diperbarui", "data": { "quota": 30, "start_time": "09:00", ... } }
  ```

---

#### `DELETE /doctor-schedules/{id}`
Memnonaktifkan (*soft-deactivate*) jadwal praktik dengan mengubah `is_active` menjadi `false`. Data jadwal tetap tersimpan untuk keperluan historis.

- **Akses:** Khusus Role `admin` atau `doctor`
- **Headers:**
  - `Authorization: Bearer <TOKEN_ADMIN_ATAU_DOKTER>`
  - `Accept: application/json`
- **URL Parameter:** `id` = ID jadwal
- **Response Sukses (200 OK):**
  ```json
  { "message": "Jadwal praktik berhasil dinonaktifkan" }
  ```

---

## 🛑 Kode Status HTTP Umum

| Status Code | Makna | Penyebab Umum |
| :--- | :--- | :--- |
| `200 OK` | Berhasil | Permintaan data berhasil didapatkan |
| `201 Created` | Berhasil Dibuat | Data baru berhasil disimpan ke database |
| `401 Unauthorized` | Belum Login / Token Kadaluwarsa | Header `Authorization: Bearer ...` tidak disertakan atau token salah |
| `403 Forbidden` | Akses Ditolak | Role akun tidak memiliki izin mengakses endpoint ini |
| `404 Not Found` | Data Tidak Ditemukan | ID sumber daya (misal appointment_id) tidak ada di database |
| `422 Unprocessable Entity` | Validasi Gagal | Format payload atau field wajib belum diisi dengan benar |
| `500 Server Error` | Gangguan Sistem | Terjadi kendala internal pada server database |
