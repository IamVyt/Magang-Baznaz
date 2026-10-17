const gerak = window.matchMedia('(prefers-reduced-motion: no-preference)');

// ===== Menu hamburger untuk tablet dan HP =====
const nav = document.querySelector('nav');
const burger = document.querySelector('.burger');

if (nav && burger) {
  const atur = (buka) => {
    nav.classList.toggle('open', buka);
    burger.setAttribute('aria-expanded', String(buka));
  };
  burger.addEventListener('click', () => atur(!nav.classList.contains('open')));
  nav.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => atur(false)));
}

// ===== Testimoni: berjalan sendiri ke kanan, bisa diseret, berhenti saat disentuh =====
// Kartu digandakan secara otomatis supaya putarannya tidak pernah putus.
const slider = document.getElementById('sl');
const lintas = document.getElementById('lintas');

if (slider && lintas && lintas.children.length) {
  const asli = [...lintas.children];
  const n = asli.length;
  const titik = [...document.querySelectorAll('.dots button')];
  const LAJU = 48; // piksel per detik, isi bergerak ke kanan

  let x = 0;        // posisi lintasan
  let sisa = 0;     // dorongan yang belum selesai dijalankan
  let tunda = 0;    // jeda (ms) setelah pengguna berinteraksi
  let ditahan = false;
  let diseret = false;
  let terlihat = true;
  let aktif = -1;
  let mulai = false;
  let area = 0, lebar = 0, langkah = 0, putaran = 0;
  let terakhirX = 0, terakhirT = 0, kec = 0;
  let sebelum = performance.now();

  const bungkus = () => { x = (((x % putaran) + putaran) % putaran) - putaran; };

  const gambar = () => {
    lintas.style.transform = `translate3d(${x}px,0,0)`;
    const tengah = -x + area / 2;
    const j = Math.max(0, Math.round((tengah - lebar / 2) / langkah));
    if (j === aktif) return;
    aktif = j;
    [...lintas.children].forEach((kartu, i) => kartu.classList.toggle('on', i === j));
    titik.forEach((t, i) => t.classList.toggle('on', i === j % n));
  };

  const susun = () => {
    lintas.querySelectorAll('[data-klon]').forEach((k) => k.remove());
    area = slider.clientWidth;
    lebar = asli[0].offsetWidth;
    langkah = lebar + parseFloat(getComputedStyle(asli[0]).marginRight);
    putaran = langkah * n;
    const jumlahSet = Math.ceil(area / putaran) + 1;
    for (let s = 1; s < jumlahSet; s++) {
      asli.forEach((kartu) => {
        const salinan = kartu.cloneNode(true);
        salinan.classList.remove('on');
        salinan.setAttribute('aria-hidden', 'true');
        salinan.dataset.klon = '';
        lintas.appendChild(salinan);
      });
    }
    aktif = -1;
    if (!mulai) { x = area / 2 - lebar / 2 - putaran; mulai = true; } // awal: testimoni pertama di tengah
    bungkus();
    gambar();
  };

  // jarak > 0 menggeser isi ke kanan, jarak < 0 ke kiri
  const dorong = (jarak) => {
    tunda = 2200;
    if (gerak.matches) {
      sisa += jarak;
    } else {
      x += jarak;
      bungkus();
      gambar();
    }
  };

  const jalan = (t) => {
    const dt = Math.min(Math.max((t - sebelum) / 1000, 0), 0.05);
    sebelum = t;
    if (terlihat && !document.hidden) {
      let geser = 0;
      if (!diseret) {
        const s = sisa * (1 - Math.exp(-dt * 9));
        sisa -= s;
        geser += s;
        if (Math.abs(sisa) < 0.3) { geser += sisa; sisa = 0; }
        if (gerak.matches && !ditahan && tunda <= 0) geser += LAJU * dt;
      }
      if (tunda > 0) tunda -= dt * 1000;
      if (geser) { x += geser; bungkus(); }
      gambar();
    }
    requestAnimationFrame(jalan);
  };

  // Panah kiri dan kanan
  document.querySelectorAll('[data-geser]').forEach((tombol) => {
    tombol.addEventListener('click', () => dorong(Number(tombol.dataset.geser) * langkah));
  });

  // Titik penanda: klik untuk menengahkan testimoni tertentu
  titik.forEach((t) => {
    t.addEventListener('click', () => {
      const k = Number(t.dataset.ke);
      const tengah = -(x + sisa) + area / 2;
      const dekat = Math.round((tengah - lebar / 2) / langkah);
      let j = k + n * Math.round((dekat - k) / n);
      if (j < 0) j += n;
      if (j > lintas.children.length - 1) j -= n;
      const tujuan = area / 2 - (j * langkah + lebar / 2) - x;
      tunda = 2200;
      if (gerak.matches) {
        sisa = tujuan;
      } else {
        x += tujuan;
        bungkus();
        gambar();
      }
    });
  });

  // Seret dengan mouse atau jari, lepas dengan sedikit luncuran
  slider.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    diseret = true;
    sisa = 0;
    kec = 0;
    terakhirX = e.clientX;
    terakhirT = e.timeStamp;
    slider.classList.add('drag');
    slider.setPointerCapture(e.pointerId);
  });
  slider.addEventListener('pointermove', (e) => {
    if (!diseret) return;
    const dx = e.clientX - terakhirX;
    const dms = Math.max(e.timeStamp - terakhirT, 1);
    kec = 0.8 * kec + 0.2 * (dx / dms);
    x += dx;
    bungkus();
    terakhirX = e.clientX;
    terakhirT = e.timeStamp;
  });
  const lepas = (e) => {
    if (!diseret) return;
    diseret = false;
    slider.classList.remove('drag');
    tunda = 1800;
    if (e.timeStamp - terakhirT > 100) kec = 0;
    if (gerak.matches) sisa = Math.max(-2 * langkah, Math.min(2 * langkah, kec * 220));
  };
  ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((nama) => slider.addEventListener(nama, lepas));

  // Berhenti saat kursor di atas testimoni atau saat difokuskan dengan keyboard
  slider.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') ditahan = true; });
  slider.addEventListener('pointerleave', () => { ditahan = false; });
  slider.addEventListener('focusin', () => { ditahan = true; });
  slider.addEventListener('focusout', () => { ditahan = false; });
  slider.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { dorong(langkah); e.preventDefault(); }
    if (e.key === 'ArrowLeft') { dorong(-langkah); e.preventDefault(); }
  });

  // Hemat tenaga: berhenti menghitung saat section tidak terlihat
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(([m]) => { terlihat = m.isIntersecting; }, { rootMargin: '120px' }).observe(slider);
  }

  let tunggu;
  window.addEventListener('resize', () => {
    clearTimeout(tunggu);
    tunggu = setTimeout(susun, 150);
  });

  susun();
  requestAnimationFrame(jalan);
}

// ===== Alur kerja: buka tutup dengan animasi tinggi yang halus =====
const semuaAlur = document.querySelectorAll('details');

function geser(d, buka) {
  d.classList.toggle('on', buka);
  if (!gerak.matches) {
    d.open = buka;
    return;
  }
  const dari = d.offsetHeight;
  d.open = buka;
  const ke = d.offsetHeight;
  d.open = true; // tetap terbuka selama animasi berjalan
  d.style.overflow = 'hidden';
  const anim = d.animate(
    { height: [dari + 'px', ke + 'px'] },
    { duration: 380, easing: 'cubic-bezier(.2,.8,.2,1)' }
  );
  d._anim = anim;
  anim.onfinish = () => {
    d.open = buka;
    d.style.overflow = '';
    d._anim = null;
  };
}

semuaAlur.forEach((d) => {
  d.querySelector('summary').addEventListener('click', (e) => {
    e.preventDefault();
    if (d._anim) return;
    const buka = !d.classList.contains('on');
    if (buka) {
      semuaAlur.forEach((lain) => {
        if (lain !== d && lain.classList.contains('on')) geser(lain, false);
      });
    }
    geser(d, buka);
  });
});

// ===== Jendela penjelasan program (tombol Pelajari) =====
function tutup(d, setelah) {
  if (!d.open || d.classList.contains('closing')) return;
  const selesai = () => {
    d.classList.remove('closing');
    d.close();
    if (setelah) setelah();
  };
  if (!gerak.matches) return selesai();
  d.classList.add('closing');
  d.addEventListener('animationend', selesai, { once: true });
}

document.querySelectorAll('[data-dialog]').forEach((tombol) => {
  tombol.addEventListener('click', () => document.getElementById(tombol.dataset.dialog).showModal());
});

document.querySelectorAll('dialog.dlg').forEach((d) => {
  d.addEventListener('click', (e) => { if (e.target === d) tutup(d); });
  d.addEventListener('cancel', (e) => { e.preventDefault(); tutup(d); });
  d.querySelectorAll('[data-close]').forEach((t) => {
    t.addEventListener('click', (e) => {
      const href = t.getAttribute('href');
      if (href && href.startsWith('#')) {
        e.preventDefault();
        tutup(d, () => document.querySelector(href).scrollIntoView({ behavior: 'smooth' }));
      } else {
        tutup(d);
      }
    });
  });
});
