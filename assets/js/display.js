/* DHH TV Display — slideshow engine. Config injected by PHP as window.DHH_DISPLAY. */
(function () {
  'use strict';

  var injected = window.DHH_DISPLAY || {};

  var CONFIG = {
    apiUrl:          injected.apiUrl          || '/wp-json/dhh-display/v1/posts',
    postCount:       injected.postCount       || 3,
    durations:       injected.durations       || {},
    refreshInterval: injected.refreshInterval || 600000,
    fetchTimeout:    injected.fetchTimeout     || 8000,
    qrBaseUrl:       injected.qrBaseUrl        || 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&color=3c7f3d&data=',
    logoUrl:         injected.logoUrl          || 'https://dhhpanelproducts.co.uk/wp-content/uploads/2021/08/dhh-panel-products-white.svg'
  };

  /* Clock */
  function updateClocks() {
    var now = new Date();
    var time = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    var months = ['January', 'February', 'March', 'April', 'May', 'June',
                  'July', 'August', 'September', 'October', 'November', 'December'];
    var dateStr = days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()];
    document.querySelectorAll('.tb-time').forEach(function (el) { el.textContent = time; });
    document.querySelectorAll('.tb-date').forEach(function (el) { el.textContent = dateStr; });
  }

  /* Helpers */
  function topBar() {
    return '<div class="top-bar">' +
        '<div class="tb-logo"><img src="' + CONFIG.logoUrl + '" alt="DHH"></div>' +
        '<div class="tb-clock"><div class="tb-time"></div><div class="tb-date"></div></div>' +
      '</div>';
  }

  function qrBlock(permalink) {
    return '<div class="news-qr">' +
        '<img src="' + CONFIG.qrBaseUrl + encodeURIComponent(permalink) + '" alt="QR Code">' +
        '<div class="qr-label"><strong>Scan to read more</strong><span>dhhpanelproducts.co.uk</span></div>' +
      '</div>';
  }

  /* News slide — image/text split. imageRight flips the sides. */
  function buildNewsSlide(post, imageRight) {
    var slide = document.createElement('div');
    slide.className = 'slide slide--news dynamic-slide' + (imageRight ? ' reverse' : '');

    var hasImg = post.featured_image && post.featured_image.length;
    var imgClass = 'news-image' + (hasImg ? '' : ' news-image--empty');
    var imgStyle = hasImg ? ' style="background-image:url(\'' + post.featured_image + '\')"' : '';

    slide.innerHTML =
      topBar() +
      '<div class="news-split">' +
        '<div class="' + imgClass + '"' + imgStyle + '></div>' +
        '<div class="news-text">' +
          '<span class="label-tag">' + post.category + '</span>' +
          '<h2>' + post.title + '</h2>' +
          '<p class="news-excerpt">' + post.excerpt + '</p>' +
          qrBlock(post.permalink) +
        '</div>' +
      '</div>';
    return slide;
  }

  /* Fetch new slides and swap. Keep existing slides on failure. */
  var started = false;

  function fetchAndBuildNews() {
    var url = CONFIG.apiUrl + '?count=' + CONFIG.postCount;
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, CONFIG.fetchTimeout);

    fetch(url, { signal: controller.signal })
      .then(function (res) {
        clearTimeout(timeout);
        if (!res.ok) throw new Error('API response: ' + res.status);
        return res.json();
      })
      .then(function (posts) {
        var wrap = document.getElementById('displayWrap');
        if (!wrap) return;

        // First article image-left, then alternate.
        var fresh = (Array.isArray(posts) ? posts : []).map(function (post, i) {
          return buildNewsSlide(post, i % 2 === 1);
        });

        wrap.querySelectorAll('.dynamic-slide').forEach(function (el) { el.remove(); });
        var productsSlide = wrap.querySelector('.slide--products');
        fresh.forEach(function (slide) { wrap.insertBefore(slide, productsSlide); });

        updateClocks();
        initSlideshow();
      })
      .catch(function (err) {
        clearTimeout(timeout);
        console.error('DHH Display: failed to refresh posts —', err);
        if (!started) initSlideshow();
      });
  }

  /* Slideshow engine — per-slide timing */
  var slides = [];
  var currentSlide = 0;
  var timer = null;

  function durationFor(el) {
    var d = CONFIG.durations || {};
    if (el.classList.contains('slide--welcome'))  return d.welcome  || d.default || 10000;
    if (el.classList.contains('slide--about'))     return d.about    || d.default || 10000;
    if (el.classList.contains('slide--news'))      return d.news     || d.default || 10000;
    if (el.classList.contains('slide--products'))  return d.products  || d.default || 10000;
    if (el.classList.contains('slide--community')) return d.community || d.default || 10000;
    if (el.classList.contains('slide--contact'))   return d.contact   || d.default || 10000;
    return d.default || 10000;
  }

  function initSlideshow() {
    started = true;
    if (timer) clearTimeout(timer);

    slides = document.querySelectorAll('.slide');
    currentSlide = 0;

    slides.forEach(function (s) { s.classList.remove('active'); });
    if (slides.length === 0) return;
    slides[0].classList.add('active');

    var indicatorContainer = document.getElementById('indicators');
    if (indicatorContainer) {
      indicatorContainer.innerHTML = '';
      slides.forEach(function (_, i) {
        var dot = document.createElement('div');
        dot.classList.add('dot');
        if (i === 0) dot.classList.add('active');
        indicatorContainer.appendChild(dot);
      });
    }

    run();
  }

  function run() {
    var dur = durationFor(slides[currentSlide]);
    resetProgressBar(dur);
    timer = setTimeout(nextSlide, dur);
  }

  function resetProgressBar(dur) {
    var bar = document.getElementById('progressBar');
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.width = '0%';
    void bar.offsetWidth; // trigger reflow
    bar.style.transition = 'width ' + dur + 'ms linear';
    bar.style.width = '100%';
  }

  function nextSlide() {
    if (slides.length === 0) return;

    slides[currentSlide].classList.remove('active');
    var dots = document.querySelectorAll('.slide-indicators .dot');
    if (dots[currentSlide]) dots[currentSlide].classList.remove('active');

    currentSlide = (currentSlide + 1) % slides.length;

    slides[currentSlide].classList.add('active');
    if (dots[currentSlide]) dots[currentSlide].classList.add('active');

    run();
  }

  /* Scale the fixed 1920x1080 stage to fit the screen, 16:9 preserved. */
  function fitStage() {
    var wrap = document.getElementById('displayWrap');
    if (!wrap) return;
    var scale = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    wrap.style.transform = 'scale(' + scale + ')';
  }

  /* Init */
  fitStage();
  window.addEventListener('resize', fitStage);

  updateClocks();
  setInterval(updateClocks, 30000);

  fetchAndBuildNews();
  setInterval(fetchAndBuildNews, CONFIG.refreshInterval);
})();
