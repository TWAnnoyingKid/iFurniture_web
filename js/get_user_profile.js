/**
 * 獲取用戶檔案資訊
 * @returns {Promise<Object|null>} 用戶檔案資料或 null
 */
async function getUserProfile() {
    try {
        // 首先檢查登入狀態
        const loginResponse = await fetch('../php/auth.php?action=check_login', {
            method: 'GET',
            credentials: 'same-origin' // 確保傳送 session cookies
        });
        
        if (!loginResponse.ok) {
            console.error('無法檢查登入狀態');
            return null;
        }
        
        const loginData = await loginResponse.json();
        
        // 如果用戶未登入，直接返回
        if (!loginData.isLoggedIn) {
            console.log('用戶未登入，無法獲取檔案資訊');
            return null;
        }
        // 獲取用戶檔案資訊
        const profileResponse = await fetch('../php/auth.php?action=get_profile', {
            method: 'GET',
            credentials: 'same-origin' // 確保傳送 session cookies
        });
        
        if (!profileResponse.ok) {
            console.error('無法獲取用戶檔案資訊');
            return null;
        }
        
        const profileData = await profileResponse.json();
        
        // 檢查 API 回應是否成功
        if (profileData.success) {
            // 印出公司名稱
            const companyName = profileData.user?.company || '未設定公司';
            console.log('用戶公司名稱:', companyName);
            
            return profileData.user;
        } else {
            console.error('獲取用戶檔案失敗:', profileData.message);
            return null;
        }
        
    } catch (error) {
        console.error('獲取用戶檔案時發生錯誤:', error);
        return null;
    }
}

/**
 * 初始化用戶檔案模組
 * 在頁面載入完成後自動執行
 */
async function initUserProfile() {
    // 檢查是否在適當的頁面（非登入頁面）
    if (window.location.pathname.includes('login.html')) {
        console.log('當前在登入頁面，跳過用戶檔案獲取');
        return;
    }
    
    // 等待 DOM 載入完成後執行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', async () => {
            const userProfile = await getUserProfile();
            if (userProfile) {
                console.log('用戶檔案初始化完成');
            } else {
                console.log('用戶檔案初始化失敗或用戶未登入');
            }
        });
    } else {
        // DOM 已經載入完成，直接執行
        const userProfile = await getUserProfile();
        if (userProfile) {
            console.log('用戶檔案初始化完成');
        } else {
            console.log('用戶檔案初始化失敗或用戶未登入');
        }
    }
}

// 自動初始化
initUserProfile();

// 將函數暴露到全域，供其他模組使用
window.getUserProfile = getUserProfile;