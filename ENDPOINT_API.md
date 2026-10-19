# Panduan Pengujian Lengkap Endpoint API SimKlinik (Master Sequential Flow)

## A. Variabel Lingkungan Pengujian (Postman / Insomnia Environment)

Sebelum memulai pengujian, siapkan variabel environment berikut pada client API Anda:

| Nama Variabel | Keterangan Nilai | Diperoleh dari Langkah |
|---|---|---|
| `base_url` | Alamat backend (contoh: `http://localhost:8000`) | Input manual awal |
| `doctor_secret` | Secret registrasi dokter (`KlinikSehatRahasia2026!`) | File `.env` server |
| `patient_id` | ID user pasien (`user.id` pada respons registrasi/login) | Langkah 1 / 2 |
| `patient_token` | Token otentikasi Sanctum pasien | Langkah 2 |
| `doctor_id` | ID profil dokter (`data.doctor_profile.id`) | Langkah 5 |
| `doctor_token` | Token otentikasi Sanctum dokter | Langkah 6 |
| `schedule_id` | ID sesi jadwal praktik dokter (`data.id`) | Langkah 7 |
| `pharmacist_token` | Token otentikasi Sanctum apoteker | Langkah 11 |
| `medicine_id` | ID obat di apotek (`data.id`) | Langkah 12 |
| `appointment_id` | ID draft antrean janji temu (`data.id`) | Langkah 16 |
| `billing_id` | ID tagihan kasir (`data.id`) | Langkah 26 |
| `admin_token` | Token otentikasi Sanctum administrator | Langkah 30 |

> **Catatan MongoDB ID**: MongoDB dapat mengembalikan pengenal objek sebagai `id` atau `_id`. Pada JavaScript/Postman, gunakan sintaks fallback: `response.data.id || response.data._id`.

---

## B. Alur Pengujian Berurutan (Step-by-Step Master Flow)

---

### TAHAP 1: Registrasi, Login & Profil Pasien

#### Langkah 1 — Registrasi Akun Pasien Baru (Publik)
Pasien mendaftar akun dasar. Data detail medis/kependudukan bersifat **opsional** agar pendaftaran awal berlangsung cepat.

- **Metode & URL:** `POST {{base_url}}/api/register`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
- **Authorization:** *None*
- **Request Body (JSON):**
```json
{
    "name": "Budi Santoso",
    "email": "budi.santoso@example.test",
    "password": "password123",
    "phone": "081234567890",
    "address": "Jl. Sudirman No. 45, Denpasar",
    "role": "patient"
}
```
- **Expected Status:** `201 Created`
- **Tindakan Lanjutan:**
  - Simpan nilai `user.id` ke variabel `patient_id`.
  - Amati objek `profile_completeness`: flag `is_complete: false` dan `is_appointment_ready: false` menunjukkan bahwa data NIK dan tanggal lahir masih belum terisi.

---

#### Langkah 2 — Login Pasien
Pasien melakukan login untuk mendapatkan token API dan status kelengkapan profil.

- **Metode & URL:** `POST {{base_url}}/api/login`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
- **Authorization:** *None*
- **Request Body (JSON):**
```json
{
    "email": "budi.santoso@example.test",
    "password": "password123"
}
```
- **Expected Status:** `200 OK`
- **Tindakan Lanjutan:**
  - Simpan nilai `token` ke variabel `patient_token`.
  - Simpan nilai `user.id` ke variabel `patient_id` jika belum tersimpan.
  - Front-end dapat menggunakan data `profile_completeness` pada respons ini untuk menampilkan banner pengingat "Silakan lengkapi profil Anda".

---

#### Langkah 3 — Lihat Profil Pasien & Status Kelengkapan Data
Mengambil detail informasi profil pasien yang sedang login beserta rincian field yang masih kurang.

- **Metode & URL:** `GET {{base_url}}/api/patient/profile`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{patient_token}}`
- **Authorization:** Bearer Token Pasien
- **Request Body:** *None*
- **Expected Status:** `200 OK`
- **Contoh Respons:**
```json
{
    "status": "success",
    "message": "Profil pasien berhasil dimuat",
    "data": {
        "user": {
            "id": "6aa...",
            "name": "Budi Santoso",
            "email": "budi.santoso@example.test",
            "phone": "081234567890",
            "address": "Jl. Sudirman No. 45, Denpasar",
            "role": "patient"
        },
        "patient_profile": {
            "nik": null,
            "place_of_birth": null,
            "date_of_birth": null
        },
        "profile_completeness": {
            "is_complete": false,
            "is_appointment_ready": false,
            "percentage": 30,
            "missing_fields": ["nik", "place_of_birth", "date_of_birth", "gender", "blood_type", "emergency_contact_name", "emergency_contact_phone"]
        }
    }
}
```

---

#### Langkah 4 — Perbarui / Lengkapi Data Profil Pasien
Pasien mengisi data detail (NIK, tanggal lahir, kontak darurat, golongan darah, dan riwayat alergi).

- **Metode & URL:** `PUT {{base_url}}/api/patient/profile`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{patient_token}}`
- **Authorization:** Bearer Token Pasien
- **Request Body (JSON):**
```json
{
    "nik": "5171012345670001",
    "bpjs_number": "0001234567890",
    "place_of_birth": "Denpasar",
    "date_of_birth": "1995-05-15",
    "gender": "L",
    "blood_type": "O",
    "rhesus": "+",
    "marital_status": "Menikah",
    "occupation": "Karyawan Swasta",
    "allergies": ["Amoxicillin", "Udang"],
    "chronic_diseases": ["Hipertensi"],
    "emergency_contact_name": "Siti Rahma",
    "emergency_contact_phone": "081298765432",
    "emergency_contact_relation": "Pasangan"
}
```
- **Expected Status:** `200 OK`
- **Hasil:** Properti `profile_completeness.is_complete: true`, `is_appointment_ready: true`, dan `percentage: 100`. Pasien kini siap membuat janji temu.

---

### TAHAP 2: Registrasi Dokter, Login & Pembuatan Jadwal Praktik

#### Langkah 5 — Registrasi Dokter Baru (Dilindungi Secret Key)
Mendaftarkan akun dokter dan legalitas praktiknya (STR & SIP). Wajib menyertakan header khusus `X-Register-Secret`.

- **Metode & URL:** `POST {{base_url}}/api/doctors/register`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `X-Register-Secret: {{doctor_secret}}`
- **Authorization:** *None* (Proteksi Secret Key)
- **Request Body (JSON):**
```json
{
    "name": "dr. Andi Pratama, Sp.PD",
    "email": "dr.andi@example.test",
    "password": "password123",
    "str_number": "STR-5171-98765",
    "sip_number": "SIP.12345/2026/01",
    "specialization": "Poliklinik Umum",
    "consultation_fee": 75000,
    "gender": "L"
}
```
- **Expected Status:** `201 Created`
- **Tindakan Lanjutan:** Simpan nilai `data.doctor_profile.id` ke variabel `doctor_id`.

---

#### Langkah 6 — Login Dokter
Dokter melakukan login untuk mendapatkan token akses sebelum membuat jadwal dan memeriksa antrean.

- **Metode & URL:** `POST {{base_url}}/api/login`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
- **Authorization:** *None*
- **Request Body (JSON):**
```json
{
    "email": "dr.andi@example.test",
    "password": "password123"
}
```
- **Expected Status:** `200 OK`
- **Tindakan Lanjutan:** Simpan nilai `token` ke variabel `doctor_token`.

---

#### Langkah 7 — Tambah Sesi Jadwal Praktik Dokter
Dokter (atau Admin) membuat jadwal praktik tanggal spesifik dengan batas kuota pasien.

- **Metode & URL:** `POST {{base_url}}/api/doctor-schedules`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{doctor_token}}`
- **Authorization:** Bearer Token Dokter (atau Admin)
- **Request Body (JSON):**
> *Catatan: Set `schedule_date` ke tanggal hari ini atau tanggal berobat yang dituju, format YYYY-MM-DD.*
```json
{
    "doctor_id": "{{doctor_id}}",
    "schedule_date": "2026-10-20",
    "start_time": "08:00",
    "end_time": "12:00",
    "quota": 15
}
```
- **Expected Status:** `201 Created`
- **Tindakan Lanjutan:** Simpan nilai `data.id` ke variabel `schedule_id`.

---

#### Langkah 8 — Lihat Jadwal Praktik Aktif Dokter
Melihat seluruh jadwal dokter yang berstatus aktif.

- **Metode & URL:** `GET {{base_url}}/api/doctor-schedules/{{doctor_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{doctor_token}}`
- **Authorization:** Bearer Token Dokter / Pasien / Admin
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

#### Langkah 9 — Perbarui Jadwal Praktik Dokter (Opsional/Ubah Kuota)
Memperbarui kuota atau jam sesi praktik dokter.

- **Metode & URL:** `PUT {{base_url}}/api/doctor-schedules/{{schedule_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{doctor_token}}`
- **Authorization:** Bearer Token Dokter (atau Admin)
- **Request Body (JSON):**
```json
{
    "quota": 20,
    "end_time": "13:00"
}
```
- **Expected Status:** `200 OK`

---

### TAHAP 3: Akun Farmasi & Inventaris Obat (Batch FEFO)

#### Langkah 10 — Registrasi Akun Apoteker
Mendaftarkan akun staf apotek (farmasi) menggunakan opsi `role: pharmacist`.

- **Metode & URL:** `POST {{base_url}}/api/register`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
- **Authorization:** *None*
- **Request Body (JSON):**
```json
{
    "name": "Siti Apoteker, S.Farm",
    "email": "siti.apotek@example.test",
    "password": "password123",
    "phone": "081987654321",
    "address": "Denpasar",
    "role": "pharmacist"
}
```
- **Expected Status:** `201 Created`

---

#### Langkah 11 — Login Apoteker
Login untuk mendapatkan token akses farmasi.

- **Metode & URL:** `POST {{base_url}}/api/login`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
- **Authorization:** *None*
- **Request Body (JSON):**
```json
{
    "email": "siti.apotek@example.test",
    "password": "password123"
}
```
- **Expected Status:** `200 OK`
- **Tindakan Lanjutan:** Simpan nilai `token` ke variabel `pharmacist_token`.

---

#### Langkah 12 — Tambah Obat Baru dengan Manajemen Batch FEFO
Apoteker menginput obat dengan beberapa batch yang memiliki tanggal kedaluwarsa berbeda (FEFO: First Expired, First Out).

- **Metode & URL:** `POST {{base_url}}/api/medicines`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker (atau Admin)
- **Request Body (JSON):**
```json
{
    "name": "Amoxicillin 500mg",
    "category": "Tablet",
    "base_price": 5000,
    "batches": [
        {
            "batch_no" : "AMX-EXP-NEAR",
            "stock": 10,
            "exp_date": "2026-11-01"
        },
        {
            "batch_no": "AMX-EXP-FAR",
            "stock": 30,
            "exp_date": "2027-11-01"
        }
    ]
}
```
- **Expected Status:** `201 Created`
- **Tindakan Lanjutan:** Simpan nilai `data.id` ke variabel `medicine_id`.

---

#### Langkah 13 — Lihat Daftar Seluruh Obat
Melihat inventaris obat di apotek.

- **Metode & URL:** `GET {{base_url}}/api/medicines`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker / Dokter / Admin
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

#### Langkah 14 — Lihat Detail Satu Obat
Melihat data detail dan stok batch dari satu obat.

- **Metode & URL:** `GET {{base_url}}/api/medicines/{{medicine_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker / Dokter / Admin
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

#### Langkah 15 — Perbarui Data / Batch Obat
Memperbarui harga jual atau batch obat.

- **Metode & URL:** `PUT {{base_url}}/api/medicines/{{medicine_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker (atau Admin)
- **Request Body (JSON):**
```json
{
    "base_price": 5500
}
```
- **Expected Status:** `200 OK`

---

### TAHAP 4: Alur Pendaftaran Janji Temu (Appointments Booking)

#### Langkah 16 — Buat Draft Janji Temu Pasien
Pasien menginput tanggal kunjungan, keluhan, dan jenis penjamin.

- **Metode & URL:** `POST {{base_url}}/api/appointments`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{patient_token}}`
- **Authorization:** Bearer Token Pasien
- **Request Body (JSON):**
> *Catatan: Gunakan tanggal yang sama dengan `schedule_date` pada Langkah 7 (misal: `2026-10-20`).*
```json
{
    "date": "2026-10-20",
    "complaint": "Demam tinggi dan sakit tenggorokan",
    "guarantor": "Umum",
    "require_complete_profile": true
}
```
- **Expected Status:** `201 Created`
- **Hasil:** Appointment tersimpan dengan status `draft`.
- **Tindakan Lanjutan:** Simpan nilai `data.id` ke variabel `appointment_id`.

---

#### Langkah 17 — Ubah Draft Janji Temu (Opsional)
Jika pasien ingin mengganti tanggal, keluhan, atau penjamin sebelum memilih dokter.

- **Metode & URL:** `PATCH {{base_url}}/api/appointments/{{appointment_id}}/draft`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{patient_token}}`
- **Authorization:** Bearer Token Pasien
- **Request Body (JSON):**
```json
{
    "complaint": "Demam, batuk kering, dan sakit tenggorokan"
}
```
- **Expected Status:** `200 OK`

---

#### Langkah 18 — Minta Rekomendasi Dokter Berdasarkan Keluhan
Sistem menganalisis keluhan pasien, menentukan poliklinik tujuan, dan merekomendasikan jadwal dokter aktif yang masih memiliki kuota.

- **Metode & URL:** `POST {{base_url}}/api/appointments/recommend`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{patient_token}}`
- **Authorization:** Bearer Token Pasien
- **Request Body (JSON):**
```json
{
    "appointment_id": "{{appointment_id}}"
}
```
- **Expected Status:** `200 OK`
- **Contoh Respons:**
```json
{
    "status": "success",
    "appointment_id": "6aa...",
    "date": "2026-10-20",
    "complaint": "Demam, batuk kering, dan sakit tenggorokan",
    "recommendation": {
        "specialization": "Poliklinik Umum",
        "doctor_id": "{{doctor_id}}",
        "doctor_schedule_id": "{{schedule_id}}",
        "start_time": "08:00",
        "end_time": "13:00"
    },
    "available_doctors": [ ... ]
}
```

---

#### Langkah 19 — Cek Seluruh Dokter Tersedia & Sisa Kuota
Alternatif bagi pasien untuk melihat semua dokter yang relevan beserta informasi sisa kuota pada tanggal draft.

- **Metode & URL:** `GET {{base_url}}/api/appointments/available-doctors?appointment_id={{appointment_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{patient_token}}`
- **Authorization:** Bearer Token Pasien
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

#### Langkah 20 — Konfirmasi Dokter & Jadwal (Nomor Antrean Terbit)
Pasien mengonfirmasi pilihan dokter dan sesi jadwal. Sistem memvalidasi ketersediaan kuota, mengubah status dari `draft` menjadi `waiting`, dan menerbitkan nomor antrean resmi (contoh: `A-001`).

- **Metode & URL:** `POST {{base_url}}/api/appointments/{{appointment_id}}/confirm`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{patient_token}}`
- **Authorization:** Bearer Token Pasien
- **Request Body (JSON):**
```json
{
    "doctor_id": "{{doctor_id}}",
    "doctor_schedule_id": "{{schedule_id}}"
}
```
- **Expected Status:** `200 OK`
- **Hasil:** Respons memuat `queue_number` (misal `A-001`), `status: "waiting"`, `practice_start_time`, dan `practice_end_time`.

---

#### Langkah 21 — Lihat Daftar Antrean (Pasien & Dokter)
- Jika diakses oleh **Pasien**: Menampilkan antrean aktif milik pasien bersangkutan.
- Jika diakses oleh **Dokter / Admin**: Menampilkan seluruh antrean pasien yang terdaftar di poli.
- **Filter Query Opsional:**
  - `?status=waiting`: Menampilkan hanya pasien yang masih menunggu (sangat berguna untuk dashboard panggil dokter).
  - `?date=YYYY-MM-DD`: Menampilkan antrean pada tanggal tertentu.
  - `?doctor_id=...`: Menampilkan antrean dokter tertentu.

- **Metode & URL:** `GET {{base_url}}/api/appointments?status=waiting`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{doctor_token}}` *(atau `Bearer {{patient_token}}`)*
- **Authorization:** Bearer Token Dokter / Admin / Pasien
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

### TAHAP 5: Pelayanan Medis Poliklinik, Pemanggilan & Rekam Medis (SOAP)

#### Langkah 22 — Dokter Memanggil Pasien ke Ruang Praktik
Dokter mengubah status antrean menjadi `called` saat memanggil nomor pasien. Sistem otomatis mencatat `last_called_at` dan menambahkan penghitung `call_count`.

- **Metode & URL:** `PATCH {{base_url}}/api/appointments/{{appointment_id}}/status`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{doctor_token}}`
- **Authorization:** Bearer Token Dokter (atau Admin)
- **Request Body (JSON):**
```json
{
    "status": "called"
}
```
- **Expected Status:** `200 OK`

> **Skenario Pasien Tidak Ada di Ruang Tunggu (Return to Waiting / Skip):**
> Jika pasien (misal Antrean 3) dipanggil tetapi tidak menyahut/belum hadir:
> 1. Dokter/perawat mengembalikan status Pasien 3 kembali ke `waiting`:
>    - Kirim: `PATCH /api/appointments/{{id_antrean_3}}/status` dengan `{ "status": "waiting" }`.
>    - Sistem otomatis menandai properti `is_recalled: true` dan mencatat `returned_to_waiting_at`.
> 2. Dokter langsung beralih memanggil Pasien 4 (`status: called` $\rightarrow$ `in_consultation` $\rightarrow$ `completed`).
> 3. Setelah Pasien 4 selesai, dokter memuat kembali daftar pasien yang masih menunggu via `GET /api/appointments?status=waiting`.
> 4. Pasien 3 tetap muncul di daftar `waiting`, dan dokter dapat langsung menekan tombol **Panggil Kembali (Recall)** (`status: called`) kapan saja! Counter `call_count` akan otomatis naik menjadi `2`.

---

#### Langkah 23 — Dokter Memulai Konsultasi Medis
Dokter mengubah status menjadi `in_consultation` saat pasien sudah masuk dan pemeriksaan fisik berlangsung.

- **Metode & URL:** `PATCH {{base_url}}/api/appointments/{{appointment_id}}/status`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{doctor_token}}`
- **Authorization:** Bearer Token Dokter (atau Admin)
- **Request Body (JSON):**
```json
{
    "status": "in_consultation"
}
```
- **Expected Status:** `200 OK`

---

#### Langkah 24 — Dokter Mengisi Rekam Medis SOAP & Resep Obat
Dokter mendokumentasikan hasil pemeriksaan SOAP dan meresepkan obat.
> **Efek Otomatis Sistem:**
> 1. Status appointment otomatis diubah menjadi `completed`.
> 2. Kepatuhan UU PDP: Sistem otomatis mencatat log audit pembuatan rekam medis.

- **Metode & URL:** `POST {{base_url}}/api/medical-records`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{doctor_token}}`
- **Authorization:** Bearer Token Dokter
- **Request Body (JSON):**
```json
{
    "appointment_id": "{{appointment_id}}",
    "patient_id": "{{patient_id}}",
    "subjective": "Pasien mengeluh nyeri menelan dan demam sejak 2 hari",
    "objective": "Suhu 38.3 C, faring hiperemis (+), tonsil T1-T1",
    "assessment": "Faringitis Akut",
    "plan": "Terapi antibiotik oral, istirahat cukup, dan hidrasi",
    "prescriptions": [
        {
            "medicine_id": "{{medicine_id}}",
            "quantity": 12
        }
    ]
}
```
- **Expected Status:** `201 Created`

---

#### Langkah 25 — Lihat Riwayat Rekam Medis Pasien
Pasien dapat melihat riwayat rekam medis miliknya sendiri. Dokter dan Admin juga dapat melihat riwayat medis pasien untuk referensi klinis.

- **Metode & URL:** `GET {{base_url}}/api/medical-records/patient/{{patient_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{patient_token}}` *(atau `Bearer {{doctor_token}}`)*
- **Authorization:** Bearer Token Pasien / Dokter / Admin
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

### TAHAP 6: Kasir, Pembayaran & Pemotongan Stok Obat FEFO

#### Langkah 26 — Kasir / Apoteker Menerbitkan Tagihan Pasien
Kasir menghitung biaya jasa dokter dan total resep obat.
> **Keamanan Stok:** Stok obat **belum** dipotong pada tahap ini jika status masih `unpaid` untuk mencegah stok hilang jika pasien batal menebus obat.

- **Metode & URL:** `POST {{base_url}}/api/billings`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker (atau Admin)
- **Request Body (JSON):**
```json
{
    "appointment_id": "{{appointment_id}}",
    "doctor_fee": 75000,
    "guarantor": "Umum"
}
```
- **Expected Status:** `201 Created`
- **Hasil:**
  - Tagihan berstatus `unpaid`.
  - Status appointment tetap `completed` secara klinis, dan `payment_status` menjadi `pending_payment`.
- **Tindakan Lanjutan:** Simpan nilai `data.id` ke variabel `billing_id`.

---

#### Langkah 27 — Lihat Seluruh Daftar Tagihan Kasir
Melihat daftar tagihan pembayaran di klinik.

- **Metode & URL:** `GET {{base_url}}/api/billings`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker / Admin / Pasien
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

#### Langkah 28 — Lihat Detail Satu Tagihan
Melihat rincian komponen tagihan (jasa dokter, biaya obat, total biaya).

- **Metode & URL:** `GET {{base_url}}/api/billings/{{billing_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker / Admin / Pasien
- **Request Body:** *None*
- **Expected Status:** `200 OK`

---

#### Langkah 29 — Pembayaran Lunas (Eksekusi Otomatis Potong Stok FEFO)
Saat pasien membayar tagihan, kasir mengubah status menjadi `paid`.
> **Otomasi FEFO:** Sistem secara otomatis mendeteksi batch obat terdekat kedaluwarsa (`AMX-EXP-NEAR`), memotong stoknya terlebih dahulu hingga habis, lalu melanjutkan ke batch berikutnya (`AMX-EXP-FAR`).

- **Metode & URL:** `PUT {{base_url}}/api/billings/{{billing_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer {{pharmacist_token}}`
- **Authorization:** Bearer Token Apoteker (atau Admin)
- **Request Body (JSON):**
```json
{
    "payment_status": "paid",
    "payment_method": "cash"
}
```
- **Expected Status:** `200 OK`
- **Hasil:**
  - `payment_status` tagihan berubah menjadi `paid`.
  - `stock_deducted` bernilai `true`.
  - `payment_status` pada appointment berubah menjadi `paid`.

---

### TAHAP 7: Audit Compliance & Pemeliharaan Data (Administrator)

#### Langkah 30 — Login Administrator
Login sebagai Administrator untuk melakukan pemantauan audit dan pemeliharaan master data.

- **Metode & URL:** `POST {{base_url}}/api/login`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
- **Authorization:** *None*
- **Request Body (JSON):**
```json
{
    "email": "admin@simklinik.com",
    "password": "password123"
}
```
- **Expected Status:** `200 OK`
- **Tindakan Lanjutan:** Simpan nilai `token` ke variabel `admin_token`.

---

#### Langkah 31 — Pantau Log Audit Rekam Medis (Kepatuhan UU PDP)
Admin memantau riwayat aktivitas akses dan pembuatan rekam medis.

- **Metode & URL:** `GET {{base_url}}/api/medical-records/audit-logs?per_page=20`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{admin_token}}`
- **Authorization:** Bearer Token Admin
- **Request Body:** *None*
- **Expected Status:** `200 OK`
- **Hasil:** Menampilkan daftar log audit beserta user, IP address, user-agent, dan jenis aksi (`create`, `view_history`, `unauthorized_attempt`).

---

#### Langkah 32 — Nonaktifkan Jadwal Praktik Dokter
Menonaktifkan sesi jadwal dokter (soft deactivation: `is_active: false`) agar data riwayat antrean tetap terjaga.

- **Metode & URL:** `DELETE {{base_url}}/api/doctor-schedules/{{schedule_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{admin_token}}` *(atau `Bearer {{doctor_token}}`)*
- **Authorization:** Bearer Token Admin / Dokter
- **Request Body:** *None*
- **Expected Status:** `200 OK`
- **Hasil Pesan:** `"Jadwal praktik berhasil dinonaktifkan"`

---

#### Langkah 33 — Hapus Obat dari Inventaris
Admin menghapus data obat dari daftar apotek jika sudah tidak beredar.

- **Metode & URL:** `DELETE {{base_url}}/api/medicines/{{medicine_id}}`
- **Headers:**
  - `Accept: application/json`
  - `Authorization: Bearer {{admin_token}}`
- **Authorization:** Bearer Token Admin
- **Request Body:** *None*
- **Expected Status:** `200 OK`
- **Hasil Pesan:** `"Data obat berhasil dihapus dari inventaris"`

---

## C. Ringkasan Matriks Pengujian Seluruh Endpoint

| No. | Langkah Alur | HTTP Method | Endpoint URI | Wajib Header / Authorization | Role Diizinkan |
|:---:|:---|:---:|:---|:---|:---|
| 1 | Registrasi Pasien | `POST` | `/api/register` | `Accept`, `Content-Type` | Publik |
| 2 | Login Pasien | `POST` | `/api/login` | `Accept`, `Content-Type` | Publik |
| 3 | Lihat Profil Pasien | `GET` | `/api/patient/profile` | `Authorization: Bearer {{patient_token}}` | `patient` |
| 4 | Update Profil Pasien | `PUT` | `/api/patient/profile` | `Authorization: Bearer {{patient_token}}` | `patient` |
| 5 | Registrasi Dokter | `POST` | `/api/doctors/register` | `X-Register-Secret: {{doctor_secret}}` | Publik (Secret) |
| 6 | Login Dokter | `POST` | `/api/login` | `Accept`, `Content-Type` | Publik |
| 7 | Tambah Jadwal Praktik | `POST` | `/api/doctor-schedules` | `Authorization: Bearer {{doctor_token}}` | `doctor`, `admin` |
| 8 | Lihat Jadwal Dokter | `GET` | `/api/doctor-schedules/{doctorId}` | `Authorization: Bearer {{token}}` | Authenticated |
| 9 | Update Jadwal Dokter | `PUT` | `/api/doctor-schedules/{id}` | `Authorization: Bearer {{doctor_token}}` | `doctor`, `admin` |
| 10 | Registrasi Apoteker | `POST` | `/api/register` | `Accept`, `Content-Type` | Publik |
| 11 | Login Apoteker | `POST` | `/api/login` | `Accept`, `Content-Type` | Publik |
| 12 | Tambah Obat (FEFO) | `POST` | `/api/medicines` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist` |
| 13 | Lihat Semua Obat | `GET` | `/api/medicines` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist`, `doctor` |
| 14 | Detail Satu Obat | `GET` | `/api/medicines/{medicine}` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist`, `doctor` |
| 15 | Update Data/Batch Obat | `PUT` | `/api/medicines/{medicine}` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist` |
| 16 | Buat Draft Janji Temu | `POST` | `/api/appointments` | `Authorization: Bearer {{patient_token}}` | `patient` |
| 17 | Ubah Draft Janji Temu | `PATCH` | `/api/appointments/{id}/draft` | `Authorization: Bearer {{patient_token}}` | `patient` |
| 18 | Rekomendasi Dokter | `POST` | `/api/appointments/recommend` | `Authorization: Bearer {{patient_token}}` | `patient` |
| 19 | Cari Jadwal & Kuota | `GET` | `/api/appointments/available-doctors` | `Authorization: Bearer {{patient_token}}` | `patient` |
| 20 | Konfirmasi Antrean | `POST` | `/api/appointments/{id}/confirm` | `Authorization: Bearer {{patient_token}}` | `patient` |
| 21 | Lihat Daftar Antrean | `GET` | `/api/appointments` | `Authorization: Bearer {{token}}` | `patient`, `doctor`, `admin` |
| 22 | Panggil Pasien | `PATCH` | `/api/appointments/{id}/status` | `Authorization: Bearer {{doctor_token}}` | `doctor`, `admin` |
| 23 | Mulai Konsultasi | `PATCH` | `/api/appointments/{id}/status` | `Authorization: Bearer {{doctor_token}}` | `doctor`, `admin` |
| 24 | Input SOAP Rekam Medis | `POST` | `/api/medical-records` | `Authorization: Bearer {{doctor_token}}` | `doctor` |
| 25 | Riwayat Rekam Medis | `GET` | `/api/medical-records/patient/{patientId}` | `Authorization: Bearer {{token}}` | `patient`, `doctor`, `admin` |
| 26 | Terbitkan Tagihan Kasir | `POST` | `/api/billings` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist` |
| 27 | Daftar Tagihan Kasir | `GET` | `/api/billings` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist`, `patient` |
| 28 | Detail Satu Tagihan | `GET` | `/api/billings/{billing}` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist`, `patient` |
| 29 | Pembayaran Lunas (FEFO) | `PUT` | `/api/billings/{billing}` | `Authorization: Bearer {{pharmacist_token}}` | `admin`, `pharmacist` |
| 30 | Login Administrator | `POST` | `/api/login` | `Accept`, `Content-Type` | Publik |
| 31 | Audit Trail UU PDP | `GET` | `/api/medical-records/audit-logs` | `Authorization: Bearer {{admin_token}}` | `admin` |
| 32 | Nonaktifkan Jadwal | `DELETE` | `/api/doctor-schedules/{id}` | `Authorization: Bearer {{admin_token}}` | `admin`, `doctor` |
| 33 | Hapus Obat | `DELETE` | `/api/medicines/{medicine}` | `Authorization: Bearer {{admin_token}}` | `admin` |
