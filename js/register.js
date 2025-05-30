document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');

    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            event.preventDefault(); // 防止表單實際提交

            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (password !== confirmPassword) {
                alert('密碼與確認密碼不相符！');
                return;
            }

            // 實際應用中，這裡會進行進一步驗證並發送請求到後端
            console.log('註冊表單已提交');
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            console.log('使用者名稱:', username, 'Email:', email);
            alert('註冊功能尚未實作！');
        });
    }
}); 