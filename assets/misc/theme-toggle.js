(function () {
  var root = document.documentElement;
  var button = document.getElementById('theme-toggle');
  if (!button) {
    return;
  }

  var icon = button.querySelector('.fa');
  var label = button.querySelector('.theme-toggle-label');

  function isDark() {
    return root.classList.contains('theme-dark');
  }

  function updateButton() {
    var dark = isDark();
    var action = dark ? 'terang' : 'gelap';
    button.setAttribute('aria-pressed', dark ? 'true' : 'false');
    button.setAttribute('aria-label', 'Aktifkan tema '+action);
    button.setAttribute('title', 'Aktifkan tema '+action);
    icon.className = 'fa '+(dark ? 'fa-sun-o' : 'fa-moon-o');
    label.textContent = dark ? 'Terang' : 'Gelap';
  }

  button.addEventListener('click', function () {
    var dark = !isDark();
    root.classList.toggle('theme-dark', dark);
    updateButton();
    try {
      window.localStorage.setItem('simpleton.theme', dark ? 'dark' : 'light');
    } catch (error) {
      if (window.console && window.console.warn) {
        window.console.warn('Preferensi tema tidak dapat disimpan di browser ini.', error);
      }
    }
  });

  updateButton();
}());
