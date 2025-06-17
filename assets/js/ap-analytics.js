(function(){
  document.querySelectorAll('.ap-directory').forEach(container => {
    // Listen for our custom events
    container.addEventListener('ap:loaded', e => {
      if ( window.gtag ) {
        gtag('event','directory_loaded', {
          directory_type: e.detail.type,
          limit: e.detail.limit
        });
      }
    });

    container.addEventListener('ap:filter_applied', e => {
      if ( window.gtag ) {
        gtag('event','directory_filter', {
          directory_type: e.detail.type,
          limit: e.detail.limit,
          event_type: e.detail.event_type
        });
      }
    });
  });

  // Dashboard load
  document.addEventListener('DOMContentLoaded', () => {
    const dash = document.querySelector('.ap-user-dashboard');
    if ( dash && window.gtag ) {
      gtag('event','user_dashboard_loaded');
    }
  });
})();
