<?php

declare(strict_types=1);

/*
| Pesan validasi Bahasa Indonesia (cakupan aturan yang dipakai BankSoal).
| Nama kolom yang ramah diatur di method attributes() pada tiap Form Request.
*/
return [
    'accepted' => ':attribute harus diterima.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, strip, dan garis bawah.',
    'array' => ':attribute harus berupa daftar.',
    'after' => ':attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => ':attribute harus berupa tanggal yang sama dengan atau setelah :date.',
    'between' => [
        'numeric' => ':attribute harus di antara :min dan :max.',
        'string' => ':attribute harus di antara :min dan :max karakter.',
    ],
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi saat ini tidak sesuai.',
    'date' => ':attribute bukan tanggal yang valid.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max digit angka.',
    'distinct' => ':attribute memiliki nilai ganda.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa berkas.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'max' => [
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'file' => ':attribute tidak boleh lebih dari :max kilobita.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
        'array' => ':attribute tidak boleh lebih dari :max item.',
    ],
    'mimes' => ':attribute harus berupa berkas bertipe: :values.',
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'file' => ':attribute minimal :min kilobita.',
        'string' => ':attribute minimal :min karakter.',
        'array' => ':attribute minimal memiliki :min item.',
    ],
    'present' => ':attribute wajib ada.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terdaftar.',
    'uploaded' => ':attribute gagal diunggah.',

    'password' => [
        'letters' => ':attribute harus mengandung setidaknya satu huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus mengandung setidaknya satu angka.',
        'symbols' => ':attribute harus mengandung setidaknya satu simbol.',
        'uncompromised' => ':attribute pernah bocor di internet. Pilih kata sandi lain.',
    ],

    'custom' => [],
    'attributes' => [],
];
