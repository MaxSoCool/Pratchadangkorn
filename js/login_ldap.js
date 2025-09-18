// ./js/login_ldap.js (ฉบับปรับปรุง)

$(document).ready(function() {
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();

        var loginButton = $('#loginButton');
        loginButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังตรวจสอบ...');

        var userid = $('#username').val();
        var pwd = $('#password').val();

        if (!userid || !pwd) {
            showErrorModal('กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน');
            loginButton.prop('disabled', false).html('เข้าสู่ระบบ');
            return;
        }

        $.ajax({
            url: './php/api_proxy.php',
            method: 'POST',
            data: { userid: userid, pwd: pwd },
            dataType: 'json'
        }).done(function (response) {
            // *** ปรับปรุงเงื่อนไขตรงนี้ ให้ตรงกับ response จาก Postman ***
            if (response && response.status_code === "1" && response.data) {
                
                // สำเร็จ: ส่งข้อมูล "เฉพาะส่วน data" ไปสร้าง Session
                $.ajax({
                    url: './php/create_session.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(response.data), // ส่งเฉพาะ object data ไป
                    dataType: 'json'
                }).done(function(sessionResponse) {
                    if (sessionResponse.status === 'success') {
                        window.location.href = sessionResponse.redirect_url;
                    } else {
                        showErrorModal(sessionResponse.message);
                        loginButton.prop('disabled', false).html('เข้าสู่ระบบ');
                    }
                }).fail(function() {
                    showErrorModal('ไม่สามารถติดต่อกับเซิร์ฟเวอร์เพื่อสร้างเซสชันได้');
                    loginButton.prop('disabled', false).html('เข้าสู่ระบบ');
                });

            } else {
                // ไม่สำเร็จ: แสดง message จาก API หรือข้อความมาตรฐาน
                showErrorModal(response.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
                loginButton.prop('disabled', false).html('เข้าสู่ระบบ');
            }
        }).fail(function () {
            showErrorModal('เกิดข้อผิดพลาดในการสื่อสารกับเซิร์ฟเวอร์');
            loginButton.prop('disabled', false).html('เข้าสู่ระบบ');
        });
    });

    function showErrorModal(message) {
        $('#errorModalBody').text(message);
        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    }
});