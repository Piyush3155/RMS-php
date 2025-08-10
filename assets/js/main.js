// Common JS functions
$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Confirm delete actions
    $('.delete-confirm').click(function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
});

// Sound alert function
function playAlert() {
    var audio = new Audio('../../assets/js/alert.mp3');
    audio.play().catch(function(error) {
        console.log('Audio play failed:', error);
    });
}

// Show loading spinner
function showLoading() {
    $('#loadingSpinner').show();
}

// Hide loading spinner
function hideLoading() {
    $('#loadingSpinner').hide();
}
