<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$current_user = wp_get_current_user();
$user_name    = $current_user ? $current_user->display_name : '';
$user_initial = '';
if ( '' !== $user_name ) {
	$user_initial = function_exists( 'mb_substr' ) ? mb_substr( $user_name, 0, 1 ) : preg_replace( '/^(.).*$/us', '$1', $user_name );
}
if ( '' === $user_initial ) {
	$user_initial = 'م';
}
$currency_sym = function_exists( 'get_woocommerce_currency_symbol' )
	? trim( wp_strip_all_tags( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ) )
	: __( 'تومان', 'sefareshban' );
if ( '' === $currency_sym ) {
	$currency_sym = __( 'تومان', 'sefareshban' );
}
?>
<div class="sefareshban-wrap">
<div class="sefareshban-root" data-theme="light" data-density="comfortable" id="sefareshbanRoot" dir="rtl" lang="fa">
<div class="app">
  <!-- ===== sidebar ===== -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">س</div>
      <div class="brand-txt">
        <b>سفارش‌بان</b>
        <span>مدیریت سفارشات ووکامرس</span>
      </div>
    </div>

    <div class="nav-label">منوی اصلی</div>
    <nav class="nav" aria-label="ناوبری اصلی">
      <a href="#orders" data-section="orders" aria-current="page">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h6M9 16h4"/></svg>
        <span>سفارش‌ها</span>
        <span class="count num" id="navOrdersCount">…</span>
      </a>
      <a href="#products" data-section="products">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3.3 8.3L12 13l8.7-4.7M12 21v-8"/></svg>
        <span>محصولات</span>
      </a>
      <a href="#customers" data-section="customers">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3.2 3.2 0 010 6"/></svg>
        <span>مشتریان</span>
      </a>
      <a href="#reports" data-section="reports">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V9M10 19V5M16 19v-6M22 19H2"/></svg>
        <span>گزارش‌ها</span>
      </a>

      <div class="nav-label">سایر</div>
      <a href="#settings" data-section="settings">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9 2 2 0 11-2.8 2.8 1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5 2 2 0 11-4 0 1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.9.3 2 2 0 11-2.8-2.8 1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1 2 2 0 110-4 1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.9 2 2 0 112.8-2.8 1.7 1.7 0 001.9.3 1.7 1.7 0 001-1.5 2 2 0 114 0 1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3 2 2 0 112.8 2.8 1.7 1.7 0 00-.3 1.9 1.7 1.7 0 001.5 1 2 2 0 110 4 1.7 1.7 0 00-1.5 1z"/></svg>
        <span>تنظیمات</span>
      </a>
    </nav>

    <div class="promo" id="sidebarPromo">
      <b>در حال بررسی سفارش‌ها…</b>
      <p>وضعیت سفارش‌های نیازمند اقدام بررسی می‌شود.</p>
      <button type="button" data-go="pending">بررسی سفارش‌ها</button>
    </div>
  </aside>

  <!-- ===== main ===== -->
  <div class="main">
    <header class="topbar">
      <div class="crumbs"><b><?php echo esc_html( get_bloginfo( 'name' ) ?: __( 'فروشگاه', 'sefareshban' ) ); ?></b><span>/</span><span class="crumb-text">سفارش‌ها</span></div>
      <div class="tools">
        <button type="button" class="circle" id="soundBtn" title="اعلان صوتی سفارش جدید" aria-label="اعلان صوتی سفارش جدید" aria-pressed="true">
          <svg id="soundIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        </button>
        <button type="button" class="circle" id="themeBtn" title="حالت روشن / تاریک" aria-label="تغییر پوسته">
          <svg id="themeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A8.5 8.5 0 1111.2 3a6.6 6.6 0 009.8 9.8z"/></svg>
        </button>
        <button type="button" class="circle" id="densityBtn" title="چگالی جدول (راحت / فشرده)" aria-label="چگالی جدول" aria-pressed="false">
          <svg id="densityIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <button type="button" class="circle accent" id="newOrderBtn" title="ثبت سفارش سریع (تلفنی / دستی)" aria-label="ثبت سفارش جدید">
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

      <!-- ==================== ORDERS SECTION ==================== -->
      <section id="section-orders" class="app-section active">
        <div class="pagehead">
          <div>
            <h1>سفارش‌ها</h1>
            <p>پیگیری لحظه‌ای سفارش‌ها، وضعیت پرداخت، کد رهگیری و ارسال</p>
          </div>
          <div class="acts">
            <button type="button" class="btn" id="exportBtn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 11l4 4 4-4M4 20h16"/></svg>
              خروجی اکسل / پستی
            </button>
            <div class="pop-wrap">
              <button type="button" class="btn" id="moreBtn" aria-expanded="false" aria-haspopup="menu">
                اقدامات بیشتر
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <div class="pop-menu" id="moreMenu" role="menu">
                <button type="button" role="menuitem" data-more-action="quick-order">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                  ثبت سفارش سریع (تلفنی)
                </button>
                <button type="button" role="menuitem" data-more-action="new-order-wc">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15h6"/></svg>
                  فرم سفارش پیش‌فرض ووکامرس
                </button>
                <button type="button" role="menuitem" data-more-action="native-wc">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6M10 14L21 3"/></svg>
                  لیست پیش‌فرض ووکامرس
                </button>
                <button type="button" role="menuitem" data-more-action="refresh-all">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 10-2.3 5.7M20 5v6h-6"/></svg>
                  به‌روزرسانی داده‌ها
                </button>
                <hr>
                <button type="button" role="menuitem" data-more-action="go-reports">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round"><path d="M4 19V9M10 19V5M16 19v-6M22 19H2"/></svg>
                  مشاهده گزارش‌های فروش
                </button>
              </div>
            </div>
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
                <div class="kpi-value num">— <small>سفارش</small></div>
                <div class="kpi-foot">
                  <span class="delta up">…</span>
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
                <div class="kpi-value num">— <small><?php echo esc_html( $currency_sym ); ?></small></div>
                <div class="kpi-foot">
                  <span class="delta up">…</span>
                  <span id="salesFootLabel">نسبت به دیروز</span>
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
                <div class="kpi-value num">— <small>سفارش</small></div>
                <div class="kpi-foot">
                  <span class="delta warn">…</span>
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
                <div class="kpi-value num">— <small><?php echo esc_html( $currency_sym ); ?></small></div>
                <div class="kpi-foot">
                  <span class="delta up" id="aovDelta">…</span>
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
            <p>برای تغییر سریع وضعیت، مستقیماً روی برچسب وضعیت در جدول کلیک کنید</p>
          </div>
          <div class="sec-tools" role="search" aria-label="جست‌وجو و ابزار سفارش‌ها">
            <div class="quick-dates" id="quickDateBar" role="group" aria-label="فیلتر سریع تاریخ">
              <button type="button" class="qd-btn active" data-qdate="all">همه</button>
              <button type="button" class="qd-btn" data-qdate="today">امروز</button>
              <button type="button" class="qd-btn" data-qdate="yesterday">دیروز</button>
              <button type="button" class="qd-btn" data-qdate="7d">۷ روز</button>
              <button type="button" class="qd-btn" data-qdate="this_month">این ماه</button>
            </div>

            <label class="search" for="q">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
              <input id="q" name="q" type="search" placeholder="شماره سفارش، نام، موبایل، رهگیری…" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" enterkeyhint="search">
            </label>

            <button type="button" class="circle has-badge" id="filterBtn" title="فیلتر پیشرفته (تاریخ شمسی، شهر، روش ارسال…)" aria-label="فیلتر پیشرفته" aria-expanded="false" style="width:34px;height:34px">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
              <span class="filter-dot" id="filterDot" hidden></span>
            </button>

            <div class="pop-wrap">
              <button type="button" class="circle" id="colToggleBtn" title="تنظیم ستون‌های جدول" aria-label="ستون‌های جدول" aria-expanded="false" style="width:34px;height:34px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/></svg>
              </button>
              <div class="pop-menu col-menu" id="colToggleMenu" role="menu">
                <div class="col-menu-title">نمایش ستون‌های جدول</div>
                <label class="col-chk"><input type="checkbox" data-col-toggle="customer" checked> مشتری</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="location"> شهر / استان</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="date" checked> تاریخ ثبت</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="status" checked> وضعیت سفارش</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="payment" checked> روش پرداخت</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="shipping"> روش ارسال</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="tracking"> کد رهگیری مرسوله</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="items" checked> اقلام سفارش</label>
                <label class="col-chk"><input type="checkbox" data-col-toggle="total" checked> مبلغ کل</label>
              </div>
            </div>

            <button type="button" class="circle" id="expandBtn" title="تازه‌سازی" aria-label="تازه‌سازی" style="width:34px;height:34px">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 10-2.3 5.7M20 5v6h-6"/></svg>
            </button>
          </div>
        </div>

        <!-- Advanced filter panel -->
        <div class="adv-filters" id="advFilters" hidden>
          <div class="adv-grid">
            <div class="adv-field">
              <label for="fltDateFromDisplay">از تاریخ (شمسی)</label>
              <input type="text" id="fltDateFromDisplay" class="jalali-input" placeholder="انتخاب از تقویم…" readonly autocomplete="off">
              <input type="hidden" id="fltDateFrom">
            </div>
            <div class="adv-field">
              <label for="fltDateToDisplay">تا تاریخ (شمسی)</label>
              <input type="text" id="fltDateToDisplay" class="jalali-input" placeholder="انتخاب از تقویم…" readonly autocomplete="off">
              <input type="hidden" id="fltDateTo">
            </div>
            <div class="adv-field">
              <label for="fltPayment">روش پرداخت</label>
              <select id="fltPayment">
                <option value="">همه درگاه‌ها / روش‌ها</option>
              </select>
            </div>
            <div class="adv-field">
              <label for="fltShipping">روش ارسال</label>
              <input type="text" id="fltShipping" list="sbShippingList" placeholder="مثلاً پیشتاز، تیپاکس، پیک…">
              <datalist id="sbShippingList"></datalist>
            </div>
            <div class="adv-field">
              <label for="fltState">استان</label>
              <select id="fltState">
                <option value="">همه استان‌ها</option>
              </select>
            </div>
            <div class="adv-field">
              <label for="fltCity">شهر</label>
              <input type="text" id="fltCity" placeholder="مثلاً تهران، شیراز…">
            </div>
            <div class="adv-field">
              <label for="fltMinTotal">حداقل مبلغ (<?php echo esc_html( $currency_sym ); ?>)</label>
              <input type="number" id="fltMinTotal" min="0" step="1000" placeholder="مثلاً ۱۰۰۰۰۰">
            </div>
            <div class="adv-field">
              <label for="fltMaxTotal">حداکثر مبلغ (<?php echo esc_html( $currency_sym ); ?>)</label>
              <input type="number" id="fltMaxTotal" min="0" step="1000" placeholder="بدون محدودیت">
            </div>
          </div>
          <div class="adv-bottom">
            <div class="adv-checks">
              <label class="note-type-lbl"><input type="checkbox" id="fltOnlyWithNote"> فقط دارای یادداشت مشتری</label>
              <label class="note-type-lbl"><input type="checkbox" id="fltOnlyDelayed"> فقط سفارش‌های دارای تأخیر (+۲۴ ساعت)</label>
            </div>
            <div class="adv-actions">
              <button type="button" class="btn" id="resetAdvFilters">پاک‌سازی همه</button>
              <button type="button" class="btn primary" id="applyAdvFilters">اعمال فیلترها</button>
            </div>
          </div>
        </div>

        <!-- chips -->
        <div class="chips" role="tablist" aria-label="فیلتر وضعیت" id="chips"></div>

        <!-- table -->
        <section class="card">
          <div class="table-scroll">
            <table id="tbl" class="hide-col-location hide-col-shipping hide-col-tracking">
              <thead>
                <tr>
                  <th class="col-check"><input type="checkbox" id="checkAll" aria-label="انتخاب همه"></th>
                  <th class="sortable" data-sort="id" data-col="id">شماره سفارش
                    <span class="sicons"><svg class="u" viewBox="0 0 8 5"><path d="M4 0l4 5H0z"/></svg><svg class="d" viewBox="0 0 8 5"><path d="M4 5L0 0h8z"/></svg></span>
                  </th>
                  <th class="sortable" data-sort="name" data-col="customer">مشتری
                    <span class="sicons"><svg class="u" viewBox="0 0 8 5"><path d="M4 0l4 5H0z"/></svg><svg class="d" viewBox="0 0 8 5"><path d="M4 5L0 0h8z"/></svg></span>
                  </th>
                  <th data-col="location">شهر / استان</th>
                  <th class="sortable c-date" data-sort="date" data-col="date">تاریخ
                    <span class="sicons"><svg class="u" viewBox="0 0 8 5"><path d="M4 0l4 5H0z"/></svg><svg class="d" viewBox="0 0 8 5"><path d="M4 5L0 0h8z"/></svg></span>
                  </th>
                  <th data-col="status">وضعیت</th>
                  <th class="hide-sm" data-col="payment">روش پرداخت</th>
                  <th data-col="shipping">روش ارسال</th>
                  <th data-col="tracking">کد رهگیری</th>
                  <th class="c-items" data-col="items">اقلام</th>
                  <th class="sortable" data-sort="total" data-col="total">مبلغ
                    <span class="sicons"><svg class="u" viewBox="0 0 8 5"><path d="M4 0l4 5H0z"/></svg><svg class="d" viewBox="0 0 8 5"><path d="M4 5L0 0h8z"/></svg></span>
                  </th>
                  <th class="col-actions"><span class="sr">عملیات</span></th>
                </tr>
              </thead>
              <tbody id="tbody">
                <tr><td colspan="12" class="muted" style="padding:28px;text-align:center">در حال بارگذاری سفارش‌ها…</td></tr>
              </tbody>
            </table>
            <div class="empty" id="empty">
              <div class="ic">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
              </div>
              <h3>سفارشی پیدا نشد</h3>
              <p>عبارت جست‌وجو یا فیلترهای انتخاب‌شده را تغییر دهید.</p>
              <button type="button" class="btn" id="clearFilters">پاک‌کردن فیلترها</button>
            </div>
          </div>

          <div class="pager">
            <div class="info">
              <label for="perPage">تعداد در صفحه</label>
              <select id="perPage">
                <option value="8" selected>۸</option>
                <option value="12">۱۲</option>
                <option value="20">۲۰</option>
                <option value="50">۵۰</option>
              </select>
              <span class="num" id="pagerInfo"></span>
            </div>
            <div class="pages" id="pages"></div>
          </div>
        </section>

      </section>

      <!-- ==================== DEDICATED SINGLE ORDER PAGE ==================== -->
      <section id="section-order-detail" class="app-section" aria-label="صفحه اختصاصی سفارش">
        <div id="orderPageHost"></div>
      </section>

      <!-- ==================== CUSTOMERS SECTION ==================== -->
      <section id="section-customers" class="app-section">
        <div class="pagehead">
          <div>
            <h1>مشتریان</h1>
            <p>لیست خریداران، تعداد سفارش‌ها و مجموع خرید هر مشتری</p>
          </div>
          <div class="acts">
            <button type="button" class="btn" id="refreshCustomersBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 10-2.3 5.7M20 5v6h-6"/></svg>
              به‌روزرسانی
            </button>
          </div>
        </div>
        <div class="sec-head" style="margin-bottom:10px">
          <div class="muted num" id="customersTotalInfo"></div>
          <div class="sec-tools" role="search" aria-label="جست‌وجوی مشتریان">
            <label class="search" for="customerSearch">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
              <input id="customerSearch" type="search" placeholder="جست‌وجوی نام، موبایل، ایمیل یا شهر…" autocomplete="off">
            </label>
          </div>
        </div>
        <section class="card">
          <div class="table-scroll">
            <table>
              <thead><tr>
                <th>مشتری</th><th>ایمیل</th><th>تلفن</th><th>شهر</th><th>آخرین سفارش</th><th>تعداد سفارش</th><th>جمع خرید</th><th class="col-actions"><span class="sr">مشاهده</span></th>
              </tr></thead>
              <tbody id="customersBody"><tr><td colspan="8" class="muted" style="padding:24px;text-align:center">در حال بارگذاری…</td></tr></tbody>
            </table>
          </div>
          <div class="pager" id="customersPagerWrap">
            <div class="info"><span class="num" id="customersPagerInfo"></span></div>
            <div class="pages" id="customersPages"></div>
          </div>
        </section>
      </section>

      <!-- ==================== PRODUCTS SECTION ==================== -->
      <section id="section-products" class="app-section">
        <div class="pagehead">
          <div>
            <h1>محصولات</h1>
            <p>مدیریت سریع، بررسی موجودی و آمار فروش محصولات فروشگاه</p>
          </div>
          <div class="acts">
            <button type="button" class="btn" id="refreshProductsBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 10-2.3 5.7M20 5v6h-6"/></svg>
              به‌روزرسانی
            </button>
            <button type="button" class="btn primary" id="newProductBtn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
              محصول جدید
            </button>
          </div>
        </div>
        <div class="sec-head" style="margin-bottom:10px">
          <div class="muted num" id="productsTotalInfo"></div>
          <div class="sec-tools" role="search" aria-label="جست‌وجوی محصولات">
            <label class="search" for="productSearch">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
              <input id="productSearch" type="search" placeholder="جست‌وجوی نام محصول یا SKU…" autocomplete="off">
            </label>
          </div>
        </div>
        <section class="card">
          <div class="table-scroll">
            <table>
              <thead><tr>
                <th>محصول</th><th>قیمت</th><th>موجودی</th><th>وضعیت</th><th>تعداد فروش</th><th class="col-actions"><span class="sr">عملیات</span></th>
              </tr></thead>
              <tbody id="productsBody"><tr><td colspan="6" class="muted" style="padding:24px;text-align:center">در حال بارگذاری…</td></tr></tbody>
            </table>
          </div>
          <div class="pager" id="productsPagerWrap">
            <div class="info"><span class="num" id="productsPagerInfo"></span></div>
            <div class="pages" id="productsPages"></div>
          </div>
        </section>
      </section>

      <!-- ==================== REPORTS SECTION ==================== -->
      <section id="section-reports" class="app-section">
        <div class="pagehead">
          <div>
            <h1>گزارش‌ها</h1>
            <p>تحلیل جامع فروش، وضعیت سفارشات، محصولات پرفروش و درگاه‌های پرداخت</p>
          </div>
          <div class="acts">
            <div class="chips" id="reportRangeTabs" style="margin-bottom:0">
              <button type="button" class="chip" data-rep-days="7" aria-selected="false">۷ روز اخیر</button>
              <button type="button" class="chip" data-rep-days="30" aria-selected="true">۳۰ روز اخیر</button>
              <button type="button" class="chip" data-rep-days="90" aria-selected="false">۹۰ روز اخیر</button>
              <button type="button" class="chip" data-rep-days="365" aria-selected="false">یک سال اخیر</button>
            </div>
          </div>
        </div>

        <div class="kpis" style="margin-bottom:14px" id="reportsKpis">
          <article class="kpi" data-tone="emerald">
            <div class="kpi-label"><span class="chip-ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg></span>فروش ناخالص</div>
            <div class="kpi-main"><div class="kpi-metrics"><div class="kpi-value num" id="repGrossSales">—</div><div class="kpi-foot"><span id="repNetSalesSub">خالص: —</span></div></div></div>
          </article>
          <article class="kpi" data-tone="rose">
            <div class="kpi-label"><span class="chip-ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h6M9 16h4"/></svg></span>سفارش‌های موفق</div>
            <div class="kpi-main"><div class="kpi-metrics"><div class="kpi-value num" id="repPaidOrders">—</div><div class="kpi-foot"><span id="repAllOrdersSub">کل ثبت‌شده: —</span></div></div></div>
          </article>
          <article class="kpi" data-tone="indigo">
            <div class="kpi-label"><span class="chip-ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 016 0v2"/></svg></span>میانگین ارزش سفارش</div>
            <div class="kpi-main"><div class="kpi-metrics"><div class="kpi-value num" id="repAov">—</div><div class="kpi-foot"><span id="repItemsSoldSub">اقلام فروخته‌شده: —</span></div></div></div>
          </article>
          <article class="kpi" data-tone="amber">
            <div class="kpi-label"><span class="chip-ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M21 10H3M21 6H3M21 14H3M21 18H3"/></svg></span>ارسال و تخفیف‌ها</div>
            <div class="kpi-main"><div class="kpi-metrics"><div class="kpi-value num" id="repShippingTotal">—</div><div class="kpi-foot"><span id="repDiscountSub">تخفیف داده‌شده: —</span></div></div></div>
          </article>
        </div>

        <div class="reports-grid">
          <section class="card rep-card rep-span-2">
            <div class="rep-card-head">
              <div>
                <h3>روند فروش و درآمد</h3>
                <p id="repChartSubtitle">نمودار فروش بازه انتخاب‌شده</p>
              </div>
            </div>
            <div class="rep-chart-box" id="repMainChart">
              <div class="viz-tip num" aria-hidden="true"></div>
            </div>
          </section>

          <section class="card rep-card">
            <div class="rep-card-head">
              <div>
                <h3>تفکیک وضعیت سفارش‌ها</h3>
                <p>سهم هر وضعیت در بازه انتخابی</p>
              </div>
            </div>
            <div class="rep-list" id="repStatusList">
              <div class="muted" style="padding:16px 0">در حال بارگذاری…</div>
            </div>
          </section>

          <section class="card rep-card">
            <div class="rep-card-head">
              <div>
                <h3>روش‌های پرداخت</h3>
                <p>میزان تراکنش به تفکیک درگاه پرداخت</p>
              </div>
            </div>
            <div class="rep-list" id="repGatewayList">
              <div class="muted" style="padding:16px 0">در حال بارگذاری…</div>
            </div>
          </section>

          <section class="card rep-card">
            <div class="rep-card-head">
              <div>
                <h3>پرفروش‌ترین محصولات</h3>
                <p>بر اساس مجموع مبلغ فروش در این بازه</p>
              </div>
            </div>
            <div class="rep-list" id="repTopProducts">
              <div class="muted" style="padding:16px 0">در حال بارگذاری…</div>
            </div>
          </section>

          <section class="card rep-card full-if-odd">
            <div class="rep-card-head">
              <div>
                <h3>شهرهای برتر</h3>
                <p>بیشترین سفارش‌های ثبت‌شده بر اساس شهر</p>
              </div>
            </div>
            <div class="rep-list" id="repTopCities">
              <div class="muted" style="padding:16px 0">در حال بارگذاری…</div>
            </div>
          </section>
        </div>
      </section>

      <!-- ==================== SETTINGS SECTION ==================== -->
      <section id="section-settings" class="app-section">
        <div class="pagehead"><div><h1>تنظیمات</h1><p>پیکربندی و شخصی‌سازی افزونه سفارش‌بان</p></div></div>
        <section class="card"><div style="padding:22px;max-width:780px">
          <form id="sbSettingsForm" style="display:flex;flex-direction:column;gap:20px">
            <div>
              <label for="setPerPage" style="display:block;font-weight:600;margin-bottom:6px">تعداد پیش‌فرض سفارش در هر صفحه</label>
              <select id="setPerPage" class="sb-ctl-input" style="max-width:220px">
                <option value="8">۸ سفارش</option>
                <option value="12">۱۲ سفارش</option>
                <option value="20">۲۰ سفارش</option>
                <option value="50">۵۰ سفارش</option>
              </select>
            </div>

            <div>
              <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
                <input type="checkbox" id="setReplaceWcMenu" checked> جایگزینی خودکار صفحه پیش‌فرض سفارشات ووکامرس با سفارش‌بان
              </label>
              <div class="muted" style="margin-top:4px">در صورت غیرفعال کردن، منوی پیش‌فرض سفارشات ووکامرس نیز در کنار سفارش‌بان قابل مشاهده خواهد بود.</div>
            </div>

            <div>
              <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
                <input type="checkbox" id="setCompact"> حالت فشرده جدول به‌صورت پیش‌فرض
              </label>
              <div class="muted" style="margin-top:4px">ردیف‌های جدول را جمع‌وجورتر نمایش می‌دهد تا سفارش‌های بیشتری در یک نگاه دیده شوند.</div>
            </div>

            <div>
              <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
                <input type="checkbox" id="setAutoRefresh"> رفرش خودکار لیست سفارش‌ها (هر ۶۰ ثانیه)
              </label>
              <div class="muted" style="margin-top:4px">در صورت باز بودن پنل، سفارش‌های جدید را به‌صورت خودکار بررسی و دریافت می‌کند.</div>
            </div>

            <div>
              <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
                  <input type="checkbox" id="setSoundAlert" checked> پخش صدای اعلان و نمایش هشدار در عنوان تب مرورگر برای سفارش جدید
                </label>
                <button type="button" class="btn" id="testSoundBtn" style="height:28px;padding:0 10px;font-size:11.5px">تست صدای زنگ</button>
              </div>
            </div>

            <hr style="border:0;border-top:1px solid var(--border-soft);margin:4px 0">

            <div>
              <b style="display:block;font-size:14px;margin-bottom:10px">مشخصات فرستنده روی برچسب پستی (Shipping Label)</b>
              <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                <div>
                  <label for="setSenderPhone" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">تلفن تماس فرستنده</label>
                  <input type="text" id="setSenderPhone" class="sb-ctl-input" placeholder="مثلاً ۰۲۱۸۸۰۰۰۰۰۰">
                </div>
                <div>
                  <label for="setSenderAddress" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">نشانی فرستنده (در صورت خالی بودن از تنظیمات ووکامرس خوانده می‌شود)</label>
                  <input type="text" id="setSenderAddress" class="sb-ctl-input" placeholder="استان، شهر، خیابان، پلاک…">
                </div>
              </div>
            </div>

            <div style="display:flex;gap:10px;align-items:center">
              <button type="submit" class="btn primary">ذخیره تنظیمات</button>
              <span id="sbSettingsMsg" class="muted"></span>
            </div>
          </form>
          <hr style="border:0;border-top:1px solid var(--border-soft);margin:22px 0">
          <div style="display:flex;flex-direction:column;gap:10px">
            <div><b style="display:block;margin-bottom:4px">درباره افزونه</b><span class="muted">سفارش‌بان - میز کار حرفه‌ای مدیریت سفارشات ووکامرس · نسخه <?php echo esc_html( SEFARESHBAN_VERSION ); ?></span></div>
            <div><b style="display:block;margin-bottom:4px">سازگاری</b><span class="muted">سازگار با جداول سفارشی ووکامرس (HPOS)، حالت سنتی (Legacy Post Type) و تاریخ شمسی داخلی.</span></div>
          </div>
        </div></section>
      </section>

      </div>
    </main>
  </div>
</div>

<!-- ==================== FLOATING PORTALS, MODALS & DRAWERS ==================== -->

<!-- Bulk Action Bar -->
<div class="bulk" id="bulk" role="status">
  <span class="sel num" id="bulkCount">۰ سفارش</span>
  <span class="sep"></span>
  <div class="bulk-normal-acts" id="bulkNormalActs">
    <button type="button" data-bulk="status">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
      تغییر وضعیت
    </button>
    <button type="button" data-bulk="print">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4v-7h16v7h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/></svg>
      چاپ فاکتور
    </button>
    <button type="button" data-bulk="label">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h6M7 13h10"/></svg>
      برچسب پستی
    </button>
    <button type="button" data-bulk="export">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 11l4 4 4-4M4 20h16"/></svg>
      خروجی CSV
    </button>
    <button type="button" data-bulk="delete" style="color:#ffb3c0">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
      زباله‌دان
    </button>
  </div>
  <div class="bulk-trash-acts" id="bulkTrashActs" hidden>
    <button type="button" data-bulk="restore" style="color:#7ef0b5">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      بازیابی سفارش‌ها
    </button>
    <button type="button" data-bulk="force-delete" style="color:#ffb3c0">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
      حذف دائمی
    </button>
  </div>
  <button type="button" class="close" data-bulk="clear" aria-label="لغو انتخاب">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
  </button>
</div>

<!-- Inline Status Change Popover -->
<div class="floating-pop" id="inlineStatusMenu" role="menu" hidden></div>

<!-- Items Preview Floating Tooltip -->
<div class="floating-pop items-preview-pop" id="itemsPreviewPop" hidden></div>

<!-- Jalali Calendar Date Picker Popover -->
<div class="floating-pop jalali-pop" id="jalaliPickerPop" hidden></div>

<!-- Bulk Status Modal -->
<div class="scrim" id="bulkModalScrim"></div>
<div class="sb-modal" id="bulkStatusModal" role="dialog" aria-modal="true" aria-labelledby="bulkModalTitle" hidden>
  <div class="sb-modal-head">
    <h3 id="bulkModalTitle">تغییر گروهی وضعیت سفارش‌ها</h3>
    <button type="button" class="circle" data-close-bulk-modal aria-label="بستن" style="width:30px;height:30px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div class="sb-modal-body">
    <p class="muted" id="bulkModalDesc" style="margin:0 0 10px">وضعیت جدید را برای سفارش‌های انتخاب‌شده مشخص کنید:</p>
    <div class="select">
      <select id="bulkStatusSelect" aria-label="وضعیت جدید"></select>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
    </div>
  </div>
  <div class="sb-modal-foot">
    <button type="button" class="btn" data-close-bulk-modal>انصراف</button>
    <button type="button" class="btn primary" id="confirmBulkStatusBtn">اعمال تغییر وضعیت</button>
  </div>
</div>

<!-- Configurable CSV & Postal Export Modal -->
<div class="scrim" id="exportModalScrim"></div>
<div class="sb-modal sb-modal-lg" id="exportModal" role="dialog" aria-modal="true" aria-labelledby="exportModalTitle" hidden>
  <div class="sb-modal-head">
    <h3 id="exportModalTitle">خروجی اکسل / CSV سفارش‌ها</h3>
    <button type="button" class="circle" data-close-export-modal aria-label="بستن" style="width:30px;height:30px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div class="sb-modal-body">
    <p class="muted" id="exportScopeDesc" style="margin:0 0 12px">خروجی بر اساس فیلترهای فعلی آماده می‌شود.</p>
    <div class="export-mode-cards">
      <label class="exp-card active" id="expCardStandard">
        <input type="radio" name="sbExportFormat" value="standard" checked>
        <div>
          <b>خروجی استاندارد و قابل تنظیم</b>
          <span>مناسب حسابداری، گزارش‌گیری اکسل و بایگانی با ستون‌های دلخواه</span>
        </div>
      </label>
      <label class="exp-card" id="expCardPostal">
        <input type="radio" name="sbExportFormat" value="postal">
        <div>
          <b>فرمت آماده پنل‌های پستی و لجستیک</b>
          <span>مناسب تاپین، پستکس، پادرو و تیپاکس (شامل مبلغ COD، کد پستی، استان/شهر و محتویات)</span>
        </div>
      </label>
    </div>

    <div id="exportColsBox" style="margin-top:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <b style="font-size:12px">انتخاب ستون‌های خروجی استاندارد:</b>
        <button type="button" class="link-btn" id="expSelectAllCols">انتخاب همه</button>
      </div>
      <div class="exp-cols-grid" id="exportColsGrid">
        <label class="col-chk"><input type="checkbox" value="order_number" checked> شماره سفارش</label>
        <label class="col-chk"><input type="checkbox" value="name" checked> نام مشتری</label>
        <label class="col-chk"><input type="checkbox" value="phone" checked> تلفن همراه</label>
        <label class="col-chk"><input type="checkbox" value="email"> ایمیل</label>
        <label class="col-chk"><input type="checkbox" value="state" checked> استان</label>
        <label class="col-chk"><input type="checkbox" value="city" checked> شهر</label>
        <label class="col-chk"><input type="checkbox" value="address"> آدرس کامل</label>
        <label class="col-chk"><input type="checkbox" value="postcode" checked> کد پستی</label>
        <label class="col-chk"><input type="checkbox" value="date" checked> تاریخ ثبت (شمسی)</label>
        <label class="col-chk"><input type="checkbox" value="status" checked> وضعیت</label>
        <label class="col-chk"><input type="checkbox" value="payment" checked> روش پرداخت</label>
        <label class="col-chk"><input type="checkbox" value="shipping"> روش ارسال</label>
        <label class="col-chk"><input type="checkbox" value="tracking" checked> کد رهگیری</label>
        <label class="col-chk"><input type="checkbox" value="items" checked> نام اقلام</label>
        <label class="col-chk"><input type="checkbox" value="qty" checked> تعداد اقلام</label>
        <label class="col-chk"><input type="checkbox" value="shipping_cost"> هزینه ارسال</label>
        <label class="col-chk"><input type="checkbox" value="discount"> مبلغ تخفیف</label>
        <label class="col-chk"><input type="checkbox" value="total" checked> مبلغ کل</label>
        <label class="col-chk"><input type="checkbox" value="customer_note"> یادداشت مشتری</label>
      </div>
    </div>
  </div>
  <div class="sb-modal-foot">
    <button type="button" class="btn" data-close-export-modal>انصراف</button>
    <button type="button" class="btn primary" id="confirmExportBtn">دانلود فایل CSV</button>
  </div>
</div>

<!-- Quick Phone/Manual Order Creation Drawer -->
<div class="scrim" id="quickOrderScrim"></div>
<aside class="drawer drawer-wide" id="quickOrderDrawer" role="dialog" aria-modal="true" aria-labelledby="qoTitle">
  <div class="d-head">
    <div>
      <div class="t1" id="qoTitle">ثبت سفارش سریع (تلفنی / دستی)</div>
      <div class="t2">ثبت آنی سفارش در ووکامرس · <a href="#" id="openNativeNewOrderLink" style="color:var(--accent);text-decoration:none;font-weight:600">فرم کامل ووکامرس ↗</a></div>
    </div>
    <button type="button" class="circle" data-close-qo aria-label="بستن">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <form id="quickOrderForm" class="d-body" style="display:flex;flex-direction:column;gap:0">
    <section class="d-sec" style="padding-top:6px">
      <div class="d-label">۱. انتخاب اقلام سفارش</div>
      <div class="qo-search-wrap">
        <input type="search" id="qoProductSearch" class="sb-ctl-input" placeholder="جست‌وجوی نام محصول یا کد SKU برای افزودن…" autocomplete="off">
        <div class="qo-prod-results" id="qoProdResults" hidden></div>
      </div>
      <div class="qo-cart-list" id="qoCartList">
        <div class="muted" style="padding:10px 0;text-align:center">هنوز محصولی به سفارش اضافه نشده است.</div>
      </div>
    </section>

    <section class="d-sec">
      <div class="d-label">۲. اطلاعات خریدار و نشانی ارسال</div>
      <div class="qo-grid-2">
        <div>
          <label class="qo-lbl" for="qoName">نام و نام خانوادگی *</label>
          <input type="text" id="qoName" class="sb-ctl-input" placeholder="مثلاً علی محمدی" required>
        </div>
        <div>
          <label class="qo-lbl" for="qoPhone">شماره موبایل *</label>
          <input type="tel" id="qoPhone" class="sb-ctl-input num" dir="ltr" style="text-align:right" placeholder="0912..." required>
        </div>
        <div>
          <label class="qo-lbl" for="qoState">استان</label>
          <select id="qoState" class="sb-ctl-input"><option value="">انتخاب استان…</option></select>
        </div>
        <div>
          <label class="qo-lbl" for="qoCity">شهر</label>
          <input type="text" id="qoCity" class="sb-ctl-input" placeholder="نام شهر">
        </div>
        <div class="qo-span-2">
          <label class="qo-lbl" for="qoAddress">نشانی پستی</label>
          <input type="text" id="qoAddress" class="sb-ctl-input" placeholder="خیابان، کوچه، پلاک، واحد…">
        </div>
        <div>
          <label class="qo-lbl" for="qoPostcode">کد پستی</label>
          <input type="text" id="qoPostcode" class="sb-ctl-input num" dir="ltr" style="text-align:right" placeholder="۱۰ رقمی">
        </div>
        <div>
          <label class="qo-lbl" for="qoEmail">ایمیل (اختیاری)</label>
          <input type="email" id="qoEmail" class="sb-ctl-input" dir="ltr" style="text-align:right" placeholder="customer@mail.com">
        </div>
        <div class="qo-span-2">
          <label class="qo-lbl" for="qoNote">توضیحات سفارش / یادداشت مشتری</label>
          <input type="text" id="qoNote" class="sb-ctl-input" placeholder="مثلاً ارسال در نوبت عصر…">
        </div>
      </div>
    </section>

    <section class="d-sec">
      <div class="d-label">۳. حمل‌ونقل، تخفیف و وضعیت</div>
      <div class="qo-grid-2">
        <div>
          <label class="qo-lbl" for="qoShipTitle">عنوان روش ارسال</label>
          <input type="text" id="qoShipTitle" class="sb-ctl-input" list="sbShippingList" value="ارسال پستی">
        </div>
        <div>
          <label class="qo-lbl" for="qoShipCost">هزینه ارسال (<?php echo esc_html( $currency_sym ); ?>)</label>
          <input type="number" id="qoShipCost" class="sb-ctl-input num" min="0" step="1000" value="0">
        </div>
        <div>
          <label class="qo-lbl" for="qoDiscount">تخفیف دستی (<?php echo esc_html( $currency_sym ); ?>)</label>
          <input type="number" id="qoDiscount" class="sb-ctl-input num" min="0" step="1000" value="0">
        </div>
        <div>
          <label class="qo-lbl" for="qoPayment">روش پرداخت</label>
          <select id="qoPayment" class="sb-ctl-input"><option value="">انتخاب روش پرداخت…</option></select>
        </div>
        <div class="qo-span-2">
          <label class="qo-lbl" for="qoStatus">وضعیت اولیه سفارش</label>
          <select id="qoStatus" class="sb-ctl-input"></select>
        </div>
      </div>
      <div class="totals" style="margin-top:14px;background:var(--surface-2);padding:10px 14px;border-radius:12px">
        <div class="r grand" style="margin:0;padding:0;border:0">
          <span>مبلغ نهایی قابل پرداخت:</span>
          <b class="num" id="qoGrandTotal">۰ <?php echo esc_html( $currency_sym ); ?></b>
        </div>
      </div>
    </section>
  </form>
  <div class="d-foot">
    <button type="button" class="btn" data-close-qo>انصراف</button>
    <button type="submit" form="quickOrderForm" class="btn primary" id="qoSubmitBtn">ثبت نهایی سفارش</button>
  </div>
</aside>

<!-- Order Detail Drawer -->
<div class="scrim" id="scrim"></div>
<aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle"></aside>

<!-- Product Detail Drawer -->
<div class="scrim" id="productScrim"></div>
<aside class="drawer" id="productDrawer" role="dialog" aria-modal="true" aria-labelledby="productDrawerTitle"></aside>

<div class="toast" id="toast" role="status" aria-live="polite">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
  <span id="toastMsg"></span>
</div>
</div>
</div>
