<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Berkas unggahan
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_mb' => 20,
        'mimes' => ['docx', 'xlsx', 'pdf', 'png', 'jpg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Impor akun guru
    |--------------------------------------------------------------------------
    */
    'import' => [
        'max_rows' => 1000,
        'max_subject_rows' => 500,
        'max_kb' => 5120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama antrian
    |--------------------------------------------------------------------------
    | Lokal memakai "default" agar `composer dev` langsung memprosesnya.
    | Di produksi ubah lewat .env, mis. BANKSOAL_QUEUE_IMPORT=import
    */
    'queues' => [
        'import' => env('BANKSOAL_QUEUE_IMPORT', 'default'),
        'extract' => env('BANKSOAL_QUEUE_EXTRACT', 'default'),
        'export' => env('BANKSOAL_QUEUE_EXPORT', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Konversi PDF (opsional)
    |--------------------------------------------------------------------------
    | Isi dengan path soffice.exe (LibreOffice) agar ekspor final juga menghasilkan PDF, mis.
    | BANKSOAL_SOFFICE="C:\\Program Files\\LibreOffice\\program\\soffice.exe". Kosong = hanya DOCX.
    */
    'soffice_path' => env('BANKSOAL_SOFFICE'),

    /*
    |--------------------------------------------------------------------------
    | Kata sandi
    |--------------------------------------------------------------------------
    */
    // true = job berat (impor, ekstraksi naskah, PDF) berjalan segera setelah respons tanpa worker antrian.
    'inline_jobs' => env('BANKSOAL_INLINE_JOBS', true),

    'password' => [
        'min_length' => 10,
        'generated_length' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Nilai bawaan pengaturan (dapat diubah Admin di halaman Pengaturan)
    |--------------------------------------------------------------------------
    */
    'settings_defaults' => [
        'school_name' => 'SMA Negeri 1 Turen',
        'allow_self_validation' => true,
        'require_cross_validator_for_self' => false,
        'export_format' => [
            'font' => 'Times New Roman',
            'font_size' => 12,
            'margin_cm' => 2.0,
            'kop_top' => "Pemerintah Provinsi Jawa Timur\nDinas Pendidikan",
            'kop_extra' => "Jalan Mayjend Panjaitan 65 Turen, Malang 65175, Telp (0341) 824711\nLaman: www.smanegeri1turen.sch.id, pos-el: admin@smanegeri1turen.sch.id",
            'show_logo' => true,
            'footer_text' => '',
            'doc_prefix' => 'NSK',
            'show_key_sheet' => true,
            'show_blueprint' => true,
            'show_recap' => false,
        ],
    ],

    'timezone' => 'Asia/Jakarta',
];
