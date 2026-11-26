/* Sefareshban Admin JS */
(() => {
  'use strict';

  const SB = window.SEFARESHBAN || {};
  const ajaxUrl = SB.ajaxUrl || '';
  const nonce = SB.nonce || '';
  const i18n = SB.i18n || {};
  const wcStatuses = SB.statuses || {};

  /* ================= status map ================= */
  const STATUS_META = {
    pending:    { label: 'در انتظار پرداخت', cls: 'pending' },
    processing: { label: 'در حال انجام', cls: 'processing' },
    completed:  { label: 'تکمیل‌شده', cls: 'completed' },
    onhold:     { label: 'در انتظار بررسی', cls: 'onhold' },
    cancelled:  { label: 'لغو‌شده', cls: 'cancelled' },
    refunded:   { label: 'مسترد‌شده', cls: 'refunded' },
    failed:     { label: 'ناموفق', cls: 'cancelled' },
    draft:      { label: 'پیش‌نویس', cls: 'onhold' },
    'checkout-draft': { label: 'پیش‌نویس', cls: 'onhold' }
  };

  const STATUS = {};
  // Build from WC statuses so labels match site
  Object.keys(wcStatuses).forEach(k => {
    const slug = k === 'on-hold' ? 'onhold' : k;
    const meta = STATUS_META[slug] || { label: wcStatuses[k].label || k, cls: 'onhold' };
    STATUS[slug] = { label: wcStatuses[k].label || meta.label, cls: meta.cls };
  });
  // Ensure common ones exist
  ['pending','processing','completed','onhold','cancelled','refunded'].forEach(k => {
    if (!STATUS[k]) STATUS[k] = STATUS_META[k];
  });

  /* ================= helpers ================= */
  const fa = n => new Intl.NumberFormat('fa-IR').format(Number(n) || 0);
  const faId = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]);
  const money = n => fa(Math.round(Number(n)||0)) + ' ' + (i18n.toman || 'تومان');
  const shortMoney = n => fa(Math.round(Number(n)||0)) + ' <small>' + (i18n.toman || 'تومان') + '</small>';
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const esc = (s='') => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  /* ================= state ================= */
  const state = {
    status: 'all',
    q: '',
    page: 1,
    per: 8,
    sort: { key: 'date', dir: 'desc' },
    selected: new Set(),
    openId: null,
    rows: [],
    total: 0,
    statusCounts: { all: 0 },
    loading: false,
    stats: null,
  };

  const TABS = [
    {k:'all', l:'همه'},
    {k:'pending', l: (STATUS.pending||{}).label || 'در انتظار پرداخت'},
    {k:'processing', l: (STATUS.processing||{}).label || 'در حال انجام'},
    {k:'completed', l: (STATUS.completed||{}).label || 'تکمیل‌شده'},
    {k:'onhold', l: (STATUS.onhold||{}).label || 'در انتظار بررسی'},
    {k:'cancelled', l: (STATUS.cancelled||{}).label || 'لغو‌شده'}
  ];

  /* ================= API ================= */
  async function api(action, data = {}) {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', nonce);
    Object.keys(data).forEach(k => {
      const v = data[k];
      if (Array.isArray(v)) v.forEach(x => body.append(k + '[]', x));
      else if (v !== undefined && v !== null) body.set(k, v);
    });
    const res = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });
    const json = await res.json();
    if (!json || !json.success) {
      const msg = (json && json.data && json.data.message) ? json.data.message : (i18n.error || 'خطا');
      throw new Error(msg);
    }
    return json.data;
  }

  function countFor(k) {
    if (k === 'all') return state.statusCounts.all || 0;
    return state.statusCounts[k] || 0;
  }


  /* ================= render ================= */
  function renderChips() {
    const wrap = $('#chips');
    if (!wrap) return;
    wrap.innerHTML = TABS.map(t => `
      <button class="chip" role="tab" data-tab="${t.k}" aria-selected="${state.status===t.k}">
        ${esc(t.l)}<span class="c num">${fa(countFor(t.k))}</span>
      </button>`).join('');
  }

  function renderTable() {
    const rows = state.rows;
    const tb = $('#tbody');
    if (!tb) return;

    tb.innerHTML = rows.map(o => {
      const sel = state.selected.has(o.id);
      const st = STATUS[o.status] || { label: o.statusLabel || o.status, cls: 'onhold' };
      const dateHtml = `<span class="num" style="font-weight:500">${esc(o.date||'')}</span> <span class="muted num">${esc(o.time||'')}</span>`;
      return `
      <tr data-id="${o.id}" class="${sel?'is-selected':''}">
        <td class="col-check"><input type="checkbox" data-check="${o.id}" ${sel?'checked':''} aria-label="انتخاب سفارش ${faId(o.id)}"></td>
        <td><a class="oid num" href="#" data-open="${o.id}"><span>#</span>${faId(o.id)}</a></td>
        <td>
          <div class="cust"><b>${esc(o.name||'')}</b><span>${esc(o.city||'')}</span></div>
        </td>
        <td class="c-date">${dateHtml}</td>
        <td><span class="badge" data-status="${esc(o.status)}">${esc(st.label)}</span></td>
        <td class="hide-sm"><span class="muted" style="font-size:12.5px">${esc(o.pay||'—')}</span></td>
        <td class="c-items"><span class="muted num" style="font-size:12.5px">${fa(o.qty||0)} قلم</span></td>
        <td><span class="money num">${shortMoney(o.total||0)}</span></td>
        <td class="col-actions">
          <button class="dots" data-open="${o.id}" title="مشاهده سفارش" aria-label="مشاهده سفارش ${faId(o.id)}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
          </button>
        </td>
      </tr>`;
    }).join('');

    const total = state.total;
    $('#empty').classList.toggle('show', !state.loading && rows.length === 0);
    $('#tbl').style.display = (!state.loading && rows.length === 0) ? 'none' : 'table';

    $$('th[data-sort]').forEach(th => {
      if (th.dataset.sort === state.sort.key) th.setAttribute('aria-sort', state.sort.dir === 'asc' ? 'ascending' : 'descending');
      else th.removeAttribute('aria-sort');
    });

    const allOnPage = rows.length > 0 && rows.every(o => state.selected.has(o.id));
    const someOnPage = rows.some(o => state.selected.has(o.id));
    const chk = $('#checkAll');
    if (chk) { chk.checked = allOnPage; chk.indeterminate = !allOnPage && someOnPage; }

    $('#pagerInfo').textContent = total ? `· ${fa(total)} سفارش` : '';

    const pages = Math.max(1, Math.ceil(total / state.per));
    let p = `
      <button class="pg" data-page="${state.page-1}" ${state.page===1?'disabled':''} aria-label="صفحه قبل">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6l6 6-6 6"/></svg>
      </button>`;
    const maxBtns = 5;
    let start = Math.max(1, state.page - Math.floor(maxBtns/2));
    let end = Math.min(pages, start + maxBtns - 1);
    start = Math.max(1, end - maxBtns + 1);
    for (let i=start; i<=end; i++) {
      p += `<button class="pg num" data-page="${i}" aria-current="${i===state.page}">${fa(i)}</button>`;
    }
    p += `
      <button class="pg" data-page="${state.page+1}" ${state.page===pages?'disabled':''} aria-label="صفحه بعد">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 6l-6 6 6 6"/></svg>
      </button>`;
    $('#pages').innerHTML = p;

    const n = state.selected.size;
    $('#bulk').classList.toggle('show', n > 0);
    $('#bulkCount').textContent = `${fa(n)} سفارش انتخاب شده`;
  }

  async function fetchOrders() {
    state.loading = true;
    try {
      const data = await api('sefareshban_get_orders', {
        page: state.page,
        per_page: state.per,
        status: state.status,
        search: state.q,
        sort_key: state.sort.key,
        sort_dir: state.sort.dir,
      });
      state.rows = data.rows || [];
      state.total = data.total || 0;
      state.statusCounts = data.statusCounts || state.statusCounts;
      state.page = data.page || state.page;
      state.per = data.perPage || state.per;
    } catch (e) {
      console.error(e);
      toast(i18n.error || 'خطا در دریافت سفارش‌ها');
      state.rows = [];
      state.total = 0;
    } finally {
      state.loading = false;
      renderChips();
      renderTable();
      const navCount = document.querySelector('.nav a[aria-current="page"] .count');
      if (navCount) navCount.textContent = fa(state.statusCounts.all || state.total || 0);
    }
  }


  /* ================= stats / KPIs ================= */
  function renderStats(stats) {
    if (!stats) return;
    state.stats = stats;
    // Today orders
    const todayCountEl = document.querySelector('.kpi[data-tone="rose"] .kpi-value');
    if (todayCountEl) todayCountEl.innerHTML = `${fa(stats.todayCount||0)} <small>سفارش</small>`;
    const todayDelta = document.querySelector('.kpi[data-tone="rose"] .delta');
    if (todayDelta) {
      const diff = (stats.todayCount||0) - (stats.yesterdayCount||0);
      const pct = stats.yesterdayCount ? Math.round((diff / stats.yesterdayCount) * 100) : (stats.todayCount ? 100 : 0);
      const up = diff >= 0;
      todayDelta.className = 'delta ' + (up ? 'up' : 'down');
      todayDelta.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${up?'M6 15l6-6 6 6':'M6 9l6 6 6-6'}"/></svg>${fa(Math.abs(pct))}٪`;
    }

    // Sales today
    const salesEl = document.querySelector('.kpi[data-tone="emerald"] .kpi-value');
    if (salesEl) salesEl.innerHTML = `${fa(Math.round(stats.todaySales||0))} <small>${esc(i18n.toman||'تومان')}</small>`;
    const salesDelta = document.querySelector('.kpi[data-tone="emerald"] .delta');
    if (salesDelta) {
      const diff = (stats.todaySales||0) - (stats.yesterdaySales||0);
      const pct = stats.yesterdaySales ? Math.round((diff / stats.yesterdaySales) * 100) : (stats.todaySales ? 100 : 0);
      const up = diff >= 0;
      salesDelta.className = 'delta ' + (up ? 'up' : 'down');
      salesDelta.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${up?'M6 15l6-6 6 6':'M6 9l6 6 6-6'}"/></svg>${fa(Math.abs(pct))}٪`;
    }

    // Pending
    const pendingEl = document.querySelector('.kpi[data-tone="amber"] .kpi-value');
    if (pendingEl) pendingEl.innerHTML = `${fa(stats.pendingCount||0)} <small>سفارش</small>`;
    const urgent = stats.urgentCount||0;
    const pending = stats.pendingCount||0;
    const deltaUrgent = document.querySelector('.kpi[data-tone="amber"] .delta');
    if (deltaUrgent) {
      const hasUrgent = urgent > 0;
      deltaUrgent.className = 'delta ' + (hasUrgent ? 'down' : 'up');
      deltaUrgent.innerHTML = hasUrgent
        ? `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5l3 2"/></svg>${fa(urgent)} فوری`
        : `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>بدون تأخیر`;
    }

    // Promo
    const promoB = document.querySelector('.promo b');
    const promoP = document.querySelector('.promo p');
    if (promoB) promoB.textContent = urgent > 0 ? `${fa(urgent)} سفارش نیازمند بررسی` : 'همه چیز روبه‌راه است';
    if (promoP) promoP.textContent = urgent > 0 ? 'بیش از ۲۴ ساعت در انتظار اقدام مانده‌اند.' : 'سفارش فوری در انتظار ندارید.';

    // AOV
    const aovEl = document.querySelector('.kpi[data-tone="indigo"] .kpi-value');
    if (aovEl) aovEl.innerHTML = `${fa(Math.round(stats.aov30||0))} <small>${esc(i18n.toman||'تومان')}</small>`;

    renderKpiCharts();
  }

  async function fetchStats() {
    try {
      const data = await api('sefareshban_get_stats', {});
      renderStats(data);
    } catch (e) {
      console.error(e);
      // Keep static/demo charts as fallback
      renderKpiCharts();
    }
  }


  /* ================= drawer ================= */
  function openOrder(id) {
    // Show loading state in drawer
    state.openId = +id;
    $('#drawer').innerHTML = `<div class="d-head"><div><div class="t1" id="drawerTitle">سفارش <span class="oid num"><span>#</span>${faId(id)}</span></div><div class="t2">در حال بارگذاری…</div></div><button class="circle" data-close aria-label="بستن"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div><div class="d-body"><div style="padding:24px;color:var(--muted);font-size:13px">در حال دریافت اطلاعات سفارش…</div></div>`;
    $('#drawer').classList.add('open');
    $('#scrim').classList.add('show');

    api('sefareshban_get_order', { order_id: id }).then(data => {
      if (state.openId !== +id) return;
      renderDrawer(data.order);
    }).catch(e => {
      console.error(e);
      if (state.openId !== +id) return;
      $('#drawer').innerHTML = `<div class="d-head"><div><div class="t1" id="drawerTitle">سفارش <span class="oid num"><span>#</span>${faId(id)}</span></div><div class="t2" style="color:var(--danger)">خطا در دریافت اطلاعات</div></div><button class="circle" data-close aria-label="بستن"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div><div class="d-body"><div style="padding:24px;color:var(--muted)">${esc(e.message||'خطا')}</div></div>`;
    });
  }

  function renderDrawer(o) {
    state.openId = o.id;
    const st = STATUS[o.status] || { label: o.statusLabel || o.status };
    const rows = (o.notes||[]).map(n => `
      <div class="tl ${n.sys?'sys':''}">
        <b>${esc(n.by||'')}</b>
        <p>${esc(n.t||'')}</p>
        <time>${esc(n.at||'')}</time>
      </div>`).join('');

    const items = (o.items||[]).map(i => `
      <div class="item">
        <div class="thumb">${i.img ? `<img src="${esc(i.img)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px">` : esc(i.c||'•')}</div>
        <div class="nm"><b>${esc(i.n||'')}</b><span class="num">${fa(i.q||0)} × ${money(i.p||0)}</span></div>
        <div class="pr num">${fa(Math.round((i.q||0)*(i.p||0)))}</div>
      </div>`).join('');

    const opts = Object.keys(STATUS).map(k =>
      `<option value="${k}" ${o.status===k?'selected':''}>${esc(STATUS[k].label)}</option>`
    ).join('');

    const orderCount = o.orderCount ? fa(o.orderCount) + ' سفارش' : 'مشتری';

    $('#drawer').innerHTML = `
      <div class="d-head">
        <div>
          <div class="t1" id="drawerTitle">
            سفارش <span class="oid num"><span>#</span>${faId(o.id)}</span>
          </div>
          <div class="t2 num">${esc(o.date||'')} — ${esc(o.time||'')} · از طریق ${esc(o.channel||'وب‌سایت')}</div>
        </div>
        <button class="circle" data-close aria-label="بستن">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>

      <div class="d-body">
        <section class="d-sec" style="padding-top:2px">
          <div class="status-row">
            <span class="badge" data-status="${esc(o.status)}" id="dStatus">${esc(st.label)}</span>
            <div class="select" style="margin-inline-start:auto;flex:0 0 190px">
              <select id="statusSelect" aria-label="تغییر وضعیت سفارش">${opts}</select>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </div>
            <button class="icon-btn" data-print="${o.id}" title="چاپ فاکتور" aria-label="چاپ فاکتور">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4v-7h16v7h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/></svg>
            </button>
          </div>
        </section>

        <section class="d-sec">
          <div class="d-label">مشتری <a href="${esc(o.editUrl||'#')}" target="_blank" rel="noopener">مشاهده در ووکامرس</a></div>
          <div class="person">
            <span class="av">${esc((o.name||'م')[0])}</span>
            <div>
              <b>${esc(o.name||'')}</b>
              <span>${esc(orderCount)}</span>
            </div>
          </div>
          <div class="contact">
            <div><span>موبایل</span><b class="num">${esc(o.phone||'—')}</b></div>
            <div><span>ایمیل</span><b dir="ltr" style="text-align:right">${esc(o.email||'—')}</b></div>
          </div>
        </section>

        <section class="d-sec">
          <div class="d-label">آدرس ارسال</div>
          <div class="addr">${esc(o.addr||'—')}</div>
          <div class="muted" style="margin-top:4px">روش پرداخت: ${esc(o.pay||'—')}</div>
        </section>

        <section class="d-sec">
          <div class="d-label">اقلام سفارش <span class="num" style="letter-spacing:0">${fa(o.qty||0)} قلم</span></div>
          <div class="items">${items || '<div class="muted">قلمی یافت نشد</div>'}</div>
        </section>

        <section class="d-sec">
          <div class="totals">
            <div class="r"><span>جمع اقلام</span><b class="num">${money(o.subtotal||0)}</b></div>
            <div class="r"><span>هزینه ارسال</span><b class="num">${(o.ship||0)==0 ? (i18n.free||'رایگان') : money(o.ship)}</b></div>
            ${(o.disc||0) ? `<div class="r"><span>تخفیف</span><b class="num" style="color:var(--ok)">− ${money(o.disc)}</b></div>` : ''}
            <div class="r grand"><span>مبلغ پرداختی</span><b class="num">${money(o.total||0)}</b></div>
          </div>
        </section>

        <section class="d-sec">
          <div class="d-label">یادداشت‌ها و تاریخچه</div>
          <div class="timeline">${rows || '<div class="muted" style="font-size:12px">یادداشتی ثبت نشده است.</div>'}</div>
          <form class="note-form" id="noteForm">
            <label class="field" for="noteInput">
              <input id="noteInput" placeholder="یادداشت داخلی برای این سفارش…" autocomplete="off" required>
            </label>
            <button class="btn primary" type="submit">ثبت</button>
          </form>
        </section>
      </div>

      <div class="d-foot">
        <button class="btn" data-close>بستن</button>
        <a class="btn primary" href="${esc(o.editUrl||'#')}" target="_blank" rel="noopener" style="text-decoration:none">باز کردن در ووکامرس</a>
      </div>`;

    $('#drawer').classList.add('open');
    $('#scrim').classList.add('show');
    setTimeout(() => $('#drawer').querySelector('[data-close]')?.focus(), 60);
  }

  function closeDrawer() {
    $('#drawer').classList.remove('open');
    $('#scrim').classList.remove('show');
    state.openId = null;
  }

  /* ================= toast ================= */
  let toastTimer;
  function toast(msg) {
    const el = $('#toastMsg');
    if (el) el.textContent = msg;
    $('#toast').classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => $('#toast').classList.remove('show'), 2600);
  }


  /* ================= actions ================= */
  async function setStatus(id, key) {
    try {
      const data = await api('sefareshban_update_status', { order_id: id, status: key });
      const st = STATUS[key] || { label: key };
      toast(`وضعیت سفارش #${faId(id)} به «${st.label}» تغییر کرد`);
      await fetchOrders();
      fetchStats();
      if (state.openId === +id && data.order) {
        const sc = $('#drawer .d-body')?.scrollTop || 0;
        renderDrawer(data.order);
        const body = $('#drawer .d-body');
        if (body) body.scrollTop = sc;
      }
    } catch (e) {
      console.error(e);
      toast(i18n.error || 'خطا در تغییر وضعیت');
    }
  }

  async function addNote(orderId, text) {
    try {
      const data = await api('sefareshban_add_note', { order_id: orderId, note: text });
      if (state.openId === +orderId && data.order) {
        renderDrawer(data.order);
        toast(i18n.noteSaved || 'یادداشت ثبت شد');
        setTimeout(() => $('#noteInput')?.focus(), 80);
      }
    } catch (e) {
      console.error(e);
      toast(i18n.error || 'خطا در ثبت یادداشت');
    }
  }

  async function exportCsv() {
    try {
      const data = await api('sefareshban_export_csv', { status: state.status, search: state.q });
      const bin = atob(data.csv);
      const bytes = new Uint8Array(bin.length);
      for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
      const url = URL.createObjectURL(new Blob([bytes], { type: 'text/csv;charset=utf-8' }));
      const a = document.createElement('a');
      a.href = url; a.download = data.filename || 'orders.csv'; a.click();
      URL.revokeObjectURL(url);
      toast(`${fa(data.count||0)} سفارش در فایل CSV ذخیره شد`);
    } catch (e) {
      console.error(e);
      toast(i18n.error || 'خطا در خروجی');
    }
  }

  async function doBulk(bulk) {
    const ids = Array.from(state.selected);
    if (!ids.length) return;
    if (bulk === 'clear') { state.selected.clear(); renderTable(); return; }
    if (bulk === 'print') { toast(`${fa(ids.length)} فاکتور برای چاپ آماده شد`); return; }
    if (bulk === 'status') {
      const next = window.prompt('وضعیت جدید را وارد کنید (processing/completed/pending/onhold/cancelled):', 'processing');
      if (!next) return;
      const key = next.trim() === 'on-hold' ? 'onhold' : next.trim();
      if (!STATUS[key]) { toast('وضعیت نامعتبر'); return; }
      try {
        await api('sefareshban_bulk_action', { bulk: 'status', ids, status: key });
        toast(i18n.bulkStatus || 'وضعیت سفارش‌ها تغییر کرد');
        state.selected.clear();
        await fetchOrders(); fetchStats();
      } catch(e){ console.error(e); toast(i18n.error||'خطا'); }
      return;
    }
    if (bulk === 'delete') {
      if (!window.confirm(i18n.confirmDelete || 'آیا مطمئن هستید؟')) return;
      try {
        await api('sefareshban_bulk_action', { bulk: 'trash', ids });
        toast(i18n.deleted || 'سفارش‌ها حذف شدند');
        state.selected.clear();
        await fetchOrders(); fetchStats();
      } catch(e){ console.error(e); toast(i18n.error||'خطا'); }
      return;
    }
  }

/* ================= KPI charts ================= */
function smoothPath(pts){
  if(pts.length < 2) return '';
  let d = `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;
  for(let i = 0; i < pts.length - 1; i++){
    const p0 = pts[i - 1] || pts[i];
    const p1 = pts[i];
    const p2 = pts[i + 1];
    const p3 = pts[i + 2] || p2;
    const c1x = p1.x + (p2.x - p0.x) / 6;
    const c1y = p1.y + (p2.y - p0.y) / 6;
    const c2x = p2.x - (p3.x - p1.x) / 6;
    const c2y = p2.y - (p3.y - p1.y) / 6;
    d += ` C ${c1x.toFixed(1)} ${c1y.toFixed(1)}, ${c2x.toFixed(1)} ${c2y.toFixed(1)}, ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
  }
  return d;
}

function positionTip(host, tip, ratio){
  const clamped = Math.max(0.18, Math.min(0.82, ratio));
  tip.style.left = (clamped * 100).toFixed(1) + '%';
}

function drawBarChart(el, items, opts){
  el.querySelector('svg.viz')?.remove();
  const W = Math.max(170, Math.round(el.clientWidth || 220)), H = 54, padT = 8, padB = 4, padX = 2;
  const max = Math.max(...items.map(d => d.v)) * 1.12;
  const slot = (W - padX * 2) / items.length;
  const bw = Math.min(20, Math.max(10, slot * 0.54));
  const gid = 'barGrad_' + el.id;

  const bars = items.map((d, i) => {
    const h = Math.max(6, (d.v / max) * (H - padT - padB));
    const x = padX + i * slot + (slot - bw) / 2;
    const y = H - padB - h;
    const isLast = i === items.length - 1;
    const delay = (i * 45) + 'ms';
    return `
      <rect class="viz-bar ${isLast ? 'is-hot' : ''}" data-i="${i}"
            x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${bw.toFixed(1)}" height="${h.toFixed(1)}"
            rx="5" fill="${isLast ? `url(#${gid})` : 'var(--kpi-c)'}"
            fill-opacity="${isLast ? '1' : '0.26'}" style="animation-delay:${delay}"/>
      <rect class="viz-hit" data-i="${i}" x="${(padX + i * slot).toFixed(1)}" y="0" width="${slot.toFixed(1)}" height="${H}"/>`;
  }).join('');

  el.insertAdjacentHTML('beforeend', `
    <svg class="viz" viewBox="0 0 ${W} ${H}" role="img" aria-label="${opts.label}">
      <defs>
        <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--kpi-c)"/>
          <stop offset="100%" stop-color="var(--kpi-c)" stop-opacity="0.58"/>
        </linearGradient>
      </defs>
      <line class="viz-grid" x1="0" y1="${(H * 0.34).toFixed(1)}" x2="${W}" y2="${(H * 0.34).toFixed(1)}"/>
      <line class="viz-grid" x1="0" y1="${(H - padB).toFixed(1)}" x2="${W}" y2="${(H - padB).toFixed(1)}"/>
      ${bars}
    </svg>`);

  const tip = el.querySelector('.viz-tip');
  const barEls = Array.from(el.querySelectorAll('.viz-bar'));
  el.querySelectorAll('.viz-hit').forEach(hit => {
    hit.addEventListener('pointerenter', () => {
      const i = +hit.dataset.i;
      const d = items[i];
      barEls.forEach((b, idx) => {
        b.setAttribute('fill-opacity', idx === i ? '1' : (idx === items.length - 1 ? '0.55' : '0.18'));
        b.classList.toggle('is-hot', idx === i);
      });
      tip.innerHTML = `${d.t} · <b>${opts.fmt(d.v)}</b>`;
      positionTip(el, tip, (i + 0.5) / items.length);
      tip.classList.add('show');
    });
  });
  el.onpointerleave = () => {
    barEls.forEach((b, idx) => {
      const isLast = idx === items.length - 1;
      b.setAttribute('fill-opacity', isLast ? '1' : '0.26');
      b.classList.toggle('is-hot', isLast);
    });
    tip.classList.remove('show');
  };
}

function drawAreaChart(el, items, opts){
  el.querySelector('svg.viz')?.remove();
  const W = Math.max(170, Math.round(el.clientWidth || 236)), H = 54, padT = 10, padB = 6, padX = 7;
  const vals = items.map(d => d.v);
  const min = Math.min(...vals);
  const max = Math.max(...vals);
  const span = Math.max(1, max - min);
  const lo = min - span * 0.28;
  const hi = max + span * 0.16;

  const pts = items.map((d, i) => ({
    x: padX + (i / (items.length - 1)) * (W - padX * 2),
    y: padT + (1 - (d.v - lo) / (hi - lo)) * (H - padT - padB),
    t: d.t, v: d.v
  }));

  const lineD = smoothPath(pts);
  const areaD = `${lineD} L ${pts[pts.length - 1].x.toFixed(1)} ${H - padB} L ${pts[0].x.toFixed(1)} ${H - padB} Z`;
  const gid = 'areaGrad_' + el.id;

  const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
  const avgY = padT + (1 - (avg - lo) / (hi - lo)) * (H - padT - padB);
  const last = pts[pts.length - 1];
  const peakIdx = vals.indexOf(max);
  const peak = pts[peakIdx];

  const slot = (W - padX * 2) / items.length;
  const hits = pts.map((p, i) =>
    `<rect class="viz-hit" data-i="${i}" x="${(p.x - slot / 2).toFixed(1)}" y="0" width="${slot.toFixed(1)}" height="${H}"/>`
  ).join('');

  el.insertAdjacentHTML('beforeend', `
    <svg class="viz" viewBox="0 0 ${W} ${H}" role="img" aria-label="${opts.label}">
      <defs>
        <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--kpi-c)" stop-opacity="0.28"/>
          <stop offset="100%" stop-color="var(--kpi-c)" stop-opacity="0.01"/>
        </linearGradient>
      </defs>
      <line class="viz-grid" x1="0" y1="${(H - padB).toFixed(1)}" x2="${W}" y2="${(H - padB).toFixed(1)}"/>
      ${opts.showAvg ? `<line class="viz-avg" x1="${padX}" y1="${avgY.toFixed(1)}" x2="${W - padX}" y2="${avgY.toFixed(1)}"/>` : ''}
      <path class="viz-area" d="${areaD}" fill="url(#${gid})"/>
      <path class="viz-line" d="${lineD}"/>
      <line class="viz-guide" x1="${last.x.toFixed(1)}" y1="${padT - 2}" x2="${last.x.toFixed(1)}" y2="${H - padB}"/>
      ${opts.showPeak && peakIdx !== items.length - 1 ? `<circle cx="${peak.x.toFixed(1)}" cy="${peak.y.toFixed(1)}" r="2.8" fill="var(--surface)" stroke="var(--kpi-c)" stroke-width="1.8" opacity=".8"/>` : ''}
      <circle class="viz-halo" cx="${last.x.toFixed(1)}" cy="${last.y.toFixed(1)}" r="7" fill="var(--kpi-c)" opacity="0.18"/>
      <circle class="viz-dot" cx="${last.x.toFixed(1)}" cy="${last.y.toFixed(1)}" r="3.6" fill="var(--surface)" stroke="var(--kpi-c)" stroke-width="2.2"/>
      ${hits}
    </svg>`);

  const tip = el.querySelector('.viz-tip');
  const dot = el.querySelector('.viz-dot');
  const halo = el.querySelector('.viz-halo');
  const guide = el.querySelector('.viz-guide');

  const focusPoint = (p, active) => {
    dot.setAttribute('cx', p.x.toFixed(1));
    dot.setAttribute('cy', p.y.toFixed(1));
    dot.setAttribute('r', active ? '4.4' : '3.6');
    halo.setAttribute('cx', p.x.toFixed(1));
    halo.setAttribute('cy', p.y.toFixed(1));
    guide.setAttribute('x1', p.x.toFixed(1));
    guide.setAttribute('x2', p.x.toFixed(1));
    guide.classList.toggle('show', active);
  };

  el.querySelectorAll('.viz-hit').forEach(hit => {
    hit.addEventListener('pointerenter', () => {
      const i = +hit.dataset.i;
      const p = pts[i];
      focusPoint(p, true);
      tip.innerHTML = `${p.t} · <b>${opts.fmt(p.v)}</b>`;
      positionTip(el, tip, p.x / W);
      tip.classList.add('show');
    });
  });
  el.onpointerleave = () => {
    focusPoint(last, false);
    tip.classList.remove('show');
  };
}

function renderKpiCharts(){
  const s = state.stats;
  const ordersData = (s && s.series7 && s.series7.length) ? s.series7.map((d,i) => ({t: d.label, v: d.count, isToday: i === s.series7.length-1})) : [
    {t:'شنبه', v:14}, {t:'یکشنبه', v:18}, {t:'دوشنبه', v:15},
    {t:'سه‌شنبه', v:20}, {t:'چهارشنبه', v:17}, {t:'پنج‌شنبه', v:21}, {t:'امروز', v:24}
  ];
  drawBarChart($('#chartOrders'), ordersData, {
    label:'نمودار ستونی تعداد سفارش‌های ۷ روز اخیر',
    fmt: v => `${fa(v)} سفارش`
  });

  const salesData = (s && s.series7 && s.series7.length) ? s.series7.map(d => ({t: d.label, v: Math.round(d.sales)})) : [
    {t:'شنبه', v:12400000}, {t:'یکشنبه', v:14800000}, {t:'دوشنبه', v:13100000},
    {t:'سه‌شنبه', v:16900000}, {t:'چهارشنبه', v:15200000}, {t:'پنج‌شنبه', v:17100000}, {t:'امروز', v:18450000}
  ];
  drawBarChart($('#chartSales'), salesData, {
    label:'نمودار ستونی فروش ۷ روز اخیر',
    fmt: v => `${fa(Math.round(v))} تومان`
  });

  const pendingData = (s && s.pendingSeries7 && s.pendingSeries7.length) ? s.pendingSeries7.map(d => ({t: d.label, v: d.count})) : [
    {t:'شنبه', v:5}, {t:'یکشنبه', v:6}, {t:'دوشنبه', v:4},
    {t:'سه‌شنبه', v:7}, {t:'چهارشنبه', v:5}, {t:'پنج‌شنبه', v:6}, {t:'امروز', v:7}
  ];
  const chartPending = $('#chartPending');
  if (chartPending) drawBarChart(chartPending, pendingData, {
    label:'نمودار سفارش‌های در انتظار ۷ روز اخیر',
    fmt: v => `${fa(v)} سفارش`
  });

  const aovData = (s && s.aovSeries && s.aovSeries.length) ? s.aovSeries.map(d => ({t: d.label, v: Math.round(d.value)})) : [
    {t:'۵ آبان', v:1140000}, {t:'۱۰ آبان', v:1290000}, {t:'۱۵ آبان', v:1210000},
    {t:'۲۰ آبان', v:1340000}, {t:'۲۵ آبان', v:1280000}, {t:'۳۰ آبان', v:1265000},
    {t:'۲ آذر', v:1255000}, {t:'امروز', v:1230000}
  ];
  drawBarChart($('#chartAov'), aovData, {
    label:'نمودار ستونی میانگین ارزش سفارش ۳۰ روزه',
    fmt: v => `${fa(Math.round(v))} تومان`
  });
}


  let kpiResizeTimer;
  if (typeof ResizeObserver !== 'undefined') {
    const kpis = $('.kpis');
    if (kpis) new ResizeObserver(() => {
      clearTimeout(kpiResizeTimer);
      kpiResizeTimer = setTimeout(renderKpiCharts, 120);
    }).observe(kpis);
  }

  /* ================= events ================= */
  // initial render
  renderChips();
  renderTable();
  renderKpiCharts();
  fetchStats();
  fetchOrders().then(() => {
    // update sidebar count
    const navCount = document.querySelector('.nav a[aria-current="page"] .count');
    if (navCount) navCount.textContent = fa(state.statusCounts.all || state.total || 0);
  });

  $$('[data-filter-tab]').forEach(el => el.addEventListener('click', () => {
    state.status = el.dataset.filterTab;
    state.page = 1;
    state.selected.clear();
    renderChips();
    fetchOrders();
    toast(`فیلتر «${(STATUS[state.status]||{}).label || state.status}» اعمال شد`);
  }));

  const chipsWrap = $('#chips');
  if (chipsWrap) chipsWrap.addEventListener('click', e => {
    const t = e.target.closest('[data-tab]');
    if (!t) return;
    state.status = t.dataset.tab;
    state.page = 1;
    state.selected.clear();
    renderChips();
    fetchOrders();
  });

  let searchTimer;
  const q = $('#q');
  if (q) q.addEventListener('input', e => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.q = e.target.value.trim();
      state.page = 1;
      fetchOrders();
    }, 350);
  });

  const perPage = $('#perPage');
  if (perPage) perPage.addEventListener('change', e => {
    state.per = +e.target.value;
    state.page = 1;
    state.selected.clear();
    fetchOrders();
  });

  const tbody = $('#tbody');
  if (tbody) tbody.addEventListener('click', e => {
    const chk = e.target.closest('[data-check]');
    if (chk) {
      const id = +chk.dataset.check;
      chk.checked ? state.selected.add(id) : state.selected.delete(id);
      chk.closest('tr').classList.toggle('is-selected', chk.checked);
      const ids = state.rows.map(o => o.id);
      const all = ids.length && ids.every(i => state.selected.has(i));
      const some = ids.some(i => state.selected.has(i));
      $('#checkAll').checked = all;
      $('#checkAll').indeterminate = !all && some;
      $('#bulk').classList.toggle('show', state.selected.size > 0);
      $('#bulkCount').textContent = `${fa(state.selected.size)} سفارش انتخاب شده`;
      return;
    }
    const open = e.target.closest('[data-open]');
    if (open) { e.preventDefault(); openOrder(open.dataset.open); return; }
    const row = e.target.closest('tr[data-id]');
    if (row) openOrder(row.dataset.id);
  });

  const checkAll = $('#checkAll');
  if (checkAll) checkAll.addEventListener('change', e => {
    const ids = state.rows.map(o => o.id);
    ids.forEach(i => e.target.checked ? state.selected.add(i) : state.selected.delete(i));
    renderTable();
  });

  const pages = $('#pages');
  if (pages) pages.addEventListener('click', e => {
    const b = e.target.closest('[data-page]');
    if (!b || b.disabled) return;
    const p = +b.dataset.page;
    const max = Math.max(1, Math.ceil(state.total / state.per));
    if (p < 1 || p > max) return;
    state.page = p;
    fetchOrders();
  });

  $$('th[data-sort]').forEach(th => th.addEventListener('click', () => {
    const k = th.dataset.sort;
    if (state.sort.key === k) state.sort.dir = state.sort.dir === 'asc' ? 'desc' : 'asc';
    else state.sort = { key: k, dir: 'desc' };
    state.page = 1;
    fetchOrders();
  }));

  const bulk = $('#bulk');
  if (bulk) bulk.addEventListener('click', e => {
    const b = e.target.closest('[data-bulk]');
    if (!b) return;
    doBulk(b.dataset.bulk);
  });

  const clearFilters = $('#clearFilters');
  if (clearFilters) clearFilters.addEventListener('click', () => {
    state.q = ''; state.status = 'all'; state.page = 1;
    const qi = $('#q'); if (qi) qi.value = '';
    renderChips(); fetchOrders();
  });

  const exportBtn = $('#exportBtn');
  if (exportBtn) exportBtn.addEventListener('click', exportCsv);
  const newOrderBtn = $('#newOrderBtn');
  if (newOrderBtn) newOrderBtn.addEventListener('click', () => {
    window.open(SB.newOrderUrl || ((SB.adminUrl || '/wp-admin/') + 'post-new.php?post_type=shop_order'), '_blank');
  });
  const moreBtn = $('#moreBtn');
  if (moreBtn) moreBtn.addEventListener('click', () => toast('اقدامات بیشتر در نسخه‌های بعدی افزونه'));
  const filterBtn = $('#filterBtn');
  if (filterBtn) filterBtn.addEventListener('click', () => toast('فیلتر پیشرفته در نسخه‌های بعدی افزونه'));
  const expandBtn = $('#expandBtn');
  if (expandBtn) expandBtn.addEventListener('click', () => { fetchOrders(); fetchStats(); toast(i18n.refresh || 'به‌روز شد'); });

  $$('[data-nav]').forEach(a => a.addEventListener('click', e => {
    const href = a.getAttribute('href') || '';
    if (!href || href === '#') { e.preventDefault(); toast('این بخش در نسخه افزونه اضافه می‌شود'); }
  }));
  const promoBtn = document.querySelector('.promo button');
  if (promoBtn) promoBtn.addEventListener('click', () => {
    state.status = 'pending'; state.page = 1; state.selected.clear();
    renderChips(); fetchOrders();
    document.querySelector('.content')?.scrollTo({top:0, behavior:'smooth'});
  });

  const themeBtn = $('#themeBtn');
  if (themeBtn) themeBtn.addEventListener('click', () => {
    const root = document.getElementById('sefareshbanRoot');
    if (!root) return;
    const dark = root.dataset.theme === 'dark';
    root.dataset.theme = dark ? 'light' : 'dark';
    localStorage.setItem('woo-theme', root.dataset.theme);
    $('#themeIcon').innerHTML = dark
      ? '<path d="M21 12.8A8.5 8.5 0 1111.2 3a6.6 6.6 0 009.8 9.8z"/>'
      : '<circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/>';
  });

  const densityBtn = $('#densityBtn');
  if (densityBtn) densityBtn.addEventListener('click', e => {
    const root = document.getElementById('sefareshbanRoot');
    if (!root) return;
    const compact = root.dataset.density === 'compact';
    root.dataset.density = compact ? 'comfortable' : 'compact';
    e.currentTarget.setAttribute('aria-pressed', String(!compact));
    localStorage.setItem('woo-density', root.dataset.density);
  });

  const scrim = $('#scrim');
  if (scrim) scrim.addEventListener('click', closeDrawer);

  const drawer = $('#drawer');
  if (drawer) drawer.addEventListener('click', e => {
    if (e.target.closest('[data-close]')) { closeDrawer(); return; }
    const pr = e.target.closest('[data-print]');
    if (pr) { window.print(); return; }
    const soon = e.target.closest('[data-soon]');
    if (soon) { e.preventDefault(); toast(`${soon.dataset.soon} — در نسخه افزونه`); }
  });

  if (drawer) drawer.addEventListener('change', e => {
    if (e.target.id === 'statusSelect') setStatus(state.openId, e.target.value);
  });

  if (drawer) drawer.addEventListener('submit', e => {
    if (e.target.id !== 'noteForm') return;
    e.preventDefault();
    const input = $('#noteInput');
    const txt = input.value.trim();
    if (!txt) return;
    addNote(state.openId, txt);
  });

  document.addEventListener('keydown', e => {
    const tag = e.target.tagName;
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(tag);
    if (e.key === 'Escape' && state.openId) { closeDrawer(); return; }
    if (e.key === '/' && !typing) { e.preventDefault(); $('#q')?.focus(); }
  });

  /* restore prefs */
  const root = document.getElementById('sefareshbanRoot');
  if (root) {
    if (localStorage.getItem('woo-theme') === 'dark') {
      root.dataset.theme = 'dark';
      const ic = $('#themeIcon');
      if (ic) ic.innerHTML = '<circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/>';
    }
    if (localStorage.getItem('woo-density') === 'compact') {
      root.dataset.density = 'compact';
      $('#densityBtn')?.setAttribute('aria-pressed','true');
    }
  }

})();
