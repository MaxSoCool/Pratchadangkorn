document.addEventListener('DOMContentLoaded', () => {
    const loginStatusInput = document.getElementById('loginStatus');
    const loginMessageInput = document.getElementById('loginMessage');
    
    if (loginStatusInput && loginMessageInput) {
        const loginStatus = loginStatusInput.value;
        const loginMessage = loginMessageInput.value;

        const successModalElement = document.getElementById('successModal');
        const errorModalElement = document.getElementById('errorModal');
        const successModalBody = document.getElementById('successModalBody');
        const errorModalBody = document.getElementById('errorModalBody');

        if (successModalElement && errorModalElement && successModalBody && errorModalBody) {
            const successModal = new bootstrap.Modal(successModalElement);
            const errorModal = new bootstrap.Modal(errorModalElement);

            if (loginStatus === 'success') {
                successModalBody.textContent = loginMessage;
                successModal.show();
            } else if (loginStatus === 'error') {
                errorModalBody.textContent = loginMessage;
                errorModal.show();
            }
        } else {
            console.error('Modal or Modal Body elements not found.');
        }
    }
});