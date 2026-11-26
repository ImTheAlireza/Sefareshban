/* Sefareshban Admin JS */
(() => {
  'use strict';

  const SB = window.SEFARESHBAN || {};
  const ajaxUrl = SB.ajaxUrl || '';
  const nonce = SB.nonce || '';
  const i18n = SB.i18n || {};
  const wcStatuses = SB.statuses || {};
  const paymentGateways = SB.paymentGateways || [];
  const shippingMethods = SB.shippingMethods || [];
  const iranStates = SB.states || [];
  const initSettings = SB.initialSettings || {};
  const storeInfo = Object.assign({ name: SB.siteName || 'فروشگاه', address: '', postcode: '', phone: '' }, SB.store || {});
  const CURRENCY = SB.currency || i18n.currency || i18n.toman || 'تومان';
  const baseDocTitle = document.title || 'سفارش‌بان';

  /* ================= status map ================= */
  const STATUS_META = {
    pending:          { label: 'در انتظار پرداخت', cls: 'pending' },
    processing:       { label: 'در حال انجام', cls: 'processing' },
    completed:        { label: 'تکمیل‌شده', cls: 'completed' },
    onhold:           { label: 'در انتظار بررسی', cls: 'onhold' },
    cancelled:        { label: 'لغو‌شده', cls: 'cancelled' },
    refunded:         { label: 'مسترد‌شده', cls: 'refunded' },
    failed:           { label: 'ناموفق', cls: 'failed' },
    trash:            { label: 'زباله‌دان', cls: 'trash' },
    draft:            { label: 'پیش‌نویس', cls: 'draft' },
    'checkout-draft': { label: 'پیش‌نویس تسویه', cls: 'checkout-draft' }
  };

  const STATUS = {};
  Object.keys(wcStatuses).forEach(k => {
    const slug = k === 'on-hold' ? 'onhold' : k;
    const meta = STATUS_META[slug] || { label: wcStatuses[k].label || k, cls: 'onhold' };
    STATUS[slug] = { label: wcStatuses[k].label || meta.label, cls: meta.cls };
  });
  ['pending', 'processing', 'completed', 'onhold', 'cancelled', 'refunded', 'failed'].forEach(k => {
    if (!STATUS[k]) STATUS[k] = STATUS_META[k];
  });

  /* ================= helpers ================= */
  const fa = n => new Intl.NumberFormat('fa-IR').format(Number(n) || 0);
  const faId = n => String(n ?? '').replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]);
  const enDigits = s => String(s ?? '').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  const pad2 = n => String(n).padStart(2, '0');
  const esc = (s = '') => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const money = n => fa(Math.round(Number(n) || 0)) + ' ' + CURRENCY;
  const shortMoney = n => fa(Math.round(Number(n) || 0)) + ' <small>' + esc(CURRENCY) + '</small>';
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  /* ================= Jalali <-> Gregorian Math ================= */
  const JALALI_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

  function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    const gy2 = gm > 2 ? gy + 1 : gy;
    let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
    let jy = -1595 + (33 * Math.floor(days / 12053));
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      jy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    const jd = 1 + (days < 186 ? (days % 31) : ((days - 186) % 30));
    return [jy, jm, jd];
  }

  function jalaliToGregorian(jy, jm, jd) {
    jy += 1595;
    let days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd + (jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    let gy = 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
      gy += 100 * Math.floor(--days / 36524);
      days %= 36524;
      if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      gy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    let gd = days + 1;
    const isLeapG = (gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0);
    const sal_a = [0, 31, isLeapG ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 1;
    for (; gm <= 12 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
    return [gy, gm, gd];
  }

  function jalaliMonthLength(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    const [gy, gm, gd] = jalaliToGregorian(jy, 12, 30);
    const [rjy, rjm, rjd] = gregorianToJalali(gy, gm, gd);
    return (rjy === jy && rjm === 12 && rjd === 30) ? 30 : 29;
  }

  function isoToJalaliDisplay(isoYmd) {
    if (!isoYmd || !/^\d{4}-\d{2}-\d{2}$/.test(isoYmd)) return '';
    const [gy, gm, gd] = isoYmd.split('-').map(Number);
    const [jy, jm, jd] = gregorianToJalali(gy, gm, gd);
    return faId(`${jy}/${pad2(jm)}/${pad2(jd)}`);
  }

  function localTodayIso(daysOffset = 0) {
    const d = new Date();
    if (daysOffset) d.setDate(d.getDate() + daysOffset);
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
  }

  /* ================= Clipboard Copy Helper ================= */
  async function copyToClipboard(text, btnEl, label = 'متن') {
    const val = String(text || '').trim();
    if (!val) return;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(val);
      } else {
        const ta = document.createElement('textarea');
        ta.value = val;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
      }
      if (btnEl) {
        btnEl.classList.add('copied');
        setTimeout(() => btnEl.classList.remove('copied'), 1500);
      }
      toast(`${label} کپی شد ✓`);
    } catch (err) {
      console.error(err);
      toast('خطا در کپی به حافظه');
    }
  }

  /* ================= Web Audio Chime & Tab Notification ================= */
  let audioCtx = null;
  function playNotificationChime() {
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      if (!audioCtx) audioCtx = new Ctx();
      if (audioCtx.state === 'suspended') audioCtx.resume();

      const now = audioCtx.currentTime;
      const notes = [587.33, 880]; // D5 -> A5 pleasant bell chime
      notes.forEach((freq, idx) => {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, now + idx * 0.13);
        gain.gain.setValueAtTime(0.001, now + idx * 0.13);
        gain.gain.exponentialRampToValueAtTime(0.18, now + idx * 0.13 + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + idx * 0.13 + 0.45);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(now + idx * 0.13);
        osc.stop(now + idx * 0.13 + 0.48);
      });
    } catch (e) {
      console.warn('Audio notification skipped:', e);
    }
  }

  let knownMaxOrderId = null;
  let unreadNewOrders = 0;

  function clearUnreadBadge() {
    if (unreadNewOrders > 0) {
      unreadNewOrders = 0;
      document.title = baseDocTitle;
    }
  }
  window.addEventListener('focus', clearUnreadBadge);
  document.addEventListener('pointerdown', clearUnreadBadge, { passive: true });

  function checkNewOrderArrival(latestId) {
    const id = Number(latestId) || 0;
    if (!id) return;
    if (knownMaxOrderId === null) {
      knownMaxOrderId = id;
      return;
    }
    if (id > knownMaxOrderId) {
      knownMaxOrderId = id;
      unreadNewOrders++;
      if (state.soundAlert) {
        playNotificationChime();
        document.title = `(${fa(unreadNewOrders)} سفارش جدید) ${baseDocTitle}`;
      }
      toast(`سفارش جدید #${faId(id)} ثبت شد!`);
    }
  }

  /* ================= State ================= */
  const defaultPer = [8, 12, 20, 50].includes(+initSettings.per_page) ? +initSettings.per_page : 8;
  const ALL_TOGGLE_COLS = ['customer', 'location', 'date', 'status', 'payment', 'shipping', 'tracking', 'items', 'total'];
  const DEFAULT_VISIBLE_COLS = ['customer', 'date', 'status', 'payment', 'items', 'total'];

  let savedCols = null;
  try {
    const parsed = JSON.parse(localStorage.getItem('sb-visible-cols') || 'null');
    if (Array.isArray(parsed) && parsed.length) savedCols = parsed;
  } catch (e) { /* ignore */ }

  const state = {
    section: 'orders',
    status: 'all',
    q: '',
    quickDate: 'all',
    filters: {
      dateFrom: '',
      dateTo: '',
      paymentMethod: '',
      shippingFilter: '',
      stateFilter: '',
      cityFilter: '',
      minTotal: '',
      maxTotal: '',
      onlyWithNote: false,
      onlyDelayed: false
    },
    visibleCols: new Set(savedCols || DEFAULT_VISIBLE_COLS),
    soundAlert: localStorage.getItem('sb-sound-alert') !== null
      ? localStorage.getItem('sb-sound-alert') === '1'
      : (initSettings.sound_alert !== undefined ? !!initSettings.sound_alert : true),
    page: 1,
    per: defaultPer,
    sort: { key: 'date', dir: 'desc' },
    selected: new Set(),
    openId: null,
    openDetail: null,
    pageOrderId: null,
    pageOrderDetail: null,
    rows: [],
    total: 0,
    statusCounts: { all: 0, trash: 0 },
    loading: true,
    stats: null,

    customers: [],
    customersTotal: 0,
    customersPage: 1,
    customersPer: 20,
    customersQuery: '',
    customersLoaded: false,

    products: [],
    productsTotal: 0,
    productsPage: 1,
    productsPer: 20,
    productQuery: '',
    productsLoaded: false,

    reportDays: 30,
    reportData: null,

    qoCart: [],
  };

  const BASE_TAB_KEYS = ['all', 'pending', 'processing', 'completed', 'onhold', 'cancelled', 'refunded', 'failed'];
  const TABS = [
    { k: 'all', l: 'همه' },
    ...BASE_TAB_KEYS.slice(1).filter(k => STATUS[k]).map(k => ({ k, l: STATUS[k].label })),
    ...Object.keys(STATUS)
      .filter(k => !BASE_TAB_KEYS.includes(k) && k !== 'checkout-draft' && k !== 'draft' && k !== 'trash')
      .map(k => ({ k, l: STATUS[k].label })),
    { k: 'trash', l: 'زباله‌دان', isTrash: true }
  ];

  /* ================= API ================= */
  let ordersReqSeq = 0;

  async function api(action, data = {}) {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', nonce);
    Object.keys(data).forEach(k => {
      const v = data[k];
      if (Array.isArray(v)) v.forEach(x => body.append(k + '[]', x));
      else if (typeof v === 'boolean') body.set(k, v ? '1' : '0');
      else if (v !== undefined && v !== null) body.set(k, v);
    });
    const res = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });
    let json = null;
    try {
      json = await res.json();
    } catch (parseErr) {
      throw new Error(i18n.error || 'پاسخ نامعتبر از سرور دریافت شد.');
    }
    if (!res.ok || !json || !json.success) {
      const msg = (json && json.data && json.data.message) ? json.data.message : (i18n.error || 'خطا در ارتباط با سرور');
      throw new Error(msg);
    }
    return json.data;
  }

  function countFor(k) {
    if (k === 'all') return state.statusCounts.all || 0;
    return state.statusCounts[k] || 0;
  }

  function hasActiveAdvFilters() {
    const f = state.filters;
    return !!(f.dateFrom || f.dateTo || f.paymentMethod || f.shippingFilter || f.stateFilter || f.cityFilter || f.minTotal !== '' || f.maxTotal !== '' || f.onlyWithNote || f.onlyDelayed);
  }

  function updateFilterIndicator() {
    const dot = $('#filterDot');
    if (dot) dot.hidden = !hasActiveAdvFilters();
  }

  /* ================= Column Visibility ================= */
  function applyColumnVisibility() {
    const tbl = $('#tbl');
    if (!tbl) return;
    ALL_TOGGLE_COLS.forEach(col => {
      tbl.classList.toggle('hide-col-' + col, !state.visibleCols.has(col));
    });
    $$('[data-col-toggle]').forEach(chk => {
      chk.checked = state.visibleCols.has(chk.dataset.colToggle);
    });
  }

  /* ================= Pagination helper ================= */
  function buildPagerHtml(currentPage, totalItems, perPage) {
    const pages = Math.max(1, Math.ceil(totalItems / perPage));
    let html = `
      <button type="button" class="pg" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''} aria-label="صفحه قبل">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6l6 6-6 6"/></svg>
      </button>`;
    const maxBtns = 5;
    let start = Math.max(1, currentPage - Math.floor(maxBtns / 2));
    let end = Math.min(pages, start + maxBtns - 1);
    start = Math.max(1, end - maxBtns + 1);
    for (let i = start; i <= end; i++) {
      html += `<button type="button" class="pg num" data-page="${i}" aria-current="${i === currentPage}">${fa(i)}</button>`;
    }
    html += `
      <button type="button" class="pg" data-page="${currentPage + 1}" ${currentPage >= pages ? 'disabled' : ''} aria-label="صفحه بعد">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 6l-6 6 6 6"/></svg>
      </button>`;
    return html;
  }

  /* ================= Orders Render ================= */
  function renderChips() {
    const wrap = $('#chips');
    if (!wrap) return;
    const visibleTabs = TABS.filter(t =>
      ['all', 'pending', 'processing', 'completed', 'onhold', 'cancelled'].includes(t.k)
      || countFor(t.k) > 0
      || state.status === t.k
    );
    wrap.innerHTML = visibleTabs.map(t => `
      <button type="button" class="chip" role="tab" data-tab="${esc(t.k)}" aria-selected="${state.status === t.k}">
        ${t.isTrash ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>' : ''}
        ${esc(t.l)}<span class="c num">${fa(countFor(t.k))}</span>
      </button>`).join('');
  }

  function updateBulkBar() {
    const n = state.selected.size;
    const bulk = $('#bulk');
    if (!bulk) return;
    bulk.classList.toggle('show', n > 0 && state.section === 'orders');
    const bc = $('#bulkCount');
    if (bc) bc.textContent = `${fa(n)} سفارش انتخاب شده`;

    const isTrashView = state.status === 'trash';
    const normalActs = $('#bulkNormalActs');
    const trashActs = $('#bulkTrashActs');
    if (normalActs) normalActs.hidden = isTrashView;
    if (trashActs) trashActs.hidden = !isTrashView;
  }

  function renderTable() {
    const rows = state.rows;
    const tb = $('#tbody');
    if (!tb) return;

    applyColumnVisibility();

    if (state.loading && rows.length === 0) {
      tb.innerHTML = `<tr><td colspan="12" class="muted" style="padding:28px;text-align:center">در حال بارگذاری سفارش‌ها…</td></tr>`;
      $('#empty')?.classList.remove('show');
      $('#tbl').style.display = 'table';
      return;
    }

    tb.innerHTML = rows.map(o => {
      const sel = state.selected.has(o.id);
      const st = STATUS[o.status] || STATUS_META[o.status] || { label: o.statusLabel || o.status, cls: 'onhold' };
      const orderNum = o.orderNumber || o.id;
      const dateHtml = `<span class="num" style="font-weight:500">${esc(o.date || '')}</span> <span class="muted num">${esc(o.time || '')}</span>`;
      const subMeta = [o.city, o.phone ? faId(o.phone) : ''].filter(Boolean).join(' · ');
      const locText = [o.state, o.city].filter(Boolean).join('، ') || '—';

      // Smart indicators
      const noteInd = o.customerNote
        ? `<span class="row-ind note-ind" title="یادداشت مشتری: ${esc(o.customerNote)}"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>یادداشت</span>`
        : '';
      const delayInd = o.isDelayed
        ? `<span class="row-ind delay-ind" title="${fa(o.delayHours || 24)} ساعت در انتظار اقدام"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>تأخیر</span>`
        : '';

      // Loyalty indicator
      const cCount = Number(o.customerOrderCount) || 1;
      let loyaltyHtml = '';
      if (cCount >= 3) {
        loyaltyHtml = `<span class="loyalty-tag repeat num" title="مشتری وفادار فروشگاه">★ ${fa(cCount)} خرید</span>`;
      } else if (cCount === 1) {
        loyaltyHtml = `<span class="loyalty-tag first">خرید اول</span>`;
      }

      // Status badge (interactive inline status dropdown for non-trash)
      const statusCellHtml = o.status === 'trash'
        ? `<span class="badge" data-status="trash">زباله‌دان</span>`
        : `<button type="button" class="badge is-interactive" data-status="${esc(o.status)}" data-inline-status="${o.id}" title="کلیک برای تغییر سریع وضعیت">${esc(st.label)}<svg class="chev" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></button>`;

      // Items mini-thumbnails + preview trigger
      const previews = (o.itemsPreview || []).slice(0, 2);
      const thumbsHtml = previews.map(it =>
        `<span class="mini-th">${it.img ? `<img src="${esc(it.img)}" alt="">` : esc(Array.from(it.n || '•')[0] || '•')}</span>`
      ).join('');

      // Tracking cell
      const trackingHtml = o.trackingCode
        ? `<span class="badge num" data-status="completed" style="font-size:11px" title="${esc(o.trackingCarrier || '')}">${esc(faId(o.trackingCode))}</span>`
        : `<span class="muted">—</span>`;

      return `
      <tr data-id="${o.id}" class="${sel ? 'is-selected' : ''}">
        <td class="col-check"><input type="checkbox" data-check="${o.id}" ${sel ? 'checked' : ''} aria-label="انتخاب سفارش ${faId(orderNum)}"></td>
        <td data-col="id">
          <div class="oid-wrap">
            <a class="oid num" href="#order-${o.id}" data-open="${o.id}"><span>#</span>${esc(faId(orderNum))}</a>
            ${noteInd}${delayInd}
          </div>
        </td>
        <td data-col="customer">
          <div class="cust">
            <div class="cust-info">
              <b><span>${esc(o.name || '')}</span>${loyaltyHtml}</b>
              <span class="cust-sub">${esc(subMeta)}</span>
            </div>
          </div>
        </td>
        <td data-col="location"><span class="muted" style="font-size:12.5px">${esc(locText)}</span></td>
        <td class="c-date" data-col="date">${dateHtml}</td>
        <td data-col="status">${statusCellHtml}</td>
        <td class="hide-sm" data-col="payment"><span class="muted" style="font-size:12.5px">${esc(o.pay || '—')}</span></td>
        <td data-col="shipping"><span class="muted" style="font-size:12.5px">${esc(o.shippingMethod || '—')}</span></td>
        <td data-col="tracking">${trackingHtml}</td>
        <td class="c-items" data-col="items">
          <div class="items-cell" data-preview-id="${o.id}">
            ${thumbsHtml ? `<span class="mini-thumbs">${thumbsHtml}</span>` : ''}
            <span class="muted num" style="font-size:12.5px">${fa(o.qty || 0)} قلم</span>
          </div>
        </td>
        <td data-col="total"><span class="money num">${shortMoney(o.total || 0)}</span></td>
        <td class="col-actions">
          ${o.status === 'trash'
            ? `<div class="row-act-btns">
                <button type="button" class="btn" style="height:26px;padding:0 9px;font-size:11px" data-restore-single="${o.id}">بازیابی</button>
                <button type="button" class="dots" data-open="${o.id}" title="مشاهده صفحه سفارش" aria-label="مشاهده سفارش ${faId(orderNum)}">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/></svg>
                </button>
              </div>`
            : `<div class="row-act-btns">
                <button type="button" class="dots" data-quick-peek="${o.id}" title="پیش‌نمایش سریع کشویی" aria-label="پیش‌نمایش سریع سفارش ${faId(orderNum)}">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
                </button>
                <button type="button" class="dots" data-open="${o.id}" title="باز کردن صفحه اختصاصی سفارش" aria-label="مشاهده سفارش ${faId(orderNum)}">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
              </div>`}
        </td>
      </tr>`;
    }).join('');

    const total = state.total;
    const isEmpty = !state.loading && rows.length === 0;
    $('#empty')?.classList.toggle('show', isEmpty);
    $('#tbl').style.display = isEmpty ? 'none' : 'table';

    $$('#tbl th[data-sort]').forEach(th => {
      if (th.dataset.sort === state.sort.key) th.setAttribute('aria-sort', state.sort.dir === 'asc' ? 'ascending' : 'descending');
      else th.removeAttribute('aria-sort');
    });

    const allOnPage = rows.length > 0 && rows.every(o => state.selected.has(o.id));
    const someOnPage = rows.some(o => state.selected.has(o.id));
    const chk = $('#checkAll');
    if (chk) { chk.checked = allOnPage; chk.indeterminate = !allOnPage && someOnPage; }

    $('#pagerInfo').textContent = total ? `· ${fa(total)} سفارش` : '';
    $('#pages').innerHTML = buildPagerHtml(state.page, total, state.per);

    updateBulkBar();
  }

  function buildOrderQueryParams() {
    return {
      page: state.page,
      per_page: state.per,
      status: state.status,
      search: state.q,
      sort_key: state.sort.key,
      sort_dir: state.sort.dir,
      date_from: state.filters.dateFrom,
      date_to: state.filters.dateTo,
      payment_method: state.filters.paymentMethod,
      shipping_filter: state.filters.shippingFilter,
      state_filter: state.filters.stateFilter,
      city_filter: state.filters.cityFilter,
      min_total: state.filters.minTotal,
      max_total: state.filters.maxTotal,
      only_with_note: state.filters.onlyWithNote ? 1 : 0,
      only_delayed: state.filters.onlyDelayed ? 1 : 0,
    };
  }

  async function fetchOrders() {
    const seq = ++ordersReqSeq;
    state.loading = true;
    if (state.rows.length === 0) renderTable();

    try {
      const data = await api('sefareshban_get_orders', buildOrderQueryParams());
      if (seq !== ordersReqSeq) return;
      state.rows = data.rows || [];
      state.total = data.total || 0;
      state.statusCounts = data.statusCounts || state.statusCounts;
      state.page = data.page || state.page;
      state.per = data.perPage || state.per;
      if (data.latestOrderId) checkNewOrderArrival(data.latestOrderId);
    } catch (e) {
      if (seq !== ordersReqSeq) return;
      console.error(e);
      toast(e.message || i18n.error || 'خطا در دریافت سفارش‌ها');
      state.rows = [];
      state.total = 0;
    } finally {
      if (seq !== ordersReqSeq) return;
      state.loading = false;
      renderChips();
      renderTable();
      if (state.section === 'order-detail' && state.pageOrderDetail) {
        syncOrderPageNavButtons();
      }
      const navCount = document.getElementById('navOrdersCount');
      if (navCount) navCount.textContent = fa(state.statusCounts.all ?? state.total ?? 0);
    }
  }

  /* ================= Inline Status Popover & Items Preview Tooltip ================= */
  let activeInlineOrderId = null;

  function closeFloatingPortals() {
    const ism = $('#inlineStatusMenu');
    if (ism) ism.hidden = true;
    activeInlineOrderId = null;
    const ipp = $('#itemsPreviewPop');
    if (ipp) ipp.hidden = true;
  }

  function openInlineStatusMenu(orderId, anchorBtn) {
    const menu = $('#inlineStatusMenu');
    if (!menu || !anchorBtn) return;
    const rowObj = state.rows.find(r => +r.id === +orderId);
    const curStatus = rowObj ? rowObj.status : '';
    activeInlineOrderId = +orderId;

    menu.innerHTML = Object.keys(STATUS).map(k => `
      <button type="button" role="menuitem" data-pick-inline-status="${esc(k)}" class="${curStatus === k ? 'is-current' : ''}">
        <span>${esc(STATUS[k].label)}</span>
        ${curStatus === k ? '<span>✓</span>' : ''}
      </button>
    `).join('');

    menu.hidden = false;
    const rect = anchorBtn.getBoundingClientRect();
    const menuW = menu.offsetWidth || 180;
    const menuH = menu.offsetHeight || 220;

    let top = rect.bottom + 5;
    if (top + menuH > window.innerHeight - 12) {
      top = Math.max(12, rect.top - menuH - 5);
    }
    let left = rect.right - menuW;
    left = Math.max(12, Math.min(window.innerWidth - menuW - 12, left));

    menu.style.top = `${Math.round(top)}px`;
    menu.style.left = `${Math.round(left)}px`;
  }

  $('#inlineStatusMenu')?.addEventListener('click', e => {
    const btn = e.target.closest('[data-pick-inline-status]');
    if (!btn || !activeInlineOrderId) return;
    const targetId = activeInlineOrderId;
    const nextStatus = btn.dataset.pickInlineStatus;
    closeFloatingPortals();
    setStatus(targetId, nextStatus);
  });

  function showItemsPreview(orderId, anchorEl) {
    const pop = $('#itemsPreviewPop');
    const rowObj = state.rows.find(r => +r.id === +orderId);
    if (!pop || !rowObj || !rowObj.itemsPreview || !rowObj.itemsPreview.length) return;

    const listHtml = rowObj.itemsPreview.map(it => `
      <div class="ip-row">
        <span class="mini-th" style="width:24px;height:24px;flex:none">${it.img ? `<img src="${esc(it.img)}" alt="">` : esc(Array.from(it.n || '•')[0] || '•')}</span>
        <b>${esc(it.n || '')}</b>
        <span class="muted num">×${fa(it.q || 1)}</span>
      </div>
    `).join('');

    const extra = (rowObj.qty || 0) > rowObj.itemsPreview.reduce((s, x) => s + (x.q || 0), 0)
      ? `<div class="muted" style="padding-top:4px;font-size:10.5px">به‌همراه سایر اقلام…</div>`
      : '';

    pop.innerHTML = `
      <div class="ip-head"><span>اقلام سفارش #${esc(faId(rowObj.orderNumber || rowObj.id))}</span><span class="num">${fa(rowObj.qty || 0)} قلم</span></div>
      ${listHtml}
      ${extra}
    `;
    pop.hidden = false;

    const rect = anchorEl.getBoundingClientRect();
    const popW = pop.offsetWidth || 285;
    const popH = pop.offsetHeight || 140;
    let top = rect.bottom + 6;
    if (top + popH > window.innerHeight - 12) {
      top = Math.max(12, rect.top - popH - 6);
    }
    let left = rect.left + (rect.width / 2) - (popW / 2);
    left = Math.max(12, Math.min(window.innerWidth - popW - 12, left));

    pop.style.top = `${Math.round(top)}px`;
    pop.style.left = `${Math.round(left)}px`;
  }

  /* ================= Interactive Jalali Date Picker ================= */
  let activeJalaliTarget = null; // { isoInput, displayInput }
  let jpViewYear = 1405;
  let jpViewMonth = 1;

  function openJalaliPicker(displayInput, isoInput) {
    const pop = $('#jalaliPickerPop');
    if (!pop || !displayInput || !isoInput) return;
    activeJalaliTarget = { displayInput, isoInput };

    const curIso = isoInput.value;
    if (curIso && /^\d{4}-\d{2}-\d{2}$/.test(curIso)) {
      const [gy, gm, gd] = curIso.split('-').map(Number);
      [jpViewYear, jpViewMonth] = gregorianToJalali(gy, gm, gd);
    } else {
      const now = new Date();
      [jpViewYear, jpViewMonth] = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    }

    renderJalaliPickerGrid();
    pop.hidden = false;

    const rect = displayInput.getBoundingClientRect();
    const popW = 276;
    const popH = pop.offsetHeight || 300;
    let top = rect.bottom + 6;
    if (top + popH > window.innerHeight - 12) {
      top = Math.max(12, rect.top - popH - 6);
    }
    let left = rect.right - popW;
    left = Math.max(12, Math.min(window.innerWidth - popW - 12, left));
    pop.style.top = `${Math.round(top)}px`;
    pop.style.left = `${Math.round(left)}px`;
  }

  function renderJalaliPickerGrid() {
    const pop = $('#jalaliPickerPop');
    if (!pop || !activeJalaliTarget) return;

    const now = new Date();
    const [tJy, tJm, tJd] = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());

    let selJy = 0, selJm = 0, selJd = 0;
    const curIso = activeJalaliTarget.isoInput.value;
    if (curIso && /^\d{4}-\d{2}-\d{2}$/.test(curIso)) {
      const [gy, gm, gd] = curIso.split('-').map(Number);
      [selJy, selJm, selJd] = gregorianToJalali(gy, gm, gd);
    }

    const daysInMonth = jalaliMonthLength(jpViewYear, jpViewMonth);
    const [fGy, fGm, fGd] = jalaliToGregorian(jpViewYear, jpViewMonth, 1);
    // JS getDay(): 0=Sun..6=Sat -> convert so 0=Sat(شنبه)..6=Fri(جمعه)
    const startOffset = (new Date(fGy, fGm - 1, fGd).getDay() + 1) % 7;

    let cells = '';
    for (let i = 0; i < startOffset; i++) {
      cells += `<span class="jp-empty"></span>`;
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const isToday = (jpViewYear === tJy && jpViewMonth === tJm && d === tJd);
      const isSel = (jpViewYear === selJy && jpViewMonth === selJm && d === selJd);
      cells += `<button type="button" class="jp-day num ${isToday ? 'is-today' : ''} ${isSel ? 'is-selected' : ''}" data-jp-day="${d}">${fa(d)}</button>`;
    }

    pop.innerHTML = `
      <div class="jp-head">
        <div class="jp-nav">
          <button type="button" data-jp-nav="-1" title="ماه قبل">‹</button>
        </div>
        <div class="jp-title num">${JALALI_MONTHS[jpViewMonth - 1]} ${faId(jpViewYear)}</div>
        <div class="jp-nav">
          <button type="button" data-jp-nav="1" title="ماه بعد">›</button>
        </div>
      </div>
      <div class="jp-weekdays">
        <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
      </div>
      <div class="jp-days">${cells}</div>
      <div class="jp-foot">
        <button type="button" class="btn" data-jp-clear>پاک کردن</button>
        <button type="button" class="btn primary" data-jp-today>امروز</button>
      </div>
    `;
  }

  $('#jalaliPickerPop')?.addEventListener('click', e => {
    e.stopPropagation();
    const nav = e.target.closest('[data-jp-nav]');
    if (nav) {
      const delta = +nav.dataset.jpNav;
      jpViewMonth += delta;
      if (jpViewMonth > 12) { jpViewMonth = 1; jpViewYear++; }
      else if (jpViewMonth < 1) { jpViewMonth = 12; jpViewYear--; }
      renderJalaliPickerGrid();
      return;
    }
    const dayBtn = e.target.closest('[data-jp-day]');
    if (dayBtn && activeJalaliTarget) {
      const jd = +dayBtn.dataset.jpDay;
      const [gy, gm, gd] = jalaliToGregorian(jpViewYear, jpViewMonth, jd);
      const iso = `${gy}-${pad2(gm)}-${pad2(gd)}`;
      activeJalaliTarget.isoInput.value = iso;
      activeJalaliTarget.displayInput.value = faId(`${jpViewYear}/${pad2(jpViewMonth)}/${pad2(jd)}`);
      $('#jalaliPickerPop').hidden = true;
      activeJalaliTarget = null;
      return;
    }
    if (e.target.closest('[data-jp-today]') && activeJalaliTarget) {
      const iso = localTodayIso(0);
      activeJalaliTarget.isoInput.value = iso;
      activeJalaliTarget.displayInput.value = isoToJalaliDisplay(iso);
      $('#jalaliPickerPop').hidden = true;
      activeJalaliTarget = null;
      return;
    }
    if (e.target.closest('[data-jp-clear]') && activeJalaliTarget) {
      activeJalaliTarget.isoInput.value = '';
      activeJalaliTarget.displayInput.value = '';
      $('#jalaliPickerPop').hidden = true;
      activeJalaliTarget = null;
    }
  });

  $('#fltDateFromDisplay')?.addEventListener('click', e => {
    e.stopPropagation();
    openJalaliPicker($('#fltDateFromDisplay'), $('#fltDateFrom'));
  });
  $('#fltDateToDisplay')?.addEventListener('click', e => {
    e.stopPropagation();
    openJalaliPicker($('#fltDateToDisplay'), $('#fltDateTo'));
  });

  /* ================= Stats / KPIs ================= */
  function formatPctBadge(el, current, previous) {
    if (!el) return;
    const cur = Number(current) || 0;
    const prev = Number(previous) || 0;
    if (prev === 0 && cur === 0) {
      el.className = 'delta up';
      el.innerHTML = `۰٪`;
      return;
    }
    const diff = cur - prev;
    const pct = prev > 0 ? Math.round((diff / prev) * 100) : 100;
    const up = diff >= 0;
    el.className = 'delta ' + (up ? 'up' : 'down');
    el.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${up ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6'}"/></svg>${fa(Math.abs(pct))}٪`;
  }

  function renderStats(stats) {
    if (!stats) return;
    state.stats = stats;
    if (stats.latestOrderId) checkNewOrderArrival(stats.latestOrderId);
    if (stats.statusCounts) {
      state.statusCounts = stats.statusCounts;
      renderChips();
      const navCount = document.getElementById('navOrdersCount');
      if (navCount) navCount.textContent = fa(stats.statusCounts.all || 0);
    }

    const todayCountEl = document.querySelector('#section-orders .kpi[data-tone="rose"] .kpi-value');
    if (todayCountEl) todayCountEl.innerHTML = `${fa(stats.todayCount || 0)} <small>سفارش</small>`;
    formatPctBadge(
      document.querySelector('#section-orders .kpi[data-tone="rose"] .delta'),
      stats.todayCount,
      stats.yesterdayCount
    );

    const salesEl = document.querySelector('#section-orders .kpi[data-tone="emerald"] .kpi-value');
    if (salesEl) salesEl.innerHTML = `${fa(Math.round(stats.todaySales || 0))} <small>${esc(CURRENCY)}</small>`;
    formatPctBadge(
      document.querySelector('#section-orders .kpi[data-tone="emerald"] .delta'),
      stats.todaySales,
      stats.yesterdaySales
    );

    const pendingEl = document.querySelector('#section-orders .kpi[data-tone="amber"] .kpi-value');
    if (pendingEl) pendingEl.innerHTML = `${fa(stats.pendingCount || 0)} <small>سفارش</small>`;
    const urgent = stats.urgentCount || 0;
    const deltaUrgent = document.querySelector('#section-orders .kpi[data-tone="amber"] .delta');
    if (deltaUrgent) {
      const hasUrgent = urgent > 0;
      deltaUrgent.className = 'delta ' + (hasUrgent ? 'down' : 'up');
      deltaUrgent.innerHTML = hasUrgent
        ? `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5l3 2"/></svg>${fa(urgent)} فوری`
        : `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>بدون تأخیر`;
    }

    const promoB = document.querySelector('#sidebarPromo b');
    const promoP = document.querySelector('#sidebarPromo p');
    if (promoB) promoB.textContent = urgent > 0 ? `${fa(urgent)} سفارش نیازمند بررسی` : (stats.pendingCount > 0 ? `${fa(stats.pendingCount)} سفارش در جریان` : 'همه سفارش‌ها بررسی شده‌اند');
    if (promoP) promoP.textContent = urgent > 0 ? 'بیش از ۲۴ ساعت در انتظار اقدام مانده‌اند.' : 'سفارش فوریِ عقب‌افتاده‌ای وجود ندارد.';

    const aovEl = document.querySelector('#section-orders .kpi[data-tone="indigo"] .kpi-value');
    if (aovEl) aovEl.innerHTML = `${fa(Math.round(stats.aov30 || 0))} <small>${esc(CURRENCY)}</small>`;
    formatPctBadge($('#aovDelta'), stats.aov30, stats.prevAov30);

    renderKpiCharts();
  }

  async function fetchStats() {
    try {
      const data = await api('sefareshban_get_stats', {});
      renderStats(data);
    } catch (e) {
      console.error(e);
      renderKpiCharts();
    }
  }

  /* ================= Order Detail Drawer ================= */
  function openOrder(id) {
    closeFloatingPortals();
    state.openId = +id;
    state.openDetail = null;
    $('#drawer').innerHTML = `
      <div class="d-head">
        <div>
          <div class="t1" id="drawerTitle">سفارش <span class="oid num"><span>#</span>${faId(id)}</span></div>
          <div class="t2">در حال بارگذاری…</div>
        </div>
        <button type="button" class="circle" data-close aria-label="بستن">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="d-body"><div style="padding:24px;color:var(--muted);font-size:13px">در حال دریافت جزئیات سفارش…</div></div>`;
    $('#drawer').classList.add('open');
    $('#scrim').classList.add('show');

    api('sefareshban_get_order', { order_id: id }).then(data => {
      if (state.openId !== +id) return;
      state.openDetail = data.order;
      renderDrawer(data.order);
    }).catch(e => {
      console.error(e);
      if (state.openId !== +id) return;
      $('#drawer').innerHTML = `
        <div class="d-head">
          <div>
            <div class="t1" id="drawerTitle">سفارش <span class="oid num"><span>#</span>${faId(id)}</span></div>
            <div class="t2" style="color:var(--danger)">خطا در دریافت اطلاعات</div>
          </div>
          <button type="button" class="circle" data-close aria-label="بستن">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
          </button>
        </div>
        <div class="d-body"><div style="padding:24px;color:var(--muted)">${esc(e.message || 'خطا')}</div></div>`;
    });
  }

  function renderDrawer(o) {
    state.openId = o.id;
    state.openDetail = o;
    const st = STATUS[o.status] || STATUS_META[o.status] || { label: o.statusLabel || o.status };
    const orderNum = o.orderNumber || o.id;

    const notesHtml = (o.notes || []).map(n => `
      <div class="tl ${n.sys ? 'sys' : ''}">
        <b>${esc(n.by || '')} ${n.customer ? '<span class="badge" data-status="processing" style="height:18px;font-size:10px;padding:0 6px;margin-inline-start:4px">ارسالی به مشتری</span>' : ''}</b>
        <p>${esc(n.t || '')}</p>
        <time>${esc(n.at || '')}</time>
      </div>`).join('');

    const itemsHtml = (o.items || []).map(i => `
      <div class="item">
        <div class="thumb">${i.img ? `<img src="${esc(i.img)}" alt="" style="width:100%;height:100%;object-fit:cover">` : esc(i.c || '•')}</div>
        <div class="nm">
          <b>${esc(i.n || '')}</b>
          <span class="num">${fa(i.q || 0)} × ${money(i.p || 0)}${i.sku ? ` · ${esc(i.sku)}` : ''}</span>
        </div>
        <div class="pr num">${money(i.t ?? ((i.q || 0) * (i.p || 0)))}</div>
      </div>`).join('');

    const feesHtml = (o.fees || []).map(f => `
      <div class="r"><span>${esc(f.name || 'کارمزد / تخفیف')}</span><b class="num" style="${f.total < 0 ? 'color:var(--ok)' : ''}">${f.total < 0 ? '− ' + money(Math.abs(f.total)) : money(f.total)}</b></div>
    `).join('');

    const opts = Object.keys(STATUS).map(k =>
      `<option value="${esc(k)}" ${o.status === k ? 'selected' : ''}>${esc(STATUS[k].label)}</option>`
    ).join('');

    const carriers = ['پست پیشتاز', 'پست سفارشی', 'تیپاکس', 'چاپار', 'ماهکس', 'پیک موتوری', 'باربری', 'سایر'];
    const curCarrier = o.trackingCarrier || 'پست پیشتاز';
    const carrierOpts = carriers.map(c =>
      `<option value="${esc(c)}" ${curCarrier === c ? 'selected' : ''}>${esc(c)}</option>`
    ).join('');

    const orderCount = o.orderCount ? `${fa(o.orderCount)} سفارش ثبت‌شده` : 'مشتری';
    const avatarChar = Array.from(o.name || 'م')[0] || 'م';
    const fullShipCopy = [
      `گیرنده: ${o.name || '—'}`,
      o.phone ? `تلفن: ${o.phone}` : '',
      o.addr ? `نشانی: ${o.addr}` : '',
      o.postcode ? `کد پستی: ${o.postcode}` : ''
    ].filter(Boolean).join('\n');

    $('#drawer').innerHTML = `
      <div class="d-head">
        <div>
          <div class="t1" id="drawerTitle">
            سفارش <span class="oid num"><span>#</span>${esc(faId(orderNum))}</span>
            ${o.isDelayed ? `<span class="row-ind delay-ind">تأخیر +۲۴ ساعت</span>` : ''}
          </div>
          <div class="t2 num">${esc(o.date || '')} — ${esc(o.time || '')} · ثبت از ${esc(o.channel || 'وب‌سایت')}</div>
        </div>
        <button type="button" class="circle" data-close aria-label="بستن">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>

      <div class="d-body">
        ${o.status === 'trash' ? `
          <div class="cust-note-box" style="background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px">
            <span>این سفارش در زباله‌دان قرار دارد.</span>
            <button type="button" class="btn" style="height:28px;font-size:11.5px" data-restore-drawer="${o.id}">بازیابی سفارش</button>
          </div>
        ` : ''}

        <section class="d-sec" style="padding-top:4px">
          <div class="status-row">
            <span class="badge" data-status="${esc(o.status)}" id="dStatus">${esc(st.label)}</span>
            <div class="select" style="margin-inline-start:auto;min-width:155px;max-width:185px">
              <select id="statusSelect" aria-label="تغییر وضعیت سفارش">${opts}</select>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </div>
            <button type="button" class="icon-btn" data-print="${o.id}" title="چاپ فاکتور فروش" aria-label="چاپ فاکتور">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4v-7h16v7h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/></svg>
            </button>
            <button type="button" class="icon-btn" data-print-label="${o.id}" title="چاپ برچسب پستی (لیبل ارسال)" aria-label="چاپ برچسب پستی">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h6M7 13h10"/></svg>
            </button>
          </div>
        </section>

        <section class="d-sec">
          <div class="d-label">
            <span>اطلاعات مشتری</span>
            ${o.editUrl ? `<a href="${esc(o.editUrl)}" target="_blank" rel="noopener">ویرایش کامل در ووکامرس ↗</a>` : ''}
          </div>
          <div class="person">
            <span class="av">${esc(avatarChar)}</span>
            <div>
              <b>${esc(o.name || '')}</b>
              <span>${esc(orderCount)}</span>
            </div>
          </div>
          <div class="contact">
            <div>
              <span>موبایل ${o.phone ? `<button type="button" class="copy-btn" data-copy-val="${esc(o.phone)}" data-copy-lbl="شماره موبایل">کپی</button>` : ''}</span>
              <b class="num">${o.phone ? `<a href="tel:${esc(o.phone)}" style="color:inherit;text-decoration:none">${esc(faId(o.phone))}</a>` : '—'}</b>
            </div>
            <div>
              <span>ایمیل ${o.email ? `<button type="button" class="copy-btn" data-copy-val="${esc(o.email)}" data-copy-lbl="ایمیل">کپی</button>` : ''}</span>
              <b dir="ltr" style="text-align:right">${esc(o.email || '—')}</b>
            </div>
          </div>
        </section>

        <section class="d-sec">
          <div class="d-label">
            <span>آدرس و اطلاعات ارسال</span>
            <button type="button" class="copy-btn" data-copy-val="${esc(fullShipCopy)}" data-copy-lbl="مشخصات کامل گیرنده">کپی کامل نشانی</button>
          </div>
          <div class="addr">${esc(o.addr || 'آدرسی ثبت نشده است')}</div>
          ${o.postcode ? `
            <div style="margin-top:6px;display:flex;align-items:center;gap:8px;font-size:12px">
              <span class="muted">کد پستی:</span>
              <b class="num">${esc(faId(o.postcode))}</b>
              <button type="button" class="copy-btn" data-copy-val="${esc(o.postcode)}" data-copy-lbl="کد پستی">کپی کد پستی</button>
            </div>
          ` : ''}
          <div class="muted" style="margin-top:6px">
            روش پرداخت: <b style="color:var(--ink-2)">${esc(o.pay || '—')}</b>
            ${o.shippingMethod && o.shippingMethod !== '—' ? ` · روش ارسال: <b style="color:var(--ink-2)">${esc(o.shippingMethod)}</b>` : ''}
            ${o.transactionId ? ` · تراکنش: <b class="num" style="color:var(--ink-2)">${esc(o.transactionId)}</b>` : ''}
          </div>
          ${o.customerNote ? `<div class="cust-note-box"><b>یادداشت مشتری هنگام خرید:</b> ${esc(o.customerNote)}</div>` : ''}
        </section>

        <section class="d-sec">
          <div class="d-label">
            <span>کد رهگیری مرسوله (پست / تیپاکس / پیک)</span>
            ${o.trackingCode ? `<button type="button" class="copy-btn" data-copy-val="${esc(o.trackingCode)}" data-copy-lbl="کد رهگیری">کپی رهگیری</button>` : ''}
          </div>
          <form class="tracking-box" id="trackingForm">
            <div class="tracking-row">
              <select id="trkCarrier" class="sb-ctl-input" style="flex:0 0 130px">${carrierOpts}</select>
              <input type="text" id="trkCode" class="sb-ctl-input num" dir="ltr" style="text-align:right" placeholder="کد رهگیری ۲۴ رقمی یا بارکد…" value="${esc(o.trackingCode || '')}">
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
              <div style="display:flex;flex-direction:column;gap:4px">
                <label class="note-type-lbl"><input type="checkbox" id="trkNotify" checked> ارسال کد رهگیری در یادداشت مشتری</label>
                <label class="note-type-lbl"><input type="checkbox" id="trkComplete" ${o.status !== 'completed' ? 'checked' : ''}> تغییر وضعیت سفارش به «تکمیل‌شده»</label>
              </div>
              <button type="submit" class="btn primary" style="height:32px;padding:0 12px">ثبت رهگیری</button>
            </div>
          </form>
        </section>

        <section class="d-sec">
          <div class="d-label">اقلام سفارش <span class="num" style="letter-spacing:0">${fa(o.qty || 0)} قلم</span></div>
          <div class="items">${itemsHtml || '<div class="muted">قلمی یافت نشد</div>'}</div>
        </section>

        <section class="d-sec">
          <div class="totals">
            <div class="r"><span>جمع اقلام</span><b class="num">${money(o.subtotal || 0)}</b></div>
            <div class="r"><span>هزینه ارسال</span><b class="num">${(o.ship || 0) == 0 ? (i18n.free || 'رایگان') : money(o.ship)}</b></div>
            ${feesHtml}
            ${(o.tax || 0) > 0 ? `<div class="r"><span>مالیات</span><b class="num">${money(o.tax)}</b></div>` : ''}
            ${(o.disc || 0) > 0 ? `<div class="r"><span>تخفیف کوپن</span><b class="num" style="color:var(--ok)">− ${money(o.disc)}</b></div>` : ''}
            ${(o.refunded || 0) > 0 ? `<div class="r"><span>مبلغ مستردشده</span><b class="num" style="color:var(--danger)">− ${money(o.refunded)}</b></div>` : ''}
            <div class="r grand"><span>مبلغ کل سفارش</span><b class="num">${money(o.total || 0)}</b></div>
          </div>

          ${(o.remainingRefund || 0) > 0 ? `
            <div style="margin-top:10px">
              <button type="button" class="link-btn" id="toggleRefundBtn">ثبت استرداد وجه (Refund) جزئی یا کامل ▾</button>
              <form class="refund-box" id="refundForm" hidden>
                <div style="display:flex;gap:6px;align-items:center">
                  <input type="number" id="refAmount" class="sb-ctl-input num" min="1" max="${Math.round(o.remainingRefund)}" step="1000" placeholder="مبلغ استرداد (${esc(CURRENCY)})" required>
                  <button type="button" class="btn" id="fillMaxRefundBtn" data-max="${Math.round(o.remainingRefund)}" style="height:36px;white-space:nowrap;font-size:11.5px">کل مبلغ</button>
                </div>
                <input type="text" id="refReason" class="sb-ctl-input" placeholder="علت استرداد (اختیاری)…">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                  <label class="note-type-lbl"><input type="checkbox" id="refRestock" checked> بازگرداندن اقلام به انبار</label>
                  <button type="submit" class="btn primary" style="height:32px">ثبت استرداد</button>
                </div>
              </form>
            </div>
          ` : ''}
        </section>

        <section class="d-sec">
          <div class="d-label">یادداشت‌ها و تاریخچه</div>
          <div class="timeline">${notesHtml || '<div class="muted" style="font-size:12px">یادداشتی ثبت نشده است.</div>'}</div>
          <form class="note-form" id="noteForm">
            <div class="note-form-row">
              <label class="field" for="noteInput">
                <input type="text" id="noteInput" placeholder="ثبت یادداشت جدید برای این سفارش…" autocomplete="off" required>
              </label>
              <button class="btn primary" type="submit">ثبت</button>
            </div>
            <label class="note-type-lbl">
              <input type="checkbox" id="noteToCustomer"> ارسال یادداشت برای مشتری (ایمیل/پیامک ووکامرس)
            </label>
          </form>
        </section>
      </div>

      <div class="d-foot">
        <button type="button" class="btn" data-open-full-page="${o.id}">صفحه اختصاصی سفارش</button>
        <a class="btn primary" href="${esc(getNativeWcOrderUrl(o))}">صفحه سفارش ووکامرس ↗</a>
      </div>`;

    $('#drawer').classList.add('open');
    $('#scrim').classList.add('show');
  }

  function closeDrawer() {
    $('#drawer')?.classList.remove('open');
    $('#scrim')?.classList.remove('show');
    state.openId = null;
    state.openDetail = null;
  }

  /* ================= Dedicated Single Order Page (#section-order-detail) ================= */
  const LIFECYCLE_STEPS = [
    { k: 'pending', l: 'در انتظار پرداخت' },
    { k: 'onhold', l: 'در انتظار بررسی' },
    { k: 'processing', l: 'در حال آماده‌سازی' },
    { k: 'completed', l: 'تکمیل و ارسال‌شده' }
  ];

  function getNativeWcOrderUrl(o) {
    if (o && o.editUrl) return o.editUrl;
    const oid = (o && o.id) ? o.id : '';
    return (SB.adminUrl || '/wp-admin/') + `post.php?post=${encodeURIComponent(oid)}&action=edit&sefareshban_native=1`;
  }

  function syncOrderUrl(orderId, push = true) {
    if (typeof window === 'undefined' || !window.history || typeof window.history.pushState !== 'function') return;
    try {
      const url = new URL(window.location.href);
      if (orderId) {
        url.searchParams.set('order_id', String(orderId));
      } else {
        url.searchParams.delete('order_id');
      }
      if (url.href !== window.location.href) {
        const stObj = { section: orderId ? 'order-detail' : state.section, orderId: orderId ? +orderId : null };
        if (push) window.history.pushState(stObj, '', url.href);
        else window.history.replaceState(stObj, '', url.href);
      }
    } catch (e) {
      /* ignore URL parsing in non-standard environments */
    }
  }

  function syncOrderPageNavButtons() {
    const prevBtn = $('#odPrevOrderBtn');
    const nextBtn = $('#odNextOrderBtn');
    if (!prevBtn || !nextBtn || !state.pageOrderId) return;
    const idx = state.rows.findIndex(r => +r.id === +state.pageOrderId);
    if (idx > 0) {
      prevBtn.disabled = false;
      prevBtn.dataset.odOpen = String(state.rows[idx - 1].id);
      prevBtn.title = `سفارش قبلی (#${faId(state.rows[idx - 1].orderNumber || state.rows[idx - 1].id)})`;
    } else {
      prevBtn.disabled = true;
      delete prevBtn.dataset.odOpen;
    }
    if (idx >= 0 && idx < state.rows.length - 1) {
      nextBtn.disabled = false;
      nextBtn.dataset.odOpen = String(state.rows[idx + 1].id);
      nextBtn.title = `سفارش بعدی (#${faId(state.rows[idx + 1].orderNumber || state.rows[idx + 1].id)})`;
    } else {
      nextBtn.disabled = true;
      delete nextBtn.dataset.odOpen;
    }
  }

  let orderPageReqSeq = 0;

  function openOrderPage(id, pushHistory = true, preloadedOrder = null) {
    const oid = Number(id) || 0;
    if (!oid) return;
    closeFloatingPortals();
    closeDrawer();

    state.section = 'order-detail';
    state.pageOrderId = oid;
    state.pageOrderDetail = preloadedOrder || null;

    $$('.app-section').forEach(s => s.classList.toggle('active', s.id === 'section-order-detail'));
    $$('[data-section]').forEach(a => {
      if (a.dataset.section === 'orders') a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    updateBulkBar();

    const crumb = document.querySelector('.crumbs .crumb-text');
    if (crumb) crumb.textContent = `سفارش #${faId(oid)}`;

    if (pushHistory !== null) {
      syncOrderUrl(oid, !!pushHistory);
    }

    document.querySelector('.content')?.scrollTo({ top: 0, behavior: 'smooth' });

    const host = $('#orderPageHost');
    if (!host) return;

    if (preloadedOrder && +preloadedOrder.id === oid) {
      renderOrderPage(preloadedOrder);
      return;
    }

    const reqId = ++orderPageReqSeq;
    host.innerHTML = `
      <div class="od-top">
        <div class="od-title-wrap">
          <div class="od-back-row">
            <button type="button" class="od-back-btn" data-od-back>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              بازگشت به لیست سفارش‌ها
            </button>
          </div>
          <h1 class="od-h1">سفارش <span class="oid num"><span>#</span>${faId(oid)}</span></h1>
          <div class="od-submeta">در حال بارگذاری جزئیات کامل سفارش…</div>
        </div>
      </div>
      <div class="od-card" style="padding:36px;text-align:center;color:var(--muted)">در حال دریافت اطلاعات سفارش از ووکامرس…</div>
    `;

    api('sefareshban_get_order', { order_id: oid }).then(data => {
      if (reqId !== orderPageReqSeq || state.pageOrderId !== oid) return;
      state.pageOrderDetail = data.order;
      renderOrderPage(data.order);
    }).catch(e => {
      console.error(e);
      if (reqId !== orderPageReqSeq || state.pageOrderId !== oid) return;
      const fallbackWcUrl = getNativeWcOrderUrl({ id: oid });
      host.innerHTML = `
        <div class="od-top">
          <div class="od-title-wrap">
            <div class="od-back-row">
              <button type="button" class="od-back-btn" data-od-back>→ بازگشت به لیست سفارش‌ها</button>
            </div>
            <h1 class="od-h1">سفارش <span class="oid num"><span>#</span>${faId(oid)}</span></h1>
          </div>
        </div>
        <div class="od-card" style="padding:32px;text-align:center">
          <div style="color:var(--danger);font-weight:700;font-size:15px;margin-bottom:8px">${esc(e.message || 'خطا در دریافت اطلاعات سفارش')}</div>
          <div class="muted" style="margin-bottom:16px">ممکن است سفارش حذف شده باشد یا نوع آن غیرمعمول باشد.</div>
          <div style="display:inline-flex;gap:8px;flex-wrap:wrap;justify-content:center">
            <button type="button" class="btn" data-od-back>بازگشت به سفارش‌ها</button>
            <a class="btn wc-native-btn" href="${esc(fallbackWcUrl)}">بررسی در صفحه سفارش ووکامرس ↗</a>
          </div>
        </div>
      `;
    });
  }

  function renderOrderPage(o) {
    const host = $('#orderPageHost');
    if (!host || !o) return;
    state.pageOrderId = +o.id;
    state.pageOrderDetail = o;

    const orderNum = o.orderNumber || o.id;
    const crumb = document.querySelector('.crumbs .crumb-text');
    if (crumb) crumb.textContent = `سفارش #${faId(orderNum)}`;

    const st = STATUS[o.status] || STATUS_META[o.status] || { label: o.statusLabel || o.status };
    const nativeWcUrl = getNativeWcOrderUrl(o);

    // Stepper HTML
    const curStepIdx = LIFECYCLE_STEPS.findIndex(s => s.k === o.status);
    const stepperHtml = LIFECYCLE_STEPS.map((s, idx) => {
      let cls = '';
      let dotTxt = fa(idx + 1);
      if (o.status === 'completed') {
        cls = 'is-done';
        dotTxt = '✓';
      } else if (curStepIdx >= 0) {
        if (idx < curStepIdx) { cls = 'is-done'; dotTxt = '✓'; }
        else if (idx === curStepIdx) { cls = 'is-active'; }
      }
      return `
        <button type="button" class="od-step ${cls}" data-od-step="${esc(s.k)}" title="تغییر سریع وضعیت به «${esc(s.l)}»">
          <span class="st-dot num">${dotTxt}</span>
          <span>${esc(s.l)}</span>
        </button>
      `;
    }).join('');

    // Items Table Rows
    const itemsRowsHtml = (o.items || []).map(it => {
      const metaPills = [];
      if (it.sku) metaPills.push(`<span class="od-meta-pill num">SKU: ${esc(it.sku)}</span>`);
      (it.meta || []).forEach(m => {
        metaPills.push(`<span class="od-meta-pill">${esc(m.k)}: ${esc(m.v)}</span>`);
      });
      if (it.viewUrl) {
        metaPills.push(`<a class="od-meta-pill" href="${esc(it.viewUrl)}" target="_blank" rel="noopener" style="text-decoration:none;color:var(--accent-ink)">نمایش در سایت ↗</a>`);
      }
      const titleHtml = it.editUrl
        ? `<a class="od-prod-title" href="${esc(it.editUrl)}" target="_blank" rel="noopener">${esc(it.n || 'محصول')}</a>`
        : `<span class="od-prod-title">${esc(it.n || 'محصول')}</span>`;
      const lineTotal = it.t ?? ((it.q || 0) * (it.p || 0));
      const hasLineDisc = (it.sub || 0) > lineTotal + 1;

      return `
        <tr>
          <td>
            <div class="od-prod-cell">
              <div class="od-prod-thumb">${it.img ? `<img src="${esc(it.img)}" alt="">` : esc(it.c || '•')}</div>
              <div>
                ${titleHtml}
                ${metaPills.length ? `<div class="od-prod-meta">${metaPills.join('')}</div>` : ''}
              </div>
            </div>
          </td>
          <td class="num">${money(it.p || 0)}</td>
          <td class="num" style="font-weight:700">× ${fa(it.q || 0)}</td>
          <td class="num" style="text-align:left">
            ${hasLineDisc ? `<div class="muted" style="text-decoration:line-through;font-size:11px">${money(it.sub)}</div>` : ''}
            <b style="font-size:13.5px">${money(lineTotal)}</b>
          </td>
        </tr>
      `;
    }).join('');

    // Fees & Coupons
    const feesHtml = (o.fees || []).map(f => `
      <div class="od-kv">
        <span>${esc(f.name || 'کارمزد / تخفیف')}</span>
        <b class="num" style="${f.total < 0 ? 'color:var(--ok)' : ''}">${f.total < 0 ? '− ' + money(Math.abs(f.total)) : money(f.total)}</b>
      </div>
    `).join('');

    const couponsBadges = (o.coupons || []).map(c =>
      `<span class="badge num" data-status="completed" style="font-size:11px">کوپن: ${esc(c)}</span>`
    ).join(' ');

    // Structured Billing & Shipping
    const b = o.billing || {};
    const s = o.shipping || {};
    const bFullName = [b.firstName, b.lastName].filter(Boolean).join(' ') || o.name || '—';
    const bAddr = [b.stateName || b.state, b.city, b.address1, b.address2].filter(Boolean).join('، ') || o.addr || 'آدرسی ثبت نشده است';
    const bPostcode = b.postcode || o.postcode || '';

    const sFullName = [s.firstName, s.lastName].filter(Boolean).join(' ') || bFullName;
    const sAddr = [s.stateName || s.state, s.city, s.address1, s.address2].filter(Boolean).join('، ') || bAddr;
    const sPostcode = s.postcode || bPostcode;

    const fullShipCopy = [
      `گیرنده: ${sFullName}`,
      (b.phone || o.phone) ? `تلفن: ${b.phone || o.phone}` : '',
      sAddr ? `نشانی: ${sAddr}` : '',
      sPostcode ? `کد پستی: ${sPostcode}` : ''
    ].filter(Boolean).join('\n');

    // State options for inline edit form
    const curStateCode = b.state || s.state || '';
    const hasStateInList = iranStates.some(stItem => stItem.code === curStateCode);
    const stateOptionsHtml = [
      `<option value="">انتخاب استان…</option>`,
      ...(!hasStateInList && curStateCode ? [`<option value="${esc(curStateCode)}" selected>${esc(b.stateName || curStateCode)}</option>`] : []),
      ...iranStates.map(stItem => `<option value="${esc(stItem.code)}" ${stItem.code === curStateCode ? 'selected' : ''}>${esc(stItem.name)}</option>`)
    ].join('');

    const editFirstName = b.firstName !== undefined && (b.firstName || b.lastName)
      ? b.firstName
      : (o.name || '').split(/\s+/)[0] || '';
    const editLastName = b.lastName !== undefined && (b.firstName || b.lastName)
      ? b.lastName
      : (o.name || '').split(/\s+/).slice(1).join(' ') || '';

    // Status Select & Carrier Select
    const statusOptsHtml = Object.keys(STATUS).map(k =>
      `<option value="${esc(k)}" ${o.status === k ? 'selected' : ''}>${esc(STATUS[k].label)}</option>`
    ).join('');

    const carriers = ['پست پیشتاز', 'پست سفارشی', 'تیپاکس', 'چاپار', 'ماهکس', 'پیک موتوری', 'باربری', 'سایر'];
    const curCarrier = o.trackingCarrier || 'پست پیشتاز';
    const carrierOpts = carriers.map(c =>
      `<option value="${esc(c)}" ${curCarrier === c ? 'selected' : ''}>${esc(c)}</option>`
    ).join('');

    // Notes Timeline
    const notesHtml = (o.notes || []).map(n => `
      <div class="tl ${n.sys ? 'sys' : ''}">
        <div class="tl-head">
          <b>${esc(n.by || '')} ${n.customer ? '<span class="badge" data-status="processing" style="height:18px;font-size:10px;padding:0 6px;margin-inline-start:4px">ارسالی به مشتری</span>' : ''}</b>
          ${n.id ? `<button type="button" class="tl-del" data-od-del-note="${n.id}" title="حذف این یادداشت">×</button>` : ''}
        </div>
        <p>${esc(n.t || '')}</p>
        <time>${esc(n.at || '')}</time>
      </div>
    `).join('');

    const avatarChar = Array.from(o.name || 'م')[0] || 'م';
    const custSearchKey = o.email || o.phone || o.name || '';

    host.innerHTML = `
      <div class="od-top">
        <div class="od-title-wrap">
          <div class="od-back-row">
            <button type="button" class="od-back-btn" data-od-back>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              بازگشت به لیست سفارش‌ها
            </button>
            <div class="od-nav-pair">
              <button type="button" id="odPrevOrderBtn" disabled aria-label="سفارش قبلی">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
              </button>
              <button type="button" id="odNextOrderBtn" disabled aria-label="سفارش بعدی">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
              </button>
            </div>
          </div>

          <h1 class="od-h1">
            <span>سفارش <span class="oid num"><span>#</span>${esc(faId(orderNum))}</span></span>
            <span class="badge" data-status="${esc(o.status)}">${esc(st.label)}</span>
            ${o.isDelayed ? `<span class="row-ind delay-ind">تأخیر +${fa(o.delayHours || 24)} ساعت</span>` : ''}
            ${o.customerNote ? `<span class="row-ind note-ind">دارای یادداشت خریدار</span>` : ''}
          </h1>

          <div class="od-submeta">
            <span class="num">ثبت‌شده در ${esc(o.date || '')} ساعت ${esc(o.time || '')}</span>
            <span>·</span>
            <span>کانال ثبت: <b style="color:var(--ink-2)">${esc(o.channel || 'وب‌سایت')}</b></span>
            ${o.datePaid ? `<span>·</span><span class="num">پرداخت: ${esc(o.datePaid)}</span>` : ''}
            ${o.customerIp ? `<span>·</span><span>IP: <b class="num" dir="ltr" style="color:var(--ink-2)">${esc(o.customerIp)}</b></span>` : ''}
          </div>
        </div>

        <div class="od-acts">
          <button type="button" class="btn" data-od-print="${o.id}">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4v-7h16v7h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/></svg>
            چاپ فاکتور
          </button>
          <button type="button" class="btn" data-od-label="${o.id}">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h6M7 13h10"/></svg>
            برچسب پستی
          </button>
          <a class="btn wc-native-btn" href="${esc(nativeWcUrl)}" title="مشاهده و ویرایش کامل در صفحه سفارش پیش‌فرض خود ووکامرس">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
            باز کردن در صفحه سفارش ووکامرس
          </a>
        </div>
      </div>

      ${o.status === 'trash' ? `
        <div class="cust-note-box" style="background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;padding:12px 16px;border-radius:14px">
          <b>این سفارش هم‌اکنون در زباله‌دان قرار دارد.</b>
          <button type="button" class="btn" data-od-restore="${o.id}">بازیابی سفارش از زباله‌دان</button>
        </div>
      ` : `
        <div class="od-stepper" role="group" aria-label="مراحل پردازش سفارش">
          ${stepperHtml}
        </div>
      `}

      <div class="od-layout">
        <!-- MAIN COLUMN -->
        <div class="od-col">
          <!-- Card 1: Order Items & Totals -->
          <section class="od-card">
            <div class="od-card-head">
              <h3>اقلام سفارش (${fa(o.qty || 0)} قلم)</h3>
              <div>${couponsBadges}</div>
            </div>
            <div class="table-scroll">
              <table class="od-items-table">
                <thead>
                  <tr>
                    <th style="text-align:right">شرح محصول و ویژگی‌ها</th>
                    <th style="width:130px">فی واحد</th>
                    <th style="width:90px">تعداد</th>
                    <th style="width:150px;text-align:left">مبلغ کل</th>
                  </tr>
                </thead>
                <tbody>
                  ${itemsRowsHtml || '<tr><td colspan="4" class="muted" style="text-align:center;padding:24px">قلمی برای این سفارش ثبت نشده است.</td></tr>'}
                </tbody>
              </table>
            </div>

            <div class="od-totals-wrap">
              <div class="od-totals-box">
                <div class="od-kv"><span>جمع کل اقلام</span><b class="num">${money(o.subtotal || 0)}</b></div>
                <div class="od-kv">
                  <span>هزینه ارسال ${o.shippingMethod && o.shippingMethod !== '—' ? `<small class="muted">(${esc(o.shippingMethod)})</small>` : ''}</span>
                  <b class="num">${(o.ship || 0) == 0 ? (i18n.free || 'رایگان') : money(o.ship)}</b>
                </div>
                ${feesHtml}
                ${(o.tax || 0) > 0 ? `<div class="od-kv"><span>مالیات و عوارض</span><b class="num">${money(o.tax)}</b></div>` : ''}
                ${(o.disc || 0) > 0 ? `<div class="od-kv"><span>تخفیف کوپن</span><b class="num" style="color:var(--ok)">− ${money(o.disc)}</b></div>` : ''}
                ${(o.refunded || 0) > 0 ? `<div class="od-kv"><span>مبلغ مستردشده</span><b class="num" style="color:var(--danger)">− ${money(o.refunded)}</b></div>` : ''}
                <div class="od-kv" style="border-top:1px dashed var(--border);margin-top:4px;padding-top:10px">
                  <span style="font-weight:800;color:var(--ink);font-size:14px">مبلغ کل سفارش</span>
                  <b class="num" style="font-size:16px;font-weight:800;color:var(--accent-ink)">${money(o.total || 0)}</b>
                </div>

                ${(o.remainingRefund || 0) > 0 ? `
                  <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border-soft)">
                    <button type="button" class="link-btn" id="odToggleRefundBtn">ثبت استرداد وجه (Refund) جزئی یا کامل ▾</button>
                    <form class="refund-box" id="odRefundForm" hidden style="margin-top:8px">
                      <div style="display:flex;gap:6px;align-items:center">
                        <input type="number" id="odRefAmount" class="sb-ctl-input num" min="1" max="${Math.round(o.remainingRefund)}" step="1000" placeholder="مبلغ استرداد (${esc(CURRENCY)})" required>
                        <button type="button" class="btn" id="odFillMaxRefundBtn" data-max="${Math.round(o.remainingRefund)}" style="height:36px;white-space:nowrap;font-size:11.5px">کل سقف (${fa(Math.round(o.remainingRefund))})</button>
                      </div>
                      <input type="text" id="odRefReason" class="sb-ctl-input" placeholder="علت استرداد وجه (اختیاری)…">
                      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                        <label class="note-type-lbl"><input type="checkbox" id="odRefRestock" checked> بازگرداندن موجودی اقلام به انبار</label>
                        <button type="submit" class="btn primary" style="height:32px">ثبت استرداد وجه</button>
                      </div>
                    </form>
                  </div>
                ` : ''}
              </div>
            </div>
          </section>

          <!-- Card 2: Customer Billing & Shipping Addresses + Inline Editor -->
          <section class="od-card">
            <div class="od-card-head">
              <h3>مشخصات خریدار و نشانی ارسال</h3>
              <button type="button" class="btn" id="odToggleEditBtn" style="height:30px;padding:0 12px;font-size:12px">
                ویرایش مشخصات و آدرس ✎
              </button>
            </div>
            <div class="od-card-body">
              <div class="od-addr-grid" id="odAddrReadView">
                <div class="od-addr-card">
                  <h4>
                    <span>اطلاعات صورت‌حساب (خریدار)</span>
                    ${(b.phone || o.phone) ? `<button type="button" class="copy-btn" data-copy-val="${esc(b.phone || o.phone)}" data-copy-lbl="شماره تماس">کپی موبایل</button>` : ''}
                  </h4>
                  <div><b style="font-size:14px">${esc(bFullName)}</b>${b.company ? ` <span class="muted">(${esc(b.company)})</span>` : ''}</div>
                  <div style="line-height:1.7;color:var(--ink-2)">${esc(bAddr)}</div>
                  <div class="od-kv" style="margin-top:auto;padding-top:6px;border-top:1px dashed var(--border-soft)">
                    <span>موبایل:</span>
                    <b class="num">${(b.phone || o.phone) ? `<a href="tel:${esc(b.phone || o.phone)}" style="color:inherit;text-decoration:none">${esc(faId(b.phone || o.phone))}</a>` : '—'}</b>
                  </div>
                  <div class="od-kv">
                    <span>ایمیل:</span>
                    <b dir="ltr">${esc(b.email || o.email || '—')} ${(b.email || o.email) ? `<button type="button" class="copy-btn" data-copy-val="${esc(b.email || o.email)}" data-copy-lbl="ایمیل">کپی</button>` : ''}</b>
                  </div>
                </div>

                <div class="od-addr-card">
                  <h4>
                    <span>نشانی گیرنده مرسوله</span>
                    <button type="button" class="copy-btn" data-copy-val="${esc(fullShipCopy)}" data-copy-lbl="مشخصات کامل گیرنده">کپی کامل نشانی</button>
                  </h4>
                  <div><b style="font-size:14px">${esc(sFullName)}</b>${s.company ? ` <span class="muted">(${esc(s.company)})</span>` : ''}</div>
                  <div style="line-height:1.7;color:var(--ink-2)">${esc(sAddr)}</div>
                  <div class="od-kv" style="margin-top:auto;padding-top:6px;border-top:1px dashed var(--border-soft)">
                    <span>کد پستی ۱۰ رقمی:</span>
                    <b class="num">${sPostcode ? `${esc(faId(sPostcode))} <button type="button" class="copy-btn" data-copy-val="${esc(sPostcode)}" data-copy-lbl="کد پستی">کپی</button>` : '—'}</b>
                  </div>
                  <div class="od-kv">
                    <span>روش ارسال:</span>
                    <b>${esc(o.shippingMethod || '—')}</b>
                  </div>
                </div>
              </div>

              ${o.customerNote ? `<div class="cust-note-box" style="margin-top:12px"><b>یادداشت خریدار هنگام ثبت سفارش:</b> ${esc(o.customerNote)}</div>` : ''}

              <form id="odEditDetailsForm" hidden style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft)">
                <div class="qo-grid-2">
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">نام</label>
                    <input type="text" id="odEdFirstName" class="sb-ctl-input" value="${esc(editFirstName)}">
                  </div>
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">نام خانوادگی</label>
                    <input type="text" id="odEdLastName" class="sb-ctl-input" value="${esc(editLastName)}">
                  </div>
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">شماره موبایل</label>
                    <input type="text" id="odEdPhone" class="sb-ctl-input num" dir="ltr" style="text-align:right" value="${esc(b.phone || o.phone || '')}">
                  </div>
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">ایمیل</label>
                    <input type="email" id="odEdEmail" class="sb-ctl-input" dir="ltr" style="text-align:right" value="${esc(b.email || o.email || '')}">
                  </div>
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">استان</label>
                    <select id="odEdState" class="sb-ctl-input">${stateOptionsHtml}</select>
                  </div>
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">شهر</label>
                    <input type="text" id="odEdCity" class="sb-ctl-input" value="${esc(b.city || s.city || o.city || '')}">
                  </div>
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">کد پستی</label>
                    <input type="text" id="odEdPostcode" class="sb-ctl-input num" dir="ltr" style="text-align:right" value="${esc(sPostcode)}">
                  </div>
                  <div>
                    <label class="muted" style="display:block;margin-bottom:4px">کد رهگیری تراکنش بانکی</label>
                    <input type="text" id="odEdTransId" class="sb-ctl-input num" dir="ltr" style="text-align:right" value="${esc(o.transactionId || '')}">
                  </div>
                  <div class="qo-span-2">
                    <label class="muted" style="display:block;margin-bottom:4px">نشانی خیابان و کوچه</label>
                    <input type="text" id="odEdAddress1" class="sb-ctl-input" value="${esc(b.address1 || s.address1 || o.addr || '')}">
                  </div>
                  <div class="qo-span-2">
                    <label class="muted" style="display:block;margin-bottom:4px">پلاک، واحد و جزئیات تکمیلی نشانی</label>
                    <input type="text" id="odEdAddress2" class="sb-ctl-input" value="${esc(b.address2 || s.address2 || '')}">
                  </div>
                  <div class="qo-span-2">
                    <label class="muted" style="display:block;margin-bottom:4px">یادداشت خریدار (نمایش روی فاکتور و برچسب)</label>
                    <textarea id="odEdCustNote" class="sb-ctl-input" rows="2" style="height:auto;padding:8px 10px">${esc(o.customerNote || '')}</textarea>
                  </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
                  <button type="button" class="btn" id="odCancelEditBtn">انصراف</button>
                  <button type="submit" class="btn primary" id="odSaveDetailsBtn">ذخیره تغییرات سفارش</button>
                </div>
              </form>
            </div>
          </section>
        </div>

        <!-- SIDEBAR COLUMN -->
        <div class="od-col">
          <!-- Sidebar Card 1: Status & Payment -->
          <section class="od-card">
            <div class="od-card-head">
              <h3>وضعیت و پرداخت</h3>
              <span class="badge" data-status="${esc(o.status)}">${esc(st.label)}</span>
            </div>
            <div class="od-card-body" style="display:flex;flex-direction:column;gap:12px">
              <div style="display:flex;gap:8px;align-items:center">
                <select id="odStatusSelect" class="sb-ctl-input" aria-label="وضعیت سفارش">${statusOptsHtml}</select>
                <button type="button" class="btn primary" id="odSaveStatusBtn" style="white-space:nowrap">به‌روزرسانی</button>
              </div>
              <div style="border-top:1px solid var(--border-soft);padding-top:10px;display:flex;flex-direction:column;gap:4px">
                <div class="od-kv"><span>درگاه / روش پرداخت</span><b>${esc(o.pay || '—')}</b></div>
                <div class="od-kv">
                  <span>شماره تراکنش</span>
                  <b class="num">${o.transactionId ? `${esc(o.transactionId)} <button type="button" class="copy-btn" data-copy-val="${esc(o.transactionId)}" data-copy-lbl="شماره تراکنش">کپی</button>` : '—'}</b>
                </div>
                ${o.datePaid ? `<div class="od-kv"><span>زمان پرداخت</span><b class="num">${esc(o.datePaid)}</b></div>` : ''}
                ${o.dateCompleted ? `<div class="od-kv"><span>زمان تکمیل</span><b class="num">${esc(o.dateCompleted)}</b></div>` : ''}
              </div>
            </div>
          </section>

          <!-- Sidebar Card 2: Open in Native WooCommerce Order Page -->
          <section class="od-card" style="border-color:var(--accent-line)">
            <div class="od-wc-card" style="border:0;border-radius:0">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                <b>صفحه سفارش خود ووکامرس</b>
                <span class="badge" data-status="processing" style="font-size:10.5px">WooCommerce</span>
              </div>
              <p>برای دسترسی به متاباکس‌های افزونه‌های جانبی، زمینه‌های دلخواه یا ویرایش پیشرفته اقلام، سفارش را در ویرایشگر اصلی ووکامرس باز کنید.</p>
              <a class="btn primary" href="${esc(nativeWcUrl)}" style="justify-content:center;width:100%">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
                باز کردن در صفحه سفارش ووکامرس
              </a>
              <div style="display:flex;gap:8px">
                <a class="btn" href="${esc(nativeWcUrl)}" target="_blank" rel="noopener" style="flex:1;justify-content:center;font-size:11.5px">باز کردن در تب جدید ↗</a>
                ${o.status === 'trash'
                  ? `<button type="button" class="btn" data-od-restore="${o.id}" style="font-size:11.5px">بازیابی</button>`
                  : `<button type="button" class="btn" data-od-trash="${o.id}" style="font-size:11.5px;color:var(--danger)">انتقال به زباله‌دان</button>`}
              </div>
            </div>
          </section>

          <!-- Sidebar Card 3: Postal / Tipax Tracking -->
          <section class="od-card">
            <div class="od-card-head">
              <h3>کد رهگیری مرسوله</h3>
              ${o.trackingCode ? `<button type="button" class="copy-btn" data-copy-val="${esc(o.trackingCode)}" data-copy-lbl="کد رهگیری">کپی کد رهگیری</button>` : ''}
            </div>
            <div class="od-card-body">
              <form class="tracking-box" id="odTrackingForm" style="padding:0;border:0;background:transparent">
                <div class="tracking-row">
                  <select id="odTrkCarrier" class="sb-ctl-input" style="flex:0 0 122px">${carrierOpts}</select>
                  <input type="text" id="odTrkCode" class="sb-ctl-input num" dir="ltr" style="text-align:right" placeholder="کد رهگیری پستی / بارکد…" value="${esc(o.trackingCode || '')}">
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;margin-top:4px">
                  <label class="note-type-lbl"><input type="checkbox" id="odTrkNotify" checked> ارسال کد رهگیری در یادداشت مشتری</label>
                  <label class="note-type-lbl"><input type="checkbox" id="odTrkComplete" ${o.status !== 'completed' ? 'checked' : ''}> تغییر خودکار وضعیت به «تکمیل‌شده»</label>
                </div>
                <button type="submit" class="btn primary" style="width:100%;justify-content:center;margin-top:6px">ذخیره کد رهگیری</button>
              </form>
            </div>
          </section>

          <!-- Sidebar Card 4: Customer Summary -->
          <section class="od-card">
            <div class="od-card-head">
              <h3>پرونده مشتری</h3>
              ${o.customerEditUrl ? `<a href="${esc(o.customerEditUrl)}" target="_blank" rel="noopener" style="font-size:11.5px;color:var(--accent-ink);text-decoration:none;font-weight:600">پروفایل کاربر ↗</a>` : ''}
            </div>
            <div class="od-card-body" style="display:flex;flex-direction:column;gap:10px">
              <div class="person" style="margin:0">
                <span class="av">${esc(avatarChar)}</span>
                <div>
                  <b>${esc(o.name || 'مهمان')}</b>
                  <span>${o.customerId ? `کاربر عضو (#${faId(o.customerId)})` : 'خریدار مهمان'}</span>
                </div>
              </div>
              <div style="border-top:1px solid var(--border-soft);padding-top:8px">
                <div class="od-kv"><span>تعداد کل سفارش‌ها</span><b class="num">${fa(o.orderCount || 1)} سفارش</b></div>
                <div class="od-kv"><span>مجموع کل خرید</span><b class="num">${money(o.customerTotalSpent || o.total || 0)}</b></div>
              </div>
              ${custSearchKey ? `
                <button type="button" class="btn" data-od-cust-orders="${esc(custSearchKey)}" style="width:100%;justify-content:center;font-size:12px">
                  مشاهده تمام سفارش‌های این مشتری
                </button>
              ` : ''}
            </div>
          </section>

          <!-- Sidebar Card 5: Order Notes & Timeline -->
          <section class="od-card">
            <div class="od-card-head">
              <h3>یادداشت‌ها و تاریخچه (${fa((o.notes || []).length)})</h3>
            </div>
            <div class="od-card-body">
              <form class="note-form" id="odNoteForm" style="margin-top:0;margin-bottom:14px">
                <div class="note-form-row">
                  <label class="field" for="odNoteInput">
                    <input type="text" id="odNoteInput" placeholder="ثبت یادداشت جدید…" autocomplete="off" required>
                  </label>
                  <button class="btn primary" type="submit">ثبت</button>
                </div>
                <label class="note-type-lbl">
                  <input type="checkbox" id="odNoteToCustomer"> ارسال یادداشت برای مشتری (ایمیل / پیامک)
                </label>
              </form>
              <div class="timeline" style="max-height:380px;overflow-y:auto;padding-inline-end:4px">
                ${notesHtml || '<div class="muted" style="font-size:12px">یادداشتی برای این سفارش ثبت نشده است.</div>'}
              </div>
            </div>
          </section>
        </div>
      </div>
    `;

    syncOrderPageNavButtons();
  }

  /* ================= Print Invoice & Print Shipping Label ================= */
  function openInvoicePrintWindow(ordersList) {
    if (!ordersList || !ordersList.length) {
      toast('سفارشی برای چاپ یافت نشد');
      return;
    }
    const siteName = storeInfo.name || SB.siteName || 'فروشگاه';
    const pagesHtml = ordersList.map(o => {
      const orderNum = o.orderNumber || o.id;
      const st = (STATUS[o.status] || {}).label || o.statusLabel || o.status;
      const itemsRows = (o.items || []).map((it, idx) => `
        <tr>
          <td>${fa(idx + 1)}</td>
          <td style="text-align:right;font-weight:600">${esc(it.n || '')}${it.sku ? `<div style="font-size:11px;color:#666;font-weight:400">کد: ${esc(it.sku)}</div>` : ''}</td>
          <td>${fa(it.q || 0)}</td>
          <td>${money(it.p || 0)}</td>
          <td style="font-weight:600">${money(it.t ?? ((it.q || 0) * (it.p || 0)))}</td>
        </tr>
      `).join('');

      const feesRows = (o.fees || []).map(f =>
        `<div><span>${esc(f.name)}:</span><b>${f.total < 0 ? '− ' + money(Math.abs(f.total)) : money(f.total)}</b></div>`
      ).join('');

      return `
        <section class="inv-page">
          <header class="inv-head">
            <div>
              <h1>${esc(siteName)}</h1>
              <div class="sub">صورت‌حساب فروش کالا و خدمات</div>
            </div>
            <div class="inv-meta">
              <div><b>شماره سفارش:</b> #${esc(faId(orderNum))}</div>
              <div><b>تاریخ ثبت:</b> ${esc(o.date || '')} - ${esc(o.time || '')}</div>
              <div><b>وضعیت:</b> ${esc(st)}</div>
            </div>
          </header>

          <div class="inv-box-grid">
            <div class="inv-box">
              <b>مشخصات خریدار</b>
              <div>نام: ${esc(o.name || '—')}</div>
              <div>تلفن: ${esc(faId(o.phone || '—'))}</div>
              ${o.email ? `<div>ایمیل: ${esc(o.email)}</div>` : ''}
            </div>
            <div class="inv-box">
              <b>اطلاعات ارسال و پرداخت</b>
              <div>نشانی: ${esc(o.addr || '—')}${o.postcode ? ` — کد پستی: ${esc(faId(o.postcode))}` : ''}</div>
              <div>روش پرداخت: ${esc(o.pay || '—')}${o.shippingMethod && o.shippingMethod !== '—' ? ` | ارسال: ${esc(o.shippingMethod)}` : ''}</div>
              ${o.trackingCode ? `<div>کد رهگیری مرسوله: <b>${esc(faId(o.trackingCode))}</b></div>` : ''}
            </div>
          </div>

          ${o.customerNote ? `<div class="inv-note"><b>توضیحات خریدار:</b> ${esc(o.customerNote)}</div>` : ''}

          <table class="inv-table">
            <thead>
              <tr>
                <th style="width:45px">ردیف</th>
                <th style="text-align:right">شرح کالا / خدمات</th>
                <th style="width:70px">تعداد</th>
                <th style="width:130px">فی واحد</th>
                <th style="width:140px">مبلغ کل</th>
              </tr>
            </thead>
            <tbody>${itemsRows || '<tr><td colspan="5">اقلامی ثبت نشده است</td></tr>'}</tbody>
          </table>

          <div class="inv-totals">
            <div><span>جمع کل اقلام:</span><b>${money(o.subtotal || 0)}</b></div>
            <div><span>هزینه ارسال:</span><b>${(o.ship || 0) == 0 ? 'رایگان' : money(o.ship)}</b></div>
            ${feesRows}
            ${(o.tax || 0) > 0 ? `<div><span>مالیات و عوارض:</span><b>${money(o.tax)}</b></div>` : ''}
            ${(o.disc || 0) > 0 ? `<div><span>تخفیف:</span><b>− ${money(o.disc)}</b></div>` : ''}
            ${(o.refunded || 0) > 0 ? `<div><span>مستردشده:</span><b>− ${money(o.refunded)}</b></div>` : ''}
            <div class="grand"><span>مبلغ قابل پرداخت:</span><b>${money(o.total || 0)}</b></div>
          </div>
        </section>
      `;
    }).join('');

    const win = window.open('', '_blank', 'width=900,height=750');
    if (!win) {
      toast('لطفاً اجازه باز شدن پنجره پاپ‌آپ (Pop-up) را در مرورگر بدهید');
      return;
    }
    win.document.open();
    win.document.write(`<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <title>چاپ فاکتور — ${esc(siteName)}</title>
  <style>
    @font-face {
      font-family: 'Vazirmatn';
      src: url('${esc(SB.fontUrl || '')}') format('woff2');
      font-weight: 100 900;
    }
    * { box-sizing: border-box; font-family: 'Vazirmatn', Tahoma, Arial, sans-serif; }
    body { margin: 0; padding: 20px; background: #f4f4f6; color: #111; font-size: 13px; line-height: 1.6; }
    .print-bar { max-width: 820px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; background: #1b1b20; color: #fff; padding: 10px 16px; border-radius: 12px; }
    .print-bar button { background: #e01b48; color: #fff; border: 0; padding: 7px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px; }
    .inv-page { max-width: 820px; margin: 0 auto 24px; background: #fff; border: 1px solid #ddd; border-radius: 14px; padding: 28px; page-break-after: always; }
    .inv-page:last-child { page-break-after: auto; }
    .inv-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 14px; margin-bottom: 16px; }
    .inv-head h1 { margin: 0; font-size: 20px; font-weight: 800; }
    .inv-head .sub { font-size: 12px; color: #666; margin-top: 2px; }
    .inv-meta { text-align: left; font-size: 12.5px; line-height: 1.7; }
    .inv-box-grid { display: grid; grid-template-columns: 1fr 1.3fr; gap: 12px; margin-bottom: 14px; }
    .inv-box { border: 1px solid #e0e0e0; border-radius: 10px; padding: 10px 14px; background: #fafafa; font-size: 12.5px; }
    .inv-box > b { display: block; margin-bottom: 4px; font-size: 13px; border-bottom: 1px dashed #ddd; padding-bottom: 3px; }
    .inv-note { margin-bottom: 14px; padding: 8px 12px; border-radius: 8px; background: #fff8e6; border: 1px solid #f2dc9b; font-size: 12px; }
    .inv-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .inv-table th, .inv-table td { border: 1px solid #dcdcdc; padding: 8px 10px; text-align: center; font-size: 12.5px; }
    .inv-table th { background: #f2f2f4; font-weight: 700; }
    .inv-totals { width: 290px; margin-right: auto; border: 1px solid #dcdcdc; border-radius: 10px; padding: 10px 14px; background: #fafafa; }
    .inv-totals div { display: flex; justify-content: space-between; padding: 4px 0; font-size: 12.5px; }
    .inv-totals .grand { border-top: 1px dashed #bbb; margin-top: 6px; padding-top: 8px; font-size: 14px; font-weight: 800; }
    @media print {
      body { background: #fff; padding: 0; }
      .print-bar { display: none !important; }
      .inv-page { border: 0; border-radius: 0; margin: 0; padding: 10mm; max-width: 100%; }
    }
  </style>
</head>
<body>
  <div class="print-bar">
    <span>پیش‌نمایش چاپ فاکتور (${fa(ordersList.length)} برگ)</span>
    <button onclick="window.print()">چاپ فاکتور</button>
  </div>
  ${pagesHtml}
  <script>setTimeout(() => { window.print(); }, 350);<\/script>
</body>
</html>`);
    win.document.close();
  }

  function openShippingLabelWindow(ordersList) {
    if (!ordersList || !ordersList.length) {
      toast('سفارشی برای چاپ برچسب یافت نشد');
      return;
    }
    const senderName = storeInfo.name || SB.siteName || 'فروشگاه';
    const senderAddr = storeInfo.address || '—';
    const senderPhone = storeInfo.phone || '—';
    const senderPost = storeInfo.postcode || '—';

    const labelsHtml = ordersList.map(o => {
      const orderNum = o.orderNumber || o.id;
      const isCod = String(o.payMethod || '').toLowerCase() === 'cod';
      const itemsSummary = (o.items || []).map(it => `${esc(it.n)} (${fa(it.q)} عدد)`).join(' ، ');

      return `
        <section class="lbl-card">
          <div class="lbl-top">
            <div>
              <div class="lbl-brand">${esc(senderName)}</div>
              <div class="lbl-sub">برچسب ارسال مرسوله پستی · سفارش #${esc(faId(orderNum))}</div>
            </div>
            <div class="lbl-pay ${isCod ? 'cod' : 'paid'}">
              ${isCod ? `پس‌کرایه / پرداخت در محل: ${money(o.total || 0)}` : 'پرداخت‌شده'}
            </div>
          </div>

          <div class="lbl-sec recv">
            <div class="sec-tag">گیرنده مرسوله</div>
            <div class="recv-name">${esc(o.name || '—')}</div>
            <div class="recv-addr">${esc(o.addr || 'آدرسی ثبت نشده است')}</div>
            <div class="recv-grid">
              <div><span>شماره تماس گیرنده:</span><b dir="ltr">${esc(faId(o.phone || '—'))}</b></div>
              <div><span>کد پستی ۱۰ رقمی:</span><b dir="ltr">${esc(faId(o.postcode || '—'))}</b></div>
            </div>
          </div>

          <div class="lbl-sec send">
            <div class="sec-tag">فرستنده</div>
            <div><b>${esc(senderName)}</b> — نشانی: ${esc(senderAddr)}</div>
            <div style="display:flex;gap:18px;margin-top:4px">
              <span>تلفن: ${esc(faId(senderPhone))}</span>
              <span>کد پستی: ${esc(faId(senderPost))}</span>
              <span>تاریخ: ${esc(o.date || '')}</span>
            </div>
          </div>

          <div class="lbl-meta">
            <div><b>روش ارسال:</b> ${esc(o.shippingMethod || 'پست')} (${fa(o.qty || 0)} قلم)</div>
            ${itemsSummary ? `<div class="lbl-items"><b>محتویات:</b> ${itemsSummary}</div>` : ''}
            ${o.customerNote ? `<div class="lbl-note"><b>توضیحات خریدار:</b> ${esc(o.customerNote)}</div>` : ''}
            ${o.trackingCode ? `<div style="margin-top:4px"><b>بارکد / کد رهگیری:</b> ${esc(faId(o.trackingCode))}</div>` : ''}
          </div>
        </section>
      `;
    }).join('');

    const win = window.open('', '_blank', 'width=760,height=740');
    if (!win) {
      toast('لطفاً اجازه باز شدن پنجره پاپ‌آپ (Pop-up) را در مرورگر بدهید');
      return;
    }
    win.document.open();
    win.document.write(`<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="UTF-8">
  <title>برچسب پستی — ${esc(senderName)}</title>
  <style>
    @font-face {
      font-family: 'Vazirmatn';
      src: url('${esc(SB.fontUrl || '')}') format('woff2');
      font-weight: 100 900;
    }
    * { box-sizing: border-box; font-family: 'Vazirmatn', Tahoma, Arial, sans-serif; }
    body { margin: 0; padding: 18px; background: #f2f2f5; color: #111; font-size: 13px; line-height: 1.55; }
    .print-bar { max-width: 560px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; background: #1b1b20; color: #fff; padding: 10px 16px; border-radius: 12px; }
    .print-bar button { background: #e01b48; color: #fff; border: 0; padding: 7px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px; }
    .lbl-card { max-width: 560px; margin: 0 auto 20px; background: #fff; border: 2.2px solid #111; border-radius: 12px; padding: 18px; page-break-after: always; }
    .lbl-card:last-child { page-break-after: auto; }
    .lbl-top { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 12px; gap: 10px; }
    .lbl-brand { font-size: 18px; font-weight: 900; }
    .lbl-sub { font-size: 11.5px; color: #444; }
    .lbl-pay { padding: 5px 11px; border-radius: 8px; font-weight: 800; font-size: 12px; border: 1.5px solid #111; }
    .lbl-pay.cod { background: #111; color: #fff; }
    .lbl-sec { border: 1.5px solid #222; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; position: relative; }
    .lbl-sec.recv { background: #fcfcfd; }
    .sec-tag { font-size: 10.5px; font-weight: 800; color: #555; margin-bottom: 4px; }
    .recv-name { font-size: 17px; font-weight: 900; margin-bottom: 4px; }
    .recv-addr { font-size: 14px; font-weight: 600; line-height: 1.7; margin-bottom: 10px; }
    .recv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; border-top: 1px dashed #888; padding-top: 8px; }
    .recv-grid span { display: block; font-size: 11px; color: #555; }
    .recv-grid b { font-size: 15px; font-weight: 900; letter-spacing: .5px; }
    .lbl-sec.send { font-size: 12px; background: #f8f8fa; border-style: dashed; }
    .lbl-meta { font-size: 11.5px; color: #222; border-top: 1px solid #ccc; padding-top: 8px; }
    .lbl-items { margin-top: 4px; color: #444; }
    .lbl-note { margin-top: 6px; padding: 5px 8px; background: #fff7db; border: 1px solid #e0c97b; border-radius: 6px; font-weight: 600; }
    @media print {
      body { background: #fff; padding: 0; }
      .print-bar { display: none !important; }
      .lbl-card { margin: 0 auto; max-width: 100%; border-radius: 0; }
    }
  </style>
</head>
<body>
  <div class="print-bar">
    <span>پیش‌نمایش برچسب پستی (${fa(ordersList.length)} مرسوله)</span>
    <button onclick="window.print()">چاپ برچسب‌ها</button>
  </div>
  ${labelsHtml}
  <script>setTimeout(() => { window.print(); }, 350);<\/script>
</body>
</html>`);
    win.document.close();
  }

  async function printOrdersByIds(ids, mode = 'invoice') {
    try {
      toast(mode === 'label' ? 'در حال آماده‌سازی برچسب پستی…' : 'در حال آماده‌سازی فاکتور برای چاپ…');
      const data = await api('sefareshban_get_orders_batch', { ids });
      if (mode === 'label') openShippingLabelWindow(data.orders || []);
      else openInvoicePrintWindow(data.orders || []);
    } catch (e) {
      console.error(e);
      toast(e.message || 'خطا در دریافت اطلاعات چاپ');
    }
  }

  /* ================= Toast ================= */
  let toastTimer;
  function toast(msg) {
    const el = $('#toastMsg');
    if (el) el.textContent = msg;
    $('#toast')?.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => $('#toast')?.classList.remove('show'), 2800);
  }

  /* ================= Order Actions (Status, Note, Details, Tracking, Refund, Restore, Trash) ================= */
  function refreshActiveOrderViews(orderId, updatedOrder) {
    if (!updatedOrder) return;
    if (state.openId === +orderId) {
      const sc = $('#drawer .d-body')?.scrollTop || 0;
      renderDrawer(updatedOrder);
      const body = $('#drawer .d-body');
      if (body) body.scrollTop = sc;
    }
    if (state.section === 'order-detail' && +state.pageOrderId === +orderId) {
      const contentEl = $('.content');
      const sc = contentEl ? contentEl.scrollTop : 0;
      renderOrderPage(updatedOrder);
      if (contentEl) contentEl.scrollTop = sc;
    }
  }

  async function setStatus(id, key) {
    try {
      const data = await api('sefareshban_update_status', { order_id: id, status: key });
      const st = STATUS[key] || { label: key };
      toast(`وضعیت سفارش #${faId(id)} به «${st.label}» تغییر کرد`);
      refreshActiveOrderViews(id, data.order);
      await fetchOrders();
      fetchStats();
    } catch (e) {
      console.error(e);
      toast(e.message || i18n.error || 'خطا در تغییر وضعیت');
    }
  }

  async function addNote(orderId, text, customerNote = false, fromPage = false) {
    try {
      const data = await api('sefareshban_add_note', {
        order_id: orderId,
        note: text,
        customer_note: customerNote ? 1 : 0
      });
      refreshActiveOrderViews(orderId, data.order);
      toast(i18n.noteSaved || 'یادداشت ثبت شد');
      setTimeout(() => $(fromPage ? '#odNoteInput' : '#noteInput')?.focus(), 80);
    } catch (e) {
      console.error(e);
      toast(e.message || i18n.error || 'خطا در ثبت یادداشت');
    }
  }

  async function deleteNote(orderId, noteId) {
    if (!window.confirm('آیا از حذف این یادداشت اطمینان دارید؟')) return;
    try {
      const data = await api('sefareshban_delete_note', {
        order_id: orderId,
        note_id: noteId
      });
      refreshActiveOrderViews(orderId, data.order);
      toast('یادداشت حذف شد');
    } catch (e) {
      console.error(e);
      toast(e.message || 'خطا در حذف یادداشت');
    }
  }

  async function updateOrderDetails(orderId) {
    const btn = $('#odSaveDetailsBtn');
    try {
      if (btn) btn.disabled = true;
      const data = await api('sefareshban_update_order_details', {
        order_id: orderId,
        first_name: $('#odEdFirstName')?.value.trim() || '',
        last_name: $('#odEdLastName')?.value.trim() || '',
        phone: $('#odEdPhone')?.value.trim() || '',
        email: $('#odEdEmail')?.value.trim() || '',
        state: $('#odEdState')?.value || '',
        city: $('#odEdCity')?.value.trim() || '',
        postcode: $('#odEdPostcode')?.value.trim() || '',
        transaction_id: $('#odEdTransId')?.value.trim() || '',
        address_1: $('#odEdAddress1')?.value.trim() || '',
        address_2: $('#odEdAddress2')?.value.trim() || '',
        customer_note: $('#odEdCustNote')?.value.trim() || ''
      });
      toast('مشخصات و نشانی سفارش با موفقیت ذخیره شد ✓');
      refreshActiveOrderViews(orderId, data.order);
      await fetchOrders();
    } catch (e) {
      console.error(e);
      toast(e.message || 'خطا در ذخیره مشخصات سفارش');
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  async function saveTracking(orderId, fromPage = false) {
    const pfx = fromPage ? '#odTrk' : '#trk';
    const code = enDigits($(pfx + 'Code')?.value || '').trim();
    const carrier = $(pfx + 'Carrier')?.value || 'پست پیشتاز';
    const notify = $(pfx + 'Notify')?.checked ? 1 : 0;
    const markCompleted = $(pfx + 'Complete')?.checked ? 1 : 0;
    try {
      const data = await api('sefareshban_save_tracking', {
        order_id: orderId,
        tracking_code: code,
        carrier,
        notify_customer: notify,
        mark_completed: markCompleted
      });
      toast('اطلاعات رهگیری مرسوله ذخیره شد');
      refreshActiveOrderViews(orderId, data.order);
      await fetchOrders();
      fetchStats();
    } catch (e) {
      console.error(e);
      toast(e.message || 'خطا در ثبت کد رهگیری');
    }
  }

  async function submitRefund(orderId, fromPage = false) {
    const pfx = fromPage ? '#odRef' : '#ref';
    const amount = enDigits($(pfx + 'Amount')?.value || '0').trim();
    const reason = ($(pfx + 'Reason')?.value || '').trim();
    const restock = $(pfx + 'Restock')?.checked ? 1 : 0;
    if (!Number(amount) || Number(amount) <= 0) {
      toast('مبلغ استرداد را به‌درستی وارد کنید');
      return;
    }
    if (!window.confirm(`آیا از ثبت استرداد وجه به مبلغ ${money(amount)} اطمینان دارید؟`)) return;
    try {
      const data = await api('sefareshban_create_refund', {
        order_id: orderId,
        amount,
        reason,
        restock
      });
      toast('استرداد وجه با موفقیت ثبت شد');
      refreshActiveOrderViews(orderId, data.order);
      await fetchOrders();
      fetchStats();
    } catch (e) {
      console.error(e);
      toast(e.message || 'خطا در ثبت استرداد وجه');
    }
  }

  async function restoreSingleOrder(orderId) {
    try {
      await api('sefareshban_bulk_action', { bulk: 'restore', ids: [orderId] });
      toast('سفارش با موفقیت از زباله‌دان بازیابی شد');
      await fetchOrders();
      fetchStats();
      if (state.openId === +orderId) {
        openOrder(orderId);
      }
      if (state.section === 'order-detail' && +state.pageOrderId === +orderId) {
        openOrderPage(orderId, false);
      }
    } catch (e) {
      console.error(e);
      toast(e.message || 'خطا در بازیابی سفارش');
    }
  }

  async function trashSingleOrder(orderId) {
    if (!window.confirm('آیا از انتقال این سفارش به زباله‌دان اطمینان دارید؟')) return;
    try {
      await api('sefareshban_bulk_action', { bulk: 'trash', ids: [orderId] });
      toast('سفارش به زباله‌دان منتقل شد');
      await fetchOrders();
      fetchStats();
      if (state.section === 'order-detail' && +state.pageOrderId === +orderId) {
        switchSection('orders');
      }
    } catch (e) {
      console.error(e);
      toast(e.message || 'خطا در انتقال به زباله‌دان');
    }
  }

  /* ================= Configurable Export Modal ================= */
  function openExportModal() {
    const modal = $('#exportModal');
    const scrim = $('#exportModalScrim');
    const desc = $('#exportScopeDesc');
    if (!modal) return;
    if (desc) {
      desc.textContent = state.selected.size > 0
        ? `خروجی برای ${fa(state.selected.size)} سفارش انتخاب‌شده آماده می‌شود.`
        : `خروجی بر اساس فیلتر فعلی (${fa(state.total)} سفارش) آماده می‌شود.`;
    }
    modal.hidden = false;
    scrim?.classList.add('show');
  }

  function closeExportModal() {
    const modal = $('#exportModal');
    if (modal) modal.hidden = true;
    $('#exportModalScrim')?.classList.remove('show');
  }

  $$('input[name="sbExportFormat"]').forEach(radio => {
    radio.addEventListener('change', () => {
      const fmt = document.querySelector('input[name="sbExportFormat"]:checked')?.value || 'standard';
      $('#expCardStandard')?.classList.toggle('active', fmt === 'standard');
      $('#expCardPostal')?.classList.toggle('active', fmt === 'postal');
      const colsBox = $('#exportColsBox');
      if (colsBox) colsBox.hidden = fmt === 'postal';
    });
  });

  $('#expSelectAllCols')?.addEventListener('click', () => {
    const boxes = $$('#exportColsGrid input[type="checkbox"]');
    const allChecked = boxes.every(b => b.checked);
    boxes.forEach(b => { b.checked = !allChecked; });
  });

  async function executeExportCsv() {
    const fmt = document.querySelector('input[name="sbExportFormat"]:checked')?.value || 'standard';
    const cols = $$('#exportColsGrid input[type="checkbox"]:checked').map(b => b.value);
    if (fmt === 'standard' && cols.length === 0) {
      toast('لطفاً حداقل یک ستون را برای خروجی انتخاب کنید');
      return;
    }
    try {
      $('#confirmExportBtn').disabled = true;
      toast('در حال آماده‌سازی فایل CSV…');
      const payload = Object.assign(buildOrderQueryParams(), {
        export_format: fmt,
        columns: cols
      });
      if (state.selected.size > 0) {
        payload.ids = Array.from(state.selected);
      }
      const data = await api('sefareshban_export_csv', payload);
      const bin = atob(data.csv);
      const bytes = new Uint8Array(bin.length);
      for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
      const url = URL.createObjectURL(new Blob([bytes], { type: 'text/csv;charset=utf-8' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = data.filename || 'orders.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      closeExportModal();
      toast(`${fa(data.count || 0)} سفارش در فایل CSV ذخیره شد`);
    } catch (e) {
      console.error(e);
      toast(e.message || i18n.error || 'خطا در خروجی CSV');
    } finally {
      $('#confirmExportBtn').disabled = false;
    }
  }

  $('#exportBtn')?.addEventListener('click', openExportModal);
  $('#confirmExportBtn')?.addEventListener('click', executeExportCsv);
  $('#exportModalScrim')?.addEventListener('click', closeExportModal);
  $$('[data-close-export-modal]').forEach(b => b.addEventListener('click', closeExportModal));

  /* ================= Quick Order Creation Drawer ================= */
  function openQuickOrderDrawer() {
    closeFloatingPortals();
    $('#quickOrderDrawer')?.classList.add('open');
    $('#quickOrderScrim')?.classList.add('show');
    renderQoCart();
    setTimeout(() => $('#qoProductSearch')?.focus(), 100);
  }

  function closeQuickOrderDrawer() {
    $('#quickOrderDrawer')?.classList.remove('open');
    $('#quickOrderScrim')?.classList.remove('show');
    const resBox = $('#qoProdResults');
    if (resBox) resBox.hidden = true;
  }

  function calcQoTotal() {
    const sub = state.qoCart.reduce((acc, it) => acc + (Number(it.price) || 0) * (Number(it.qty) || 1), 0);
    const ship = Math.max(0, Number(enDigits($('#qoShipCost')?.value || 0)) || 0);
    const disc = Math.max(0, Number(enDigits($('#qoDiscount')?.value || 0)) || 0);
    const grand = Math.max(0, sub + ship - disc);
    const el = $('#qoGrandTotal');
    if (el) el.textContent = money(grand);
  }

  function renderQoCart() {
    const host = $('#qoCartList');
    if (!host) return;
    if (!state.qoCart.length) {
      host.innerHTML = `<div class="muted" style="padding:10px 0;text-align:center">هنوز محصولی به سفارش اضافه نشده است.</div>`;
      calcQoTotal();
      return;
    }
    host.innerHTML = state.qoCart.map((it, idx) => `
      <div class="qo-cart-row">
        <b>${esc(it.name)}</b>
        <span class="muted num">${money(it.price)}</span>
        <div class="qo-qty-ctl">
          <button type="button" data-qo-delta="-1" data-qo-idx="${idx}">−</button>
          <span class="num" style="min-width:20px;text-align:center;font-weight:700">${fa(it.qty)}</span>
          <button type="button" data-qo-delta="1" data-qo-idx="${idx}">+</button>
        </div>
        <button type="button" class="icon-btn" style="width:26px;height:26px;color:var(--danger)" data-qo-rm="${idx}" title="حذف">×</button>
      </div>
    `).join('');
    calcQoTotal();
  }

  let qoProdTimer;
  let lastQoSearchRows = [];
  $('#qoProductSearch')?.addEventListener('input', e => {
    clearTimeout(qoProdTimer);
    const term = e.target.value.trim();
    const resBox = $('#qoProdResults');
    if (!term) {
      if (resBox) resBox.hidden = true;
      return;
    }
    qoProdTimer = setTimeout(async () => {
      try {
        const data = await api('sefareshban_get_products', { search: term, page: 1, per_page: 12, only_published: 1 });
        const rows = data.rows || [];
        lastQoSearchRows = rows;
        if (!resBox) return;
        if (!rows.length) {
          resBox.innerHTML = `<div class="muted" style="padding:10px;text-align:center">محصولی یافت نشد</div>`;
        } else {
          resBox.innerHTML = rows.map(p => `
            <button type="button" class="qo-prod-item" data-qo-add-id="${p.id}">
              <span>${esc(p.name)}</span>
              <span class="num" style="font-weight:700;color:var(--accent-ink)">${money(p.price || 0)}</span>
            </button>
          `).join('');
        }
        resBox.hidden = false;
      } catch (err) {
        console.error(err);
      }
    }, 280);
  });

  $('#qoProdResults')?.addEventListener('click', e => {
    const btn = e.target.closest('[data-qo-add-id]');
    if (!btn) return;
    const prod = lastQoSearchRows.find(x => +x.id === +btn.dataset.qoAddId);
    if (!prod) return;
    const existing = state.qoCart.find(x => +x.product_id === +prod.id);
    if (existing) existing.qty++;
    else state.qoCart.push({ product_id: +prod.id, name: prod.name, price: Number(prod.price) || 0, qty: 1 });
    renderQoCart();
    $('#qoProdResults').hidden = true;
    if ($('#qoProductSearch')) $('#qoProductSearch').value = '';
  });

  $('#qoCartList')?.addEventListener('click', e => {
    const dBtn = e.target.closest('[data-qo-delta]');
    if (dBtn) {
      const idx = +dBtn.dataset.qoIdx;
      const delta = +dBtn.dataset.qoDelta;
      if (state.qoCart[idx]) {
        state.qoCart[idx].qty += delta;
        if (state.qoCart[idx].qty <= 0) state.qoCart.splice(idx, 1);
        renderQoCart();
      }
      return;
    }
    const rmBtn = e.target.closest('[data-qo-rm]');
    if (rmBtn) {
      state.qoCart.splice(+rmBtn.dataset.qoRm, 1);
      renderQoCart();
    }
  });

  $('#qoShipCost')?.addEventListener('input', calcQoTotal);
  $('#qoDiscount')?.addEventListener('input', calcQoTotal);

  $('#quickOrderForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    if (!state.qoCart.length) {
      toast('لطفاً حداقل یک محصول به سفارش اضافه کنید');
      $('#qoProductSearch')?.focus();
      return;
    }
    const btn = $('#qoSubmitBtn');
    try {
      if (btn) btn.disabled = true;
      const data = await api('sefareshban_create_order', {
        customer_name: $('#qoName')?.value.trim() || '',
        phone: $('#qoPhone')?.value.trim() || '',
        email: $('#qoEmail')?.value.trim() || '',
        state: $('#qoState')?.value || '',
        city: $('#qoCity')?.value.trim() || '',
        address: $('#qoAddress')?.value.trim() || '',
        postcode: $('#qoPostcode')?.value.trim() || '',
        customer_note: $('#qoNote')?.value.trim() || '',
        shipping_title: $('#qoShipTitle')?.value.trim() || 'ارسال پستی',
        shipping_cost: $('#qoShipCost')?.value || 0,
        discount_amount: $('#qoDiscount')?.value || 0,
        payment_method: $('#qoPayment')?.value || '',
        status: $('#qoStatus')?.value || 'processing',
        items_json: JSON.stringify(state.qoCart.map(x => ({ product_id: x.product_id, qty: x.qty })))
      });
      state.qoCart = [];
      $('#quickOrderForm').reset();
      closeQuickOrderDrawer();
      toast('سفارش جدید با موفقیت ثبت شد ✓');
      state.page = 1;
      await fetchOrders();
      fetchStats();
      if (data.order && data.order.id) {
        openOrderPage(data.order.id, true, data.order);
      }
    } catch (err) {
      console.error(err);
      toast(err.message || 'خطا در ثبت سفارش');
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  $('#openNativeNewOrderLink')?.addEventListener('click', e => {
    e.preventDefault();
    window.open(SB.newOrderUrl || ((SB.adminUrl || '/wp-admin/') + 'post-new.php?post_type=shop_order'), '_blank');
  });
  $('#quickOrderScrim')?.addEventListener('click', closeQuickOrderDrawer);
  $$('[data-close-qo]').forEach(b => b.addEventListener('click', closeQuickOrderDrawer));

  /* ================= Bulk Status Modal & Actions ================= */
  function openBulkStatusModal() {
    const modal = $('#bulkStatusModal');
    const scrim = $('#bulkModalScrim');
    const sel = $('#bulkStatusSelect');
    const desc = $('#bulkModalDesc');
    if (!modal || !sel) return;

    sel.innerHTML = Object.keys(STATUS).map(k =>
      `<option value="${esc(k)}" ${k === 'processing' ? 'selected' : ''}>${esc(STATUS[k].label)}</option>`
    ).join('');
    if (desc) desc.textContent = `وضعیت جدید را برای ${fa(state.selected.size)} سفارش انتخاب‌شده مشخص کنید:`;
    modal.hidden = false;
    scrim?.classList.add('show');
  }

  function closeBulkStatusModal() {
    const modal = $('#bulkStatusModal');
    if (modal) modal.hidden = true;
    $('#bulkModalScrim')?.classList.remove('show');
  }

  async function doBulk(bulk) {
    const ids = Array.from(state.selected);
    if (!ids.length) return;
    if (bulk === 'clear') { state.selected.clear(); renderTable(); return; }
    if (bulk === 'print') { await printOrdersByIds(ids, 'invoice'); return; }
    if (bulk === 'label') { await printOrdersByIds(ids, 'label'); return; }
    if (bulk === 'export') { openExportModal(); return; }
    if (bulk === 'status') { openBulkStatusModal(); return; }
    if (bulk === 'delete') {
      if (!window.confirm(i18n.confirmDelete || 'آیا از انتقال سفارش‌های انتخاب‌شده به زباله‌دان مطمئن هستید؟')) return;
      try {
        await api('sefareshban_bulk_action', { bulk: 'trash', ids });
        toast(i18n.deleted || 'سفارش‌ها به زباله‌دان منتقل شدند');
        state.selected.clear();
        await fetchOrders();
        fetchStats();
      } catch (e) {
        console.error(e);
        toast(e.message || i18n.error || 'خطا');
      }
      return;
    }
    if (bulk === 'restore') {
      try {
        await api('sefareshban_bulk_action', { bulk: 'restore', ids });
        toast(i18n.restored || 'سفارش‌ها با موفقیت بازیابی شدند');
        state.selected.clear();
        await fetchOrders();
        fetchStats();
      } catch (e) {
        console.error(e);
        toast(e.message || 'خطا در بازیابی');
      }
      return;
    }
    if (bulk === 'force-delete') {
      if (!window.confirm(i18n.confirmForceDelete || 'آیا از حذف دائمی این سفارش‌ها مطمئن هستید؟')) return;
      try {
        await api('sefareshban_bulk_action', { bulk: 'delete_permanently', ids });
        toast(i18n.forceDeleted || 'سفارش‌ها برای همیشه حذف شدند');
        state.selected.clear();
        await fetchOrders();
        fetchStats();
      } catch (e) {
        console.error(e);
        toast(e.message || 'خطا در حذف دائمی');
      }
    }
  }

  $('#confirmBulkStatusBtn')?.addEventListener('click', async () => {
    const ids = Array.from(state.selected);
    const key = $('#bulkStatusSelect')?.value || 'processing';
    if (!ids.length) { closeBulkStatusModal(); return; }
    try {
      $('#confirmBulkStatusBtn').disabled = true;
      await api('sefareshban_bulk_action', { bulk: 'status', ids, status: key });
      closeBulkStatusModal();
      toast(i18n.bulkStatus || 'وضعیت سفارش‌های انتخاب‌شده تغییر کرد');
      state.selected.clear();
      await fetchOrders();
      fetchStats();
    } catch (e) {
      console.error(e);
      toast(e.message || i18n.error || 'خطا');
    } finally {
      $('#confirmBulkStatusBtn').disabled = false;
    }
  });

  $('#bulkModalScrim')?.addEventListener('click', closeBulkStatusModal);
  $$('[data-close-bulk-modal]').forEach(b => b.addEventListener('click', closeBulkStatusModal));

  /* ================= Charts ================= */
  function smoothPath(pts) {
    if (pts.length < 2) return '';
    let d = `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;
    for (let i = 0; i < pts.length - 1; i++) {
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

  function positionTip(host, tip, ratio) {
    const clamped = Math.max(0.16, Math.min(0.84, ratio));
    tip.style.left = (clamped * 100).toFixed(1) + '%';
  }

  function drawBarChart(el, items, opts = {}) {
    if (!el || !items || !items.length) return;
    el.querySelector('svg.viz')?.remove();
    const W = Math.max(170, Math.round(el.clientWidth || 220));
    const H = opts.height || 54;
    const padT = 8, padB = 4, padX = 2;
    const rawMax = Math.max(0, ...items.map(d => Number(d.v) || 0));
    const max = rawMax > 0 ? rawMax * 1.12 : 1;
    const slot = (W - padX * 2) / items.length;
    const bw = Math.min(opts.maxBarWidth || 22, Math.max(8, slot * 0.56));
    const gid = 'barGrad_' + (el.id || Math.random().toString(36).slice(2));

    const bars = items.map((d, i) => {
      const val = Number(d.v) || 0;
      const h = rawMax > 0 ? Math.max(4, (val / max) * (H - padT - padB)) : 4;
      const x = padX + i * slot + (slot - bw) / 2;
      const y = H - padB - h;
      const isLast = i === items.length - 1;
      const delay = (i * 35) + 'ms';
      return `
        <rect class="viz-bar ${isLast ? 'is-hot' : ''}" data-i="${i}"
              x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${bw.toFixed(1)}" height="${h.toFixed(1)}"
              rx="5" fill="${isLast ? `url(#${gid})` : 'var(--kpi-c)'}"
              fill-opacity="${rawMax === 0 ? '0.14' : (isLast ? '1' : '0.28')}" style="animation-delay:${delay}"/>
        <rect class="viz-hit" data-i="${i}" x="${(padX + i * slot).toFixed(1)}" y="0" width="${slot.toFixed(1)}" height="${H}"/>`;
    }).join('');

    el.insertAdjacentHTML('beforeend', `
      <svg class="viz" style="height:${H}px" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(opts.label || '')}">
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
    if (!tip) return;
    const barEls = Array.from(el.querySelectorAll('.viz-bar'));
    el.querySelectorAll('.viz-hit').forEach(hit => {
      hit.addEventListener('pointerenter', () => {
        const i = +hit.dataset.i;
        const d = items[i];
        barEls.forEach((b, idx) => {
          b.setAttribute('fill-opacity', idx === i ? '1' : (idx === items.length - 1 ? '0.55' : '0.18'));
          b.classList.toggle('is-hot', idx === i);
        });
        tip.innerHTML = `${esc(d.t)} · <b>${opts.fmt ? opts.fmt(d.v, d) : fa(d.v)}</b>`;
        positionTip(el, tip, (i + 0.5) / items.length);
        tip.classList.add('show');
      });
    });
    el.onpointerleave = () => {
      barEls.forEach((b, idx) => {
        const isLast = idx === items.length - 1;
        b.setAttribute('fill-opacity', rawMax === 0 ? '0.14' : (isLast ? '1' : '0.28'));
        b.classList.toggle('is-hot', isLast);
      });
      tip.classList.remove('show');
    };
  }

  function drawAreaChart(el, items, opts = {}) {
    if (!el || !items || items.length < 2) return;
    el.querySelector('svg.viz')?.remove();
    const W = Math.max(200, Math.round(el.clientWidth || 320));
    const H = opts.height || 170;
    const padT = 14, padB = 22, padX = 14;
    const vals = items.map(d => Number(d.v) || 0);
    const min = 0;
    const max = Math.max(1, ...vals);
    const hi = max * 1.15;

    const pts = items.map((d, i) => ({
      x: padX + (i / (items.length - 1)) * (W - padX * 2),
      y: padT + (1 - ((Number(d.v) || 0) - min) / (hi - min)) * (H - padT - padB),
      t: d.t,
      v: Number(d.v) || 0,
      extra: d
    }));

    const lineD = smoothPath(pts);
    const areaD = `${lineD} L ${pts[pts.length - 1].x.toFixed(1)} ${H - padB} L ${pts[0].x.toFixed(1)} ${H - padB} Z`;
    const gid = 'areaGrad_' + (el.id || 'rep');
    const last = pts[pts.length - 1];

    const slot = (W - padX * 2) / items.length;
    const hits = pts.map((p, i) =>
      `<rect class="viz-hit" data-i="${i}" x="${(p.x - slot / 2).toFixed(1)}" y="0" width="${slot.toFixed(1)}" height="${H}"/>`
    ).join('');

    const step = Math.max(1, Math.ceil(pts.length / 6));
    const xLabels = pts.filter((_, i) => i % step === 0 || i === pts.length - 1).map(p =>
      `<text x="${p.x.toFixed(1)}" y="${H - 4}" text-anchor="middle" fill="var(--muted-2)" font-size="10">${esc(p.t)}</text>`
    ).join('');

    el.insertAdjacentHTML('beforeend', `
      <svg class="viz" style="height:${H}px" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(opts.label || '')}">
        <defs>
          <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--kpi-c)" stop-opacity="0.30"/>
            <stop offset="100%" stop-color="var(--kpi-c)" stop-opacity="0.02"/>
          </linearGradient>
        </defs>
        <line class="viz-grid" x1="0" y1="${(H * 0.35).toFixed(1)}" x2="${W}" y2="${(H * 0.35).toFixed(1)}"/>
        <line class="viz-grid" x1="0" y1="${(H * 0.65).toFixed(1)}" x2="${W}" y2="${(H * 0.65).toFixed(1)}"/>
        <line class="viz-grid" x1="0" y1="${(H - padB).toFixed(1)}" x2="${W}" y2="${(H - padB).toFixed(1)}"/>
        <path class="viz-area" d="${areaD}" fill="url(#${gid})"/>
        <path class="viz-line" d="${lineD}"/>
        <line class="viz-guide" x1="${last.x.toFixed(1)}" y1="${padT - 2}" x2="${last.x.toFixed(1)}" y2="${H - padB}"/>
        <circle class="viz-halo" cx="${last.x.toFixed(1)}" cy="${last.y.toFixed(1)}" r="7" fill="var(--kpi-c)" opacity="0.18"/>
        <circle class="viz-dot" cx="${last.x.toFixed(1)}" cy="${last.y.toFixed(1)}" r="3.8" fill="var(--surface)" stroke="var(--kpi-c)" stroke-width="2.2"/>
        ${xLabels}
        ${hits}
      </svg>`);

    const tip = el.querySelector('.viz-tip');
    const dot = el.querySelector('.viz-dot');
    const halo = el.querySelector('.viz-halo');
    const guide = el.querySelector('.viz-guide');
    if (!tip || !dot) return;

    const focusPoint = (p, active) => {
      dot.setAttribute('cx', p.x.toFixed(1));
      dot.setAttribute('cy', p.y.toFixed(1));
      dot.setAttribute('r', active ? '4.6' : '3.8');
      halo.setAttribute('cx', p.x.toFixed(1));
      halo.setAttribute('cy', p.y.toFixed(1));
      guide.setAttribute('x1', p.x.toFixed(1));
      guide.setAttribute('x2', p.x.toFixed(1));
      guide.classList.toggle('show', active);
    };

    el.querySelectorAll('.viz-hit').forEach(hit => {
      hit.addEventListener('pointerenter', () => {
        const p = pts[+hit.dataset.i];
        focusPoint(p, true);
        tip.innerHTML = `${esc(p.t)} · <b>${opts.fmt ? opts.fmt(p.v, p.extra) : fa(p.v)}</b>`;
        positionTip(el, tip, p.x / W);
        tip.classList.add('show');
      });
    });
    el.onpointerleave = () => {
      focusPoint(last, false);
      tip.classList.remove('show');
    };
  }

  function renderKpiCharts() {
    const s = state.stats;
    const zero7 = [
      { t: '—', v: 0 }, { t: '—', v: 0 }, { t: '—', v: 0 },
      { t: '—', v: 0 }, { t: '—', v: 0 }, { t: '—', v: 0 }, { t: 'امروز', v: 0 }
    ];

    drawBarChart($('#chartOrders'), (s && s.series7 && s.series7.length) ? s.series7.map(d => ({ t: d.label, v: d.count })) : zero7, {
      label: 'نمودار تعداد سفارش‌های ۷ روز اخیر',
      fmt: v => `${fa(v)} سفارش`
    });
    drawBarChart($('#chartSales'), (s && s.series7 && s.series7.length) ? s.series7.map(d => ({ t: d.label, v: Math.round(d.sales) })) : zero7, {
      label: 'نمودار فروش ۷ روز اخیر',
      fmt: v => money(v)
    });
    drawBarChart($('#chartPending'), (s && s.pendingSeries7 && s.pendingSeries7.length) ? s.pendingSeries7.map(d => ({ t: d.label, v: d.count })) : zero7, {
      label: 'نمودار سفارش‌های در انتظار ۷ روز اخیر',
      fmt: v => `${fa(v)} سفارش`
    });
    drawBarChart($('#chartAov'), (s && s.aovSeries && s.aovSeries.length) ? s.aovSeries.map(d => ({ t: d.label, v: Math.round(d.value) })) : zero7, {
      label: 'نمودار میانگین ارزش سفارش ۳۰ روزه',
      fmt: v => money(v)
    });
  }

  /* ================= Section Navigation ================= */
  function switchSection(name, pushHistory = true) {
    const allowed = ['orders', 'customers', 'products', 'reports', 'settings'];
    if (!allowed.includes(name)) name = 'orders';
    state.section = name;
    state.pageOrderId = null;
    state.pageOrderDetail = null;
    closeFloatingPortals();

    if (pushHistory !== null) {
      syncOrderUrl(null, !!pushHistory);
    }

    $$('.app-section').forEach(s => s.classList.toggle('active', s.id === 'section-' + name));
    $$('[data-section]').forEach(a => {
      if (a.dataset.section === name) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });

    updateBulkBar();

    const titles = { orders: 'سفارش‌ها', customers: 'مشتریان', products: 'محصولات', reports: 'گزارش‌ها', settings: 'تنظیمات' };
    const crumb = document.querySelector('.crumbs .crumb-text');
    if (crumb) crumb.textContent = titles[name] || 'سفارش‌بان';

    if (name === 'customers' && !state.customersLoaded) loadCustomers();
    if (name === 'products' && !state.productsLoaded) loadProducts();
    if (name === 'reports') loadReports(state.reportDays);
    if (name === 'orders') setTimeout(renderKpiCharts, 50);
  }

  $$('[data-section]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    switchSection(a.dataset.section);
  }));

  /* ================= Customers Section ================= */
  async function loadCustomers() {
    const body = $('#customersBody');
    if (body) body.innerHTML = '<tr><td colspan="8" class="muted" style="padding:24px;text-align:center">در حال بارگذاری مشتریان…</td></tr>';
    try {
      const data = await api('sefareshban_get_customers', {
        page: state.customersPage,
        per_page: state.customersPer,
        search: state.customersQuery
      });
      state.customersLoaded = true;
      state.customers = data.rows || [];
      state.customersTotal = data.total || 0;
      renderCustomers();
    } catch (e) {
      console.error(e);
      if (body) body.innerHTML = `<tr><td colspan="8" style="padding:24px;text-align:center;color:var(--danger)">${esc(e.message || 'خطا در دریافت مشتریان')}</td></tr>`;
    }
  }

  function renderCustomers() {
    const body = $('#customersBody');
    if (!body) return;
    const rows = state.customers;
    const total = state.customersTotal;

    const infoEl = $('#customersTotalInfo');
    if (infoEl) infoEl.textContent = total ? `${fa(total)} مشتری شناسایی‌شده` : '';
    const pInfo = $('#customersPagerInfo');
    if (pInfo) pInfo.textContent = total ? `${fa(total)} مشتری` : '';
    const pWrap = $('#customersPages');
    if (pWrap) pWrap.innerHTML = buildPagerHtml(state.customersPage, total, state.customersPer);

    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="8" class="muted" style="padding:28px;text-align:center">مشتری‌ای با این مشخصات یافت نشد.</td></tr>';
      return;
    }

    body.innerHTML = rows.map(c => {
      const ch = Array.from(c.name || 'م')[0] || 'م';
      const searchVal = c.email || c.phone || c.name || '';
      return `
        <tr data-cust-filter="${esc(searchVal)}">
          <td>
            <div class="cust">
              <span class="av" style="width:32px;height:32px;font-size:11.5px">${esc(ch)}</span>
              <div class="cust-info">
                <b>${esc(c.name || '—')}</b>
                <span class="cust-sub">${c.customerId ? `کاربر عضو (#${faId(c.customerId)})` : 'خریدار مهمان'}</span>
              </div>
            </div>
          </td>
          <td class="muted" dir="ltr" style="text-align:right">${esc(c.email || '—')}</td>
          <td class="muted num">${esc(faId(c.phone || '—'))}</td>
          <td class="muted">${esc(c.city || '—')}</td>
          <td class="muted num">${esc(c.lastOrder || '—')}</td>
          <td><span class="badge" data-status="processing">${fa(c.orders || 0)} سفارش</span></td>
          <td class="num" style="font-weight:600">${shortMoney(c.total || 0)}</td>
          <td class="col-actions">
            <button type="button" class="btn" style="height:28px;padding:0 10px;font-size:11.5px" data-view-cust-orders="${esc(searchVal)}">سفارش‌ها</button>
          </td>
        </tr>`;
    }).join('');
  }

  let custSearchTimer;
  $('#customerSearch')?.addEventListener('input', e => {
    clearTimeout(custSearchTimer);
    custSearchTimer = setTimeout(() => {
      state.customersQuery = e.target.value.trim();
      state.customersPage = 1;
      loadCustomers();
    }, 350);
  });
  $('#refreshCustomersBtn')?.addEventListener('click', () => { loadCustomers(); toast('لیست مشتریان به‌روز شد'); });
  $('#customersPages')?.addEventListener('click', e => {
    const b = e.target.closest('[data-page]');
    if (!b || b.disabled) return;
    state.customersPage = +b.dataset.page;
    loadCustomers();
  });
  $('#customersBody')?.addEventListener('click', e => {
    const btn = e.target.closest('[data-view-cust-orders]');
    const row = e.target.closest('tr[data-cust-filter]');
    const qVal = btn ? btn.dataset.viewCustOrders : (row ? row.dataset.custFilter : '');
    if (!qVal) return;
    state.q = qVal;
    state.status = 'all';
    state.page = 1;
    if ($('#q')) $('#q').value = qVal;
    switchSection('orders');
    renderChips();
    fetchOrders();
  });

  /* ================= Products Section ================= */
  async function loadProducts() {
    const body = $('#productsBody');
    if (body) body.innerHTML = '<tr><td colspan="6" class="muted" style="padding:24px;text-align:center">در حال بارگذاری محصولات…</td></tr>';
    try {
      const data = await api('sefareshban_get_products', {
        page: state.productsPage,
        per_page: state.productsPer,
        search: state.productQuery
      });
      state.productsLoaded = true;
      state.products = data.rows || [];
      state.productsTotal = data.total || 0;
      renderProducts();
    } catch (e) {
      console.error(e);
      if (body) body.innerHTML = `<tr><td colspan="6" style="padding:24px;text-align:center;color:var(--danger)">${esc(e.message || 'خطا در دریافت محصولات')}</td></tr>`;
    }
  }

  function renderProducts() {
    const body = $('#productsBody');
    if (!body) return;
    const rows = state.products || [];
    const total = state.productsTotal || 0;

    const infoEl = $('#productsTotalInfo');
    if (infoEl) infoEl.textContent = total ? `${fa(total)} محصول در فروشگاه` : '';
    const pInfo = $('#productsPagerInfo');
    if (pInfo) pInfo.textContent = total ? `${fa(total)} محصول` : '';
    const pWrap = $('#productsPages');
    if (pWrap) pWrap.innerHTML = buildPagerHtml(state.productsPage, total, state.productsPer);

    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="6" class="muted" style="padding:28px;text-align:center">محصولی یافت نشد.</td></tr>';
      return;
    }

    body.innerHTML = rows.map(p => {
      const ch = Array.from(p.name || 'ک')[0] || 'ک';
      let stockHtml = '';
      if (p.stockStatus === 'outofstock') stockHtml = `<span class="badge" data-status="cancelled">ناموجود</span>`;
      else if (p.stock !== null) stockHtml = `<span class="num" style="font-weight:600">${fa(p.stock)} عدد</span>`;
      else stockHtml = `<span class="muted">موجود</span>`;
      const pubLabel = p.status === 'publish' ? 'منتشرشده' : (p.status === 'draft' ? 'پیش‌نویس' : p.status);
      return `
        <tr data-product-id="${p.id}">
          <td>
            <div class="cust">
              <div class="thumb">${p.img ? `<img src="${esc(p.img)}" alt="" style="width:100%;height:100%;object-fit:cover">` : esc(ch)}</div>
              <div class="cust-info">
                <b>${esc(p.name || '—')}</b>
                <span class="cust-sub">${p.sku ? `SKU: ${esc(p.sku)}` : `#${faId(p.id)}`}</span>
              </div>
            </div>
          </td>
          <td class="num" style="font-weight:600">${shortMoney(p.price || 0)}</td>
          <td>${stockHtml}</td>
          <td><span class="badge" data-status="${p.status === 'publish' ? 'completed' : 'onhold'}">${esc(pubLabel)}</span></td>
          <td class="num">${fa(p.total_sales || 0)} فروش</td>
          <td class="col-actions">
            <button type="button" class="dots" data-product-open="${p.id}" aria-label="جزئیات محصول">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
            </button>
          </td>
        </tr>`;
    }).join('');
  }

  function openProductModal(id) {
    const p = (state.products || []).find(x => +x.id === +id);
    if (!p) return;
    const drawer = $('#productDrawer');
    const stockTxt = p.stockStatus === 'outofstock'
      ? 'ناموجود'
      : (p.stock === null ? 'موجود (بدون مدیریت عددی انبار)' : `${fa(p.stock)} عدد در انبار`);

    drawer.innerHTML = `
      <div class="d-head">
        <div>
          <div class="t1" id="productDrawerTitle">${esc(p.name || 'محصول')}</div>
          <div class="t2 num">${p.sku ? 'کد محصول (SKU): ' + esc(p.sku) : 'شناسه: #' + faId(p.id)}</div>
        </div>
        <button type="button" class="circle" data-close-product aria-label="بستن">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="d-body">
        ${p.img ? `<div style="margin:8px 0 14px;display:flex;justify-content:center"><img src="${esc(p.img)}" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:16px;border:1px solid var(--border)"></div>` : ''}
        <section class="d-sec" style="padding-top:4px">
          <div class="d-label">وضعیت انتشار ${p.viewUrl ? `<a href="${esc(p.viewUrl)}" target="_blank" rel="noopener">مشاهده در سایت ↗</a>` : ''}</div>
          <span class="badge" data-status="${p.status === 'publish' ? 'completed' : 'onhold'}">${p.status === 'publish' ? 'منتشر شده' : 'پیش‌نویس'}</span>
        </section>
        <section class="d-sec">
          <div class="d-label">مشخصات قیمت و موجودی</div>
          <div class="totals">
            <div class="r"><span>قیمت فعلی</span><b class="num">${money(p.price || 0)}</b></div>
            ${p.salePrice !== null && p.regularPrice > p.salePrice ? `<div class="r"><span>قیمت قبل از تخفیف</span><b class="num" style="text-decoration:line-through">${money(p.regularPrice)}</b></div>` : ''}
            <div class="r"><span>وضعیت انبار</span><b class="num">${esc(stockTxt)}</b></div>
            <div class="r"><span>تعداد کل فروش</span><b class="num">${fa(p.total_sales || 0)} عدد</b></div>
            <div class="r"><span>شناسه محصول</span><b class="num">#${faId(p.id)}</b></div>
          </div>
        </section>
      </div>
      <div class="d-foot">
        <button type="button" class="btn" data-close-product>بستن</button>
        ${p.editUrl ? `<a class="btn primary" href="${esc(p.editUrl)}" target="_blank" rel="noopener">ویرایش محصول در ووکامرس</a>` : ''}
      </div>`;
    drawer.classList.add('open');
    $('#productScrim')?.classList.add('show');
  }

  function closeProductModal() {
    $('#productDrawer')?.classList.remove('open');
    $('#productScrim')?.classList.remove('show');
  }

  let prodSearchTimer;
  $('#productSearch')?.addEventListener('input', e => {
    clearTimeout(prodSearchTimer);
    prodSearchTimer = setTimeout(() => {
      state.productQuery = e.target.value.trim();
      state.productsPage = 1;
      loadProducts();
    }, 350);
  });
  $('#refreshProductsBtn')?.addEventListener('click', () => { loadProducts(); toast('لیست محصولات به‌روز شد'); });
  $('#newProductBtn')?.addEventListener('click', () => {
    window.open(SB.newProductUrl || ((SB.adminUrl || '/wp-admin/') + 'post-new.php?post_type=product'), '_blank');
  });
  $('#productsPages')?.addEventListener('click', e => {
    const b = e.target.closest('[data-page]');
    if (!b || b.disabled) return;
    state.productsPage = +b.dataset.page;
    loadProducts();
  });
  $('#productsBody')?.addEventListener('click', e => {
    const row = e.target.closest('tr[data-product-id]');
    if (row) openProductModal(row.dataset.productId);
  });
  $('#productScrim')?.addEventListener('click', closeProductModal);
  $('#productDrawer')?.addEventListener('click', e => {
    if (e.target.closest('[data-close-product]')) closeProductModal();
  });

  /* ================= Comprehensive Reports Section ================= */
  async function loadReports(days = 30) {
    state.reportDays = days;
    $$('#reportRangeTabs [data-rep-days]').forEach(btn => {
      btn.setAttribute('aria-selected', String(+btn.dataset.repDays === +days));
    });
    const subEl = $('#repChartSubtitle');
    if (subEl) subEl.textContent = 'در حال دریافت داده‌های تحلیلی…';
    try {
      const r = await api('sefareshban_get_reports', { days });
      state.reportData = r;
      renderReports(r);
    } catch (e) {
      console.error(e);
      if (subEl) subEl.textContent = 'خطا در بارگذاری گزارش‌ها';
      toast(e.message || 'خطا در دریافت گزارش‌ها');
    }
  }

  function renderProgressList(hostSelector, rows, opts) {
    const host = $(hostSelector);
    if (!host) return;
    if (!rows || !rows.length) {
      host.innerHTML = `<div class="muted" style="padding:14px 0">${esc(opts.emptyText || 'داده‌ای در این بازه ثبت نشده است.')}</div>`;
      return;
    }
    const maxVal = Math.max(1, ...rows.map(r => Number(opts.getValue(r)) || 0));
    host.innerHTML = rows.map(r => {
      const val = Number(opts.getValue(r)) || 0;
      const pct = Math.max(3, Math.round((val / maxVal) * 100));
      return `
        <div class="rep-row">
          <div class="rep-row-top">
            <span class="rep-row-title">${opts.renderTitle(r)}</span>
            <span class="rep-row-meta num">${opts.renderMeta(r)}</span>
          </div>
          <div class="rep-bar-track">
            <div class="rep-bar-fill" style="width:${pct}%;${opts.color ? `background:${opts.color}` : ''}"></div>
          </div>
        </div>`;
    }).join('');
  }

  function renderReports(r) {
    if (!r) return;
    const setHtml = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };

    setHtml('repGrossSales', shortMoney(r.grossSales || 0));
    setHtml('repNetSalesSub', `فروش خالص: <b class="num" style="color:var(--ink-2)">${money(r.netSales || 0)}</b>`);
    setHtml('repPaidOrders', `${fa(r.paidOrdersCount || 0)} <small>سفارش</small>`);
    setHtml('repAllOrdersSub', `کل سفارش‌های ثبتی بازه: <b class="num" style="color:var(--ink-2)">${fa(r.allOrdersCount || 0)}</b>`);
    setHtml('repAov', shortMoney(r.aov || 0));
    setHtml('repItemsSoldSub', `تعداد اقلام فروخته‌شده: <b class="num" style="color:var(--ink-2)">${fa(r.itemsSold || 0)} قلم</b>`);
    setHtml('repShippingTotal', shortMoney(r.shippingTotal || 0));
    setHtml('repDiscountSub', `مجموع تخفیف‌ها: <b class="num" style="color:var(--ink-2)">${money(r.discountTotal || 0)}</b>`);

    const subEl = $('#repChartSubtitle');
    if (subEl) subEl.textContent = `روند درآمد سفارش‌های پرداخت‌شده در ${fa(r.days)} روز گذشته`;

    const chartItems = (r.chart || []).map(c => ({ t: c.label, v: c.sales, count: c.count }));
    if (chartItems.length >= 2) {
      drawAreaChart($('#repMainChart'), chartItems, {
        height: 175,
        label: 'نمودار روند فروش',
        fmt: (v, extra) => `${money(v)} (${fa(extra?.count || 0)} سفارش)`
      });
    } else if (chartItems.length === 1) {
      drawBarChart($('#repMainChart'), chartItems, {
        height: 175,
        label: 'نمودار روند فروش',
        fmt: v => money(v)
      });
    }

    renderProgressList('#repStatusList', r.byStatus || [], {
      emptyText: 'سفارشی در این بازه وجود ندارد.',
      getValue: x => x.count,
      renderTitle: x => `<span class="badge" data-status="${esc(x.status)}">${esc(x.label)}</span>`,
      renderMeta: x => `<b>${fa(x.count)} سفارش</b> <span class="muted">(${money(x.total)})</span>`,
      color: 'var(--c-indigo)'
    });
    renderProgressList('#repGatewayList', r.byGateway || [], {
      emptyText: 'تراکنش موفقی در این بازه ثبت نشده است.',
      getValue: x => x.total,
      renderTitle: x => esc(x.title),
      renderMeta: x => `<b>${money(x.total)}</b> <span class="muted">(${fa(x.count)})</span>`,
      color: 'var(--c-emerald)'
    });
    renderProgressList('#repTopProducts', r.topProducts || [], {
      emptyText: 'فروش محصولی در این بازه ثبت نشده است.',
      getValue: x => x.total,
      renderTitle: x => esc(x.name),
      renderMeta: x => `<b>${money(x.total)}</b> <span class="muted">(${fa(x.qty)} عدد)</span>`,
      color: 'var(--c-rose)'
    });
    renderProgressList('#repTopCities', r.topCities || [], {
      emptyText: 'اطلاعات شهری برای سفارش‌ها ثبت نشده است.',
      getValue: x => x.count,
      renderTitle: x => esc(x.city),
      renderMeta: x => `<b>${fa(x.count)} سفارش</b> <span class="muted">(${money(x.total)})</span>`,
      color: 'var(--c-amber)'
    });
  }

  $('#reportRangeTabs')?.addEventListener('click', e => {
    const btn = e.target.closest('[data-rep-days]');
    if (btn) loadReports(+btn.dataset.repDays);
  });

  /* ================= Events, Filters, Quick Dates, Dropdowns ================= */
  $('#chips')?.addEventListener('click', e => {
    const t = e.target.closest('[data-tab]');
    if (!t) return;
    state.status = t.dataset.tab;
    state.page = 1;
    state.selected.clear();
    renderChips();
    fetchOrders();
  });

  // Quick Date Range Presets
  $('#quickDateBar')?.addEventListener('click', e => {
    const btn = e.target.closest('[data-qdate]');
    if (!btn) return;
    const mode = btn.dataset.qdate;
    state.quickDate = mode;
    $$('#quickDateBar [data-qdate]').forEach(b => b.classList.toggle('active', b.dataset.qdate === mode));

    let fromIso = '';
    let toIso = '';
    if (mode === 'today') {
      fromIso = toIso = localTodayIso(0);
    } else if (mode === 'yesterday') {
      fromIso = toIso = localTodayIso(-1);
    } else if (mode === '7d') {
      fromIso = localTodayIso(-6);
      toIso = localTodayIso(0);
    } else if (mode === 'this_month') {
      const now = new Date();
      const [jy, jm] = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
      const [fGy, fGm, fGd] = jalaliToGregorian(jy, jm, 1);
      fromIso = `${fGy}-${pad2(fGm)}-${pad2(fGd)}`;
      toIso = localTodayIso(0);
    }

    state.filters.dateFrom = fromIso;
    state.filters.dateTo = toIso;
    if ($('#fltDateFrom')) $('#fltDateFrom').value = fromIso;
    if ($('#fltDateTo')) $('#fltDateTo').value = toIso;
    if ($('#fltDateFromDisplay')) $('#fltDateFromDisplay').value = isoToJalaliDisplay(fromIso);
    if ($('#fltDateToDisplay')) $('#fltDateToDisplay').value = isoToJalaliDisplay(toIso);

    state.page = 1;
    updateFilterIndicator();
    fetchOrders();
  });

  let searchTimer;
  $('#q')?.addEventListener('input', e => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.q = e.target.value.trim();
      state.page = 1;
      fetchOrders();
    }, 320);
  });

  const perPage = $('#perPage');
  if (perPage) {
    perPage.value = String(state.per);
    perPage.addEventListener('change', e => {
      state.per = +e.target.value;
      state.page = 1;
      state.selected.clear();
      fetchOrders();
    });
  }

  // Populate gateways, shipping methods, states in filters & quick order drawer
  if (paymentGateways.length) {
    const gwHtml = paymentGateways.map(g => `<option value="${esc(g.id)}">${esc(g.title)}</option>`).join('');
    $('#fltPayment')?.insertAdjacentHTML('beforeend', gwHtml);
    $('#qoPayment')?.insertAdjacentHTML('beforeend', gwHtml);
  }
  if (shippingMethods.length) {
    $('#sbShippingList')?.insertAdjacentHTML('beforeend', shippingMethods.map(m => `<option value="${esc(m.title)}"></option>`).join(''));
  }
  if (iranStates.length) {
    const stHtml = iranStates.map(s => `<option value="${esc(s.code)}">${esc(s.name)}</option>`).join('');
    $('#fltState')?.insertAdjacentHTML('beforeend', stHtml);
    $('#qoState')?.insertAdjacentHTML('beforeend', stHtml);
  }
  if ($('#qoStatus')) {
    $('#qoStatus').innerHTML = Object.keys(STATUS).map(k =>
      `<option value="${esc(k)}" ${k === 'processing' ? 'selected' : ''}>${esc(STATUS[k].label)}</option>`
    ).join('');
  }

  // Advanced Filter toggle & apply
  const filterBtn = $('#filterBtn');
  const advFilters = $('#advFilters');
  if (filterBtn && advFilters) {
    filterBtn.addEventListener('click', () => {
      const open = !advFilters.hidden;
      advFilters.hidden = open;
      filterBtn.setAttribute('aria-expanded', String(!open));
    });
  }

  $('#applyAdvFilters')?.addEventListener('click', () => {
    state.filters.dateFrom = $('#fltDateFrom')?.value || '';
    state.filters.dateTo = $('#fltDateTo')?.value || '';
    state.filters.paymentMethod = $('#fltPayment')?.value || '';
    state.filters.shippingFilter = $('#fltShipping')?.value.trim() || '';
    state.filters.stateFilter = $('#fltState')?.value || '';
    state.filters.cityFilter = $('#fltCity')?.value.trim() || '';
    state.filters.minTotal = $('#fltMinTotal')?.value.trim() || '';
    state.filters.maxTotal = $('#fltMaxTotal')?.value.trim() || '';
    state.filters.onlyWithNote = !!$('#fltOnlyWithNote')?.checked;
    state.filters.onlyDelayed = !!$('#fltOnlyDelayed')?.checked;
    state.quickDate = '';
    $$('#quickDateBar [data-qdate]').forEach(b => b.classList.toggle('active', (!state.filters.dateFrom && !state.filters.dateTo && b.dataset.qdate === 'all')));
    state.page = 1;
    updateFilterIndicator();
    fetchOrders();
    toast('فیلترهای پیشرفته اعمال شد');
  });

  function clearAllOrderFilters() {
    state.q = '';
    state.status = 'all';
    state.quickDate = 'all';
    state.page = 1;
    state.filters = {
      dateFrom: '', dateTo: '', paymentMethod: '', shippingFilter: '',
      stateFilter: '', cityFilter: '', minTotal: '', maxTotal: '',
      onlyWithNote: false, onlyDelayed: false
    };
    ['#q', '#fltDateFrom', '#fltDateTo', '#fltDateFromDisplay', '#fltDateToDisplay', '#fltPayment', '#fltShipping', '#fltState', '#fltCity', '#fltMinTotal', '#fltMaxTotal'].forEach(sel => {
      if ($(sel)) $(sel).value = '';
    });
    if ($('#fltOnlyWithNote')) $('#fltOnlyWithNote').checked = false;
    if ($('#fltOnlyDelayed')) $('#fltOnlyDelayed').checked = false;
    $$('#quickDateBar [data-qdate]').forEach(b => b.classList.toggle('active', b.dataset.qdate === 'all'));
    updateFilterIndicator();
    renderChips();
    fetchOrders();
  }

  $('#resetAdvFilters')?.addEventListener('click', clearAllOrderFilters);
  $('#clearFilters')?.addEventListener('click', clearAllOrderFilters);

  // Column visibility menu
  const colToggleBtn = $('#colToggleBtn');
  const colToggleMenu = $('#colToggleMenu');
  if (colToggleBtn && colToggleMenu) {
    colToggleBtn.addEventListener('click', e => {
      e.stopPropagation();
      $('#moreMenu')?.classList.remove('open');
      const isOpen = colToggleMenu.classList.toggle('open');
      colToggleBtn.setAttribute('aria-expanded', String(isOpen));
    });
    colToggleMenu.addEventListener('change', e => {
      const col = e.target.dataset?.colToggle;
      if (!col) return;
      if (e.target.checked) state.visibleCols.add(col);
      else state.visibleCols.delete(col);
      localStorage.setItem('sb-visible-cols', JSON.stringify(Array.from(state.visibleCols)));
      applyColumnVisibility();
    });
    colToggleMenu.addEventListener('click', e => e.stopPropagation());
  }

  // More actions popover menu
  const moreBtn = $('#moreBtn');
  const moreMenu = $('#moreMenu');
  if (moreBtn && moreMenu) {
    moreBtn.addEventListener('click', e => {
      e.stopPropagation();
      colToggleMenu?.classList.remove('open');
      const isOpen = moreMenu.classList.toggle('open');
      moreBtn.setAttribute('aria-expanded', String(isOpen));
    });
    moreMenu.addEventListener('click', e => {
      const act = e.target.closest('[data-more-action]')?.dataset.moreAction;
      if (!act) return;
      moreMenu.classList.remove('open');
      moreBtn.setAttribute('aria-expanded', 'false');
      if (act === 'quick-order') {
        openQuickOrderDrawer();
      } else if (act === 'new-order-wc') {
        window.open(SB.newOrderUrl || ((SB.adminUrl || '/wp-admin/') + 'post-new.php?post_type=shop_order'), '_blank');
      } else if (act === 'native-wc') {
        window.location.href = SB.wcOrdersUrl || ((SB.adminUrl || '/wp-admin/') + 'edit.php?post_type=shop_order&sefareshban_native=1');
      } else if (act === 'refresh-all') {
        fetchOrders();
        fetchStats();
        toast(i18n.refresh || 'فهرست سفارش‌ها به‌روز شد');
      } else if (act === 'go-reports') {
        switchSection('reports');
      }
    });
  }

  document.addEventListener('click', e => {
    if (!e.target.closest('.pop-wrap')) {
      moreMenu?.classList.remove('open');
      moreBtn?.setAttribute('aria-expanded', 'false');
      colToggleMenu?.classList.remove('open');
      colToggleBtn?.setAttribute('aria-expanded', 'false');
    }
    if (!e.target.closest('#inlineStatusMenu') && !e.target.closest('[data-inline-status]')) {
      const ism = $('#inlineStatusMenu');
      if (ism) ism.hidden = true;
      activeInlineOrderId = null;
    }
    if (!e.target.closest('#jalaliPickerPop') && !e.target.closest('.jalali-input')) {
      const jp = $('#jalaliPickerPop');
      if (jp) jp.hidden = true;
      activeJalaliTarget = null;
    }
    if (!e.target.closest('.qo-search-wrap')) {
      const qor = $('#qoProdResults');
      if (qor) qor.hidden = true;
    }
  });

  // Hide floating popovers on scroll
  $('.content')?.addEventListener('scroll', closeFloatingPortals, { passive: true });
  $('.table-scroll')?.addEventListener('scroll', closeFloatingPortals, { passive: true });

  // Table row click, inline status, hover items preview, single restore
  const tbody = $('#tbody');
  if (tbody) {
    tbody.addEventListener('click', e => {
      const chk = e.target.closest('[data-check]');
      if (chk) {
        const id = +chk.dataset.check;
        chk.checked ? state.selected.add(id) : state.selected.delete(id);
        chk.closest('tr')?.classList.toggle('is-selected', chk.checked);
        const ids = state.rows.map(o => o.id);
        const all = ids.length > 0 && ids.every(i => state.selected.has(i));
        const some = ids.some(i => state.selected.has(i));
        if ($('#checkAll')) {
          $('#checkAll').checked = all;
          $('#checkAll').indeterminate = !all && some;
        }
        updateBulkBar();
        return;
      }
      const inlineBtn = e.target.closest('[data-inline-status]');
      if (inlineBtn) {
        e.preventDefault();
        e.stopPropagation();
        openInlineStatusMenu(inlineBtn.dataset.inlineStatus, inlineBtn);
        return;
      }
      const restoreBtn = e.target.closest('[data-restore-single]');
      if (restoreBtn) {
        e.preventDefault();
        e.stopPropagation();
        restoreSingleOrder(+restoreBtn.dataset.restoreSingle);
        return;
      }
      const peek = e.target.closest('[data-quick-peek]');
      if (peek) {
        e.preventDefault();
        e.stopPropagation();
        openOrder(peek.dataset.quickPeek);
        return;
      }
      const open = e.target.closest('[data-open]');
      if (open) { e.preventDefault(); openOrderPage(open.dataset.open); return; }
      const row = e.target.closest('tr[data-id]');
      if (row) openOrderPage(row.dataset.id);
    });

    tbody.addEventListener('pointerover', e => {
      const cell = e.target.closest('[data-preview-id]');
      if (cell) showItemsPreview(cell.dataset.previewId, cell);
    });
    tbody.addEventListener('pointerout', e => {
      const cell = e.target.closest('[data-preview-id]');
      if (cell && !cell.contains(e.relatedTarget)) {
        const pop = $('#itemsPreviewPop');
        if (pop) pop.hidden = true;
      }
    });
  }

  $('#checkAll')?.addEventListener('change', e => {
    state.rows.forEach(o => e.target.checked ? state.selected.add(o.id) : state.selected.delete(o.id));
    renderTable();
  });

  $('#pages')?.addEventListener('click', e => {
    const b = e.target.closest('[data-page]');
    if (!b || b.disabled) return;
    const p = +b.dataset.page;
    const max = Math.max(1, Math.ceil(state.total / state.per));
    if (p < 1 || p > max) return;
    state.page = p;
    fetchOrders();
  });

  $$('#tbl th[data-sort]').forEach(th => th.addEventListener('click', () => {
    const k = th.dataset.sort;
    if (state.sort.key === k) state.sort.dir = state.sort.dir === 'asc' ? 'desc' : 'asc';
    else state.sort = { key: k, dir: 'desc' };
    state.page = 1;
    fetchOrders();
  }));

  $('#bulk')?.addEventListener('click', e => {
    const b = e.target.closest('[data-bulk]');
    if (b) doBulk(b.dataset.bulk);
  });

  $('#newOrderBtn')?.addEventListener('click', openQuickOrderDrawer);
  $('#expandBtn')?.addEventListener('click', () => {
    fetchOrders();
    fetchStats();
    toast(i18n.refresh || 'فهرست سفارش‌ها به‌روز شد');
  });

  $('#sidebarPromo button')?.addEventListener('click', () => {
    switchSection('orders');
    const stCounts = state.statusCounts || {};
    if ((stCounts.pending || 0) > 0) state.status = 'pending';
    else if ((stCounts.processing || 0) > 0) state.status = 'processing';
    else if ((stCounts.onhold || 0) > 0) state.status = 'onhold';
    else state.status = 'all';
    state.page = 1;
    state.selected.clear();
    renderChips();
    fetchOrders();
    document.querySelector('.content')?.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // Sound, Theme & Smooth Density Toggles
  function syncSoundBtnUi() {
    const btn = $('#soundBtn');
    const ic = $('#soundIcon');
    if (btn) btn.setAttribute('aria-pressed', String(state.soundAlert));
    if (ic) {
      ic.innerHTML = state.soundAlert
        ? '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>'
        : '<path d="M13.73 21a2 2 0 01-3.46 0"/><path d="M18.63 13A17.89 17.89 0 0118 8M6.26 6.26A5.86 5.86 0 006 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 00-9.33-5"/><path d="M1 1l22 22"/>';
    }
  }

  $('#soundBtn')?.addEventListener('click', () => {
    state.soundAlert = !state.soundAlert;
    localStorage.setItem('sb-sound-alert', state.soundAlert ? '1' : '0');
    if ($('#setSoundAlert')) $('#setSoundAlert').checked = state.soundAlert;
    syncSoundBtnUi();
    if (state.soundAlert) playNotificationChime();
    toast(state.soundAlert ? 'اعلان صوتی سفارش جدید فعال شد' : 'اعلان صوتی بی‌صدا شد');
  });

  $('#testSoundBtn')?.addEventListener('click', () => {
    playNotificationChime();
    toast('صدای اعلان پخش شد ♪');
  });

  $('#themeBtn')?.addEventListener('click', () => {
    const root = document.getElementById('sefareshbanRoot');
    if (!root) return;
    const dark = root.dataset.theme === 'dark';
    root.dataset.theme = dark ? 'light' : 'dark';
    localStorage.setItem('woo-theme', root.dataset.theme);
    const ic = $('#themeIcon');
    if (ic) ic.innerHTML = dark
      ? '<path d="M21 12.8A8.5 8.5 0 1111.2 3a6.6 6.6 0 009.8 9.8z"/>'
      : '<circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/>';
  });

  function syncDensityIcon(isCompact) {
    const ic = $('#densityIcon');
    if (!ic) return;
    ic.innerHTML = isCompact
      ? '<path d="M4 5h16M4 9.5h16M4 14h16M4 18.5h16"/>'
      : '<path d="M4 6h16M4 12h16M4 18h16"/>';
  }

  $('#densityBtn')?.addEventListener('click', e => {
    const root = document.getElementById('sefareshbanRoot');
    if (!root) return;
    const nextCompact = root.dataset.density !== 'compact';
    root.dataset.density = nextCompact ? 'compact' : 'comfortable';
    e.currentTarget.setAttribute('aria-pressed', String(nextCompact));
    syncDensityIcon(nextCompact);
    localStorage.setItem('woo-density', root.dataset.density);
    toast(nextCompact ? 'چگالی جدول: حالت فشرده' : 'چگالی جدول: حالت راحت');
  });

  // Order Drawer Event Delegation
  $('#scrim')?.addEventListener('click', closeDrawer);
  const drawer = $('#drawer');
  if (drawer) {
    drawer.addEventListener('click', e => {
      if (e.target.closest('[data-close]')) { closeDrawer(); return; }

      const fullPageBtn = e.target.closest('[data-open-full-page]');
      if (fullPageBtn) {
        const oid = +fullPageBtn.dataset.openFullPage;
        const preload = (state.openDetail && +state.openDetail.id === oid) ? state.openDetail : null;
        openOrderPage(oid, true, preload);
        return;
      }

      const copyBtn = e.target.closest('[data-copy-val]');
      if (copyBtn) {
        copyToClipboard(copyBtn.dataset.copyVal, copyBtn, copyBtn.dataset.copyLbl || 'اطلاعات');
        return;
      }

      const pr = e.target.closest('[data-print]');
      if (pr) {
        if (state.openDetail && +state.openDetail.id === +pr.dataset.print) openInvoicePrintWindow([state.openDetail]);
        else printOrdersByIds([+pr.dataset.print], 'invoice');
        return;
      }

      const prLbl = e.target.closest('[data-print-label]');
      if (prLbl) {
        if (state.openDetail && +state.openDetail.id === +prLbl.dataset.printLabel) openShippingLabelWindow([state.openDetail]);
        else printOrdersByIds([+prLbl.dataset.printLabel], 'label');
        return;
      }

      const restoreDrw = e.target.closest('[data-restore-drawer]');
      if (restoreDrw) {
        restoreSingleOrder(+restoreDrw.dataset.restoreDrawer);
        return;
      }

      if (e.target.closest('#toggleRefundBtn')) {
        const rf = $('#refundForm');
        if (rf) rf.hidden = !rf.hidden;
        return;
      }

      const fillMax = e.target.closest('#fillMaxRefundBtn');
      if (fillMax && $('#refAmount')) {
        $('#refAmount').value = fillMax.dataset.max || '';
      }
    });

    drawer.addEventListener('change', e => {
      if (e.target.id === 'statusSelect' && state.openId) setStatus(state.openId, e.target.value);
    });

    drawer.addEventListener('submit', e => {
      if (e.target.id === 'noteForm') {
        e.preventDefault();
        const input = $('#noteInput');
        const isCust = $('#noteToCustomer')?.checked || false;
        const txt = input ? input.value.trim() : '';
        if (!txt || !state.openId) return;
        addNote(state.openId, txt, isCust);
      } else if (e.target.id === 'trackingForm') {
        e.preventDefault();
        if (state.openId) saveTracking(state.openId);
      } else if (e.target.id === 'refundForm') {
        e.preventDefault();
        if (state.openId) submitRefund(state.openId);
      }
    });
  }

  // Dedicated Single Order Page Event Delegation (#orderPageHost)
  const orderPageHost = $('#orderPageHost');
  if (orderPageHost) {
    orderPageHost.addEventListener('click', e => {
      if (e.target.closest('[data-od-back]')) {
        switchSection('orders');
        return;
      }

      const navOrd = e.target.closest('[data-od-open]');
      if (navOrd && !navOrd.disabled && navOrd.dataset.odOpen) {
        openOrderPage(+navOrd.dataset.odOpen);
        return;
      }

      const stepBtn = e.target.closest('[data-od-step]');
      if (stepBtn && state.pageOrderId) {
        const nextSt = stepBtn.dataset.odStep;
        if (nextSt && (!state.pageOrderDetail || state.pageOrderDetail.status !== nextSt)) {
          setStatus(state.pageOrderId, nextSt);
        }
        return;
      }

      if (e.target.closest('#odSaveStatusBtn') && state.pageOrderId) {
        const nextSt = $('#odStatusSelect')?.value;
        if (nextSt) setStatus(state.pageOrderId, nextSt);
        return;
      }

      const copyBtn = e.target.closest('[data-copy-val]');
      if (copyBtn) {
        copyToClipboard(copyBtn.dataset.copyVal, copyBtn, copyBtn.dataset.copyLbl || 'اطلاعات');
        return;
      }

      const pr = e.target.closest('[data-od-print]');
      if (pr) {
        const oid = +pr.dataset.odPrint;
        if (state.pageOrderDetail && +state.pageOrderDetail.id === oid) openInvoicePrintWindow([state.pageOrderDetail]);
        else printOrdersByIds([oid], 'invoice');
        return;
      }

      const prLbl = e.target.closest('[data-od-label]');
      if (prLbl) {
        const oid = +prLbl.dataset.odLabel;
        if (state.pageOrderDetail && +state.pageOrderDetail.id === oid) openShippingLabelWindow([state.pageOrderDetail]);
        else printOrdersByIds([oid], 'label');
        return;
      }

      if (e.target.closest('#odToggleEditBtn') || e.target.closest('#odCancelEditBtn')) {
        const form = $('#odEditDetailsForm');
        if (form) form.hidden = !form.hidden;
        return;
      }

      if (e.target.closest('#odToggleRefundBtn')) {
        const rf = $('#odRefundForm');
        if (rf) rf.hidden = !rf.hidden;
        return;
      }

      const fillMax = e.target.closest('#odFillMaxRefundBtn');
      if (fillMax && $('#odRefAmount')) {
        $('#odRefAmount').value = fillMax.dataset.max || '';
        return;
      }

      const delNoteBtn = e.target.closest('[data-od-del-note]');
      if (delNoteBtn && state.pageOrderId) {
        deleteNote(state.pageOrderId, +delNoteBtn.dataset.odDelNote);
        return;
      }

      const restoreBtn = e.target.closest('[data-od-restore]');
      if (restoreBtn) {
        restoreSingleOrder(+restoreBtn.dataset.odRestore);
        return;
      }

      const trashBtn = e.target.closest('[data-od-trash]');
      if (trashBtn) {
        trashSingleOrder(+trashBtn.dataset.odTrash);
        return;
      }

      const custOrdersBtn = e.target.closest('[data-od-cust-orders]');
      if (custOrdersBtn) {
        const qVal = custOrdersBtn.dataset.odCustOrders || '';
        if (!qVal) return;
        state.q = qVal;
        state.status = 'all';
        state.page = 1;
        if ($('#q')) $('#q').value = qVal;
        switchSection('orders');
        renderChips();
        fetchOrders();
      }
    });

    orderPageHost.addEventListener('submit', e => {
      if (e.target.id === 'odNoteForm') {
        e.preventDefault();
        const input = $('#odNoteInput');
        const isCust = $('#odNoteToCustomer')?.checked || false;
        const txt = input ? input.value.trim() : '';
        if (!txt || !state.pageOrderId) return;
        addNote(state.pageOrderId, txt, isCust, true);
      } else if (e.target.id === 'odTrackingForm') {
        e.preventDefault();
        if (state.pageOrderId) saveTracking(state.pageOrderId, true);
      } else if (e.target.id === 'odRefundForm') {
        e.preventDefault();
        if (state.pageOrderId) submitRefund(state.pageOrderId, true);
      } else if (e.target.id === 'odEditDetailsForm') {
        e.preventDefault();
        if (state.pageOrderId) updateOrderDetails(state.pageOrderId);
      }
    });
  }

  // Browser Back/Forward URL sync
  window.addEventListener('popstate', () => {
    try {
      const params = new URLSearchParams(window.location.search || '');
      const oid = Number(params.get('order_id')) || 0;
      if (oid > 0) {
        openOrderPage(oid, null);
      } else if (state.section === 'order-detail') {
        switchSection('orders', null);
      }
    } catch (err) { /* ignore */ }
  });

  // Keyboard shortcuts
  document.addEventListener('keydown', e => {
    const tag = (e.target && e.target.tagName) ? e.target.tagName : '';
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(tag);
    if (e.key === 'Escape') {
      if (!$('#inlineStatusMenu')?.hidden || !$('#jalaliPickerPop')?.hidden) {
        closeFloatingPortals();
        if ($('#jalaliPickerPop')) $('#jalaliPickerPop').hidden = true;
        return;
      }
      if ($('#exportModal') && !$('#exportModal').hidden) { closeExportModal(); return; }
      if ($('#bulkStatusModal') && !$('#bulkStatusModal').hidden) { closeBulkStatusModal(); return; }
      if ($('#quickOrderDrawer')?.classList.contains('open')) { closeQuickOrderDrawer(); return; }
      if ($('#productDrawer')?.classList.contains('open')) { closeProductModal(); return; }
      if (state.openId) { closeDrawer(); return; }
    }
    if (e.key === '/' && !typing) {
      e.preventDefault();
      if (state.section === 'orders') $('#q')?.focus();
      else if (state.section === 'customers') $('#customerSearch')?.focus();
      else if (state.section === 'products') $('#productSearch')?.focus();
    }
  });

  /* ================= Settings & Preferences ================= */
  let autoRefreshTimer = null;

  function applySettings(s, updatePerPageNow = false) {
    if (!s) return;
    if (updatePerPageNow && s.per_page && [8, 12, 20, 50].includes(+s.per_page)) {
      state.per = +s.per_page;
      const pp = $('#perPage');
      if (pp) pp.value = String(state.per);
    }
    if (s.sound_alert !== undefined && localStorage.getItem('sb-sound-alert') === null) {
      state.soundAlert = !!s.sound_alert;
    }
    syncSoundBtnUi();

    if (s.sender_phone !== undefined) storeInfo.phone = s.sender_phone;
    if (s.sender_address !== undefined && s.sender_address !== '') storeInfo.address = s.sender_address;

    const root = document.getElementById('sefareshbanRoot');
    if (root && !localStorage.getItem('woo-density')) {
      root.dataset.density = s.compact ? 'compact' : 'comfortable';
      $('#densityBtn')?.setAttribute('aria-pressed', String(!!s.compact));
      syncDensityIcon(!!s.compact);
    }
    if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
    if (s.auto_refresh) {
      autoRefreshTimer = setInterval(() => {
        if (state.openId === null && state.section === 'orders') {
          fetchOrders();
          fetchStats();
        }
      }, 60000);
    }
    if ($('#setPerPage')) $('#setPerPage').value = String(s.per_page || 8);
    if ($('#setAutoRefresh')) $('#setAutoRefresh').checked = !!s.auto_refresh;
    if ($('#setSoundAlert')) $('#setSoundAlert').checked = state.soundAlert;
    if ($('#setCompact')) $('#setCompact').checked = !!s.compact;
    if ($('#setReplaceWcMenu')) $('#setReplaceWcMenu').checked = s.replace_wc_menu !== undefined ? !!s.replace_wc_menu : true;
    if ($('#setSenderPhone')) $('#setSenderPhone').value = s.sender_phone || '';
    if ($('#setSenderAddress')) $('#setSenderAddress').value = s.sender_address || '';
  }

  $('#sbSettingsForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const msg = $('#sbSettingsMsg');
    if (msg) msg.textContent = 'در حال ذخیره…';
    try {
      const payload = {
        per_page: $('#setPerPage')?.value || 8,
        auto_refresh: $('#setAutoRefresh')?.checked ? 1 : 0,
        sound_alert: $('#setSoundAlert')?.checked ? 1 : 0,
        compact: $('#setCompact')?.checked ? 1 : 0,
        replace_wc_menu: $('#setReplaceWcMenu')?.checked ? 1 : 0,
        sender_phone: $('#setSenderPhone')?.value.trim() || '',
        sender_address: $('#setSenderAddress')?.value.trim() || '',
      };
      const data = await api('sefareshban_save_settings', payload);
      localStorage.removeItem('woo-density');
      localStorage.setItem('sb-sound-alert', payload.sound_alert ? '1' : '0');
      state.soundAlert = !!payload.sound_alert;
      applySettings(data.settings || payload, true);
      if (msg) msg.textContent = 'ذخیره شد ✓';
      toast('تنظیمات با موفقیت ذخیره شد');
      state.page = 1;
      fetchOrders();
    } catch (err) {
      console.error(err);
      if (msg) msg.textContent = 'خطا در ذخیره تنظیمات';
    }
  });

  // Restore theme & density on boot
  const root = document.getElementById('sefareshbanRoot');
  if (root) {
    if (localStorage.getItem('woo-theme') === 'dark') {
      root.dataset.theme = 'dark';
      const ic = $('#themeIcon');
      if (ic) ic.innerHTML = '<circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/>';
    }
    const savedDensity = localStorage.getItem('woo-density');
    const isComp = savedDensity === 'compact' || (!savedDensity && initSettings.compact);
    if (isComp) {
      root.dataset.density = 'compact';
      $('#densityBtn')?.setAttribute('aria-pressed', 'true');
      syncDensityIcon(true);
    }
  }

  // Resize observer for charts
  let kpiResizeTimer;
  if (typeof ResizeObserver !== 'undefined') {
    const contentEl = $('.content');
    if (contentEl) new ResizeObserver(() => {
      clearTimeout(kpiResizeTimer);
      kpiResizeTimer = setTimeout(() => {
        if (state.section === 'orders') renderKpiCharts();
        else if (state.section === 'reports' && state.reportData) renderReports(state.reportData);
      }, 140);
    }).observe(contentEl);
  }

  /* ================= Boot ================= */
  applySettings(initSettings, true);
  applyColumnVisibility();
  renderChips();
  renderKpiCharts();
  fetchStats();
  fetchOrders();

  let bootOrderId = Number(SB.initialOrderId) || 0;
  try {
    const urlOid = Number(new URLSearchParams(window.location.search || '').get('order_id')) || 0;
    if (urlOid > 0) bootOrderId = urlOid;
  } catch (e) { /* ignore */ }
  if (bootOrderId > 0) {
    openOrderPage(bootOrderId, false);
  }
})();
