// ead-main.js


// --- Global Reusable AJAX Request Function ---
function submitRequest(endpoint, data, method = 'POST') {
    if (typeof EAD_VARS === 'undefined' || !EAD_VARS.ajaxUrl) {
        console.error('EAD_VARS.ajaxUrl not found.');
        return;
    }
    fetch(EAD_VARS.ajaxUrl + endpoint, {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(response => {
        if (response.message) {
            alert(response.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Make the function available globally if needed
window.submitRequest = submitRequest;

jQuery(function($){
    const container = $('#ead-badges');
    if(!container.length || typeof eadFrontend === 'undefined') return;

    fetch(eadFrontend.restUrl + 'artpulse/v1/badges', {
        headers: { 'X-WP-Nonce': eadFrontend.nonce_wp_rest || '' }
    })
    .then(r => r.json())
    .then(data => {
        if(!Array.isArray(data)) return;
        container.empty();
        data.forEach(b => {
            const cls = b.label.toLowerCase().replace(/[^a-z]/g,'');
            container.append(`<div class="ead-badge ${cls}">${b.label}</div>`);
        });
    })
    .catch(()=>{});
});
