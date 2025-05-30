document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    // 添加密碼顯示/隱藏功能
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            // 切換密碼顯示類型
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // 切換眼睛圖標
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault(); // 防止表單實際提交
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            // 檢查用戶輸入
            if (!username || !password) {
                alert('請填寫使用者名稱和密碼');
                return;
            }
            
            // 創建表單數據
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            
            // 發送登入請求到後端
            fetch('../php/login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    // 如果後端設置了重定向，則跟隨重定向
                    window.location.href = response.url;
                    return;
                }
                return response.text();
            })
            .then(data => {
                if (data && !data.includes('login')) {
                    // 顯示後端返回的錯誤訊息
                    alert(data);
                }
            })
            .catch(error => {
                console.error('登入錯誤:', error);
                alert('登入過程中發生錯誤，請稍後再試');
            });
        });
    }
}); 