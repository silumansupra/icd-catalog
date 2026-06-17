# Katalog ICD-10 / ICD-9-CM

Web sederhana untuk pencarian kode **ICD-10** (diagnosa) dan **ICD-9-CM** (tindakan)
berdasarkan kode atau deskripsi, plus pencarian **Alphabetical Index** (Volume 3).
Dibangun dengan PHP + JavaScript polos (tanpa framework), PDO, single-file.

## Fitur

- Pencarian ICD-10 (14k+ kode) dan ICD-9-CM (4k kode) by kode/nama
- Alphabetical Index (ICD-10 Volume 3) — cari istilah, lompat ke detail kode
- Live search (debounce), pagination, tampilan deskripsi (Includes/Excludes)
- Endpoint JSON internal (`?api=search`, `?api=detail`)

## Kebutuhan

- PHP 7.4+ (PDO MySQL)
- MySQL / MariaDB

## Setup

1. Buat database lalu import skema:
   ```bash
   mysql -u root -p master_icd_x < schema.sql
   ```
2. Isi datanya. **Data ICD tidak disertakan** di repo ini (lihat catatan lisensi).
   Siapkan dump data kamu sendiri sesuai struktur di `schema.sql`.
3. Salin konfigurasi dan isi kredensial DB:
   ```bash
   cp config.example.php config.php
   ```
4. Jalankan (development):
   ```bash
   php -S localhost:8000
   ```
   Buka http://localhost:8000

## Struktur tabel

| Tabel                        | Isi                                             |
| ---------------------------- | ----------------------------------------------- |
| `icd10_chapters`             | Bab ICD-10 (22)                                 |
| `icd10_blocks`               | Blok/kelompok kode                              |
| `icd10_volume1`              | Kode ICD-10 + deskripsi (tabel pencarian utama) |
| `icd10_volume3`              | Alphabetical index (istilah → kode)             |
| `icd9cm_codes`               | Kode tindakan ICD-9-CM                          |
| `icd9cm_roots` / `child1..3` | Hierarki kategori ICD-9-CM                      |

## Catatan lisensi data

Kode ICD-10 adalah milik **WHO**; versi Indonesia didistribusikan melalui
**Kemenkes RI**. Repo ini hanya berisi **kode aplikasi dan struktur tabel**,
bukan datanya. Pastikan kamu punya hak/lisensi atas data ICD yang kamu pakai
sebelum mendistribusikannya.

## Lisensi kode

Kode aplikasi: MIT (lihat `LICENSE`).
