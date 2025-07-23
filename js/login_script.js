document.addEventListener('DOMContentLoaded', function() {
    var successModal = new bootstrap.Modal(document.getElementById('successModal'), {
        keyboard: false 
    });
    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'), {
        keyboard: false
    });

    var loginStatusInput = document.getElementById('loginStatus');
    var loginMessageInput = document.getElementById('loginMessage');

    if (loginStatusInput && loginMessageInput) {
        var status = loginStatusInput.value;
        var message = loginMessageInput.value;

        if (status === 'success') {
            document.getElementById('successModalBody').innerText = message;
            successModal.show();

        } else if (status === 'failed') {
            document.getElementById('errorModalBody').innerText = message;
            errorModal.show();
        }
    }
});