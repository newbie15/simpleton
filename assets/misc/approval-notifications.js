(function () {
  var root = document.getElementById('approval-notifications');
  if (!root) { return; }
  var toggle = root.querySelector('.approval-toggle');
  var panel = root.querySelector('.approval-panel');
  var content = root.querySelector('.approval-content');
  var badge = root.querySelector('.approval-count');
  var pages = root.querySelector('.approval-pages');
  var prev = root.querySelector('.approval-prev');
  var next = root.querySelector('.approval-next');
  var pageNumber = root.querySelector('.approval-page-number');
  var page = 1;
  var requestId = 0;

  function node(tag, className, text) {
    var element = document.createElement(tag);
    if (className) { element.className = className; }
    if (text !== undefined) { element.textContent = text; }
    return element;
  }

  function load() {
    var id = ++requestId;
    content.textContent = 'Memuat notifikasi...';
    pages.hidden = true;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', root.getAttribute('data-url') + '?page=' + page, true);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onload = function () {
      if (id !== requestId) { return; }
      var data;
      try {
        data = JSON.parse(xhr.responseText);
      } catch (error) {
        content.textContent = 'Notifikasi tidak dapat dibaca. Muat ulang atau login kembali.';
        badge.hidden = true;
        return;
      }
      if (xhr.status !== 200 || !Array.isArray(data.groups) || !Array.isArray(data.errors)) {
        content.textContent = data.error || 'Notifikasi gagal dimuat. Coba lagi.';
        badge.hidden = true;
        return;
      }
      content.textContent = '';
      var total = 0;
      var max = 0;
      data.groups.forEach(function (group) {
        total += group.count;
        max = Math.max(max, group.count);
        if (!group.count) { return; }
        content.appendChild(node('div', 'approval-group', group.label + ' (' + group.count + ')'));
        group.items.forEach(function (item) {
          var link = node('a', 'approval-item');
          link.href = item.url;
          link.appendChild(node('strong', '', item.reference));
          link.appendChild(node('span', '', item.details));
          link.appendChild(node('small', '', 'Rp ' + Number(item.amount).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' · ' + item.created));
          content.appendChild(link);
        });
      });
      data.errors.forEach(function (message) {
        content.appendChild(node('div', 'approval-error', message));
      });
      if (!total && !data.errors.length) {
        content.appendChild(node('div', 'approval-empty', 'Tidak ada item yang perlu Anda setujui.'));
      }
      badge.hidden = total === 0;
      badge.textContent = total > 99 ? '99+' : String(total);
      toggle.setAttribute('aria-label', data.errors.length ? 'Notifikasi persetujuan; sebagian data gagal dimuat' : 'Notifikasi persetujuan: ' + total + ' item');
      pages.hidden = max <= 10 && page === 1;
      prev.disabled = page === 1;
      next.disabled = page * 10 >= max;
      pageNumber.textContent = 'Halaman ' + page;
    };
    xhr.onerror = function () {
      if (id !== requestId) { return; }
      content.textContent = 'Tidak dapat menghubungi server. Coba muat ulang.';
      badge.hidden = true;
    };
    xhr.send();
  }

  toggle.addEventListener('click', function () {
    var opening = panel.hidden;
    panel.hidden = !opening;
    toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    if (opening) { page = 1; load(); }
  });
  root.querySelector('.approval-refresh').addEventListener('click', load);
  prev.addEventListener('click', function () { if (page > 1) { page--; load(); } });
  next.addEventListener('click', function () { page++; load(); });
  document.addEventListener('click', function (event) {
    if (!root.contains(event.target)) {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  });
  load();
}());
