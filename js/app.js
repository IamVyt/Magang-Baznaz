// Skrip khusus halaman sistem (login dan dashboard). Jendela dialog dibuka oleh main.js
const ambil = (id) => document.getElementById(id);

// Lihat atau sembunyikan password
document.querySelectorAll('[data-pw]').forEach((tombol) => {
  tombol.addEventListener('click', () => {
    const kolom = ambil(tombol.dataset.pw);
    const tampil = kolom.type === 'password';
    kolom.type = tampil ? 'text' : 'password';
    tombol.querySelector('use').setAttribute('href', tampil ? '#eye-off' : '#eye');
  });
});

// Tombol Filter membuka dan menutup kotak pencarian
document.querySelectorAll('[data-toggle]').forEach((tombol) => {
  tombol.addEventListener('click', () => {
    const kotak = ambil(tombol.dataset.toggle);
    kotak.hidden = !kotak.hidden;
    if (!kotak.hidden) kotak.querySelector('input[type=text]').focus();
  });
});

// Dialog catat atau ubah transaksi: isian menyesuaikan jenis transaksi dan tombol yang diklik
if (ambil('dlg-form')) {
  const kategori = window.KATEGORI || { masuk: [], keluar: [] };

  document.querySelectorAll('[data-aksi]').forEach((tombol) => {
    tombol.addEventListener('click', () => {
      const d = tombol.dataset;
      const ubah = d.aksi === 'ubah';
      const masuk = d.tipe === 'masuk';
      ambil('f-aksi').value = d.aksi;
      ambil('f-tipe').value = d.tipe;
      ambil('f-id').value = d.id || 0;
      ambil('f-pihak').value = '';
      ambil('f-judul').querySelector('span').textContent = (ubah ? 'Ubah ' : 'Catat ') + (masuk ? 'pemasukan' : 'pengeluaran');
      ambil('f-lnama').firstChild.textContent = masuk ? 'Nama penyumbang atau donatur ' : 'Nama penerima manfaat ';
      ambil('f-lkat').textContent = masuk ? 'Kategori pemasukan' : 'Program penyaluran';
      ambil('f-hamba').hidden = !masuk;
      ambil('g-masuk').hidden = !masuk || ubah;
      ambil('g-keluar').hidden = masuk || ubah;
      ambil('f-muzakki').value = '';
      ambil('f-mustahik').value = '';

      const pilihan = ambil('f-kat');
      pilihan.innerHTML = '<option value="">Pilih kategori</option>';
      kategori[d.tipe].forEach((k) => pilihan.add(new Option(k.nama, k.id)));
      pilihan.value = d.kategori || '';

      ambil('f-nama').value = d.nama || '';
      ambil('f-jumlah').value = d.jumlah || '';
      ambil('f-tgl').value = d.tanggal || ambil('f-tgl').max;
      ambil('f-ket').value = d.keterangan || '';
    });
  });

  // Memilih donatur atau penerima dari master data mengisi nama dan menyimpan id-nya
  document.querySelectorAll('[data-pihak]').forEach((pilih) => {
    pilih.addEventListener('change', () => {
      const opsi = pilih.options[pilih.selectedIndex];
      ambil('f-pihak').value = opsi.value;
      if (opsi.dataset.nama) ambil('f-nama').value = opsi.dataset.nama;
    });
  });

  ambil('f-hamba').addEventListener('click', () => {
    ambil('f-nama').value = 'Hamba Allah';
    ambil('f-pihak').value = '';
    ambil('f-muzakki').value = '';
  });

  // Dialog pembatalan (void)
  document.querySelectorAll('[data-dialog="dlg-void"]').forEach((tombol) => {
    tombol.addEventListener('click', () => {
      ambil('v-id').value = tombol.dataset.id;
      ambil('v-nama').textContent = tombol.dataset.nama;
      ambil('v-alasan').value = '';
    });
  });
}

// Form donasi: tombol nominal cepat mengisi kolom nominal
document.querySelectorAll('[data-isi]').forEach((tombol) => {
  tombol.addEventListener('click', () => {
    const kolom = ambil(tombol.dataset.target);
    kolom.value = tombol.dataset.isi;
    kolom.focus();
  });
});

// Form zakat penghasilan: hitung persentase dari penghasilan lalu isi nominal (dibulatkan ke rupiah)
document.querySelectorAll('[data-hitung]').forEach((tombol) => {
  tombol.addEventListener('click', () => {
    const penghasilan = Number(ambil(tombol.dataset.sumber).value);
    if (penghasilan > 0) {
      ambil(tombol.dataset.target).value = Math.round(penghasilan * Number(tombol.dataset.hitung) / 100);
    } else {
      ambil(tombol.dataset.sumber).focus();
    }
  });
});

// Cegah donasi terkirim ganda saat tombol diklik dua kali
document.querySelectorAll('form[data-sekali]').forEach((form) => {
  form.addEventListener('submit', () => {
    const kirim = form.querySelector('[type=submit]');
    if (kirim) {
      kirim.disabled = true;
      kirim.textContent = 'Mengirim...';
    }
  });
});

// Halaman Donasi masuk: isi dialog konfirmasi atau tolak dari baris yang diklik
document.querySelectorAll('[data-proses]').forEach((tombol) => {
  tombol.addEventListener('click', () => {
    const awalan = tombol.dataset.proses;   // t = terima, k = tolak
    ambil(awalan + '-id').value = tombol.dataset.id;
    ambil(awalan + '-ringkas').textContent = tombol.dataset.ringkas;
    if (ambil(awalan + '-alasan')) ambil(awalan + '-alasan').value = '';
  });
});

// Pilihan pembayaran: tampilkan daftar bank atau e-wallet sesuai metode yang dipilih
const sinkronMetode = (form) => {
  const pilih = form.querySelector('input[name=metode]:checked');
  form.querySelectorAll('.channel').forEach((kotak) => {
    const tampil = !!pilih && pilih.value === kotak.dataset.untuk;
    kotak.hidden = !tampil;
    kotak.querySelectorAll('input').forEach((pilihan) => { pilihan.required = tampil; });
  });
};
document.querySelectorAll('form').forEach((form) => {
  if (form.querySelector('.channel')) sinkronMetode(form);
});
document.addEventListener('change', (e) => {
  if (e.target.name === 'metode' && e.target.form) sinkronMetode(e.target.form);
});

// Tombol salin nomor rekening atau e-wallet
document.querySelectorAll('[data-salin]').forEach((tombol) => {
  tombol.addEventListener('click', async () => {
    const awal = tombol.textContent;
    try {
      await navigator.clipboard.writeText(tombol.dataset.salin);
      tombol.textContent = 'Tersalin';
    } catch (galat) {
      tombol.textContent = 'Salin manual';
    }
    setTimeout(() => { tombol.textContent = awal; }, 1800);
  });
});

// Form yang meminta konfirmasi dulu sebelum dikirim
document.querySelectorAll('form[data-konfirmasi]').forEach((form) => {
  form.addEventListener('submit', (e) => {
    if (!confirm(form.dataset.konfirmasi)) e.preventDefault();
  });
});

// Halaman Metode pembayaran: isi dialog tambah atau ubah, dan dialog hapus
document.querySelectorAll('[data-rek]').forEach((tombol) => {
  tombol.addEventListener('click', () => {
    const r = tombol.dataset.row ? JSON.parse(tombol.dataset.row) : { id: 0, jenis: 'bank', nama: '', nomor: '', atas_nama: '', aktif: 1 };
    ambil('r-id').value = r.id;
    ambil('r-jenis').value = r.jenis;
    ambil('r-nama').value = r.nama;
    ambil('r-nomor').value = r.nomor;
    ambil('r-an').value = r.atas_nama;
    ambil('r-aktif').checked = !!Number(r.aktif);
    ambil('r-judul').firstElementChild.textContent = tombol.dataset.judul;
  });
});
document.querySelectorAll('[data-hapus-rek]').forEach((tombol) => {
  tombol.addEventListener('click', () => {
    ambil('h-id').value = tombol.dataset.hapusRek;
    ambil('h-nama').textContent = tombol.dataset.nama;
  });
});

// Sidebar petugas di layar kecil
document.querySelectorAll('[data-sb]').forEach((t) => t.addEventListener('click', () => document.body.classList.toggle('sb-open')));

// Halaman master data: isi dialog tambah atau ubah dari baris yang diklik
if (ambil('crud-form')) {
  const form = ambil('crud-form');
  document.querySelectorAll('[data-crud]').forEach((tombol) => {
    tombol.addEventListener('click', () => {
      const r = tombol.dataset.row ? JSON.parse(tombol.dataset.row) : {};
      form.querySelector('[name=aksi]').value = tombol.dataset.crud;
      form.querySelector('[name=id]').value = r.id || 0;
      ambil('crud-judul').querySelector('span').textContent = tombol.dataset.judul;
      form.querySelectorAll('[data-f]').forEach((i) => {
        i.value = i.type === 'password' ? '' : (r[i.name] ?? '');
        if (i.tagName === 'SELECT' && !i.value) i.selectedIndex = 0;
      });
    });
  });
  document.querySelectorAll('[data-dialog="crud-hapus"]').forEach((t) => {
    t.addEventListener('click', () => {
      ambil('ch-id').value = t.dataset.id;
      ambil('ch-nama').textContent = t.dataset.nama;
    });
  });
}

// Grafik arus kas 6 bulan (Chart.js dimuat dari dashboard.php)
const kanvas = ambil('grafik');
if (kanvas && window.Chart && window.GRAFIK) {
  const ringkas = (v) => {
    if (v >= 1e9) return 'Rp ' + (v / 1e9).toLocaleString('id-ID') + ' M';
    if (v >= 1e6) return 'Rp ' + (v / 1e6).toLocaleString('id-ID') + ' jt';
    if (v >= 1e3) return 'Rp ' + (v / 1e3).toLocaleString('id-ID') + ' rb';
    return 'Rp ' + v;
  };
  const font = { family: 'Space Grotesk', size: 11 };
  new Chart(kanvas, {
    type: 'bar',
    data: {
      labels: window.GRAFIK.label,
      datasets: [
        { label: 'Pemasukan', data: window.GRAFIK.masuk, backgroundColor: '#B9FF66', borderColor: '#191A23', borderWidth: 1, borderRadius: 6, maxBarThickness: 18 },
        { label: 'Pengeluaran', data: window.GRAFIK.keluar, backgroundColor: '#191A23', borderRadius: 6, maxBarThickness: 18 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font } },
        tooltip: { callbacks: { label: (c) => c.dataset.label + ': Rp ' + c.parsed.y.toLocaleString('id-ID') } },
      },
      scales: {
        x: { grid: { display: false }, ticks: { font } },
        y: { beginAtZero: true, ticks: { callback: ringkas, font }, grid: { color: '#eee' } },
      },
    },
  });
}
