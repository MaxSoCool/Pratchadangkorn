document.addEventListener('DOMContentLoaded', function() {
    const loginStatus = document.getElementById('loginStatus').value;
    const loginMessage = document.getElementById('loginMessage').value;

    // ตรวจสอบว่ามีสถานะการเข้าสู่ระบบถูกตั้งค่าไว้หรือไม่
    if (loginStatus) { 
        if (loginStatus === 'failed') {
            const errorModalBody = document.getElementById('errorModalBody');
            errorModalBody.textContent = loginMessage; 
            
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();
        } 

        else if (loginStatus === 'success') {
            const successModalBody = document.getElementById('successModalBody');
            successModalBody.textContent = loginMessage; 
            
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();

        }
    }
});