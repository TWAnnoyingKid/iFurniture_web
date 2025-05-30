document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');

    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', function(event) {
            event.preventDefault(); // 防止表單實際提交

            const email = document.getElementById('email').value;
            
            // 實際應用中，這裡會發送請求到後端處理密碼重設
            console.log('忘記密碼表單已提交');
            console.log('電子郵件:', email);
            alert('密碼重設郵件（模擬）已發送到 ' + email + '！實際功能尚未實作。');
        });
    }
}); 