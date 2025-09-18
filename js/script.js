document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const messageDisplay = document.getElementById('message');

    const API_ENDPOINT = 'https://inv.csc.ku.ac.th/cscapi/ldap/';
    const KEY_APP = '1db2648bd3d5251c02cd33fd5080f47c24383d0cc5be27159ec8ac01a133e685';

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault(); // ป้องกันการรีเฟรชหน้าเว็บเมื่อกด submit

        const userid = usernameInput.value;
        const pwd = passwordInput.value;

        messageDisplay.textContent = '';
        messageDisplay.classList.remove('success');

        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST', // เราได้แก้ไขเป็น POST แล้วจากปัญหา TypeError
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    "keyapp": KEY_APP,
                    "dataset": "ldap",
                    "userid": userid,
                    "pwd": pwd
                }),
            });

            const data = await response.json();

            // ** แก้ไขเงื่อนไขการตรวจสอบความสำเร็จตรงนี้ **
            // ตรวจสอบว่า HTTP response เป็น 2xx และ status_code จาก API เป็น '1'
            if (response.ok && data.status_code === '1') {
                const userName = data.data && data.data.thainame ? data.data.thainame : userid; // ใช้ชื่อไทยถ้ามี หรือใช้ userid
                messageDisplay.textContent = 'เข้าสู่ระบบสำเร็จ! ยินดีต้อนรับ ' + userName;
                messageDisplay.classList.add('success');
                // console.log('API Response:', data);
                // localStorage.setItem('userLdapData', JSON.stringify(data.data)); // เก็บข้อมูลผู้ใช้
                // window.location.href = '/dashboard.html';
            } else {
                // กรณี API ตอบกลับด้วยสถานะผิดพลาด
                // สามารถปรับข้อความให้ละเอียดขึ้นตามโครงสร้าง error response ของ API จริงได้
                messageDisplay.textContent = data.message || data.status_text || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                messageDisplay.classList.remove('success');
                // console.error('Login failed:', data);
            }
        } catch (error) {
            console.error('เกิดข้อผิดพลาดในการเชื่อมต่อ:', error);
            messageDisplay.textContent = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ ลองใหม่อีกครั้ง';
            messageDisplay.classList.remove('success');
        }
    });
});