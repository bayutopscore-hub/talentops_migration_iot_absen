# TalentOps IoT RFID Attendance

Komponen Laravel untuk migrasi absensi TalentOps menggunakan reader RFID UHF.
Komponen ini menerima scan RFID, menentukan check-in/check-out, menghubungkan
attendance dengan assignment shift karyawan, mencatat scan tidak dikenal, dan
menutup check-in yang belum memiliki check-out.

Repository ini berisi komponen aplikasi dan migration tambahan untuk database
TalentOps yang sudah memiliki tabel HR legacy. Repository ini belum merupakan
aplikasi Laravel standalone karena belum memiliki `artisan`, `bootstrap/`,
`config/`, `public/`, dan migration untuk seluruh tabel legacy.

## Fitur

- Validasi payload scan RFID.
- Pencarian kartu RFID aktif berdasarkan EPC.
- Pencatatan scan dari kartu tidak dikenal.
- Debounce scan berulang selama 60 detik.
- Penentuan otomatis `check_in` dan `check_out`.
- Validasi lokasi employee dan reader.
- Pencarian assignment shift aktif berdasarkan tanggal scan.
- Grace period keterlambatan check-in selama 5 menit.
- Review flag untuk shift mismatch dan auto-close.
- Auto-close check-in yang belum memiliki check-out.
- Dukungan shift overnight.
- Transaction dan row locking untuk mencegah duplicate attendance.

## Struktur Repository

```text
app/
├── Console/Commands/AutoCloseAttendance.php
├── Http/Controllers/Api/AttendanceScanController.php
└── Models/
    ├── AttendanceLog.php
    ├── AttendanceReviewFlag.php
    ├── RfidCard.php
    ├── TsHrisEmployee.php
    ├── TsHrisEmployeeShift.php
    ├── TsHrisJobPosition.php
    ├── TsHrisOrganization.php
    ├── TsHrisShift.php
    ├── TsStore.php
    └── UnrecognizedScan.php
database/migrations/
├── 2026_09_04_000001_create_rfid_cards_table.php
├── 2026_09_04_000002_add_shift_fields_to_attendance_logs_table.php
├── 2026_09_04_000003_create_attendance_review_flags_table.php
└── 2026_09_04_000004_create_unrecognized_scans_table.php
routes/
├── api.php
└── console.php
composer.json
```

## Persiapan Laravel

`composer.json` menggunakan PHP `^8.2`, Laravel Framework `^11.0`, PHPUnit,
Faker, Mockery, Collision, Laravel Pint, dan PSR-4 autoload `App\\` ke folder
`app/`.

Pasang komponen ini pada project yang sudah memiliki struktur Laravel lengkap,
atau lengkapi skeleton Laravel terlebih dahulu. Setelah file aplikasi lengkap
tersedia:

```bash
composer install
php artisan migrate
php artisan route:list
php artisan schedule:list
```

`composer validate` sudah berhasil dijalankan terhadap manifest repository ini.

## API Scan RFID

Route tersedia pada [routes/api.php](routes/api.php):

```php
Route::post(
    '/attendance/scan',
    [AttendanceScanController::class, 'store']
);
```

Controller berada pada
[AttendanceScanController.php](app/Http/Controllers/Api/AttendanceScanController.php).

### Request

```json
{
  "epc": "E2000017221101441890ABCD",
  "reader_id": "reader-front-door-01",
  "st_id": 7,
  "scanned_at": "2026-09-04T08:35:00+07:00"
}
```

| Field | Aturan |
| --- | --- |
| `epc` | Wajib, string, maksimal 255 karakter |
| `reader_id` | Opsional, string, maksimal 100 karakter |
| `st_id` | Wajib, integer, harus ada di `ts_stores.id` |
| `scanned_at` | Wajib, tanggal valid, tidak boleh di masa depan |

### Alur Pemrosesan

1. Controller mencari `RfidCard` dengan `epc` dan status `active`.
2. Jika tidak ditemukan, data disimpan ke `unrecognized_scans`.
3. Employee dari kartu harus tersedia dan memiliki `st_id` yang sama dengan lokasi
   scan.
4. Log terakhir employee dikunci menggunakan `lockForUpdate()` di dalam transaction.
5. Scan dalam jarak kurang dari 60 detik dari log terakhir diabaikan.
6. Scan menjadi `check_out` jika log terakhir pada hari yang sama adalah `check_in`;
   jika tidak, scan menjadi `check_in`.
7. Assignment shift aktif dicari menggunakan `TsHrisEmployeeShift::activeOn()`.
8. Check-in lebih dari 5 menit setelah waktu mulai shift diberi `is_late = true`.
9. Check-in tanpa shift aktif diberi `shift_mismatch = true` dan review flag pending.
10. Log dan review flag dibuat dalam transaction yang sama.

### Response

| HTTP | `status` | Keterangan |
| --- | --- | --- |
| `201` | `logged` | Attendance berhasil dicatat |
| `200` | `ignored` | Scan diabaikan karena debounce |
| `200` | `unrecognized` | EPC tidak terdaftar atau tidak aktif |
| `422` | `invalid_card` | Kartu aktif tidak memiliki employee |
| `422` | `invalid_store` | Lokasi scan berbeda dari lokasi employee |
| `422` | validation error | Payload tidak valid |

Response `logged` berisi `employee_name`, `type`, `is_late`, dan
`shift_mismatch`. Response `ignored` berisi `reason: debounced`.

## Model dan Relasi

Model utama berada di [app/Models](app/Models):

```text
TsHrisEmployee
├── belongsTo TsStore melalui st_id
├── belongsTo TsHrisOrganization melalui org_id
├── belongsTo TsHrisJobPosition melalui jps_id
├── belongsTo TsHrisShift melalui shift_id
├── hasMany TsHrisEmployeeShift melalui emp_id
├── hasMany RfidCard melalui employee_id
├── hasMany AttendanceLog melalui employee_id
└── hasMany AttendanceReviewFlag melalui employee_id

RfidCard
├── belongsTo TsHrisEmployee
└── belongsTo User sebagai issuer

AttendanceLog
├── belongsTo TsHrisEmployee
├── belongsTo TsStore
├── belongsTo TsHrisEmployeeShift
└── hasMany AttendanceReviewFlag

AttendanceReviewFlag
├── belongsTo AttendanceLog
├── belongsTo TsHrisEmployee
└── belongsTo User sebagai reviewer

TsHrisEmployeeShift
├── belongsTo TsHrisEmployee melalui emp_id
└── belongsTo TsHrisShift melalui shift_id
```

`TsHrisEmployeeShift::activeOn($date)` memilih assignment yang aktif, tidak
dihapus, dan berada dalam rentang tanggal. Untuk absensi berdasarkan tanggal,
assignment ini menjadi sumber shift utama; `TsHrisEmployee::shift()` merupakan
shift default atau legacy.

## Migration

Migration berada pada [database/migrations](database/migrations) dan dijalankan
setelah tabel legacy yang menjadi dependency tersedia.

| Migration | Fungsi |
| --- | --- |
| `000001_create_rfid_cards_table.php` | Kartu RFID, EPC unique, employee, dan issuer |
| `000002_add_shift_fields_to_attendance_logs_table.php` | `employee_shift_id`, `shift_mismatch`, dan index attendance |
| `000003_create_attendance_review_flags_table.php` | Flag review mismatch dan auto-close |
| `000004_create_unrecognized_scans_table.php` | Scan dari EPC yang tidak dikenali |

Tabel legacy yang harus sudah ada:

```text
users
ts_stores
ts_hris_employees
ts_hris_organizations
ts_hris_job_positions
ts_hris_shifts
ts_hris_employee_shifts
attendance_logs
```

`issued_by` dan `reviewed_by` mengarah ke `users.id`. Jika aplikasi menggunakan
tabel user lain, foreign key migration harus disesuaikan.

Index yang dibuat untuk query utama:

```text
attendance_logs(employee_id, scanned_at)
attendance_review_flags(review_status, reason)
unrecognized_scans(epc, scanned_at)
```

## Auto-Close Attendance

Command berada pada
[AutoCloseAttendance.php](app/Console/Commands/AutoCloseAttendance.php):

```bash
php artisan attendance:auto-close
```

Command dijadwalkan pada [routes/console.php](routes/console.php):

```php
Schedule::command('attendance:auto-close')
    ->dailyAt('23:59')
    ->withoutOverlapping();
```

Perilaku command:

- Mengambil check-in terakhir yang belum memiliki check-out untuk setiap employee.
- Mengunci ulang record di dalam transaction sebelum membuat auto-close.
- Menutup shift biasa pada akhir hari atau waktu command berjalan.
- Menunggu waktu selesai untuk shift overnight.
- Menyimpan `employee_shift_id` dari check-in asal.
- Membuat checkout dengan `is_auto_closed = true`.
- Membuat review flag dengan alasan `auto_closed`.
- Aman ketika scheduler dijalankan ulang karena memeriksa checkout kembali.

Aktifkan scheduler Laravel melalui worker:

```bash
php artisan schedule:work
```

Atau gunakan cron server:

```cron
* * * * * cd /path/to/laravel && php artisan schedule:run >> /dev/null 2>&1
```

## Dependency Legacy

Jangan membuat ulang tabel legacy jika tabel tersebut sudah tersedia di database
TalentOps. Pastikan hal berikut sesuai dengan schema aktual:

- Primary key dan foreign key menggunakan tipe data yang kompatibel.
- `ts_hris_employees.st_id` tersedia untuk validasi lokasi employee.
- `ts_hris_employee_shifts.emp_id` mengarah ke employee.
- Assignment shift memiliki `empshift_start_time` dan `empshift_end_time`.
- `attendance_logs` memiliki `employee_id`, `st_id`, `type`, `scanned_at`,
  `is_auto_closed`, dan `is_late`.
- Tabel `users` benar-benar menjadi sumber issuer dan reviewer.
- Timezone aplikasi dan timezone perangkat RFID konsisten.

## Catatan Production

Sebelum deployment production:

- Tambahkan authentication atau signature khusus perangkat RFID.
- Tambahkan rate limiting untuk endpoint scan.
- Uji retry perangkat, dua reader bersamaan, timezone, dan shift overnight.
- Pastikan scheduler hanya berjalan satu kali pada satu environment.
- Tambahkan test untuk `logged`, `ignored`, `unrecognized`, `invalid_card`, dan
  `invalid_store`.
- Validasi kebijakan penghapusan data historis scan tidak dikenal.
- Pastikan semua foreign key tersedia sebelum migration tambahan dijalankan.

## Status Repository

Komponen model, controller, route API, migration tambahan, command auto-close,
scheduler registration, dan Composer manifest sudah tersedia. Repository masih
memerlukan skeleton aplikasi Laravel lengkap serta schema legacy TalentOps untuk
dapat menjalankan migration, route listing, dan scheduler secara end-to-end.
