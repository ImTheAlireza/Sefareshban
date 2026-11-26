<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$current_user = wp_get_current_user();
$user_name    = $current_user ? $current_user->display_name : '';
$user_initial = $user_name ? mb_substr( $user_name, 0, 1 ) : 'م';
?>
<div class="sefareshban-wrap">
<div class="sefareshban-root" data-theme="light" data-density="comfortable" id="sefareshbanRoot" dir="rtl" lang="fa">
<div class="app">
  <!-- ===== sidebar ===== -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">و</div>
      <div class="brand-txt">
        <b>مدیریت سفارش</b>
        <span>ووکامرس</span>
      </div>
    </div>

    <div class="nav-label">منوی اصلی</div>
    <nav class="nav" aria-label="ناوبری اصلی">
      <a href="#" data-nav>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="8" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="15" width="7" height="6" rx="2"/></svg>
        <span>داشبورد</span>
      </a>
      <a href="#" aria-current="page">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h6M9 16h4"/></svg>
        <span>سفارش‌ها</span>
        <span class="count">۱۴</span>
      </a>
      <a href="#" data-nav>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3.3 8.3L12 13l8.7-4.7M12 21v-8"/></svg>
        <span>محصولات</span>
      </a>
      <a href="#" data-nav>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3.2 3.2 0 010 6"/></svg>
        <span>مشتریان</span>
      </a>
      <a href="#" data-nav>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V9M10 19V5M16 19v-6M22 19H2"/></svg>
        <span>گزارش‌ها</span>
      </a>

      <div class="nav-label">سایر</div>
      <a href="#" data-nav>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9 2 2 0 11-2.8 2.8 1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5 2 2 0 11-4 0 1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.9.3 2 2 0 11-2.8-2.8 1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1 2 2 0 110-4 1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.9 2 2 0 112.8-2.8 1.7 1.7 0 001.9.3 1.7 1.7 0 001-1.5 2 2 0 114 0 1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3 2 2 0 112.8 2.8 1.7 1.7 0 00-.3 1.9 1.7 1.7 0 001.5 1 2 2 0 110 4 1.7 1.7 0 00-1.5 1z"/></svg>
        <span>تنظیمات</span>
      </a>
      <a href="#" data-nav>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 114 2c-.9.7-1.5 1.2-1.5 2.3"/><path d="M12 17h.01"/></svg>
        <span>پشتیبانی</span>
      </a>
    </nav>

    <div class="promo">
      <b>۳ سفارش نیازمند بررسی</b>
      <p>بیش از ۲۴ ساعت در انتظار پرداخت مانده‌اند.</p>
      <button data-go="pending">بررسی سفارش‌ها</button>
    </div>
  </aside>

  <!-- ===== main ===== -->
  <div class="main">
    <header class="topbar">
      <div class="crumbs"><b>فروشگاه</b><span>/</span><span class="crumb-text">مدیریت سفارش‌ها</span></div>
      <div class="tools">
        <button class="circle" id="themeBtn" title="روشن / تاریک" aria-label="تغییر پوسته">
          <svg id="themeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A8.5 8.5 0 1111.2 3a6.6 6.6 0 009.8 9.8z"/></svg>
        </button>
        <button class="circle" id="densityBtn" title="چگالی جدول" aria-label="چگالی جدول" aria-pressed="false">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <button class="circle accent" id="newOrderBtn" title="سفارش جدید" aria-label="سفارش جدید">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </button>
        <div class="me">
          <div class="av" id="sbUserInitial"><?php echo esc_html( $user_initial ); ?></div>
          <div><b id="sbUserName"><?php echo esc_html( $user_name ); ?></b><span><?php esc_html_e( 'مدیر فروشگاه', 'sefareshban' ); ?></span></div>
        </div>
      </div>
    </header>

    <main class="content">
      <div class="wrap">

        <div class="pagehead">
          <div>
            <h1>سفارش‌ها</h1>
            <p>پیگیری سفارش‌ها، وضعیت پرداخت و ارسال</p>
          </div>
          <div class="acts">
            <button class="btn" id="exportBtn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 11l4 4 4-4M4 20h16"/></svg>
              خروجی
            </button>
            <button class="btn" id="moreBtn">
              اقدامات بیشتر
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
          </div>
        </div>

        <!-- KPI -->
        <section class="kpis" aria-label="شاخص‌ها">
          <article class="kpi" data-tone="rose">
            <div class="kpi-label">
              <span class="chip-ic" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
              </span>
              امروز
              <span class="kpi-tag"><span class="live-dot" aria-hidden="true"></span>۷ روز اخیر</span>
            </div>
            <div class="kpi-main">
              <div class="kpi-metrics">
                <div class="kpi-value num">۲۴ <small>سفارش</small></div>
                <div class="kpi-foot">
                  <span class="delta up">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
                    ۱۲٪
                  </span>
                  <span>نسبت به دیروز</span>
                </div>
              </div>
            </div>
            <div class="kpi-chart" id="chartOrders">
              <div class="viz-tip num" aria-hidden="true"></div>
            </div>
          </article>

          <article class="kpi" data-tone="emerald">
            <div class="kpi-label">
              <span class="chip-ic" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>
              </span>
              فروش امروز
              <span class="kpi-tag" title="جمع مبلغ سفارش‌های پرداخت‌شده امروز">روند ۷ روزه</span>
            </div>
            <div class="kpi-main">
              <div class="kpi-metrics">
                <div class="kpi-value num">۱۸٬۴۵۰٬۰۰۰ <small>تومان</small></div>
                <div class="kpi-foot">
                  <span class="delta up">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
                    ۸٪
                  </span>
                  <span>میانگین ۷ روز</span>
                </div>
              </div>
            </div>
            <div class="kpi-chart" id="chartSales">
              <div class="viz-tip num" aria-hidden="true"></div>
            </div>
          </article>

          <article class="kpi" data-tone="amber">
            <div class="kpi-label">
              <span class="chip-ic" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
              </span>
              در انتظار انجام
              <span class="kpi-tag" title="سفارش‌های در انتظار اقدام">۷ روز اخیر</span>
            </div>
            <div class="kpi-main">
              <div class="kpi-metrics">
                <div class="kpi-value num">۷ <small>سفارش</small></div>
                <div class="kpi-foot">
                  <span class="delta down">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    ۳ فوری
                  </span>
                  <span>نیازمند بررسی</span>
                </div>
              </div>
            </div>
            <div class="kpi-chart" id="chartPending">
              <div class="viz-tip num" aria-hidden="true"></div>
            </div>
          </article>

          <article class="kpi" data-tone="indigo">
            <div class="kpi-label">
              <span class="chip-ic" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 016 0v2"/></svg>
              </span>
              میانگین ارزش سفارش
              <span class="kpi-tag" title="میانگین مبلغ سفارش در ۳۰ روز گذشته">۳۰ روزه</span>
            </div>
            <div class="kpi-main">
              <div class="kpi-metrics">
                <div class="kpi-value num">۱٬۲۳۰٬۰۰۰ <small>تومان</small></div>
                <div class="kpi-foot">
                  <span class="delta down">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    ۲٪
                  </span>
                  <span>۳۰ روز گذشته</span>
                </div>
              </div>
            </div>
            <div class="kpi-chart" id="chartAov">
              <div class="viz-tip num" aria-hidden="true"></div>
            </div>
          </article>
        </section>

        <!-- section head -->
        <div class="sec-head">
          <div>
            <h2>خلاصه سفارش‌ها</h2>
            <p>مرور همه سفارش‌ها، بازگشت‌ها و درآمد</p>
          </div>
          <search class="sec-tools" aria-label="جست‌وجو و ابزار سفارش‌ها">
            <label class="search" for="q">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
              <input id="q" name="q" type="search" placeholder="جست‌وجوی سفارش…" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" enterkeyhint="search">
            </label>
            <button type="button" class="circle" id="filterBtn" title="فیلتر پیشرفته" aria-label="فیلتر پیشرفته" style="width:34px;height:34px">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            </button>
            <button type="button" class="circle" id="expandBtn" title="تازه‌سازی" aria-label="تازه‌سازی" style="width:34px;height:34px">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 10-2.3 5.7M20 5v6h-6"/></svg>
            </button>
          </search>
        </div>

        <!-- chips -->
        <div class="chips" role="tablist" aria-label="فیلتر وضعیت" id="chips"></div>

        <!-- table -->
        <section class="card">
          <div class="table-scroll">
            <table id="tbl">
              <thead>
                <tr>
                  <th class="col-check"><input type="checkbox" id="checkAll" aria-label="انتخاب همه"></th>
                  <th>شماره سفارش</th>
                  <th class="sortable" data-sort="name">مشتری
                    <span class="sicons"><svg class="u" viewBox="0 0 8 5"><path d="M4 0l4 5H0z"/></svg><svg class="d" viewBox="0 0 8 5"><path d="M4 5L0 0h8z"/></svg></span>
                  </th>
                  <th class="sortable c-date" data-sort="date">تاریخ
                    <span class="sicons"><svg class="u" viewBox="0 0 8 5"><path d="M4 0l4 5H0z"/></svg><svg class="d" viewBox="0 0 8 5"><path d="M4 5L0 0h8z"/></svg></span>
                  </th>
                  <th>وضعیت</th>
                  <th class="hide-sm">روش پرداخت</th>
                  <th class="c-items">اقلام</th>
                  <th class="sortable" data-sort="total">مبلغ
                    <span class="sicons"><svg class="u" viewBox="0 0 8 5"><path d="M4 0l4 5H0z"/></svg><svg class="d" viewBox="0 0 8 5"><path d="M4 5L0 0h8z"/></svg></span>
                  </th>
                  <th class="col-actions"><span class="sr">عملیات</span></th>
                </tr>
              </thead>
              <tbody id="tbody"></tbody>
            </table>
            <div class="empty" id="empty">
              <div class="ic">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
              </div>
              <h3>سفارشی پیدا نشد</h3>
              <p>عبارت جست‌وجو یا فیلتر وضعیت را تغییر دهید.</p>
              <button class="btn" id="clearFilters">پاک‌کردن فیلترها</button>
            </div>
          </div>

          <div class="pager">
            <div class="info">
              <label for="perPage">تعداد در صفحه</label>
              <select id="perPage">
                <option value="8" selected>۸</option>
                <option value="12">۱۲</option>
                <option value="20">۲۰</option>
              </select>
              <span class="num" id="pagerInfo"></span>
            </div>
            <div class="pages" id="pages"></div>
          </div>
        </section>

      </div>
    </main>
  </div>
</div>

<!-- bulk -->
<div class="bulk" id="bulk" role="status">
  <span class="sel num" id="bulkCount">۰ سفارش</span>
  <span class="sep"></span>
  <button data-bulk="status">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
    تغییر وضعیت
  </button>
  <button data-bulk="print">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4v-7h16v7h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/></svg>
    چاپ فاکتور
  </button>
  <button data-bulk="delete" style="color:#ffb3c0">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
    حذف
  </button>
  <button class="close" data-bulk="clear" aria-label="بستن">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
  </button>
</div>

<!-- drawer -->
<div class="scrim" id="scrim"></div>
<aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle"></aside>

<div class="toast" id="toast" role="status" aria-live="polite">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
  <span id="toastMsg"></span>
</div>
</div>
</div>
