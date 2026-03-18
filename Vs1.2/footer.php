<!-- DANH SÁCH SẢN PHẨM ĐÃ XEM -->
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
        #ai-chat-window { display: none; position: fixed; bottom: 80px; right: 10px; width: calc(100% - 20px); max-width: 360px; height: 480px; max-height: 80vh; background: white; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); z-index: 1001; flex-direction: column; overflow: hidden; border: 1px solid #e5e7eb; }
        @media (min-width: 768px) { #ai-chat-window { bottom: 90px; right: 20px; height: 520px; } }
        #ai-chat-window.active { display: flex; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; background: #f9fafb; }
        .message { max-width: 85%; padding: 10px 14px; font-size: 13px; line-height: 1.5; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .message.user { align-self: flex-end; background: #0046ab; color: white; border-radius: 16px 16px 4px 16px; }
        .message.ai { align-self: flex-start; background: white; color: #1f2937; border-radius: 16px 16px 16px 4px; border: 1px solid #e5e7eb; }
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
            <div class="message ai">Xin chào! Tôi là AI của DIENMAYPRO. Tôi có thể giúp gì cho bạn hôm nay?</div>
        </div>
        <div class="p-3 bg-white border-t border-gray-100 flex gap-2 items-center">
            <input type="text" id="ai-input" placeholder="Nhập câu hỏi..." class="flex-1 text-sm bg-gray-100 border-transparent rounded-full px-4 py-2 focus:outline-none focus:ring-1 focus:ring-primary focus:bg-white transition">
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

    <!-- SCRIPTS -->
    <script>
        // SẢN PHẨM ĐÃ XEM
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
            if (viewed.length === 0) { section.classList.add('hidden'); return; }
            section.classList.remove('hidden'); container.innerHTML = '';
            
            viewed.forEach(p => {
                let priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
                let oldPriceHTML = '';
                if(p.old_price && p.discount > 0) {
                    let oldPriceFmt = new Intl.NumberFormat('vi-VN').format(p.old_price) + 'đ';
                    oldPriceHTML = `<div class="flex items-center gap-1"><span class="text-gray-400 text-[10px] line-through">${oldPriceFmt}</span><span class="text-danger text-[10px] font-bold">-${p.discount}%</span></div>`;
                }
                let pJson = JSON.stringify(p).replace(/"/g, '&quot;');
                const card = document.createElement('div');
                card.className = 'min-w-[150px] w-[150px] flex-shrink-0 bg-white border border-gray-200 hover:border-primary rounded-xl p-3 relative group transition cursor-pointer flex flex-col';
                card.setAttribute('onclick', `window.location.href='product_detail.php?id=${p.id}'`);
                card.innerHTML = `
                    <button onclick="event.stopPropagation(); removeViewedProduct('${p.id}')" class="absolute top-1 right-1 bg-gray-100 hover:bg-red-500 text-gray-500 hover:text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] transition z-20">
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
            if(confirm('Bạn có chắc muốn xóa lịch sử xem?')) { localStorage.removeItem(VIEWED_KEY); renderViewedProducts(); }
        }

        document.addEventListener('DOMContentLoaded', renderViewedProducts);

        // AI CHAT
        const apiKey = ""; const modelName = "gemini-2.5-flash-preview-09-2025";
        const chatWindow = document.getElementById('ai-chat-window'), chatMessages = document.getElementById('chat-messages'), aiInput = document.getElementById('ai-input');
        function toggleAIChat() { chatWindow.classList.toggle('active'); if (chatWindow.classList.contains('active')) aiInput.focus(); }
        function appendMessage(text, role) { const msgDiv = document.createElement('div'); msgDiv.className = `message ${role}`; msgDiv.innerText = text; chatMessages.appendChild(msgDiv); chatMessages.scrollTop = chatMessages.scrollHeight; }
        function showLoading() { const loader = document.createElement('div'); loader.className = 'message ai loading-indicator'; loader.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>'; loader.id = 'ai-loading'; chatMessages.appendChild(loader); chatMessages.scrollTop = chatMessages.scrollHeight; }
        function removeLoading() { const loader = document.getElementById('ai-loading'); if (loader) loader.remove(); }
        
        async function callGemini(prompt) {
            const systemPrompt = "Bạn là chuyên gia tư vấn điện máy của DienMayPRO. Trả lời ngắn gọn, chuyên nghiệp, đưa ra lời khuyên thực tế.";
            let retries = 0; const maxRetries = 3;
            while (retries < maxRetries) {
                try {
                    const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${modelName}:generateContent?key=${apiKey}`, {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }], systemInstruction: { parts: [{ text: systemPrompt }] } })
                    });
                    if (!response.ok) throw new Error('API failed'); const data = await response.json(); return data.candidates?.[0]?.content?.parts?.[0]?.text;
                } catch (e) { retries++; if (retries === maxRetries) throw e; await new Promise(r => setTimeout(r, Math.pow(2, retries) * 500)); }
            }
        }
        
        async function sendMessage() {
            const text = aiInput.value.trim(); if (!text) return;
            appendMessage(text, 'user'); aiInput.value = ''; showLoading();
            try { const aiResponse = await callGemini(text); removeLoading(); appendMessage(aiResponse || "Xin lỗi, tôi gặp chút trục trặc.", 'ai'); } 
            catch (error) { removeLoading(); appendMessage("Hệ thống đang bảo trì. Vui lòng thử lại sau.", 'ai'); }
        }
        function askAIAboutProduct(productName) { if (!chatWindow.classList.contains('active')) toggleAIChat(); aiInput.value = `Tư vấn về sản phẩm: ${productName}.`; sendMessage(); }
        function searchAssistant(inputId) { const query = document.getElementById(inputId).value; if (query) askAIAboutProduct(query); }
        aiInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
    </script>
</body>
</html>