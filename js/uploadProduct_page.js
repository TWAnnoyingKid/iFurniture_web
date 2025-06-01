document.addEventListener('DOMContentLoaded', () => {
    const uploadForm = document.getElementById('uploadForm');
    const productImagesInput = document.getElementById('productImages');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const processedResultsContainer = document.getElementById('processedResultsContainer');
    const submitBtn = document.getElementById('submitBtn');
    const modelViewerContainer = document.getElementById('modelViewerContainer');
    const modelViewer = document.getElementById('modelViewer');
    const uploadProductBtn = document.getElementById('uploadProductBtn');
    
    // 從配置文件獲取 API 基礎 URL
    const API_BASE_URL = CONFIG.api.baseUrl;
    
    let selectedFiles = []; // 用於存儲實際 File 對象，以便從預覽中移除
    let currentModelInfo = null; // 存儲當前處理的模型信息

    // 設置模型查看器默認參數
    if (modelViewer) {
        modelViewer.exposure = CONFIG.modelViewer.defaultExposure;
        modelViewer.shadowIntensity = CONFIG.modelViewer.defaultShadowIntensity;
        modelViewer.shadowSoftness = CONFIG.modelViewer.defaultShadowSoftness;
        modelViewer.autoRotate = CONFIG.modelViewer.autoRotate;
    }

    if (productImagesInput) {
        productImagesInput.addEventListener('change', (event) => {
            imagePreviewContainer.innerHTML = ''; // 清空舊預覽
            selectedFiles = Array.from(event.target.files); // 更新 selectedFiles
            
            if (selectedFiles.length === 0) {
                const p = document.createElement('p');
                p.textContent = '未選擇任何圖片。';
                imagePreviewContainer.appendChild(p);
                return;
            }

            // 驗證文件大小和類型
            const invalidFiles = selectedFiles.filter(file => {
                const isValidType = CONFIG.upload.allowedTypes.includes(file.type);
                const isValidSize = file.size <= CONFIG.upload.maxFileSize;
                return !isValidType || !isValidSize;
            });

            if (invalidFiles.length > 0) {
                alert(`有 ${invalidFiles.length} 個文件不符合要求。\n僅允許 ${CONFIG.upload.allowedTypes.join(', ')} 格式，\n且每個文件大小不超過 ${CONFIG.upload.maxFileSize / (1024 * 1024)}MB。`);
                
                // 過濾掉無效文件
                selectedFiles = selectedFiles.filter(file => {
                    const isValidType = CONFIG.upload.allowedTypes.includes(file.type);
                    const isValidSize = file.size <= CONFIG.upload.maxFileSize;
                    return isValidType && isValidSize;
                });
                
                if (selectedFiles.length === 0) {
                    const p = document.createElement('p');
                    p.textContent = '未選擇任何有效圖片。';
                    imagePreviewContainer.appendChild(p);
                    return;
                }
            }

            // 檢查文件數量限制
            if (selectedFiles.length > CONFIG.upload.maxFiles) {
                alert(`最多只能選擇 ${CONFIG.upload.maxFiles} 個文件，超過的部分將被忽略。`);
                selectedFiles = selectedFiles.slice(0, CONFIG.upload.maxFiles);
            }

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const previewItem = document.createElement('div');
                    previewItem.classList.add('preview-image-item');
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = `預覽 ${file.name}`;
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.classList.add('remove-preview-btn');
                    removeBtn.innerHTML = '&times;';
                    removeBtn.type = 'button'; // 防止觸發表單提交
                    removeBtn.onclick = () => {
                        previewItem.remove();
                        // 從 selectedFiles 中移除對應的 File 對象
                        selectedFiles.splice(index, 1);
                        // 更新 input.files (這比較棘手，標準做法是重新構建 DataTransfer)
                        updateFileInputFiles();
                        if(imagePreviewContainer.children.length === 0){
                            const p = document.createElement('p');
                            p.textContent = '未選擇任何圖片。';
                            imagePreviewContainer.appendChild(p);
                        }
                    };
                    
                    previewItem.appendChild(img);
                    previewItem.appendChild(removeBtn);
                    imagePreviewContainer.appendChild(previewItem);
                }
                reader.readAsDataURL(file);
            });
            
            // 更新文件輸入框
            updateFileInputFiles();
        });
    }

    function updateFileInputFiles() {
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        productImagesInput.files = dataTransfer.files;
    }

    // 顯示加載中動畫
    function showLoading() {
        const loadingDiv = document.createElement('div');
        loadingDiv.classList.add('loading-indicator');
        processedResultsContainer.innerHTML = '';
        processedResultsContainer.style.display = 'flex'; // 確保容器可見
        processedResultsContainer.appendChild(loadingDiv);
        
        // 隱藏模型查看器
        modelViewerContainer.style.display = 'none';
    }

    // 完全隱藏處理結果區域
    function hideProcessedResults() {
        processedResultsContainer.innerHTML = '';
        processedResultsContainer.style.display = 'none'; // 完全隱藏
    }

    // 顯示處理結果區域
    function showProcessedResults() {
        processedResultsContainer.style.display = 'flex'; // 重新顯示
    }

    // 清除處理結果區域
    function clearResults() {
        showProcessedResults();
        processedResultsContainer.innerHTML = '<p class="no-results">尚未處理任何圖片。</p>';
        modelViewerContainer.style.display = 'none';
    }

    // 上傳並處理圖片
    if (uploadForm) {
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            submitBtn.disabled = true;
            submitBtn.textContent = '上傳處理中...';
            
            // 顯示加載動畫
            showLoading();

            const formData = new FormData();
            const textPrompt = document.getElementById('modalProductCategory')?.value || '';
            
            if (textPrompt) {
                formData.append('textPrompt', textPrompt);
            }

            if (selectedFiles.length === 0) {
                alert('請至少選擇一張圖片！');
                submitBtn.disabled = false;
                submitBtn.textContent = '上傳並處理';
                clearResults();
                return;
            }
            
            selectedFiles.forEach((file) => {
                formData.append('productImages[]', file, file.name);
            });

            // 後端API端點
            const backendEndpoint = `${API_BASE_URL}/api/upload-and-process-images`;

            try {
                if (CONFIG.debug) {
                    console.log('正在連接到處理服務.....');
                }
                
                const response = await fetch(backendEndpoint, {
                    method: 'POST',
                    body: formData,
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ message: '發生未知錯誤，狀態碼：' + response.status }));
                    throw new Error(errorData.message || `伺服器錯誤: ${response.status}`);
                }

                const results = await response.json();
                
                if (CONFIG.debug) {
                    console.log('收到處理結果');
                }
                
                if (results && results.processed_images && results.processed_images.length > 0) {
                    // 尋找3D模型文件
                    const modelFiles = results.processed_images.filter(item => item.type === '3d_model' && item.format === 'glb');
                    
                    if (modelFiles.length > 0) {
                        // 完全隱藏處理結果區域，避免白色空間
                        hideProcessedResults();
                        
                        // 記錄當前模型信息，包含產品序號和資料夾資訊
                        currentModelInfo = {
                            name: results.product_name,
                            url: `${API_BASE_URL}${modelFiles[0].url}`,
                            product_id: results.product_id || modelFiles[0].product_id,  // 產品序號
                            product_folder: results.product_folder || modelFiles[0].product_folder  // 資料夾名稱
                        };
                        
                        // 設置 model-viewer 源
                        modelViewer.src = currentModelInfo.url;
                        modelViewer.alt = `${currentModelInfo.name} 3D 模型`;
                        
                        // 直接顯示模型查看器
                        modelViewerContainer.style.display = 'block';
                        
                        // 等待一下讓模型加載，然後滾動到查看器位置 (僅在移動設備上)
                        if (window.innerWidth <= 768) {
                            setTimeout(() => {
                                modelViewerContainer.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start'
                                });
                            }, 500);
                        }
                        
                        // 在控制台記錄產品資訊
                        if (CONFIG.debug) {
                            console.log('產品資訊:', {
                                name: currentModelInfo.name,
                                product_id: currentModelInfo.product_id,
                                product_folder: currentModelInfo.product_folder,
                                model_url: currentModelInfo.url
                            });
                        }
                    } else {
                        showProcessedResults();
                        processedResultsContainer.innerHTML = '<p class="no-results">處理完成，但未生成 3D 模型文件。</p>';
                        modelViewerContainer.style.display = 'none';
                    }
                } else {
                    showProcessedResults();
                    processedResultsContainer.innerHTML = '<p class="no-results">處理完成，但未收到有效的結果。</p>';
                    modelViewerContainer.style.display = 'none';
                }

            } catch (error) {  
                console.error('上傳或處理失敗:', error);
                showProcessedResults();
                processedResultsContainer.innerHTML = ''; 
                const pError = document.createElement('p');
                pError.classList.add('no-results');
                pError.style.color = 'red';
                pError.textContent = `處理失敗：${error.message || '請檢查網路連線或稍後再試。'}`;
                processedResultsContainer.appendChild(pError);
                modelViewerContainer.style.display = 'none';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '上傳並處理';
            }
        });
    }

    // **【修改部分】** 創建商品資訊輸入對話框
    function createProductInfoModal() {
        // 創建模態對話框結構
        const modal = document.createElement('div');
        modal.className = 'product-info-modal';
        modal.innerHTML = `
            <div class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>輸入商品詳細資訊</h3>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="productInfoForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="modalProductName">商品名稱 *</label>
                                    <input type="text" id="modalProductName" required>
                                </div>
                                <div class="form-group">
                                    <label for="modalProductPrice">商品價格 (元) *</label>
                                    <input type="number" id="modalProductPrice" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="modalProductCategory">商品種類 *</label>
                                    <select id="modalProductCategory" required>
                                        <option value="">請選擇...</option>
                                        <option value="chair">椅子</option>
                                        <option value="desk">桌子</option>
                                        <option value="sofa">沙發</option>
                                        <option value="bed">床具</option>
                                        <option value="cabinet">櫃子</option>
                                        <option value="decoration">裝飾品</option>
                                        <option value="lighting">燈具</option>
                                        <option value="storage">收納用品</option>
                                        <option value="other">其他</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="modalProductUrl">商品網址 (選填)</label>
                                    <input type="url" id="modalProductUrl" placeholder="https://...">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="modalProductDescription">商品描述</label>
                                <textarea id="modalProductDescription" rows="3" placeholder="請描述商品特色、材質、用途等..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>商品尺寸 (cm)</label>
                                <div class="size-inputs">
                                    <div class="size-input-group">
                                        <label for="modalProductWidth">寬</label>
                                        <input type="number" id="modalProductWidth" min="0" step="0.1" placeholder="0.0">
                                    </div>
                                    <div class="size-input-group">
                                        <label for="modalProductHeight">高</label>
                                        <input type="number" id="modalProductHeight" min="0" step="0.1" placeholder="0.0">
                                    </div>
                                    <div class="size-input-group">
                                        <label for="modalProductDepth">深</label>
                                        <input type="number" id="modalProductDepth" min="0" step="0.1" placeholder="0.0">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="modalProductImages">商品圖片 (可多選)</label>
                                <input type="file" id="modalProductImages" multiple accept="image/*">
                                <div id="modalImagePreview" class="modal-image-preview"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" id="modalCancelBtn">取消</button>
                        <button type="button" class="btn-main" id="modalSaveBtn">儲存商品</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // 處理圖片預覽
        const modalImagesInput = document.getElementById('modalProductImages');
        const modalImagePreview = document.getElementById('modalImagePreview');
        
        modalImagesInput.addEventListener('change', (event) => {
            modalImagePreview.innerHTML = '';
            const files = Array.from(event.target.files);
            
            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'modal-preview-item';
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" alt="預覽圖片">
                            <button type="button" class="remove-preview-btn" onclick="this.parentElement.remove()">&times;</button>
                        `;
                        modalImagePreview.appendChild(previewItem);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });

        return modal;
    }

    // **【修改部分】** 上傳商品到產品資料庫
    if (uploadProductBtn) {
        uploadProductBtn.addEventListener('click', async () => {
            if (!currentModelInfo) {
                alert('請先處理並生成3D模型！');
                return;
            }
            
            // 創建並顯示商品資訊輸入對話框
            const modal = createProductInfoModal();
            
            // 處理對話框事件
            const modalCancelBtn = modal.querySelector('#modalCancelBtn');
            const modalCloseBtn = modal.querySelector('.modal-close');
            const modalSaveBtn = modal.querySelector('#modalSaveBtn');
            
            // 關閉對話框
            const closeModal = () => {
                document.body.removeChild(modal);
            };
            
            modalCancelBtn.addEventListener('click', closeModal);
            modalCloseBtn.addEventListener('click', closeModal);
            
            // 點擊覆蓋層關閉對話框
            modal.querySelector('.modal-overlay').addEventListener('click', (e) => {
                if (e.target === modal.querySelector('.modal-overlay')) {
                    closeModal();
                }
            });
            
            // 儲存商品
            modalSaveBtn.addEventListener('click', async () => {
                try {
                    modalSaveBtn.disabled = true;
                    modalSaveBtn.textContent = '儲存中...';
                    
                    // 收集表單資料
                    const formData = new FormData();
                    
                    // 基本資訊
                    formData.append('name', document.getElementById('modalProductName').value);
                    formData.append('price', parseFloat(document.getElementById('modalProductPrice').value));
                    formData.append('category', document.getElementById('modalProductCategory').value);
                    formData.append('description', document.getElementById('modalProductDescription').value);
                    formData.append('url', document.getElementById('modalProductUrl').value);
                    
                    // **【修改部分】** 添加產品序號和資料夾資訊
                    if (currentModelInfo.product_id) {
                        formData.append('product_id', currentModelInfo.product_id);
                    }
                    if (currentModelInfo.product_folder) {
                        formData.append('product_folder', currentModelInfo.product_folder);
                    }
                    
                    // 尺寸資訊
                    const width = parseFloat(document.getElementById('modalProductWidth').value) || 0;
                    const height = parseFloat(document.getElementById('modalProductHeight').value) || 0;
                    const depth = parseFloat(document.getElementById('modalProductDepth').value) || 0;
                    
                    // const sizeOptions = {
                    //     width: width ? parseFloat(width) : null,
                    //     height: height ? parseFloat(height) : null,
                    //     depth: depth ? parseFloat(depth) : null
                    // };
                    // formData.append('size_options', JSON.stringify(sizeOptions));

                    formData.append('width', width);
                    formData.append('height', height);
                    formData.append('depth', depth);

                    
                    // 模型資訊
                    formData.append('model_url', currentModelInfo.url);
                    
                    // 商品圖片
                    const imageFiles = document.getElementById('modalProductImages').files;
                    for (let i = 0; i < imageFiles.length; i++) {
                        formData.append('images[]', imageFiles[i]);
                    }
                    
                    // 發送到後端
                    const response = await fetch('../php/save_product.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP 錯誤: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert(`商品 "${result.product.name}" 儲存成功！`);
                        closeModal();
                        
                        // 刷新頁面
                        window.location.reload();
                        
                    } else {
                        throw new Error(result.message || '儲存商品失敗');
                    }
                    
                } catch (error) {
                    console.error('儲存商品失敗:', error);
                    alert(`儲存商品失敗: ${error.message}`);
                } finally {
                    modalSaveBtn.disabled = false;
                    modalSaveBtn.textContent = '儲存商品';
                }
            });
        });
    }
});
