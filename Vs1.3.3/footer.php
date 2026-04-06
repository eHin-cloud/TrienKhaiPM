<!-- DANH SÁCH SẢN PHẨM ĐÃ XEM -->
<?php require_once 'config_api.php'; ?>    
<?php if (basename($_SERVER['PHP_SELF']) !== 'product_detail.php'): ?>
    <section id="recently-viewed-section" class="container mx-auto px-4 mt-10 mb-10 hidden">
        <div class="bg-white p-4 md:p-5 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i> Sản phẩm bạn vừa xem
                </h2>
                <button onclick="clearAllViewed()" class="text-xs text-red-500 hover:text-red-700 font-medium bg-red-50 px-3 py-1.5 rounded-lg transition">Xóa tất cả</button>
            </div>
            <div id="viewed-products-container" class="flex gap-4 overflow-x-auto pb-2 hide-scrollbar"></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- AI CHAT WINDOW & FOOTER CONTENT -->
    <style>
        #ai-chat-window { display: none; position: fixed; bottom: 80px; right: 10px; width: calc(100% - 20px); max-width: 360px; height: 500px; max-height: 80vh; background: white; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); z-index: 1001; flex-direction: column; overflow: hidden; border: 1px solid #e5e7eb; }
        @media (min-width: 768px) { #ai-chat-window { bottom: 90px; right: 20px; height: 550px; } }
        #ai-chat-window.active { display: flex; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; background: #f9fafb; scroll-behavior: smooth; }
        .message { max-width: 85%; padding: 10px 14px; font-size: 13.5px; line-height: 1.5; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-wrap: break-word; }
        .message.user { align-self: flex-end; background: #0046ab; color: white; border-radius: 16px 16px 4px 16px; }
        .message.ai { align-self: flex-start; background: white; color: #1f2937; border-radius: 16px 16px 16px 4px; border: 1px solid #e5e7eb; }
        .message.ai b { color: #0046ab; }
        .loading-dots span { display: inline-block; width: 6px; height: 6px; background: #999; border-radius: 50%; margin: 0 2px; animation: bounce 1.4s infinite ease-in-out both; }
        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1.0); } }
    </style>

    <div id="ai-chat-window">
        <div class="bg-primary text-white p-3 flex justify-between items-center shadow-sm z-10">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-primary text-lg"><i class="fa-solid fa-robot"></i></div>
                <div>
                    <div class="font-bold text-sm">Trợ lý AI PRO</div>
                    <div class="text-[10px] text-blue-200 flex items-center gap-1"><span class="w-1.5 h-1.5 bg-green-400 rounded-full inline-block"></span> Đang trực tuyến</div>
                </div>
            </div>
            <button onclick="toggleAIChat()" class="hover:bg-white/20 w-8 h-8 rounded-full transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="chat-messages" id="chat-messages">
        </div>

        <div class="px-3 pb-2 pt-2 bg-white overflow-x-auto hide-scrollbar whitespace-nowrap border-t border-gray-100 shadow-[0_-5px_10px_rgba(0,0,0,0.02)]">
            <button onclick="sendQuickMessage('Tư vấn giúp tôi Tivi dưới 15 triệu')" class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1">📺 Tivi dưới 15tr</button>
            <button onclick="sendQuickMessage('Tìm máy lạnh tiết kiệm điện Inverter')" class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1">❄️ Máy lạnh Inverter</button>
            <button onclick="sendQuickMessage('Chính sách trả góp và bảo hành thế nào?')" class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition">💳 Trả góp & Bảo hành</button>
        </div>

        <div class="p-3 bg-white border-t border-gray-100 flex gap-2 items-center">
            <input type="text" id="ai-input" placeholder="Nhập câu hỏi..." class="flex-1 text-[13px] bg-gray-100 border-transparent rounded-full px-4 py-2.5 focus:outline-none focus:ring-1 focus:ring-primary focus:bg-white transition">
            <button onclick="sendMessage()" class="bg-primary text-white w-9 h-9 rounded-full flex items-center justify-center hover:bg-blue-800 transition shadow-sm"><i class="fa-solid fa-paper-plane text-[13px]"></i></button>
        </div>
    </div>

    <div onclick="toggleAIChat()" class="fixed bottom-4 right-4 md:bottom-6 md:right-6 z-50 group">
        <div class="bg-secondary text-primary p-3 rounded-full shadow-xl flex items-center justify-center cursor-pointer hover:scale-110 transition duration-300 w-12 h-12 md:w-14 md:h-14">
            <i class="fa-solid fa-robot text-xl md:text-2xl"></i>
            <span class="absolute 0 top-0 right-0 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white"></span></span>
        </div>
    </div>

    <footer class="bg-white py-6 md:py-8 border-t border-gray-200 mt-auto text-center">
        <div class="container mx-auto px-4">
            <div class="font-bold text-xl text-primary mb-2">DIENMAY<span class="text-secondary">PRO</span></div>
            <p class="text-gray-500 text-xs md:text-sm font-medium">© 2026 DIENMAYPRO. Hệ thống siêu thị điện máy hàng đầu.</p>
        </div>
    </footer>

    <!-- Tích hợp thư viện SweetAlert2 cho hiệu ứng thông báo mượt mà giống Admin -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // HÀM GỌI MODAL THÀNH CÔNG BẰNG SWEETALERT2
        function showSuccessModal(message) {
            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: message,
                timer: 2000,
                showConfirmButton: false,
                backdrop: `rgba(0,0,0,0.4)`
            });
        }

        // XỬ LÝ GỬI FORM TRẢ GÓP BẰNG AJAX
        function submitInstallment(e) {
            e.preventDefault(); 
            const formData = new FormData(document.getElementById('installmentForm'));
            fetch('save_installment.php', { method: 'POST', body: new URLSearchParams(formData) })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal('Đăng ký tư vấn trả góp thành công!');
                    document.getElementById('installmentModal').classList.add('hidden');
                    document.getElementById('installmentForm').reset();
                } else { 
                    Swal.fire({ icon: 'error', title: 'Lỗi!', text: data.message }); 
                }
            }).catch(error => { 
                Swal.fire({ icon: 'error', title: 'Lỗi!', text: 'Có lỗi xảy ra khi gửi yêu cầu!' }); 
            });
        }

        // AJAX THÊM VÀO GIỎ HÀNG 
        function addToCartAjax(productId) {
            fetch('add_to_cart.php', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&id=' + productId
            })
            .then(response => response.json()) 
            .then(data => {
                if (data.success) {
                    let badge = document.getElementById('cart-count-badge');
                    if (badge) {
                        badge.innerText = data.cart_count;
                        badge.classList.remove('hidden');
                        badge.classList.add('animate-bounce');
                        setTimeout(() => badge.classList.remove('animate-bounce'), 1000);
                    }
                    showSuccessModal('Đã thêm sản phẩm vào giỏ hàng!');
                } else if (data.message === 'not_logged_in') {
                    let loginModal = document.getElementById('loginModal');
                    if(loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: 'Thông báo', text: 'Bạn cần đăng nhập để thêm vào giỏ hàng!' });
                }
            })
            .catch(error => { 
                Swal.fire({ icon: 'error', title: 'Lỗi!', text: 'Lỗi kết nối hoặc phiên đăng nhập!' }); 
            });
        }

        // AJAX MUA NGAY (Thêm vào giỏ xong chuyển hướng sang trang Cart)
        function buyNowAjax(productId) {
            fetch('add_to_cart.php', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&id=' + productId
            })
            .then(response => response.json()) 
            .then(data => {
                if (data.success) { window.location.href = 'cart.php'; } 
                else if (data.message === 'not_logged_in') {
                    let loginModal = document.getElementById('loginModal');
                    if(loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: 'Thông báo', text: 'Bạn cần đăng nhập để mua hàng!' });
                }
            })
            .catch(error => { window.location.href = 'add_to_cart.php?id=' + productId; });
        }

        // --- SẢN PHẨM ĐÃ XEM ---
        const VIEWED_KEY = 'dienmay_viewed_products';

        function viewProduct(product) {
            let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
            viewed = viewed.filter(p => String(p.id) !== String(product.id));
            viewed.unshift(product);
            if (viewed.length > 10) viewed.pop();
            localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
            window.location.href = 'product_detail.php?id=' + product.id;
        }

        function renderViewedProducts() {
            let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
            const container = document.getElementById('viewed-products-container');
            const section = document.getElementById('recently-viewed-section');
            if (!section || !container) return;

            if (viewed.length === 0) { section.classList.add('hidden'); return; }
            section.classList.remove('hidden'); container.innerHTML = '';
            
            viewed.forEach(p => {
                let priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
                let oldPriceHTML = '';
                if(p.old_price && p.discount > 0) {
                    let oldPriceFmt = new Intl.NumberFormat('vi-VN').format(p.old_price) + 'đ';
                    oldPriceHTML = `<div class="flex items-center gap-1"><span class="text-gray-400 text-[10px] line-through">${oldPriceFmt}</span><span class="text-danger text-[10px] font-bold">-${p.discount}%</span></div>`;
                }
                const card = document.createElement('div');
                card.className = 'min-w-[150px] w-[150px] flex-shrink-0 bg-white border border-gray-200 hover:border-primary rounded-xl p-3 relative group transition cursor-pointer flex flex-col shadow-sm';
                card.setAttribute('onclick', `window.location.href='product_detail.php?id=${p.id}'`);
                card.innerHTML = `
                    <button onclick="event.stopPropagation(); removeViewedProduct('${p.id}')" class="absolute top-1 right-1 bg-gray-100 hover:bg-red-500 text-gray-500 hover:text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] transition z-20 shadow-sm">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <div class="h-24 mb-2 flex items-center justify-center overflow-hidden"><img src="${p.image}" class="max-w-full max-h-full object-contain group-hover:scale-105 transition"></div>
                    <span class="text-[10px] text-gray-500 mb-1 block uppercase">${p.brand_name || ''}</span>
                    <h4 class="text-[11px] text-gray-800 line-clamp-2 h-7 leading-snug font-medium group-hover:text-primary transition">${p.name}</h4>
                    <div class="mt-auto pt-2"><div class="text-danger font-bold text-xs">${priceFmt}</div>${oldPriceHTML}</div>
                `;
                container.appendChild(card);
            });
        }

        function removeViewedProduct(id) {
            let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
            viewed = viewed.filter(p => String(p.id) !== String(id));
            localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
            renderViewedProducts();
        }

        function clearAllViewed() {
            Swal.fire({
                title: 'Bạn có chắc chắn?',
                text: "Bạn muốn xóa toàn bộ lịch sử đã xem?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Đồng ý',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    localStorage.removeItem(VIEWED_KEY); 
                    renderViewedProducts();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', renderViewedProducts);

        // --- AI CHAT PRO ---
        const apiKey = "<?= $GEMINI_API_KEY ?>";
        const modelName = "gemini-3-flash-preview";
        
        const chatWindow = document.getElementById('ai-chat-window'), chatMessages = document.getElementById('chat-messages'), aiInput = document.getElementById('ai-input');
        
        let hasWelcomed = false;

        function toggleAIChat() { 
            chatWindow.classList.toggle('active'); 
            if (chatWindow.classList.contains('active')) {
                aiInput.focus(); 
                if(!hasWelcomed) {
                    hasWelcomed = true;
                    let greeting = "Xin chào! Tôi là Trợ lý AI của DIENMAYPRO. Tôi có thể giúp gì cho bạn hôm nay?";
                    if (typeof currentProductContext !== 'undefined') {
                        greeting = `Chào bạn! Bạn đang xem <b>${currentProductName}</b> phải không? Bạn cần tôi tư vấn về tính năng, trả góp hay khuyến mãi của sản phẩm này?`;
                    }
                    appendMessage(greeting, 'ai');
                }
            }
        }

        function appendMessage(text, role) { 
            const msgDiv = document.createElement('div'); 
            msgDiv.className = `message ${role}`; 
            msgDiv.innerHTML = text; 
            chatMessages.appendChild(msgDiv); 
            chatMessages.scrollTop = chatMessages.scrollHeight; 
        }

        function showLoading() { 
            const loader = document.createElement('div'); 
            loader.className = 'message ai loading-indicator'; 
            loader.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>'; 
            loader.id = 'ai-loading'; 
            chatMessages.appendChild(loader); 
            chatMessages.scrollTop = chatMessages.scrollHeight; 
        }

        function removeLoading() { 
            const loader = document.getElementById('ai-loading'); 
            if (loader) loader.remove(); 
        }
        
        function sendQuickMessage(text) {
            if (!chatWindow.classList.contains('active')) toggleAIChat();
            aiInput.value = text;
            sendMessage();
        }

        async function callGemini(prompt) {
            let contextInstruction = "Khách hàng đang ở Trang chủ hoặc xem danh mục chung. Hãy tư vấn tổng quan.";
            if (typeof currentProductContext !== 'undefined') {
                contextInstruction = `ĐẶC BIỆT LƯU Ý: Khách hàng ĐANG XEM SẢN PHẨM NÀY:\n${currentProductContext}\n-> Nếu khách hỏi các câu đại từ như "Cái này", "Sản phẩm này xài tốt không", "Giá bao nhiêu", hãy sử dụng thông tin sản phẩm trên để trả lời chính xác giá và khuyến mãi.`;
            }

            const fullPrompt = `BỐI CẢNH: Bạn là Trợ lý bán hàng của Điện Máy PRO. Hãy tư vấn thân thiện, ngắn gọn, chốt đơn. KHÔNG dùng dấu sao (**) để in đậm, chỉ dùng thẻ <b> và <br>.\n${contextInstruction}\n\nCÂU HỎI: ${prompt}`;
            const fallbackModels = ["gemini-3-flash-preview", "gemini-2.5-flash", "gemini-1.5-flash", "gemini-1.5-pro"];
            
            for (let model of fallbackModels) {
                try {
                    const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${apiKey.trim()}`, {
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ contents: [{ parts: [{ text: fullPrompt }] }] })
                    });
                    
                    const data = await response.json(); 

                    if (response.ok && data.candidates) {
                        return data.candidates[0].content.parts[0].text;
                    }
                    if (data.error && data.error.code === 404) { continue; }
                    throw new Error(data.error?.message || "Lỗi Google API");
                    
                } catch (e) { 
                    if (model === fallbackModels[fallbackModels.length - 1]) throw e; 
                }
            }
        }
        
        async function sendMessage() {
            const text = aiInput.value.trim(); if (!text) return;
            appendMessage(text, 'user'); 
            aiInput.value = ''; 
            showLoading();
            
            try { 
                const aiResponse = await callGemini(text); 
                removeLoading(); 
                const cleanResponse = (aiResponse || "Xin lỗi, tôi gặp chút trục trặc.").replace(/\n/g, '<br>');
                appendMessage(cleanResponse, 'ai'); 
            } catch (error) { 
                removeLoading(); 
                console.error("Lỗi:", error);
                appendMessage(`<b class="text-red-500">LỖI:</b> <br> <span class="text-xs text-gray-500">${error.message}</span>`, 'ai'); 
            }
        }

        function askAIAboutProduct(productName) { 
            if (!chatWindow.classList.contains('active')) toggleAIChat(); 
            aiInput.value = `Phân tích ưu nhược điểm của: ${productName}.`; 
            sendMessage(); 
        }

        function searchAssistant(inputId) { 
            const query = document.getElementById(inputId).value; 
            if (query) askAIAboutProduct(query); 
        }
        
        aiInput.addEventListener('keypress', (e) => { 
            if (e.key === 'Enter') sendMessage(); 
        });
    </script>
</body>
</html>