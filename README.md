# 🎓 QLC - Quranic Leadership Centre Platform

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18.x-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://react.dev)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![MongoDB](https://img.shields.io/badge/MongoDB-Atlas-47A248?style=for-the-badge&logo=mongodb&logoColor=white)](https://www.mongodb.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)

**QLC (Quranic Leadership Centre)** adalah platform manajemen pembelajaran (LMS) dan portal pendidikan modern yang dirancang untuk mengintegrasikan interaksi antara **Admin**, **Guru (Teacher)**, **Orang Tua (Parents)**, dan **Mitra**. Platform ini dilengkapi dengan landing page interaktif, pendaftaran siswa online, serta sistem laporan perkembangan siswa secara real-time.

---

## 🚀 Fitur Utama

- **🌐 Public Landing Page Interaktif**:
  - Profil lembaga & pengurus (*Foundations & Leaders*).
  - Katalog program pendidikan & detail program.
  - Galeri kegiatan (Foto & Video) dan Agenda kegiatan terbaru.
  - Halaman pengajuan kerja sama mitra.

- **👥 Multi-Role User System**:
  - **Admin**: Dashboard analitik menyeluruh, manajemen master data, program, galeri, dan user.
  - **Teacher**: Pengelolaan laporan perkembangan siswa (*Progress Reports*), penjadwalan, dan presensi.
  - **Parents**: Pendaftaran siswa baru (*Enrollment*) dengan unggah dokumen, serta pemantauan laporan belajar anak.
  - **Mitra**: Dashboard laporan kerja sama dan kolaborasi program.

- **🔐 Autentikasi & Keamanan**:
  - Autentikasi berbasis Laravel Breeze + Inertia.
  - Verifikasi pendaftaran berbasis **OTP Email** & penanganan sesi aman.
  - Middleware kontrol akses berbasis peran (*Role-based Access Control / RBAC*).

- **🔔 API Notifikasi**:
  - Sistem pengiriman & penandaan notifikasi terbaca (*Mark as Read*) secara real-time.

---

## 🛠️ Stack Teknologi

- **Backend**: [Laravel 12](https://laravel.com)
- **Database**: [MongoDB](https://www.mongodb.com/) (menggunakan driver `mongodb/laravel-mongodb`)
- **Frontend**: [React 18](https://react.dev) + [TypeScript](https://www.typescriptlang.org/) via [Inertia.js 2.0](https://inertiajs.com)
- **Bundler & Build Tool**: [Vite 7](https://vitejs.dev/)
- **Styling**: [Tailwind CSS](https://tailwindcss.com) + [Lucide React Icons](https://lucide.dev/)
- **Code Quality**:
  - [Laravel Pint](https://laravel.com/docs/pint) (PHP Code Style)
  - TypeScript Compiler (`tsc --noEmit`)
  - PHPUnit / Artisan Test Suite

---

## 📋 Prasyarat Sistem

Sebelum menjalankan proyek ini, pastikan sistem Anda telah memenuhi prasyarat berikut:

- **PHP** `>= 8.2` (dengan ekstensi `mongodb` terinstal)
- **Composer** `>= 2.x`
- **Node.js** `>= 18.x` & **npm** `>= 9.x`
- **MongoDB** (Lokal atau MongoDB Atlas Cluster)

---

## ⚙️ Panduan Instalasi & Pengaturan

Follow langkah-langkah di bawah untuk menjalankan proyek secara lokal:

### 1. Clone Repository & Masuk ke Direktori
```bash
git clone https://github.com/Haitsam06/QLC.git
cd QLC
```

### 2. Instal Dependensi (PHP & Node.js)
```bash
composer install
npm install
```

### 3. Konfigurasi Environment File
Salin `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Buka file `.env` dan atur variabel koneksi MongoDB serta Mail Server:
```env
APP_NAME="Quranic Leadership Centre"
APP_URL=http://127.0.0.1:8000

# Database Configuration (MongoDB)
DB_CONNECTION=mongodb
MONGODB_URI="mongodb+srv://<username>:<password>@<cluster>.mongodb.net/?appName=Cluster0"
MONGODB_DATABASE=qlc

# Mail Configuration (SMTP / Mailtrap)
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Seeding Database
Jalankan seeder untuk mengisi data awal (Role, Admin, Landing Page, Program, dll):
```bash
php artisan db:seed
```

### 6. Jalankan Server Pengembang (Development Server)
Anda dapat menjalankan server backend (Laravel) dan frontend (Vite) sekaligus menggunakan command concurrently bawaan:
```bash
npm run dev
# ATAU via Composer:
composer run dev
```

Aplikasi dapat diakses melalui browser di: `http://127.0.0.1:8000`

---

## 🧪 Perintah Pengembangan & Quality Check

- **Menjalankan TypeScript Type-Checking**:
  ```bash
  npx tsc --noEmit
  ```

- **Menjelajah & Memeriksa Formatting Code Style PHP (Pint)**:
  ```bash
  # Uji format tanpa mengubah file (Dry-run)
  ./vendor/bin/pint --test

  # Perbaiki format PHP secara otomatis
  ./vendor/bin/pint
  ```

- **Menjalankan Pengujian (Testing)**:
  ```bash
  php artisan test
  ```

- **Build untuk Production**:
  ```bash
  npm run build
  ```

---

## 📂 Struktur Direktori Utama

```text
QLC/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # Controller untuk Admin, Teacher, Parents, Mitra, Auth
│   │   └── Middleware/      # Middleware RBAC & Security Headers
│   ├── Mail/                # Mailable (Send OTP Mail, Verify Registration)
│   └── Models/              # Model Eloquent MongoDB (User, Student, Program, etc.)
├── config/                  # File konfigurasi aplikasi & MongoDB
├── database/
│   ├── migrations/          # Migrasi koleksi MongoDB
│   └── seeders/             # Data seeder dummy & master data
├── resources/
│   ├── js/                  # Source code Frontend (React + TypeScript + Inertia)
│   │   ├── Components/      # Reusable UI components
│   │   ├── Layouts/         # App Layouts per role
│   │   └── Pages/           # Inertia Pages (Welcome, Dashboards, Landing, etc.)
│   └── css/                 # Global styles & Tailwind entrypoint
├── routes/
│   ├── web.php              # Route aplikasi utama & role-based dashboard
│   ├── api.php              # Endpoint API (Notifikasi, dll)
│   └── auth.php             # Route autentikasi Laravel Breeze
└── vite.config.js           # Konfigurasi Vite + React + Laravel Plugin
```

---
