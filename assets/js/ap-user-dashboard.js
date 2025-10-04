(function () {
  function init() {
    const containers = document.querySelectorAll('.ap-user-dashboard[data-ap-dashboard-role]');

    if (!containers.length) {
      return;
    }

    containers.forEach((container) => {
      if (!container.classList.contains('ap-role-dashboard')) {
        container.classList.add('ap-role-dashboard');
      }

      if (window.ArtPulseDashboardsApp && typeof window.ArtPulseDashboardsApp.init === 'function') {
        window.ArtPulseDashboardsApp.init(container);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
