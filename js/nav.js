// 確保在 DOM 加載完成後執行
document.addEventListener('DOMContentLoaded', function() {
    // 初始化導航欄
    initNavBar();
    
    // 綁定導航欄中各按鈕的事件處理
    bindNavEvents();
    
    // 初始化頁面滾動行為
    initScrollBehavior();
});

/**
 * 初始化導航欄 - 檢查登入狀態並相應更新導航欄顯示
 */
async function initNavBar() {
    try {
        // 從配置文件獲取 API URL，如果有的話
        const apiBaseUrl = window.CONFIG && window.CONFIG.api ? 
                          window.CONFIG.api.baseUrl : '';
        
        // 使用動態或靜態的 API 路徑          
        const checkLoginUrl = apiBaseUrl ? 
                             `${apiBaseUrl}/php/auth.php?action=check_login` : 
                             '../php/auth.php?action=check_login';
                             
        // 檢查登入狀態
        const response = await fetch(checkLoginUrl);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        // 獲取導航欄元素
        const nav = document.querySelector('nav');
        const dropdownDiv = document.querySelector('.dropdown');
        
        if (data.isLoggedIn) {
            // 用戶已登入，顯示管理下拉選單
            if (dropdownDiv) {
                // 確保下拉選單按鈕顯示為"管理"
                const dropdownBtn = dropdownDiv.querySelector('.dropbtn');
                if (dropdownBtn) dropdownBtn.textContent = '管理';
                
                const logoutLink = document.getElementById('logoutLink');
                if (logoutLink) {
                    const logoutUrl = apiBaseUrl ? 
                                     `${apiBaseUrl}/php/auth.php?action=logout` : 
                                     '../php/auth.php?action=logout';
                    logoutLink.setAttribute('href', logoutUrl);
                    
                    // 移除之前可能的事件監聽器
                    const newLogoutLink = logoutLink.cloneNode(true);
                    logoutLink.parentNode.replaceChild(newLogoutLink, logoutLink);
                }
                
                // 顯示下拉選單（如果之前被隱藏）
                dropdownDiv.style.display = '';
            }
            
            // 從 MongoDB 獲取用戶資料（如果需要）
            getUserProfileFromMongoDB();
        } else {
            // 用戶未登入，將下拉選單替換為登入按鈕
            if (dropdownDiv && nav) {
                // 移除下拉選單
                nav.removeChild(dropdownDiv);
                
                // 創建登入按鈕
                const loginLink = document.createElement('a');
                loginLink.href = '../web/login.html';
                loginLink.className = 'login-btn';
                loginLink.textContent = '登入';
                
                // 添加到導航欄
                nav.appendChild(loginLink);
            }
        }
    } catch (error) {
        console.error('初始化導航欄時出錯:', error);
        
        // 錯誤處理 - 默認顯示登入按鈕
        handleNavError();
    }
}

// 處理導航欄初始化錯誤 - 顯示登入按鈕作為後備
function handleNavError() {
    const nav = document.querySelector('nav');
    const dropdownDiv = document.querySelector('.dropdown');
    
    if (dropdownDiv && nav) {
        // 隱藏下拉選單
        dropdownDiv.style.display = 'none';
        
        // 檢查是否已有登入按鈕
        let loginBtn = nav.querySelector('.login-btn');
        if (!loginBtn) {
            // 創建登入按鈕
            loginBtn = document.createElement('a');
            loginBtn.href = '..//login.html';
            loginBtn.className = 'login-btn';
            loginBtn.textContent = '登入';
            
            // 添加到導航欄
            nav.appendChild(loginBtn);
        }
    }
}

// 從 MongoDB 獲取用戶資料
async function getUserProfileFromMongoDB() {
    try {
        // 從配置文件獲取 API URL，如果有的話
        const apiBaseUrl = window.CONFIG && window.CONFIG.api ? 
                          window.CONFIG.api.baseUrl : '';
                          
        // 使用動態或靜態的 API 路徑                  
        const profileUrl = apiBaseUrl ? 
                          `${apiBaseUrl}/php/auth.php?action=get_profile` : 
                          '../php/auth.php?action=get_profile';
        
        const response = await fetch(profileUrl);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // 用戶資料獲取成功，可以在這裡處理進一步的頁面定制
            // console.log('已獲取用戶資料:', data.user);
            
            // 派發用戶資料載入完成事件，讓其他腳本可以監聽並處理
            const userDataEvent = new CustomEvent('userDataLoaded', {
                detail: { userData: data.user }
            });
            document.dispatchEvent(userDataEvent);
            
            // 可以在這裡添加基於用戶資料的 UI 更新
            updateUIWithUserData(data.user);
        } else {
            console.warn('獲取用戶資料失敗:', data.message);
        }
    } catch (error) {
        console.error('獲取用戶資料時出錯:', error);
    }
}

// 根據用戶資料更新 UI 元素
function updateUIWithUserData(userData) {
    // 可以根據用戶角色顯示/隱藏某些元素
    if (userData && userData.role === 'admin') {
        // 顯示管理員專屬功能
        const adminElements = document.querySelectorAll('.admin-only');
        adminElements.forEach(el => el.style.display = 'block');
    }
    
    // 更新用戶名稱顯示（如果頁面上有這樣的元素）
    const userNameElements = document.querySelectorAll('.user-name');
    userNameElements.forEach(el => {
        el.textContent = userData.name || userData.account || '用戶';
    });
}

// 綁定導航欄中各按鈕的事件處理
function bindNavEvents() {
    // 登出按鈕處理
    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        // 移除現有的事件監聽器（如果有）
        const newLogoutLink = logoutLink.cloneNode(true);
        if (logoutLink.parentNode) {
            logoutLink.parentNode.replaceChild(newLogoutLink, logoutLink);
        }
        
        // 添加新的事件監聽器
        newLogoutLink.addEventListener('click', async function(event) {
            event.preventDefault();
            
            try {
                // 從配置文件獲取 API URL，如果有的話
                const apiBaseUrl = window.CONFIG && window.CONFIG.api ? 
                                 window.CONFIG.api.baseUrl : '';
                                 
                // 使用動態或靜態的 API 路徑                
                const logoutUrl = apiBaseUrl ? 
                                `${apiBaseUrl}/php/auth.php?action=logout` : 
                                '../php/auth.php?action=logout';
                
                const response = await fetch(logoutUrl);
                if (!response.ok) {
                    throw new Error(`登出請求失敗: ${response.status}`);
                }
                
                // 登出成功，重新載入頁面或重定向到登入頁面
                window.location.href = '../web/login.html';
            } catch (error) {
                console.error('登出過程出錯:', error);
                alert('登出失敗，請稍後再試');
            }
        });
    }
    
    // 其他導航欄按鈕的事件處理
    const manageProductsLink = document.getElementById('manageProductsLink');
    if (manageProductsLink) {
        manageProductsLink.addEventListener('click', function(event) {
            window.location.href = '../web/manage_products.html';
        });
    }
    
    const manageUsersLink = document.getElementById('manageUsersLink');
    if (manageUsersLink) {
        manageUsersLink.addEventListener('click', function(event) {
            event.preventDefault();
            alert('「管理用戶」功能尚未實作！');
        });
    }
}

// 初始化頁面滾動行為 - 控制導航欄的顯示和隱藏
function initScrollBehavior() {
    const header = document.querySelector('header');
    let lastScrollTop = 0;
    const scrollThreshold = 5; // 滾動超過5px才觸發判斷，防止抖動
    let headerHeight = 0;

    function setHeaderHeight() {
        if (header) {
            headerHeight = header.offsetHeight;
            // 設定 main 內容的上邊距以避免被固定頁首遮擋
            const mainContent = document.querySelector('main'); 
            if (mainContent) {
                mainContent.style.paddingTop = headerHeight + 'px';
            }
        }
    }

    // 初始設定高度和padding
    setHeaderHeight();
    // 當視窗大小改變時重新計算 (例如旋轉設備)
    window.addEventListener('resize', setHeaderHeight);

    if (header) {
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            // 判斷滾動方向
            if (Math.abs(scrollTop - lastScrollTop) <= scrollThreshold) {
                return; // 如果滾動幅度太小，則不處理
            }

            if (scrollTop > lastScrollTop && scrollTop > headerHeight) {
                // 向下滾動且滾動距離超過頁首高度
                header.classList.add('header-hidden');
            } else {
                // 向上滾動或滾動距離未超過頁首高度（或已到頂部附近）
                header.classList.remove('header-hidden');
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop; // 處理 iOS 上的 overscroll
        }, false);
    }
}