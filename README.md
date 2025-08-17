# simple-web-shortlink

URL shortener sederhana berbasis PHP + .htaccess. Proyek ini mempermudah Anda membuat tautan pendek (shortlink) di server sendiri, lengkap dengan halaman admin, login, dan statistik dasar.

> Catatan: README ini disusun berdasarkan struktur berkas yang ada di repo (`index.php`, `shorten.php`, `redirect.php`, `r.php`, `stats.php`, `admin.php`, `login.php`, `register.php`, dsb). Silakan sesuaikan jika ada perbedaan implementasi di kode Anda.

---

## Fitur

* 🔗 **Pemendek URL**: Buat shortlink dari URL panjang melalui antarmuka web (`index.php`) atau endpoint (`shorten.php`).
* 🧭 **Redirect cepat**: Akses `https://domain.tld/<kode>` akan mengarahkan ke URL asli melalui `redirect.php` / `r.php`.
* 🧮 **Statistik dasar**: Lihat jumlah klik dan daftar tautan di `stats.php` (jika diaktifkan di kode).
* 🔐 **Otentikasi & Admin**: `login.php`, `register.php`, dan `admin.php` untuk mengelola tautan (buat, hapus, unduh ekspor—sesuai fitur yang aktif di kode).
* 📦 **Ringan & mandiri**: PHP murni, tanpa framework berat. Cocok untuk shared hosting.

---

## Prasyarat

* PHP **7.4+** (disarankan PHP 8.x)
* Web server (Apache/Nginx)
* Modul **mod\_rewrite** aktif (Apache) atau rule rewrite setara (Nginx)
* Folder project dapat ditulis (writeable) jika menyimpan data ke berkas

> Jika project menyimpan data pada file/SQLite/MySQL, sesuaikan permission & kredensialnya pada file konfigurasi yang relevan (lihat bagian **Konfigurasi**).

---

## Instalasi Cepat

1. **Clone / Unduh**

   ```bash
   git clone https://github.com/zfchial/simple-web-shortlink.git
   cd simple-web-shortlink
   ```
2. **Letakkan di web root** (mis. `/var/www/html/simple-web-shortlink` atau subfolder lain).
3. **Aktifkan rewrite**

   * **Apache**: pastikan `.htaccess` aktif (lihat rule di bawah). Jika menggunakan subfolder, sesuaikan `RewriteBase` bila diperlukan.
   * **Nginx**: tambahkan blok rewrite (contoh di bawah) supaya `/KODE` diproses oleh `redirect.php`.
4. **(Opsional) Buat akun admin**

   * Akses `https://domain.tld/register.php` (jika tersedia) untuk membuat akun awal.
   * Atau atur user langsung di database/file konfigurasi sesuai implementasi.
5. **Selesai.** Buka `https://domain.tld/` untuk mencoba.

---

## Konfigurasi

Implementasi konfigurasi bisa berbeda. Umumnya yang perlu dicek di awal baris file utama (`index.php`, `shorten.php`, dsb):

* **BASE\_URL / SITE\_URL**: jika tersedia, set ke domain Anda (mis. `https://short.domain.tld`).
* **Penyimpanan**: pastikan jalur folder/DB yang digunakan untuk menyimpan shortlink bisa ditulis (permission 755/775/777 sesuai kebutuhan dan kebijakan keamanan Anda).
* **Kredensial Admin**: jika disimpan di file/env/DB, ganti nilai default.

> Jika repo menyertakan file khusus konfigurasi (mis. `config.php` atau `.env.example`), salin ke `.env`/`config.php` dan sesuaikan nilainya.

---

## Rewrite Rules

### Apache (.htaccess)

Contoh umum `.htaccess` untuk mengarahkan semua path tanpa file/dir ke handler redirect:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
# Arahkan /KODE ke redirect.php?k=KODE (sesuaikan param kalau di kode berbeda)
RewriteRule ^([^/]+)/?$ redirect.php?k=$1 [L,QSA]
```

> Jika repo sudah menyertakan `.htaccess`, gunakan yang bawaan dan **sesuaikan saja** jika struktur berbeda.

### Nginx

Tambahkan ke blok `server {}`:

```nginx
location / {
    try_files $uri $uri/ /redirect.php?k=$uri;
}
```

> Jika aplikasi menggunakan `index.php` sebagai front controller, Anda bisa mengarahkannya ke `index.php?q=$uri` lalu mapping di PHP.

---

## Cara Pakai

### 1) Lewat Halaman Web

* Buka `https://domain.tld/` (`index.php`).
* Tempel URL panjang → klik **Shorten**.
* Anda akan menerima shortlink, misal `https://domain.tld/abc123`.

### 2) Lewat Endpoint (API sederhana)

Kirim permintaan ke `shorten.php`:

* **POST** atau **GET** parameter umum:

  * `url` (wajib): URL tujuan penuh, mis. `https://example.com/path?a=1`.
  * `alias` (opsional): kode custom, mis. `promo-ramadhan`. Jika bentrok, server mengembalikan error.

Contoh `curl`:

```bash
# auto-generate kode
curl -X POST https://domain.tld/shorten.php \
  -d "url=https://example.com/some/very/long/path?ref=abc"

# dengan alias custom
curl -X POST https://domain.tld/shorten.php \
  -d "url=https://example.com/some/very/long/path?ref=abc" \
  -d "alias=promo-ramadhan"
```

Respons dapat berupa HTML/JSON/Teks tergantung implementasi di `shorten.php`. Silakan sesuaikan penanganannya pada sisi klien.

### 3) Redirect

* Akses `https://domain.tld/<kode>` → diarahkan otomatis oleh `redirect.php` / `r.php`.

### 4) Statistik

* Kunjungi `https://domain.tld/stats.php` untuk melihat daftar tautan/klik (jika diaktifkan oleh kode).

### 5) Admin

* `https://domain.tld/login.php` → masuk
* `https://domain.tld/admin.php` → kelola tautan (hapus, ekspor, dll—bergantung fitur di repo)

---

## Struktur Proyek (ringkas)

* `.htaccess` — aturan rewrite untuk Apache.
* `index.php` — halaman utama pembuat shortlink.
* `shorten.php` — endpoint untuk membuat shortlink (menerima `url`, opsi `alias`).
* `redirect.php` / `r.php` — handler redirect untuk `/<kode>`.
* `stats.php` — halaman statistik tautan (opsional).
* `login.php`, `register.php`, `logout.php` — otentikasi pengguna/admin.
* `admin.php`, `admin_actions.php` — panel admin & aksi manajemen tautan.
* `upload.php`, `download.php`, `save_file.php`, `delete.php`, `delete_all_files.php`, `f.php` — utilitas tambahan (mis. unggah/kelola berkas jika fitur ini dipakai di aplikasi Anda). Non-inti shortlink; aktifkan hanya jika memang digunakan.

> Nama & peran file di atas diambil dari isi repo; cek masing-masing file untuk detail parameter & logika aktual.

---

## Tips Keamanan

* Ubah kredensial default, batasi pendaftaran (`register.php`) hanya untuk admin.
* Validasi & sanitasi `url` (hanya skema yang diizinkan: `http`, `https`, dll.).
* Tambahkan **rate limiting**/captcha pada `shorten.php` jika dibuka publik.
* Lindungi halaman admin dengan session yang aman (cookie `HttpOnly`, `SameSite`, opsi HTTPS-only).
* Jika menyimpan data di file, pastikan lokasi penyimpanan tidak dapat diakses publik (di luar web root atau dilindungi `.htaccess`).

---

## Deployment

* **Shared Hosting (Apache)**: unggah semua file ke public\_html, pastikan `.htaccess` aktif.
* **VPS (Nginx + PHP-FPM)**: sesuaikan blok server & root; pastikan permission file/folder benar.
* **Docker (opsional)**: buat Dockerfile sederhana berbasis `php:apache` atau `php:fpm-alpine` dan salin project ke container, expose port 80/443.

---

## Lisensi

Belum ditemukan file lisensi pada repo saat README ini disusun. Pertimbangkan menambahkan lisensi (mis. MIT) agar jelas bagi kontributor/pengguna.

---

## Roadmap Ide (opsional)

* Custom domain per pengguna
* Expiration date untuk shortlink
* Password-protected link
* Statistik klik lebih lengkap (referrer, UA, geo)
* API JSON resmi dengan token
* Ekspor/backup data

---

## Kontribusi

PR & issue sangat dipersilakan:

1. Fork repo
2. Buat branch fitur: `git checkout -b feat/nama-fitur`
3. Commit terstruktur: `feat: deskripsi singkat`
4. PR ke `main` + jelaskan perubahan

---

## Kredit

* Penulis proyek: pemilik repo `zfchial`
* README: disusun otomatis berdasarkan struktur repo
