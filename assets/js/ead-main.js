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
