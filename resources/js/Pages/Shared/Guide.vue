<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpenText, Download, FileSpreadsheet, FileText, Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

type Role = 'teacher' | 'validator' | 'admin';

const props = defineProps<{ defaultRole: string | null }>();

const tabs: { key: Role; label: string; intro: string }[] = [
    { key: 'teacher', label: 'Guru', intro: 'Membuat paket soal, memperbaikinya, lalu mengunduh naskah final.' },
    { key: 'validator', label: 'Validator', intro: 'Menelaah soal per nomor memakai daftar periksa, menyetujui atau meminta perubahan.' },
    { key: 'admin', label: 'Admin', intro: 'Menyiapkan tahun ajaran, akun, penugasan, lalu memantau seluruh paket.' },
];

const active = ref<Role>((['teacher', 'validator', 'admin'] as string[]).includes(props.defaultRole ?? '') ? (props.defaultRole as Role) : 'teacher');
function print(): void {
    window.print();
}

const current = computed(() => tabs.find((t) => t.key === active.value)!);

interface Step {
    title: string;
    text: string;
    link?: { href: string; label: string };
}

const steps: Record<Role, Step[]> = {
    teacher: [
        { title: 'Masuk dan ganti kata sandi', text: 'Masuk dengan email atau NIP. Pada login pertama Anda diminta mengganti kata sandi awal dari Admin. Pilih periode asesmen pada bilah atas.' },
        { title: 'Siapkan kisi-kisi dan naskah', text: 'Gunakan template standar. Kisi-kisi berupa Excel, naskah berupa Word. Ikuti aturan penulisan pada bagian "Menulis naskah" di bawah.', link: { href: '/teacher/packages/create', label: 'Buat paket' } },
        { title: 'Buat paket soal', text: 'Pilih penugasan (mapel & tingkat), beri judul, unggah kisi-kisi dan naskah. Sistem memecah berkas menjadi soal per nomor dan menampilkan peringatan bila ada yang tidak cocok.' },
        { title: 'Periksa dan perbaiki per nomor', text: 'Buka tiap nomor: ubah teks, pilihan, kunci, atau indikator. Rumus ditulis di antara \\( dan \\); gambar cukup ditempel (Ctrl+V) di kotak soal. Simpan sebagai draf kapan saja.' },
        { title: 'Kirim ke validator', text: 'Setelah semua nomor lengkap, kirim paket. Anda mendapat notifikasi saat validator menyetujui atau meminta perubahan.' },
        { title: 'Revisi bila diminta', text: 'Nomor berstatus "Perlu ganti" memuat catatan validator. Perbaiki nomor itu saja, atau unggah ulang naskah revisi; gambar lama otomatis dilepas dan diganti.' },
        { title: 'Unduh hasil akhir', text: 'Saat semua nomor disetujui, paket berstatus Fix. Unduh naskah final (DOCX, PDF bila tersedia), naskah berformat Template CBT sekolah (siap diunggah ke CBT), rekap validasi, atau soal tervalidasi dari panel Unduhan.' },
    ],
    validator: [
        { title: 'Buka Inbox Telaah', text: 'Paket yang menunggu Anda tampil di Inbox, diurutkan dari yang paling mendesak. Dashboard menampilkan ringkasan dan tenggat.', link: { href: '/validator/inbox', label: 'Inbox Telaah' } },
        { title: 'Telaah nomor demi nomor', text: 'Kisi-kisi dan soal tampil berdampingan. Baca indikator, periksa soal, pilihan, dan kunci. Tombol terjemah membantu memahami soal berbahasa lain.' },
        { title: 'Isi daftar periksa', text: 'Sepuluh butir daftar periksa (indikator, materi, bahasa, kunci, pengecoh, level, bebas bias, stimulus, pilihan homogen, soal dibutuhkan). Hasilnya tercatat dan muncul pada rekap validasi.' },
        { title: 'Cek kemiripan', text: 'Panel kemiripan menunjukkan soal serupa di arsip, paket Fix semester lain, dan paket lain pada periode ini, lengkap dengan persentasenya.' },
        { title: 'Setujui atau minta perubahan', text: 'Setujui nomor yang sudah baik. Untuk yang belum, pilih "Minta perubahan" dan tulis catatan yang jelas agar guru tahu apa yang diperbaiki. Keputusan dapat dibuka kembali bila perlu.' },
        { title: 'Selesai', text: 'Bila semua nomor disetujui, paket otomatis berstatus Fix dan naskah final dibuat. Rekap validasi siap cetak dapat diunduh dari panel Unduhan.' },
    ],
    admin: [
        { title: 'Siapkan struktur akademik', text: 'Urutan: Tahun Ajaran → Semester (aktifkan satu) → Periode Asesmen → Mapel (beserta tingkat yang mengajarkannya).', link: { href: '/admin/academic-years', label: 'Tahun Ajaran' } },
        { title: 'Buat akun guru', text: 'Tambah satu per satu atau impor Excel. Cukup email atau NIP (keduanya bisa dipakai login). Kolom password opsional; kosong = dibuat acak. Unduh kartu akun (sekali unduh) untuk dibagikan.', link: { href: '/admin/teachers', label: 'Akun Guru' } },
        { title: 'Atur penugasan mengajar', text: 'Buka menu Penugasan Mengajar: seluruh mapel beserta kelas dan fasenya sudah tampil urut abjad. Pada tiap baris, pilih guru pengampunya (satu guru per mapel-kelas; ringkasan jumlah penugasan tampil di atas tabel). Gunakan "Salin dari semester lain" untuk rollover ke semester baru.', link: { href: '/admin/assignments', label: 'Penugasan' } },
        { title: 'Tunjuk validator', text: 'Aktifkan peran validator pada guru yang dipilih dan atur cakupan mapel/tingkatnya.', link: { href: '/admin/validators', label: 'Validator' } },
        { title: 'Pantau paket', text: 'Monitoring paket menampilkan status dan progres. Admin dapat mengganti validator, mengunci paket Fix, dan mengunduh massal (ZIP) naskah final, soal tervalidasi, atau rekap.', link: { href: '/admin/packages', label: 'Monitoring Paket' } },
        { title: 'Arsip dan pengaturan', text: 'Impor naskah tahun lalu ke Arsip untuk pengecekan kemiripan. Pada Pengaturan, atur kop sekolah, logo, font, dan format dokumen final.', link: { href: '/admin/settings', label: 'Pengaturan' } },
    ],
};

const statuses = [
    { name: 'Draft', text: 'Paket masih disusun guru.' },
    { name: 'Terkirim', text: 'Menunggu validator.' },
    { name: 'Ditelaah', text: 'Validator sedang menelaah.' },
    { name: 'Perlu revisi', text: 'Ada nomor yang harus diperbaiki guru.' },
    { name: 'Fix', text: 'Semua nomor disetujui; naskah final siap.' },
    { name: 'Terkunci', text: 'Dikunci admin; tidak dapat diubah.' },
];

const rules = [
    { t: 'Judul bagian', d: 'Baris sendiri: Pilihan Ganda, Pilihan Ganda Kompleks, Benar/Salah, Menjodohkan, Isian, atau Uraian. Menentukan bentuk soal.' },
    { t: 'Nomor soal', d: 'Daftar bernomor otomatis Word (1., 2., …), berlanjut di seluruh dokumen dan sama dengan "No. Soal" pada kisi-kisi.' },
    { t: 'Pilihan jawaban', d: 'Daftar huruf otomatis A–E, mulai ulang dari A pada tiap soal. Maksimal 5 pilihan.' },
    { t: 'Kunci jawaban', d: 'Beri warna font MERAH pada pilihan yang benar. Pilihan ganda kompleks: beberapa pilihan merah.' },
    { t: 'Benar/Salah', d: 'Tulis soal, lalu pernyataan berhuruf (A, B, …) di bawahnya. Akhiri tiap pernyataan dengan "(B/S)" dan warnai huruf yang benar (B atau S) merah.' },
    { t: 'Menjodohkan', d: 'Tulis jawaban benar di dalam tanda kurung merah di akhir tiap pernyataan, mis. "Surabaya (Jawa Timur)". Lalu satu baris "Pengecoh: Sulawesi Selatan; Kalimantan Timur" (pisahkan dengan titik koma). Pilihan jawaban harus lebih banyak daripada pernyataan: 5 pernyataan = minimal 6 pilihan.' },
    { t: 'Isian & uraian', d: 'Kunci/pedoman penskoran tidak dibaca dari naskah; isi di BESTIE pada halaman soal.' },
    { t: 'Rumus & gambar', d: 'Rumus: Insert → Equation. Gambar: tempel di bawah teks soal. Keduanya tampil pada telaah dan naskah final.' },
    { t: 'Stimulus', d: 'Paragraf tanpa nomor di antara soal dan pilihan dianggap bagian soal (bacaan, tabel, kutipan).' },
];

const faqs = [
    { q: 'Kunci jawaban tidak terbaca / peringatan "kunci belum ditandai".', a: 'Pastikan warna font benar-benar merah (bukan sorotan/highlight) pada pilihan yang benar. Untuk Benar/Salah, huruf B atau S pada "(B/S)" yang diberi warna merah. Anda juga bisa memperbaiki kunci langsung di halaman soal.' },
    { q: 'Nomor atau bentuk soal tidak cocok dengan kisi-kisi.', a: 'Samakan kolom "No. Soal" dan "Bentuk Soal" pada kisi-kisi dengan urutan serta judul bagian pada naskah. Peringatan menunjukkan nomor yang berbeda.' },
    { q: 'Gambar tidak muncul di soal.', a: 'Tempel gambar langsung di bawah teks soal pada Word (bukan melayang di belakang teks), atau tempel (Ctrl+V) di kotak soal pada halaman edit. Gambar yang sudah diganti saat revisi dilepas otomatis.' },
    { q: 'Boleh unggah PDF?', a: 'Boleh, tetapi PDF harus berisi teks (bukan hasil pindai) dan tidak membawa warna kunci maupun gambar. Untuk hasil paling lengkap gunakan DOCX.' },
    { q: 'Naskah final belum bisa diunduh.', a: 'DOCX dibuat langsung begitu paket Fix. PDF menyusul bila LibreOffice dikonfigurasi. Bila format dokumen (kop, font) diubah Admin, klik "Buat ulang naskah final".' },
    { q: 'Lupa kata sandi.', a: 'Hubungi Admin untuk mengatur ulang; kata sandi baru ditampilkan sekali dan wajib diganti saat masuk.' },
    { q: 'Apa arti persentase kemiripan?', a: 'Kemiripan teks tertinggi dengan soal di arsip, paket Fix semester lain, atau paket lain pada periode ini. ≥ 70% ditandai tinggi, ≥ 50% sedang. Ini alat bantu; keputusan tetap pada validator.' },
    { q: 'Tampilan terlalu terang/gelap, atau menu terlalu lebar.', a: 'Gunakan tombol tema di bilah atas. Menu samping dapat diciutkan dengan tombol di bilah atas, di samping periode.' },
];
</script>

<template>
    <Head title="Panduan" />
    <PageHeader title="Panduan penggunaan BESTIE" description="Langkah kerja tiap peran, cara menulis naskah dan kisi-kisi, serta jawaban pertanyaan umum.">
        <button type="button" class="btn-secondary print:hidden" @click="print"><Printer class="size-4" /> Cetak</button>
    </PageHeader>

    <!-- Template -->
    <section v-if="active === 'teacher'" class="card mb-6 grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
        <a href="/templates/naskah" class="group flex items-center gap-4 rounded-xl border border-line p-4 transition hover:border-primary-400 hover:bg-surface-2">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"><FileText class="size-6" /></span>
            <span class="min-w-0">
                <span class="block font-bold text-ink">Template naskah soal (.docx)</span>
                <span class="block text-xs text-ink-soft">Contoh enam bentuk soal, penomoran otomatis, kunci merah, rumus.</span>
            </span>
            <Download class="ml-auto size-4 shrink-0 text-ink-faint group-hover:text-primary-600" />
        </a>
        <a href="/templates/kisi-kisi-word" class="group flex items-center gap-4 rounded-xl border border-line p-4 transition hover:border-primary-400 hover:bg-surface-2">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><FileText class="size-6" /></span>
            <span class="min-w-0">
                <span class="block font-bold text-ink">Template kisi-kisi (.docx)</span>
                <span class="block text-xs text-ink-soft">Tabel Word berjudul kolom standar, kolom wajib berwarna oranye, contoh nomor 1–8.</span>
            </span>
            <Download class="ml-auto size-4 shrink-0 text-ink-faint group-hover:text-primary-600" />
        </a>
        <a href="/templates/kisi-kisi" class="group flex items-center gap-4 rounded-xl border border-line p-4 transition hover:border-primary-400 hover:bg-surface-2">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><FileSpreadsheet class="size-6" /></span>
            <span class="min-w-0">
                <span class="block font-bold text-ink">Template kisi-kisi (.xlsx)</span>
                <span class="block text-xs text-ink-soft">Kolom standar, contoh nomor 1–8, daftar pilihan level &amp; bentuk, lembar petunjuk.</span>
            </span>
            <Download class="ml-auto size-4 shrink-0 text-ink-faint group-hover:text-primary-600" />
        </a>
    </section>

    <!-- Langkah per peran -->
    <section class="card mb-6 p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <BookOpenText class="size-5 text-primary-600" />
            <h2 class="text-base font-bold text-ink">Langkah kerja {{ current.label }}</h2>
            <span class="ml-auto rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ current.label }}</span>
        </div>
        <p class="mt-2 text-sm text-ink-soft">{{ current.intro }}</p>

        <ol class="mt-5 space-y-4">
            <li v-for="(s, i) in steps[active]" :key="s.title" class="flex gap-4">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-bold text-white">{{ i + 1 }}</span>
                <div class="min-w-0 pb-1">
                    <p class="font-bold text-ink">{{ s.title }}</p>
                    <p class="mt-0.5 text-sm leading-relaxed text-ink-soft">{{ s.text }}</p>
                    <Link v-if="s.link" :href="s.link.href" class="mt-1 inline-block text-sm font-semibold text-primary-600 hover:underline print:hidden">{{ s.link.label }} →</Link>
                </div>
            </li>
        </ol>
    </section>

    <!-- Alur status -->
    <section class="card mb-6 p-5 sm:p-6">
        <h2 class="text-base font-bold text-ink">Status paket</h2>
        <ol class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="(s, i) in statuses" :key="s.name" class="flex items-start gap-3 rounded-xl bg-surface-2 p-3">
                <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-800">{{ i + 1 }}</span>
                <span><span class="block text-sm font-bold text-ink">{{ s.name }}</span><span class="block text-xs text-ink-soft">{{ s.text }}</span></span>
            </li>
        </ol>
        <p class="mt-3 text-xs text-ink-faint">Status tiap nomor: Belum ditelaah → Disetujui / Perlu ganti → Direvisi (setelah diperbaiki guru) → Disetujui.</p>
    </section>

    <!-- Menulis naskah -->
    <section v-if="active === 'teacher'" class="card mb-6 p-5 sm:p-6">
        <h2 class="text-base font-bold text-ink">Menulis naskah (.docx) agar terbaca otomatis</h2>
        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <dl class="space-y-3">
                <div v-for="r in rules" :key="r.t">
                    <dt class="text-sm font-bold text-ink">{{ r.t }}</dt>
                    <dd class="text-sm text-ink-soft">{{ r.d }}</dd>
                </div>
            </dl>

            <!-- Contoh tampilan di Word -->
            <div class="h-fit rounded-xl border border-line bg-white p-5 font-serif text-[13px] leading-relaxed text-slate-900 shadow-soft" aria-label="Contoh naskah di Word">
                <p class="font-bold">Pilihan Ganda</p>
                <p><span class="mr-2">1.</span>Hasil dari 3/4 + 1/4 adalah ....</p>
                <p class="ml-5">A. &nbsp;1/2</p>
                <p class="ml-5">B. &nbsp;3/4</p>
                <p class="ml-5 text-red-600">C. &nbsp;1 <span class="ml-1 rounded bg-red-50 px-1 font-sans text-[10px] font-semibold">← kunci (merah)</span></p>
                <p class="ml-5">D. &nbsp;5/4</p>
                <p class="mt-3 font-bold">Benar/ Salah</p>
                <p><span class="mr-2">2.</span>Bilangan prima terkecil adalah 2. (<span class="text-red-600">B</span>/S)</p>
                <p class="mt-3 font-bold">Menjodohkan</p>
                <p><span class="mr-2">3.</span>Jodohkan akar kuadrat dengan hasilnya!</p>
                <p class="ml-5">A. &nbsp;Akar dari 16 (<span class="text-red-600">4</span>)</p>
                <p class="ml-5">B. &nbsp;Akar dari 81 (<span class="text-red-600">9</span>)</p>
            </div>
        </div>
    </section>

    <!-- Kisi-kisi -->
    <section class="card mb-6 p-5 sm:p-6">
        <h2 class="text-base font-bold text-ink">Menulis kisi-kisi</h2>
        <div class="table-wrap mt-4">
            <table class="table">
                <thead><tr><th>Kolom</th><th>Status</th><th>Isi</th></tr></thead>
                <tbody>
                    <tr><td class="font-semibold">Capaian Pembelajaran, Materi, Submateri</td><td class="text-ink-faint">Opsional</td><td>Boleh sel digabung; baris di bawahnya otomatis mewarisi nilainya.</td></tr>
                    <tr><td class="font-semibold">Indikator Soal</td><td class="font-bold text-danger-600">WAJIB</td><td>Satu indikator per nomor: "Disajikan …, peserta didik dapat …".</td></tr>
                    <tr><td class="font-semibold">No. Soal</td><td class="font-bold text-danger-600">WAJIB</td><td>Angka nomor pada naskah. Inilah yang dicocokkan dengan naskah.</td></tr>
                    <tr><td class="font-semibold">Level Kognitif</td><td class="text-ink-faint">Opsional</td><td>C1 Pengetahuan, C2 Pemahaman, C3 Aplikasi, C4 Analisis, C5 Evaluasi, C6 Kreasi.</td></tr>
                    <tr><td class="font-semibold">Bentuk Soal</td><td class="font-bold text-danger-600">WAJIB</td><td>PG, PGK (pilihan ganda kompleks), BS (benar/salah), MJD (menjodohkan), IS (isian singkat), Uraian.</td></tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-ink-faint">Yang wajib hanya Indikator Soal, No. Soal, dan Bentuk Soal; kolom lain boleh dikosongkan. Kisi-kisi dapat diunggah sebagai Excel, Word, atau PDF berteks; Excel paling akurat.</p>
    </section>

    <!-- FAQ -->
    <section class="card mb-8 p-5 sm:p-6">
        <h2 class="text-base font-bold text-ink">Pertanyaan umum</h2>
        <div class="mt-3 divide-y divide-line">
            <details v-for="f in faqs" :key="f.q" class="group py-3">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-ink">
                    {{ f.q }}
                    <span class="text-ink-faint transition group-open:rotate-45" aria-hidden="true">+</span>
                </summary>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ f.a }}</p>
            </details>
        </div>
    </section>
</template>
