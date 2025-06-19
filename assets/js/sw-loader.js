(function(){
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      if (window.APServiceWorker && window.APServiceWorker.enabled) {
        navigator.serviceWorker.register(window.APServiceWorker.url);
      }
    });
  }
})();
