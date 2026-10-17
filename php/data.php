<?php
// Semua isi teks website ada di file ini. Ubah di sini, tampilan ikut berubah.
if (session_status() === PHP_SESSION_NONE) {
    session_start();   // dibaca navigasi untuk menampilkan tombol Login atau nama pengguna
}

// Dipakai bersama php/fungsi.php, jadi dibuat hanya bila belum ada
if (!function_exists('e')) {
    function e($teks)
    {
        return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
    }
}

$site = [
    'nama'    => 'UPZ BAZNAS PG',
    'lengkap' => 'UPZ BAZNAS Petrokimia Gresik',
    'email'   => 'isi alamat email',
    'telp'    => 'isi nomor kontak',
    'alamat'  => 'Gresik, Jawa Timur',
];

$menu = [
    'tentang' => 'Tentang',
    'program' => 'Program',
    'dampak'  => 'Dampak',
    'alur'    => 'Alur',
    'tim'     => 'Tim',
];

// Ganti dengan logo resmi (taruh di folder img) bila memang bermitra
$mitra = ['BAZNAS', 'Petrokimia Gresik', 'Pupuk Indonesia', 'Logo mitra'];

// [nama, warna kartu (l abu-abu, g hijau, d gelap), penjelasan singkat, poin penting]
// Penjelasan ini tampil di jendela yang terbuka saat tombol Pelajari diklik
$program = [
    ['Zakat penghasilan', 'l', 'Zakat penghasilan adalah zakat yang dikeluarkan dari gaji, honor, atau pendapatan lain yang diterima secara rutin. Dana yang terkumpul disalurkan kepada golongan yang berhak menerima zakat.', [
        'Kadar zakat penghasilan umumnya 2,5 persen dari penghasilan yang sudah mencapai nisab.',
        'Besaran nisab mengikuti ketetapan BAZNAS yang berlaku, jadi cek angka terbarunya sebelum menghitung.',
        'Bisa ditunaikan setiap bulan atau sekaligus setahun, sesuai kemudahan masing-masing.',
        'Klik Salurkan sekarang untuk memilih paket dan membayar, atau hubungi pengurus UPZ bila ada pertanyaan.',
    ]],
    ['Infak dan sedekah', 'g', 'Infak dan sedekah adalah pemberian sukarela di luar zakat. Tidak ada batas minimal maupun waktu tertentu, sehingga siapa pun bisa berbagi sesuai kemampuan.', [
        'Tidak ada nisab dan kadar tertentu, nominalnya bebas.',
        'Dapat diberikan kapan saja, tidak harus menunggu waktu tertentu.',
        'Dana digunakan untuk program sosial yang dikelola UPZ. Rinciannya bisa ditanyakan ke pengurus.',
    ]],
    ['Bantuan pendidikan', 'd', 'Program ini membantu biaya pendidikan bagi anak dan pelajar dari keluarga kurang mampu agar mereka tetap bisa bersekolah.', [
        'Sasaran: pelajar dan mahasiswa dari keluarga kurang mampu.',
        'Contoh bentuk bantuan: biaya sekolah, perlengkapan belajar, atau beasiswa.',
        'Syarat dan jadwal pengajuan ditetapkan pengurus UPZ. Isi bagian ini dengan ketentuan yang berlaku.',
    ]],
    ['Layanan kesehatan', 'l', 'Program ini membantu warga yang kesulitan membiayai pengobatan dan layanan kesehatan dasar.', [
        'Sasaran: warga kurang mampu yang membutuhkan bantuan kesehatan.',
        'Contoh bentuk bantuan: biaya berobat, obat, atau layanan kesehatan gratis.',
        'Syarat dan cara pengajuan ditetapkan pengurus UPZ. Isi bagian ini dengan ketentuan yang berlaku.',
    ]],
    ['Pemberdayaan ekonomi', 'g', 'Program ini mendukung warga prasejahtera membangun atau mengembangkan usaha kecil agar penghasilan keluarga lebih stabil.', [
        'Sasaran: pelaku usaha kecil dan keluarga prasejahtera.',
        'Contoh bentuk bantuan: modal usaha, peralatan, atau pelatihan.',
        'Syarat dan cara pengajuan ditetapkan pengurus UPZ. Isi bagian ini dengan ketentuan yang berlaku.',
    ]],
    ['Bantuan kemanusiaan', 'd', 'Program ini memberi bantuan cepat bagi warga yang terdampak bencana atau kondisi darurat.', [
        'Sasaran: warga yang terdampak bencana atau musibah.',
        'Contoh bentuk bantuan: kebutuhan pokok, tempat tinggal sementara, atau perlengkapan darurat.',
        'Syarat dan cara pengajuan ditetapkan pengurus UPZ. Isi bagian ini dengan ketentuan yang berlaku.',
    ]],
];

// Isi dengan data penyaluran yang sebenarnya
$dampak = [
    'Dana pendidikan disalurkan untuk membantu anak dari keluarga prasejahtera agar tetap bersekolah. Ganti teks ini dengan data penyaluran terbaru.',
    'Dana pemberdayaan ekonomi dipakai untuk modal usaha kecil di sekitar lingkungan perusahaan. Ganti teks ini dengan data penyaluran terbaru.',
    'Bantuan kesehatan dan kemanusiaan diberikan cepat kepada warga yang terdampak. Ganti teks ini dengan data penyaluran terbaru.',
];

// [nama, jabatan, deskripsi]
$tim = [
    ['Nama pengurus', 'Ketua UPZ', 'Mengoordinasikan seluruh kegiatan penghimpunan dan penyaluran dana.'],
    ['Nama pengurus', 'Sekretaris', 'Mengelola administrasi, laporan, dan dokumentasi kegiatan.'],
    ['Nama pengurus', 'Bendahara', 'Mencatat dana masuk dan keluar secara tertib dan akuntabel.'],
    ['Nama pengurus', 'Divisi penghimpunan', 'Mengedukasi dan melayani insan perusahaan yang ingin berdonasi.'],
    ['Nama pengurus', 'Divisi pendistribusian', 'Memverifikasi penerima manfaat dan menyalurkan bantuan.'],
    ['Nama pengurus', 'Divisi humas', 'Mengelola informasi publik dan komunikasi dengan donatur.'],
];

// [judul, isi]
$alur = [
    ['Konsultasi awal', 'Kami memahami kebutuhan donatur, jenis dana yang ingin ditunaikan, dan program yang dituju.'],
    ['Penghimpunan dana', 'Dana zakat, infak, dan sedekah dihimpun melalui mekanisme yang telah ditetapkan UPZ.'],
    ['Verifikasi penerima', 'Calon penerima manfaat didata dan diverifikasi agar bantuan tepat sasaran.'],
    ['Penyaluran program', 'Dana disalurkan sesuai program pendidikan, kesehatan, ekonomi, atau kemanusiaan.'],
    ['Pendampingan', 'Penerima manfaat didampingi agar bantuan memberi dampak yang berkelanjutan.'],
    ['Pelaporan transparan', 'Laporan penghimpunan dan penyaluran disampaikan kepada donatur secara berkala.'],
];

// [isi, nama, peran]. Minta izin dulu sebelum memajang testimoni asli
$testimoni = [
    ['Ganti dengan testimoni donatur yang sebenarnya. Tulis pengalaman menyalurkan zakat atau infak melalui UPZ.', 'Nama donatur', 'Karyawan Petrokimia Gresik'],
    ['Ganti dengan testimoni penerima manfaat yang sebenarnya, setelah mendapat izin untuk dipublikasikan.', 'Nama penerima', 'Penerima manfaat'],
    ['Ganti dengan testimoni mitra atau pengurus yang sebenarnya.', 'Nama mitra', 'Mitra program'],
];

// ===== Penyaluran donasi (halaman Salurkan) =====

// Ubah nama program menjadi bagian alamat, mis. "Zakat penghasilan" menjadi "zakat-penghasilan"
function slug($teks)
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$teks)), '-');
}

// Cari program di daftar $program berdasarkan slug namanya
function cari_program(array $daftar, string $slug): ?array
{
    foreach ($daftar as $p) {
        if (slug($p[0]) === $slug) {
            return ['nama' => $p[0], 'warna' => $p[1], 'ringkas' => $p[2], 'poin' => $p[3]];
        }
    }
    return null;
}

// Batas nominal untuk donasi dengan nominal bebas (dalam rupiah)
$batasDonasi = ['min' => 10000, 'maks' => 1000000000];

// Paket penyaluran per program. Kunci harus sama dengan slug nama program di atas.
// 'kategori' = kode kategori pemasukan di tabel kategori_dana yang dipakai saat donasi dikonfirmasi ke kas.
// Paket: [nama paket, nominal (0 = nominal bebas), penjelasan, opsi tambahan (boleh dikosongkan)]
// Paket di bawah ini hanya contoh. Sesuaikan nama dan nominalnya dengan ketentuan UPZ.
$penyaluran = [
    'zakat-penghasilan' => ['kategori' => 'ZAKAT_MAAL', 'paket' => [
        ['Zakat bulanan', 0, 'Tunaikan zakat penghasilan setiap bulan sesuai perhitungan Anda.', 'hitung'],
        ['Zakat tahunan', 0, 'Tunaikan zakat penghasilan satu tahun sekaligus.', 'hitung'],
    ]],
    'infak-dan-sedekah' => ['kategori' => 'INFAK', 'paket' => [
        ['Paket Berbagi', 50000, 'Infak ringan untuk mendukung program sosial UPZ.'],
        ['Paket Peduli', 100000, 'Sedekah untuk membantu lebih banyak penerima manfaat.'],
        ['Paket Berkah', 250000, 'Infak untuk memperkuat seluruh program UPZ.'],
        ['Nominal bebas', 0, 'Tentukan sendiri nominal infak atau sedekah Anda.'],
    ]],
    'bantuan-pendidikan' => ['kategori' => 'DANA_SOSIAL', 'paket' => [
        ['Perlengkapan sekolah', 150000, 'Membantu perlengkapan belajar bagi pelajar kurang mampu.'],
        ['Biaya sekolah satu bulan', 300000, 'Membantu biaya sekolah seorang pelajar selama satu bulan.'],
        ['Beasiswa satu semester', 1500000, 'Membantu biaya pendidikan seorang pelajar selama satu semester.'],
        ['Nominal bebas', 0, 'Tentukan sendiri nominal bantuan pendidikan Anda.'],
    ]],
    'layanan-kesehatan' => ['kategori' => 'DANA_SOSIAL', 'paket' => [
        ['Obat dan vitamin', 100000, 'Membantu kebutuhan obat dan vitamin bagi warga kurang mampu.'],
        ['Biaya berobat', 500000, 'Membantu biaya berobat warga yang kesulitan membayar pengobatan.'],
        ['Layanan kesehatan gratis', 1000000, 'Mendukung layanan kesehatan gratis bagi warga sekitar.'],
        ['Nominal bebas', 0, 'Tentukan sendiri nominal bantuan kesehatan Anda.'],
    ]],
    'pemberdayaan-ekonomi' => ['kategori' => 'DANA_SOSIAL', 'paket' => [
        ['Peralatan usaha kecil', 500000, 'Membantu pelaku usaha kecil melengkapi peralatan usahanya.'],
        ['Modal usaha', 1000000, 'Membantu modal usaha bagi keluarga prasejahtera.'],
        ['Pelatihan usaha', 2000000, 'Mendukung pelatihan agar usaha kecil lebih berkembang.'],
        ['Nominal bebas', 0, 'Tentukan sendiri nominal bantuan pemberdayaan ekonomi Anda.'],
    ]],
    'bantuan-kemanusiaan' => ['kategori' => 'DANA_SOSIAL', 'paket' => [
        ['Paket kebutuhan pokok', 100000, 'Membantu kebutuhan pokok bagi warga yang terdampak musibah.'],
        ['Perlengkapan darurat', 250000, 'Membantu perlengkapan darurat bagi korban bencana.'],
        ['Tempat tinggal sementara', 1000000, 'Membantu tempat tinggal sementara bagi warga yang kehilangan rumah.'],
        ['Nominal bebas', 0, 'Tentukan sendiri nominal bantuan kemanusiaan Anda.'],
    ]],
];

// Metode pembayaran: kode => [nama, keterangan singkat, langkah pembayaran]
// Rekening bank, e-wallet, dan kode QRIS tidak diisi di sini. Admin mengaturnya di menu Metode pembayaran
// pada dashboard, dan metode baru bisa dipilih donatur setelah diaktifkan. Hanya Tunai yang selalu tersedia.
$metode = [
    'transfer' => ['Transfer bank', 'Pilih bank tujuan transfer', [
        'Transfer sesuai nominal ke rekening bank pilihan Anda yang tertera di atas.',
        'Tuliskan nomor referensi donasi pada berita transfer.',
        'Setelah transfer, tekan Saya sudah membayar dan unggah bukti transfer.',
    ]],
    'ewallet' => ['E-wallet', 'GoPay, OVO, DANA, ShopeePay, dan lainnya', [
        'Buka aplikasi e-wallet pilihan Anda, lalu kirim sesuai nominal ke nomor yang tertera di atas.',
        'Cantumkan nomor referensi donasi pada catatan pembayaran bila tersedia.',
        'Setelah membayar, tekan Saya sudah membayar dan unggah tangkapan layar bukti pembayaran.',
    ]],
    'qris' => ['QRIS', 'Pindai dengan aplikasi bank atau e-wallet', [
        'Buka aplikasi bank atau e-wallet Anda, lalu pindai kode QRIS di atas.',
        'Masukkan nominal sesuai donasi dan selesaikan pembayaran.',
        'Setelah membayar, tekan Saya sudah membayar dan unggah bukti pembayaran.',
    ]],
    'tunai' => ['Tunai di kantor UPZ', 'Serahkan langsung kepada pengurus', [
        'Lokasi: isi alamat kantor UPZ',
        'Jam layanan: isi jam layanan UPZ',
        'Sebutkan nomor referensi donasi kepada pengurus saat menyerahkan dana, lalu tekan Saya sudah membayar.',
    ]],
];
