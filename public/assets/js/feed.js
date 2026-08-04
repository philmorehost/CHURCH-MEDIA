(function () {
  'use strict';

  var scroller = document.getElementById('feedScroller');
  var loadingEl = document.getElementById('feedLoading');
  var template = document.getElementById('feedSlideTemplate');
  if (!scroller || !template) { return; }

  var endpoint = scroller.getAttribute('data-endpoint');
  var state = { page: 1, hasMore: true, loading: false, category: '', seenIds: new Set() };
  var muted = true;
  var currentPlaying = null;

  var slideObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      var video = entry.target.querySelector('video');
      if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
        if (video) { playVideo(video); }
        pingView(entry.target.getAttribute('data-post-id'));
      } else if (video) {
        video.pause();
      }
    });
  }, { root: scroller, threshold: [0, 0.6] });

  var sentinelObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) { loadPage(); }
    });
  }, { root: scroller, threshold: 0.1 });

  function playVideo(video) {
    if (currentPlaying && currentPlaying !== video) { currentPlaying.pause(); }
    video.muted = muted;
    video.play().catch(function () {});
    currentPlaying = video;
  }

  function pingView(postId) {
    if (!postId || state.seenIds.has(postId + ':viewed')) { return; }
    state.seenIds.add(postId + ':viewed');
    fetch('/api/post?id=' + encodeURIComponent(postId)).catch(function () {});
  }

  function formatCount(n) {
    if (n >= 1000000) { return (n / 1000000).toFixed(1) + 'M'; }
    if (n >= 1000) { return (n / 1000).toFixed(1) + 'K'; }
    return String(n);
  }

  function buildSlide(post) {
    var node = template.content.cloneNode(true);
    var slide = node.querySelector('.feed-slide');
    slide.setAttribute('data-post-id', post.id);

    var mediaEl = node.querySelector('.feed-media');
    var items = post.media_items && post.media_items.length ? post.media_items : [];
    var activeIndex = 0;

    function renderMedia(index) {
      mediaEl.innerHTML = '';
      var item = items[index];
      if (!item) { return; }
      if (item.type === 'video') {
        var v = document.createElement('video');
        v.src = item.file_url;
        v.loop = true;
        v.muted = muted;
        v.playsInline = true;
        v.preload = 'metadata';
        if (item.thumbnail_url) { v.poster = item.thumbnail_url; }
        v.addEventListener('click', function () {
          muted = !muted;
          v.muted = muted;
          slide.classList.toggle('show-hint', muted);
          setTimeout(function () { slide.classList.remove('show-hint'); }, 1200);
        });
        mediaEl.appendChild(v);
      } else {
        var img = document.createElement('img');
        img.src = item.file_url;
        img.alt = item.alt_text || '';
        img.loading = 'lazy';
        mediaEl.appendChild(img);
      }
    }
    renderMedia(0);

    if (items.length > 1) {
      var left = document.createElement('div');
      var right = document.createElement('div');
      [left, right].forEach(function (zone, i) {
        zone.style.cssText = 'position:absolute;top:0;bottom:0;width:35%;z-index:2;' + (i === 0 ? 'left:0;' : 'right:0;');
        mediaEl.appendChild(zone);
      });
      left.addEventListener('click', function () { activeIndex = (activeIndex - 1 + items.length) % items.length; renderMedia(activeIndex); });
      right.addEventListener('click', function () { activeIndex = (activeIndex + 1) % items.length; renderMedia(activeIndex); });
    }

    node.querySelector('.feed-type-badge').textContent = post.post_type === 'vertical_reel' ? '▶ Reel' : (post.post_type === 'carousel' ? '⧉ Carousel' : 'Photo');
    node.querySelector('.feed-author').textContent = post.author_name || '';
    node.querySelector('.feed-text').textContent = post.caption || '';

    var likeBtn = node.querySelector('.feed-like');
    var likeCount = node.querySelector('.like-count');
    var viewCount = node.querySelector('.view-count');
    likeCount.textContent = formatCount(post.likes_count || 0);
    viewCount.textContent = formatCount(post.views_count || 0);
    if (post.liked_by_viewer) { likeBtn.classList.add('liked'); }

    likeBtn.addEventListener('click', function () {
      fetch('/api/like', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: post.id }),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status !== 'success') { return; }
          likeBtn.classList.toggle('liked', data.liked);
          likeCount.textContent = formatCount(data.likes_count);
        });
    });

    node.querySelector('.feed-share').addEventListener('click', function () {
      var url = window.location.origin + '/feed#post-' + post.id;
      if (navigator.share) {
        navigator.share({ title: post.caption || 'Check this out', url: url }).catch(function () {});
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url);
      }
    });

    slideObserver.observe(slide);
    return node;
  }

  function loadPage() {
    if (state.loading || !state.hasMore) { return; }
    state.loading = true;
    if (loadingEl) { loadingEl.style.display = 'flex'; }

    var url = endpoint + '?page=' + state.page + (state.category ? '&category=' + encodeURIComponent(state.category) : '');
    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (loadingEl) { loadingEl.remove(); loadingEl = null; }
        var oldSentinel = scroller.querySelector('.feed-sentinel');
        if (oldSentinel) { sentinelObserver.unobserve(oldSentinel); oldSentinel.remove(); }

        (data.data || []).forEach(function (post) {
          scroller.appendChild(buildSlide(post));
        });

        state.hasMore = !!data.has_more;
        state.page += 1;

        if (state.hasMore) {
          var sentinel = document.createElement('div');
          sentinel.className = 'feed-sentinel';
          sentinel.style.height = '1px';
          scroller.appendChild(sentinel);
          sentinelObserver.observe(sentinel);
        } else if (scroller.children.length) {
          var end = document.createElement('div');
          end.className = 'feed-end';
          end.innerHTML = '<div>You\'re all caught up ✨</div>';
          scroller.appendChild(end);
        } else {
          var empty = document.createElement('div');
          empty.className = 'feed-end';
          empty.innerHTML = '<div>No posts in this category yet.</div>';
          scroller.appendChild(empty);
        }
      })
      .finally(function () { state.loading = false; });
  }

  document.querySelectorAll('.feed-chip-row .chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('.feed-chip-row .chip').forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      state.category = chip.getAttribute('data-category') || '';
      state.page = 1;
      state.hasMore = true;
      scroller.querySelectorAll('.feed-slide, .feed-sentinel, .feed-end').forEach(function (el) { el.remove(); });
      loadPage();
    });
  });

  loadPage();
})();
