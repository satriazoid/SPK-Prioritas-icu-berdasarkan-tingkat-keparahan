# SPK Prioritas Pasien ICU Berdasarkan Tingkat Keparahan

Proyek ini adalah sistem pendukung keputusan untuk menentukan prioritas pasien di ICU menggunakan dataset WiDS Datathon 2020 (Kaggle). Sistem ini membandingkan dua metode hibrida: **AHP + SAW** dan **AHP + WP**.

##  Deskripsi Singkat
Sistem ini mengambil data medis pasien seperti usia, BMI, kadar oksigen (SpO2), dan detak jantung untuk menghitung skor keparahan. Bobot kriteria ditentukan menggunakan **AHP**, kemudian perangkingan dilakukan melalui dua metode (**SAW** dan **WP**) untuk melihat perbandingan hasil prioritas pasien.

##  Kriteria (Matriks 4x4)
- **Age** (Usia)
- **BMI** (Body Mass Index)
- **d1_spo2_min** (Oksigen minimal hari pertama)
- **d1_heartrate_min** (Detak jantung minimal hari pertama)

##  Struktur Folder
- `analytics/`: Berisi proses data mining (preprocessing) menggunakan Python.
- `core/`: Logika utama perhitungan metode SPK.

##  Teknologi
- PHP (Native)
- MySQL (Laragon / phpMyAdmin)
- Kaggle Dataset
