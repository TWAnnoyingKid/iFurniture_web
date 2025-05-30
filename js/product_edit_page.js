document.addEventListener('DOMContentLoaded', () => {
    const editProductForm = document.getElementById('editProductForm');
    const productIdField = document.getElementById('productId');
    const productNameField = document.getElementById('productName');
    const productPriceField = document.getElementById('productPrice');
    const productCategoryField = document.getElementById('productCategory');
    const productDescriptionField = document.getElementById('productDescription');
    const dimensionWidthField = document.getElementById('dimensionWidth');
    const dimensionHeightField = document.getElementById('dimensionHeight');
    const dimensionDepthField = document.getElementById('dimensionDepth');
    // const productStockField = document.getElementById('productStock');
    
    const currentImagesContainer = document.getElementById('currentImagesContainer');
    const newProductImagesField = document.getElementById('newProductImages');
    const newImagesPreviewContainer = document.getElementById('newImagesPreview');
    const currentModelContainer = document.getElementById('currentModelContainer');
    const modelPreviewContainer = document.getElementById('modelPreviewContainer');

    const loadingContainer = document.getElementById('loadingContainer');
    const messageContainer = document.getElementById('messageContainer');
    const saveChangesBtn = document.getElementById('saveChangesBtn');

    let currentProductId = null;
    let currentImages = []; // 儲存當前商品的圖片資訊
    let imagesToDelete = []; // 儲存要刪除的圖片ID
    let hasUnsavedChanges = false; // 追蹤是否有未保存的更改

    // 從 URL 獲取商品 ID
    function getProductIdFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.get('id');
    }

    // 顯示訊息
    function showMessage(message, type = 'error') {
        messageContainer.textContent = message;
        messageContainer.className = `message-container ${type}`;
        messageContainer.style.display = 'block';
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, 5000);
    }

    // 載入商品詳細資訊
    async function loadProductDetails(productId) {
        loadingContainer.style.display = 'block';
        editProductForm.style.display = 'none';
        try {
            const response = await fetch(`../php/get_product_details.php?id=${productId}`, {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP 錯誤: ${response.status}`);
            }
            const result = await response.json();

            if (result.success && result.product) {
                populateForm(result.product);
                editProductForm.style.display = 'flex';
            } else {
                throw new Error(result.message || '無法獲取商品詳細資訊');
            }
        } catch (error) {
            console.error('載入商品詳細資訊失敗:', error);
            showMessage(`載入商品資訊失敗: ${error.message}`, 'error');
            // 可以考慮導回列表頁或顯示更明顯的錯誤
        } finally {
            loadingContainer.style.display = 'none';
        }
    }

    // 填充表單
    function populateForm(product) {
        productIdField.value = product.id;
        productNameField.value = product.name || '';
        productPriceField.value = product.price || '';
        productCategoryField.value = product.category || '';
        productDescriptionField.value = product.description || '';
        
        if (product.dimensions) {
            dimensionWidthField.value = product.dimensions.width || '';
            dimensionHeightField.value = product.dimensions.height || '';
            dimensionDepthField.value = product.dimensions.depth || '';
        }
        
        // productStockField.value = product.stock !== undefined ? product.stock : '';

        // 儲存當前圖片資訊
        currentImages = product.images || [];
        
        // 填充現有圖片，添加刪除功能
        renderCurrentImages();

        // 填充現有模型，添加預覽和刪除功能
        renderCurrentModel(product.model);
        
        // 設置表單變更監聽
        setupFormChangeListeners();
    }
    
    // 渲染當前圖片
    function renderCurrentImages() {
        currentImagesContainer.innerHTML = '';
        
        if (currentImages.length === 0) {
            currentImagesContainer.innerHTML = '<p>目前沒有圖片</p>';
            return;
        }
        
        const imageGrid = document.createElement('div');
        imageGrid.className = 'image-grid';
        
        currentImages.forEach((image, index) => {
            const imageContainer = document.createElement('div');
            imageContainer.className = 'image-item';
            imageContainer.dataset.imageId = image.id || index;
            
            const imgElement = document.createElement('img');
            imgElement.src = image.url;
            imgElement.alt = image.filename || '商品圖片';
            
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'image-delete-btn';
            deleteBtn.innerHTML = '&times;';
            deleteBtn.title = '刪除圖片';
            deleteBtn.addEventListener('click', () => {
                // 標記圖片為待刪除
                if (image.id) {
                    imagesToDelete.push(image.id);
                }
                // 從當前圖片陣列中移除
                currentImages = currentImages.filter((_, i) => i !== index);
                // 重新渲染圖片
                renderCurrentImages();
                hasUnsavedChanges = true;
            });
            
            imageContainer.appendChild(imgElement);
            imageContainer.appendChild(deleteBtn);
            imageGrid.appendChild(imageContainer);
        });
        
        currentImagesContainer.appendChild(imageGrid);
    }
    
    // 渲染當前模型
    function renderCurrentModel(model) {
        currentModelContainer.innerHTML = '';
        
        if (!model || !model.url) {
            currentModelContainer.innerHTML = '<p>目前沒有3D模型</p>';
            return;
        }
        
        const modelContainer = document.createElement('div');
        modelContainer.className = 'model-container';
        
        // 添加模型資訊
        const modelInfo = document.createElement('div');
        modelInfo.className = 'model-info';
        modelInfo.innerHTML = `
            <p>當前模型: ${model.filename || '未知檔名'}</p>
        `;
        
        // 添加預覽按鈕
        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.className = 'btn-secondary model-preview-btn';
        previewBtn.textContent = '預覽模型';
        previewBtn.addEventListener('click', () => {
            showModelPreview(model.url, model.file_id, productNameField.value);
        });
        
        const buttonGroup = document.createElement('div');
        buttonGroup.className = 'button-group';
        buttonGroup.appendChild(previewBtn);
        
        modelContainer.appendChild(modelInfo);
        modelContainer.appendChild(buttonGroup);
        currentModelContainer.appendChild(modelContainer);
    }
    
    // 顯示模型預覽
    function showModelPreview(modelUrl, modelFileId, productName) {
        const modelModal = document.getElementById('modelModal');
        const modalBody = modelModal.querySelector('.modal-body');
        const modelModalTitle = document.getElementById('modelModalTitle');
        const modelFileIdSpan = document.getElementById('modelFileId');
        
        // 移除舊的 model-viewer 元素（如果存在）
        const oldModelViewer = document.getElementById('modalModelViewer');
        if (oldModelViewer) {
            oldModelViewer.remove();
        }
        
        // 創建新的 model-viewer 元素
        const newModelViewer = document.createElement('model-viewer');
        newModelViewer.id = 'modalModelViewer';
        newModelViewer.src = modelUrl;
        newModelViewer.alt = `${productName || '商品'} 3D 模型`;
        newModelViewer.setAttribute('camera-controls', '');
        newModelViewer.setAttribute('auto-rotate', '');
        newModelViewer.setAttribute('shadow-intensity', '1');
        newModelViewer.setAttribute('shadow-softness', '1');
        newModelViewer.setAttribute('exposure', '1.5');
        newModelViewer.style.width = '100%';
        newModelViewer.style.height = '450px'; // 與 CSS 中的高度一致
        
        // 將新的 model-viewer 元素添加到模態對話框中
        // 確保 model-info 在 model-viewer 之後
        const modelInfoDiv = modalBody.querySelector('.model-info');
        if (modelInfoDiv) {
            modalBody.insertBefore(newModelViewer, modelInfoDiv);
        } else {
            modalBody.appendChild(newModelViewer);
        }
        
        // 設置模型資訊
        modelModalTitle.textContent = productName ? `${productName} - 3D模型預覽` : '3D 模型預覽';
        if (modelFileIdSpan) { // 確保 modelFileIdSpan 存在
            modelFileIdSpan.textContent = modelFileId || '-';
        }
        
        // 顯示模態對話框
        modelModal.style.display = 'block';
        
        // 添加關閉按鈕事件
        const closeModelModalBtn = document.getElementById('closeModelModal');
        const modalCloseBtn = modelModal.querySelector('.modal-close');
        
        // 移除舊的事件監聽器（避免重複添加）
        closeModelModalBtn.removeEventListener('click', closeModelPreviewModal);
        modalCloseBtn.removeEventListener('click', closeModelPreviewModal);
        
        // 添加新的事件監聽器
        closeModelModalBtn.addEventListener('click', closeModelPreviewModal);
        modalCloseBtn.addEventListener('click', closeModelPreviewModal);
        
        // 點擊覆蓋層關閉模態對話框
        const modalOverlay = modelModal.querySelector('.modal-overlay');
        modalOverlay.removeEventListener('click', handleOverlayClick);
        modalOverlay.addEventListener('click', handleOverlayClick);
        
        // 鍵盤事件（ESC 鍵關閉）
        document.removeEventListener('keydown', handleKeyDown); // 移除舊的，避免重複
        document.addEventListener('keydown', handleKeyDown);
    }
    
    // 關閉模型預覽模態對話框
    function closeModelPreviewModal() {
        const modelModal = document.getElementById('modelModal');
        
        // 隱藏模態對話框
        modelModal.style.display = 'none';
        
        // 移除 model-viewer 元素
        const modelViewer = document.getElementById('modalModelViewer');
        if (modelViewer) {
            modelViewer.remove();
        }
        
        // 移除鍵盤事件監聽器
        document.removeEventListener('keydown', handleKeyDown);
    }
    
    // 處理覆蓋層點擊
    function handleOverlayClick(e) {
        const modelModal = document.getElementById('modelModal');
        if (e.target === modelModal.querySelector('.modal-overlay')) {
            closeModelPreviewModal();
        }
    }
    
    // 處理鍵盤事件
    function handleKeyDown(e) {
        if (e.key === 'Escape') {
            closeModelPreviewModal();
        }
    }
    
    // 設置表單變更監聽
    function setupFormChangeListeners() {
        // 監聽所有輸入欄位的變更
        const formInputs = editProductForm.querySelectorAll('input, textarea, select');
        formInputs.forEach(input => {
            input.addEventListener('change', () => {
                hasUnsavedChanges = true;
            });
            if (input.type === 'text' || input.tagName === 'TEXTAREA') {
                input.addEventListener('input', () => {
                    hasUnsavedChanges = true;
                });
            }
        });
        
        // 監聽新圖片上傳
        newProductImagesField.addEventListener('change', handleNewImagesPreview);
    }
    
    // 處理新上傳圖片的預覽
    function handleNewImagesPreview() {
        newImagesPreviewContainer.innerHTML = '';
        
        if (newProductImagesField.files.length === 0) {
            newImagesPreviewContainer.style.display = 'none';
            return;
        }
        
        newImagesPreviewContainer.style.display = 'block';
        const previewGrid = document.createElement('div');
        previewGrid.className = 'image-grid';
        
        Array.from(newProductImagesField.files).forEach(file => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewContainer = document.createElement('div');
                previewContainer.className = 'image-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = file.name;
                
                previewContainer.appendChild(img);
                previewGrid.appendChild(previewContainer);
            };
            
            reader.readAsDataURL(file);
        });
        
        newImagesPreviewContainer.appendChild(previewGrid);
        hasUnsavedChanges = true;
    }

    // 處理表單提交
    editProductForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        // 表單驗證
        if (!validateForm()) {
            return;
        }
        
        saveChangesBtn.disabled = true;
        saveChangesBtn.textContent = '儲存中...';

        const formData = new FormData(editProductForm);
        
        // 處理尺寸，確保以物件形式發送
        const dimensions = {
            width: dimensionWidthField.value || null,
            height: dimensionHeightField.value || null,
            depth: dimensionDepthField.value || null,
        };
        formData.set('dimensions', JSON.stringify(dimensions));

        // 添加要刪除的圖片ID
        if (imagesToDelete.length > 0) {
            formData.set('imagesToDelete', JSON.stringify(imagesToDelete));
        }

        // 移除空的檔案輸入，避免後端問題
        if (newProductImagesField.files.length === 0) {
            formData.delete('newProductImages[]');
        }

        try {
            const response = await fetch('../php/update_product.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData 
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP 錯誤: ${response.status}. ${errorText}`);
            }

            const result = await response.json();

            if (result.success) {
                showMessage(result.message || '商品更新成功！', 'success');
                hasUnsavedChanges = false;
                
                // 成功後顯示提示並在2秒後跳轉回商品列表
                setTimeout(() => {
                    window.location.href = 'manage_products.html';
                }, 2000);
            } else {
                throw new Error(result.message || '更新商品失敗');
            }

        } catch (error) {
            console.error('更新商品失敗:', error);
            showMessage(`更新商品失敗: ${error.message}`, 'error');
        } finally {
            saveChangesBtn.disabled = false;
            saveChangesBtn.textContent = '儲存變更';
        }
    });
    
    // 表單驗證
    function validateForm() {
        // 重置所有錯誤提示
        document.querySelectorAll('.form-error').forEach(el => el.remove());
        
        let isValid = true;
        
        // 驗證商品名稱
        if (!productNameField.value.trim()) {
            showFieldError(productNameField, '請輸入商品名稱');
            isValid = false;
        }
        
        // 驗證價格
        if (!productPriceField.value || parseFloat(productPriceField.value) < 0) {
            showFieldError(productPriceField, '請輸入有效的價格');
            isValid = false;
        }
        
        // 驗證分類
        if (!productCategoryField.value) {
            showFieldError(productCategoryField, '請選擇商品分類');
            isValid = false;
        }
        
        return isValid;
    }
    
    // 顯示欄位錯誤
    function showFieldError(field, message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'form-error';
        errorElement.textContent = message;
        
        // 將錯誤訊息插入到欄位後面
        field.parentNode.appendChild(errorElement);
        
        // 添加錯誤樣式到欄位
        field.classList.add('input-error');
        
        // 當欄位獲得焦點時移除錯誤樣式
        field.addEventListener('focus', function() {
            this.classList.remove('input-error');
            const error = this.parentNode.querySelector('.form-error');
            if (error) {
                error.remove();
            }
        }, { once: true });
    }


    // 初始化
    currentProductId = getProductIdFromUrl();
    if (currentProductId) {
        loadProductDetails(currentProductId);
    } else {
        showMessage('錯誤：未提供商品ID。', 'error');
        editProductForm.style.display = 'none';
        loadingContainer.style.display = 'none';
    }
    
    // 頁面離開提示
    window.addEventListener('beforeunload', (e) => {
        if (hasUnsavedChanges) {
            // 顯示標準的離開提示
            e.preventDefault();
            e.returnValue = '您有未保存的更改，確定要離開嗎？';
            return e.returnValue;
        }
    });
    
    // 返回按鈕點擊事件
    document.querySelector('.page-header .btn-secondary').addEventListener('click', (e) => {
        if (hasUnsavedChanges) {
            if (!confirm('您有未保存的更改，確定要離開嗎？')) {
                e.preventDefault();
            }
        }
    });
});
