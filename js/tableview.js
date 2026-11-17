/*
 * tableview.js — filter + pagination client-side untuk tabel besar,
 * plus UI aksi massal (checkbox) via initBulkUI().
 * Dipakai halaman PPP Secrets, Hotspot Users, dan tabel dashboard lain.
 *
 * Kontrak DOM:
 *  - #dataTable          tabel; baris data WAJIB punya class "row-data".
 *  - #tableSearch        input pencarian (pengganti #filterTable supaya tidak
 *                        bentrok dengan filter jQuery lama di index.php).
 *  - #perPageSel         select jumlah baris per halaman.
 *  - #pageInfo           span info "x-y dari z data".
 *  - #pagePager          div tombol pager (dirender ulang di sini).
 *
 * Filter pencarian, sorting (makeAllSortable), dan pagination digabung:
 * hanya baris yang cocok pencarian DAN berada di window halaman aktif
 * yang ditampilkan. KPI di PHP tetap dihitung dari data penuh.
 */
function initTableView(defaultPerPage) {
  // Style checkbox & pagination di-inject sekali dari sini supaya kedua
  // halaman (PPP & Hotspot) otomatis konsisten tanpa mengubah tema global.
  if (!document.getElementById('tv-style')) {
    var st = document.createElement('style');
    st.id = 'tv-style';
    st.textContent =
      /* ---- Checkbox kustom (appearance:none + SVG check) ---- */
      "input.row-check, #dataTable thead input[type='checkbox'] {" +
      "  appearance: none; -webkit-appearance: none;" +
      "  width: 15px; height: 15px; margin: 0; vertical-align: middle;" +
      "  border: 1.5px solid rgba(128,128,128,.5); border-radius: 4px;" +
      "  background: transparent; cursor: pointer; transition: all .12s ease; }" +
      "input.row-check:hover, #dataTable thead input[type='checkbox']:hover { border-color: #1EA4E1; }" +
      "input.row-check:focus-visible, #dataTable thead input[type='checkbox']:focus-visible { outline: 2px solid #1EA4E1; outline-offset: 2px; }" +
      "input.row-check:checked, #dataTable thead input[type='checkbox']:checked," +
      "input.row-check:indeterminate, #dataTable thead input[type='checkbox']:indeterminate {" +
      "  background-color: #1EA4E1; border-color: #1EA4E1;" +
      "  background-repeat: no-repeat; background-position: center; background-size: 11px; }" +
      "input.row-check:checked, #dataTable thead input[type='checkbox']:checked {" +
      "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' d='M3.5 8.5l3 3 6-7'/%3E%3C/svg%3E\"); }" +
      "input.row-check:indeterminate, #dataTable thead input[type='checkbox']:indeterminate {" +
      "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2.5' stroke-linecap='round' d='M3.5 8h9'/%3E%3C/svg%3E\"); }" +
      /* Baris dengan checkbox tercentang diberi tint halus */
      "#dataTable tr:has(input.row-check:checked) > td { background-color: rgba(30,164,225,.08); }" +
      /* ---- Footer pagination ---- */
      "#pageInfo { font-size: 12px; opacity: .75; white-space: nowrap; }" +
      "#perPageSel { width: auto !important; font-size: 12px; padding: 3px 8px !important; border: 1px solid rgba(128,128,128,.3); border-radius: 6px; background-color: transparent; color: inherit; cursor: pointer; }" +
      ".pg-btn { min-width: 26px; height: 26px; padding: 0 7px; display: inline-flex; align-items: center; justify-content: center;" +
      "  font-size: 12px; font-family: inherit; border-radius: 6px; border: 1px solid rgba(128,128,128,.3);" +
      "  background: transparent; color: inherit; cursor: pointer; transition: all .12s ease; }" +
      ".pg-btn:hover:not(:disabled) { border-color: #1EA4E1; color: #1EA4E1; }" +
      ".pg-btn:disabled { opacity: .35; cursor: default; }" +
      ".pg-btn.current { background: #1EA4E1; border-color: #1EA4E1; color: #fff; font-weight: 600; }" +
      /* Scrollbar tipis untuk kontainer tabel supaya tidak menimpa baris terakhir */
      ".overflow::-webkit-scrollbar { width: 8px; height: 8px; }" +
      ".overflow::-webkit-scrollbar-thumb { background: rgba(128,128,128,.35); border-radius: 4px; }" +
      ".overflow::-webkit-scrollbar-thumb:hover { background: rgba(128,128,128,.55); }" +
      ".overflow::-webkit-scrollbar-track { background: transparent; }";
    document.head.appendChild(st);
  }

  var perPage = parseInt(defaultPerPage, 10) || 100;
  var page = 1;

  var searchEl = document.getElementById('tableSearch');
  var perPageEl = document.getElementById('perPageSel');
  var infoEl = document.getElementById('pageInfo');
  var pagerEl = document.getElementById('pagePager');

  function dataRows() {
    return Array.prototype.slice.call(document.querySelectorAll('#dataTable tbody tr.row-data'));
  }

  function applyView() {
    var q = searchEl ? searchEl.value.toLowerCase() : '';
    var rows = dataRows();
    var matched = [];
    var i;
    for (i = 0; i < rows.length; i++) {
      var r = rows[i];
      if (!q || r.textContent.toLowerCase().indexOf(q) !== -1) matched.push(r);
    }
    var total = matched.length;
    var totalPages = Math.max(1, Math.ceil(total / perPage));
    if (page > totalPages) page = totalPages;
    if (page < 1) page = 1;
    var start = (page - 1) * perPage;

    for (i = 0; i < rows.length; i++) rows[i].style.display = 'none';
    for (i = 0; i < total; i++) {
      // 'table-row' eksplisit (bukan '') supaya mengalahkan aturan CSS
      // anti-flash "#dataTable tbody tr.row-data { display:none }" yang
      // menyembunyikan baris sebelum init dijalankan.
      if (i >= start && i < start + perPage) matched[i].style.display = 'table-row';
    }

    if (infoEl) {
      infoEl.textContent = total === 0
        ? 'Tidak ada data yang cocok'
        : ((start + 1) + '-' + Math.min(start + perPage, total) + ' dari ' + total + ' data');
    }
    renderPager(totalPages);
    // Sinkronkan state header checkbox & bulk bar dengan baris yang tampil.
    if (typeof updateBulkBar === 'function') updateBulkBar();
  }

  function renderPager(totalPages) {
    if (!pagerEl) return;
    pagerEl.innerHTML = '';
    var mk = function(label, target, opts) {
      opts = opts || {};
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pg-btn' + (opts.current ? ' current' : '');
      b.innerHTML = label;
      if (opts.disabled) {
        b.disabled = true;
      } else {
        b.onclick = function() { page = target; applyView(); };
      }
      pagerEl.appendChild(b);
    };
    mk('&laquo;', 1, { disabled: page === 1 });
    mk('&lsaquo;', page - 1, { disabled: page === 1 });
    // window maksimal 5 nomor halaman di sekitar halaman aktif
    var winStart = Math.max(1, page - 2);
    var winEnd = Math.min(totalPages, winStart + 4);
    winStart = Math.max(1, winEnd - 4);
    for (var p = winStart; p <= winEnd; p++) {
      mk(p, p, { current: p === page });
    }
    mk('&rsaquo;', page + 1, { disabled: page === totalPages });
    mk('&raquo;', totalPages, { disabled: page === totalPages });
  }

  if (searchEl) {
    searchEl.addEventListener('input', function() { page = 1; applyView(); });
  }
  if (perPageEl) {
    perPageEl.addEventListener('change', function() {
      perPage = parseInt(perPageEl.value, 10) || 100;
      page = 1;
      applyView();
    });
  }
  // Sorting makeAllSortable mengurutkan ulang baris DOM; terapkan ulang
  // window pagination setelahnya (setTimeout agar jalan setelah sort).
  var ths = document.querySelectorAll('#dataTable th.pointer');
  for (var t = 0; t < ths.length; t++) {
    ths[t].addEventListener('click', function() { setTimeout(applyView, 0); });
  }

  applyView();
}

/*
 * initBulkUI(scope) — UI aksi massal (checkbox) untuk halaman tabel.
 * Dipanggil setelah initTableView(): initTableView(100); initBulkUI('host');
 *
 *  scope : dikirim sebagai bulk_scope ke process/bulk.php (POST JSON).
 *          ppp | hotspot | host | cookie | hs-active | ppp-active | dhcp-lease
 *
 * Kontrak DOM:
 *  - thead input[type=checkbox]     : select-all — hanya baris yang tampil
 *                                     (halaman aktif + hasil filter).
 *  - input.row-check[data-id]       : checkbox tiap baris data.
 *  - #bulkBar / #bulkCount          : bar aksi massal + counter.
 *  - tombol aksi                    : onclick="bulkAct('remove'|'kick'|...)"
 */
function initBulkUI(scope) {
  window.toggleAllChecks = function(el) {
    // Hanya baris yang sedang tampil (halaman aktif + hasil filter) yang ikut.
    document.querySelectorAll('#dataTable tr.row-data').forEach(function(tr) {
      if (tr.style.display !== 'none') {
        var b = tr.querySelector('input.row-check');
        if (b) b.checked = el.checked;
      }
    });
    updateBulkBar();
  };

  window.updateBulkBar = function() {
    var boxes = document.querySelectorAll('#dataTable input.row-check:checked');
    var bar = document.getElementById('bulkBar');
    if (bar) {
      bar.style.display = boxes.length ? 'flex' : 'none';
      var cnt = document.getElementById('bulkCount');
      if (cnt) cnt.textContent = boxes.length;
    }
    // State header checkbox dihitung dari BARIS YANG TAMPIL saja — konsisten
    // dengan toggleAllChecks. Kalau dihitung dari seluruh baris, header
    // selalu indeterminate di dataset besar dan uncheck-all tidak pernah
    // bisa dijalankan (klik indeterminate = selalu check).
    var head = document.querySelector('#dataTable thead input[type="checkbox"]');
    if (head) {
      var visTotal = 0, visChecked = 0;
      document.querySelectorAll('#dataTable tr.row-data').forEach(function(tr) {
        if (tr.style.display !== 'none') {
          var b = tr.querySelector('input.row-check');
          if (b) { visTotal++; if (b.checked) visChecked++; }
        }
      });
      head.checked = visTotal > 0 && visChecked === visTotal;
      head.indeterminate = visChecked > 0 && visChecked < visTotal;
    }
  };

  document.addEventListener('change', function(e) {
    if (e.target && e.target.classList && e.target.classList.contains('row-check')) updateBulkBar();
  });

  window.bulkAct = function(action) {
    var boxes = document.querySelectorAll('#dataTable input.row-check:checked');
    if (!boxes.length) return;
    var ids = [], names = [];
    boxes.forEach(function(b) {
      ids.push(b.getAttribute('data-id'));
      names.push(b.getAttribute('data-name') || '');
    });

    var verbs = {
      enable: 'ENABLE', disable: 'DISABLE', remove: 'HAPUS',
      kick: 'PUTUSKAN (kick)', lunas: 'menandai LUNAS'
    };
    var verb = verbs[action] || action;
    if (action === 'remove' || action === 'kick' || action === 'lunas') {
      if (!confirm('Yakin ingin ' + verb + ' ' + ids.length + ' item terpilih?')) return;
    }

    loader();
    var fd = new FormData();
    fd.append('bulk_scope', scope);
    fd.append('bulk_action', action);
    ids.forEach(function(id) { fd.append('ids[]', id); });
    names.forEach(function(n) { fd.append('names[]', n); });

    fetch(window.location.href, { method: 'POST', body: fd })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          // Halaman yang punya showToast() mendapat notifikasi dulu; halaman
          // lain langsung reload.
          if (typeof showToast === 'function') showToast('✅ ' + data.message);
          setTimeout(function() { location.reload(); }, typeof showToast === 'function' ? 600 : 300);
        } else {
          alert('❌ ' + (data.message || 'Gagal memproses aksi bulk'));
          if (document.getElementById('loader')) document.getElementById('loader').style.display = 'none';
        }
      })
      .catch(function() {
        alert('Terjadi kesalahan koneksi.');
        if (document.getElementById('loader')) document.getElementById('loader').style.display = 'none';
      });
  };
}
