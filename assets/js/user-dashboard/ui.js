export function showLoader() {
  jQuery('#ead-loader').show();
}

export function hideLoader() {
  jQuery('#ead-loader').hide();
}

export function showToast(message, isError = false) {
  const toast = jQuery('#ead-toast');
  toast.text(message);
  toast.css('background', isError ? '#d63638' : '#0073aa').fadeIn(200);
  setTimeout(() => toast.fadeOut(300), 3000);
}
