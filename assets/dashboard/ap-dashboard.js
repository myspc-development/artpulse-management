(function(){
  const boot = window.AP_BOOT || {};
  const root = document.getElementById('ap-dashboard');
  if (!root) return;
  root.innerHTML = '<div class="ap-spinner">' + (boot?.i18n?.loading || 'Loading...') + '</div>';
  // Later phases will hydrate content based on boot.role
})();
