document.addEventListener('DOMContentLoaded', () => {
    const productsContainer = document.getElementById('productsContainer');
    const loadingContainer = document.getElementById('loadingContainer');
    const refreshBtn = document.getElementById('refreshBtn');
    const modelModal = document.getElementById('modelModal');
    const modalModelViewer = document.getElementById('modalModelViewer');
    const modelModalTitle = document.getElementById('modelModalTitle');
    const modelFileId = document.getElementById('modelFileId');
    const closeModelModal = document.getElementById('closeModelModal');
    const modalCloseBtn = document.querySelector('.modal-close');
    
    // 刪除確認對話框元素
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteProductName = document.getElementById('deleteProductName');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    let currentProductToDelete = null;

    // 載入商品列表
    async function loadProducts() {
        try {
            loadingContainer.style.display = 'block';
            productsContainer.innerHTML = '';

            const response = await fetch('../php/products.php?action=get_my_list', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP 錯誤: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                displayProducts(result.products);
                
                if (CONFIG.debug) {
                    console.log(`載入了 ${result.products.length} 個商品`, {
                        username: result.username,
                        company: result.company,
                        collection: result.collection
                    });
                }
            } else {
                throw new Error(result.message || '獲取商品失敗');
            }

        } catch (error) {
            console.error('載入商品失敗:', error);
            productsContainer.innerHTML = `
                <div class="error-message">
                    <p>載入商品失敗: ${error.message}</p>
                    <button onclick="loadProducts()" class="btn-main">重試</button>
                </div>
            `;
        } finally {
            loadingContainer.style.display = 'none';
        }
    }

    // 顯示商品列表
    function displayProducts(products) {
        if (products.length === 0) {
            productsContainer.innerHTML = `
                <div class="empty-state">
                    <p>還沒有任何商品</p>
                    <a href="uploadProduct_page.html" class="btn-main">上傳第一個商品</a>
                </div>
            `;
            return;
        }

        const productCards = products.map(product => createProductCard(product)).join('');
        productsContainer.innerHTML = productCards;
    }

    // 創建商品卡片
    function createProductCard(product) {
        const hasModel = product.model_file_id && product.model_url;
        const modelClass = hasModel ? 'has-model' : 'no-model';
        
        // 處理圖片
        let mainImageUrl = '';
        if (product.images && product.images.length > 0) {
            const firstImage = product.images[0];
            if (typeof firstImage === 'object' && firstImage.url) {
                // 新格式：GridFS圖片物件
                mainImageUrl = firstImage.url;
            } 
        }

        let sizeText = '尺寸: ';
        if (product.width != null || product.height != null || product.depth != null) {
            const dimensions = [];
            if (product.width != null && product.width > 0) dimensions.push(`寬${product.width}cm`);
            if (product.height != null && product.height > 0) dimensions.push(`高${product.height}cm`);
            if (product.depth != null && product.depth > 0) dimensions.push(`深${product.depth}cm`);
                
            if (dimensions.length > 0) {
                sizeText = dimensions.join(' × ');
            } else {
                sizeText = '尺寸待更新';
            }
        } 

        return `
            <div class="product-card" data-product-id="${product.id}">
                <div class="product-image">
                    <img src="${mainImageUrl}" alt="${product.name}">
                    ${hasModel ? `<div class="model-badge ${modelClass}">3D</div>` : ''}
                </div>
                <div class="product-info">
                    <h3 class="product-name">${product.name}</h3>
                    <p class="product-price">NT$ ${product.price.toLocaleString()}</p>
                    <p class="product-size">${sizeText}</p>
                </div>
                <div class="product-actions">
                    ${hasModel ? `
                    <button class="btn-secondary view-model-btn" data-model-url="${product.model_url}" data-model-id="${product.model_file_id}" data-product-name="${product.name}">
                        預覽模型
                    </button>
                    ` : ''}
                    <button class="btn-secondary edit-product-btn" data-product-id="${product.id}" data-product-name="${product.name}">
                        編輯商品
                    </button>
                    <button class="btn-danger delete-product-btn" data-product-id="${product.id}" data-product-name="${product.name}">
                        刪除商品
                    </button>
                </div>
            </div>
        `;
    }

    // 獲取類別中文名稱
    function getCategoryName(category) {
        const categories = {
            'chair': '椅子',
            'desk': '桌子',
            'sofa': '沙發',
            'bed': '床具',
            'cabinet': '櫃子',
            'decoration': '裝飾品',
            'lighting': '燈具',
            'storage': '收納用品',
            'other': '其他'
        };
        return categories[category] || category;
    }

    // 顯示模型預覽
    function showModelPreview(modelUrl, modelFileId, productName) {
        const modalBody = modelModal.querySelector('.modal-body');
        
        // 移除舊的 model-viewer 元素（如果存在）
        const oldModelViewer = document.getElementById('modalModelViewer');
        if (oldModelViewer) {
            oldModelViewer.remove();
        }
        
        // 創建新的 model-viewer 元素
        const newModelViewer = document.createElement('model-viewer');
        newModelViewer.id = 'modalModelViewer';
        newModelViewer.src = modelUrl;
        newModelViewer.alt = `${productName} 3D 模型`;
        newModelViewer.setAttribute('camera-controls', '');
        newModelViewer.setAttribute('auto-rotate', '');
        newModelViewer.setAttribute('shadow-intensity', '1');
        newModelViewer.setAttribute('shadow-softness', '1');
        newModelViewer.setAttribute('exposure', '1.5');
        newModelViewer.style.width = '100%';
        newModelViewer.style.height = '400px';
        
        // 將新的 model-viewer 元素添加到模態對話框中
        modalBody.appendChild(newModelViewer);
        
        modelModalTitle.textContent = `${productName} - 3D模型預覽`;
        const modelFileIdSpan = document.getElementById('modelFileIdSpan'); // 獲取新添加的 span 元素
        if (modelFileIdSpan) { // 確保 modelFileIdSpan 存在
            modelFileIdSpan.textContent = modelFileId || '-';
        }
        
        // 顯示模態對話框
        modelModal.style.display = 'block';
        
        if (CONFIG.debug) {
            console.log('顯示GridFS模型:', {
                product: productName,
                file_id: modelFileId,
                url: modelUrl
            });
        }
    }

    // 關閉模型預覽
    function closeModelPreview() {
        modelModal.style.display = 'none';
        
        // 移除 model-viewer 元素
        const modelViewer = document.getElementById('modalModelViewer');
        if (modelViewer) {
            modelViewer.remove();
        }
    }

    // 事件監聽器
    refreshBtn.addEventListener('click', loadProducts);
    closeModelModal.addEventListener('click', closeModelPreview);
    modalCloseBtn.addEventListener('click', closeModelPreview);

    // 點擊覆蓋層關閉模態對話框
    modelModal.querySelector('.modal-overlay').addEventListener('click', (e) => {
        if (e.target === modelModal.querySelector('.modal-overlay')) {
            closeModelPreview();
        }
    });
    
    // 刪除確認對話框事件
    cancelDeleteBtn.addEventListener('click', closeDeleteConfirmModal);
    confirmDeleteBtn.addEventListener('click', confirmDeleteProduct);
    
    // 點擊覆蓋層關閉刪除確認對話框
    deleteConfirmModal.querySelector('.modal-overlay').addEventListener('click', (e) => {
        if (e.target === deleteConfirmModal.querySelector('.modal-overlay')) {
            closeDeleteConfirmModal();
        }
    });

    // 商品動作事件監聽
    productsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('view-model-btn')) {
            const modelUrl = e.target.dataset.modelUrl;
            const modelId = e.target.dataset.modelId;
            const productName = e.target.dataset.productName;
            showModelPreview(modelUrl, modelId, productName);
        } else if (e.target.classList.contains('edit-product-btn')) {
            const productId = e.target.dataset.productId;
            // 導向到編輯頁面
            window.location.href = `product_edit_page.html?id=${productId}`;
        } else if (e.target.classList.contains('delete-product-btn')) {
            const productId = e.target.dataset.productId;
            const productName = e.target.dataset.productName;
            showDeleteConfirmModal(productId, productName);
        }
    });

    // 鍵盤事件
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modelModal.style.display === 'block') {
                closeModelPreview();
            }
            if (deleteConfirmModal.style.display === 'block') {
                closeDeleteConfirmModal();
            }
        }
    });
    
    // 顯示刪除確認對話框
    function showDeleteConfirmModal(productId, productName) {
        currentProductToDelete = productId;
        deleteProductName.textContent = productName;
        deleteConfirmModal.style.display = 'block';
    }
    
    // 關閉刪除確認對話框
    function closeDeleteConfirmModal() {
        deleteConfirmModal.style.display = 'none';
        currentProductToDelete = null;
    }
    
    // 確認刪除商品
    async function confirmDeleteProduct() {
        if (!currentProductToDelete) return;
        
        try {
            // 使用 FormData 來傳送資料
            const formData = new FormData();
            formData.append('productId', currentProductToDelete);
            formData.append('action', 'delete');
            
            const response = await fetch('../php/update_product.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 關閉對話框
                closeDeleteConfirmModal();
                // 重新載入商品列表
                loadProducts();
                
                if (CONFIG.debug) {
                    console.log('商品刪除成功:', result);
                }
            } else {
                alert(`刪除失敗: ${result.message || '未知錯誤'}`);
                
                if (CONFIG.debug) {
                    console.error('刪除商品失敗:', result);
                }
            }
        } catch (error) {
            console.error('刪除商品時發生錯誤:', error);
            alert(`刪除商品時發生錯誤: ${error.message}`);
        }
    }

    // 初始載入商品
    loadProducts();

    // 全域函數，供其他地方調用
    window.loadProducts = loadProducts;
});
