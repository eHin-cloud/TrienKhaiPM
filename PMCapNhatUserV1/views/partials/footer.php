<!-- ============================================================
     FOOTER.PHP - FOOTER CHUNG + AI CHAT + SẢN PHẨM ĐÃ XEM
     ============================================================
     
     File được require_once bởi tất cả các trang public.
     
     CHỨC NĂNG:
     1. SẢN PHẨM ĐÃ XEM (localStorage):
        - Lưu lịch sử SP đã xem vào localStorage (tối đa 10 SP)
        - Hiển thị dạng carousel scroll ngang
        - Nút xóa từng SP hoặc xóa tất cả
        - Không hiện trên trang product_detail (tránh trùng)
     
     2. AI CHAT PRO (Google Gemini API):
        - Cửa sổ chat floating ở góc dưới phải
        - Nút FAB (Floating Action Button) với animation ping
        - Gợi ý nhanh (Quick messages)
        - Tự nhận diện context sản phẩm đang xem
        - Fallback qua nhiều model Gemini nếu model chính lỗi
     
     3. FOOTER WEBSITE:
        - Logo + Copyright
     
     4. HÀM JS GLOBAL:
        - addToCartAjax()      : Thêm SP vào giỏ bằng AJAX
        - buyNowAjax()         : Thêm SP + redirect sang cart
        - submitInstallment()  : Gửi đăng ký trả góp AJAX
        - showSuccessModal()   : Hiện thông báo thành công (SweetAlert2)
        - viewProduct()        : Lưu SP vào lịch sử đã xem + redirect
     
     @requires config_api.php - API Key cho Google Gemini
     ============================================================ -->

<!-- ==========================================
     SECTION: SẢN PHẨM ĐÃ XEM GẦN ĐÂY
     Dữ liệu từ localStorage, render bằng JS
     Chỉ hiện ở các trang KHÔNG phải product_detail
     ========================================== -->
<?php require_once __DIR__ . '/../../core/config_api.php'; ?>
<?php if (basename($_SERVER['PHP_SELF']) !== 'product_detail.php'): ?>
    <section id="recently-viewed-section" class="container mx-auto px-4 mt-10 mb-10 hidden">
        <div class="bg-white p-4 md:p-5 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i> Sản phẩm bạn vừa xem
                </h2>
                <!-- Nút xóa toàn bộ lịch sử đã xem -->
                <button onclick="clearAllViewed()"
                    class="text-xs text-red-500 hover:text-red-700 font-medium bg-red-50 px-3 py-1.5 rounded-lg transition">Xóa
                    tất cả</button>
            </div>
            <!-- Container sẽ được JS render các thẻ sản phẩm vào đây -->
            <div id="viewed-products-container" class="flex gap-4 overflow-x-auto pb-2 hide-scrollbar"></div>
        </div>
    </section>
<?php endif; ?>

<!-- ==========================================
         AI CHAT WINDOW - Cửa sổ chat AI
         ========================================== -->

<!-- CSS cho AI Chat -->
<style>
    /* Cửa sổ chat - fixed ở góc dưới phải */
    #ai-chat-window {
        display: none;
        position: fixed;
        bottom: 80px;
        right: 10px;
        width: calc(100% - 20px);
        max-width: 360px;
        height: 500px;
        max-height: 80vh;
        background: white;
        border-radius: 12px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        z-index: 1001;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }

    /* Responsive: tăng kích thước trên desktop */
    @media (min-width: 768px) {
        #ai-chat-window {
            bottom: 90px;
            right: 20px;
            height: 550px;
        }
    }

    /* Animation slide up khi mở chat */
    #ai-chat-window.active {
        display: flex;
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Khu vực tin nhắn */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f9fafb;
        scroll-behavior: smooth;
    }

    /* Bong bóng tin nhắn chung */
    .message {
        max-width: 85%;
        padding: 10px 14px;
        font-size: 13.5px;
        line-height: 1.5;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        overflow-wrap: break-word;
    }

    /* Tin nhắn của user (bên phải, nền xanh) */
    .message.user {
        align-self: flex-end;
        background: #0046ab;
        color: white;
        border-radius: 16px 16px 4px 16px;
    }

    /* Tin nhắn của AI (bên trái, nền trắng) */
    .message.ai {
        align-self: flex-start;
        background: white;
        color: #1f2937;
        border-radius: 16px 16px 16px 4px;
        border: 1px solid #e5e7eb;
    }

    .message.ai b {
        color: #0046ab;
    }

    /* Animation loading dots (3 chấm nhảy) */
    .loading-dots span {
        display: inline-block;
        width: 6px;
        height: 6px;
        background: #999;
        border-radius: 50%;
        margin: 0 2px;
        animation: bounce 1.4s infinite ease-in-out both;
    }

    .loading-dots span:nth-child(1) {
        animation-delay: -0.32s;
    }

    .loading-dots span:nth-child(2) {
        animation-delay: -0.16s;
    }

    @keyframes bounce {

        0%,
        80%,
        100% {
            transform: scale(0);
        }

        40% {
            transform: scale(1.0);
        }
    }
</style>

<!-- Cửa sổ Chat AI -->
<div id="ai-chat-window">
    <!-- Header chat: Avatar AI + Trạng thái online + Nút đóng -->
    <div class="bg-primary text-white p-3 flex justify-between items-center shadow-sm z-10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-primary text-lg"><i
                    class="fa-solid fa-robot"></i></div>
            <div>
                <div class="font-bold text-sm">Trợ lý AI PRO</div>
                <div class="text-[10px] text-blue-200 flex items-center gap-1"><span
                        class="w-1.5 h-1.5 bg-green-400 rounded-full inline-block"></span> Đang trực tuyến</div>
            </div>
        </div>
        <button onclick="toggleAIChat()" class="hover:bg-white/20 w-8 h-8 rounded-full transition"><i
                class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- Khu vực hiển thị tin nhắn (JS render vào đây) -->
    <div class="chat-messages" id="chat-messages">
    </div>

    <!-- Gợi ý nhanh (Quick messages) - scroll ngang -->
    <div
        class="px-3 pb-2 pt-2 bg-white overflow-x-auto hide-scrollbar whitespace-nowrap border-t border-gray-100 shadow-[0_-5px_10px_rgba(0,0,0,0.02)]">
        <button onclick="sendQuickMessage('Tư vấn giúp tôi Tivi dưới 15 triệu')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1">📺
            Tivi dưới 15tr</button>
        <button onclick="sendQuickMessage('Tìm máy lạnh tiết kiệm điện Inverter')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1">❄️
            Máy lạnh Inverter</button>
        <button onclick="sendQuickMessage('Chính sách trả góp và bảo hành thế nào?')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition">💳
            Trả góp & Bảo hành</button>
    </div>

    <!-- Input gửi tin nhắn -->
    <div class="p-3 bg-white border-t border-gray-100 flex gap-2 items-center">
        <input type="text" id="ai-input" placeholder="Nhập câu hỏi..."
            class="flex-1 text-[13px] bg-gray-100 border-transparent rounded-full px-4 py-2.5 focus:outline-none focus:ring-1 focus:ring-primary focus:bg-white transition">
        <button onclick="sendMessage()"
            class="bg-primary text-white w-9 h-9 rounded-full flex items-center justify-center hover:bg-blue-800 transition shadow-sm"><i
                class="fa-solid fa-paper-plane text-[13px]"></i></button>
    </div>
</div>

<!-- NÚT FAB MỞ AI CHAT (Floating Action Button - góc dưới phải) -->
<div onclick="toggleAIChat()" class="fixed bottom-4 right-4 md:bottom-6 md:right-6 z-50 group">
    <div
        class="bg-secondary text-primary p-3 rounded-full shadow-xl flex items-center justify-center cursor-pointer hover:scale-110 transition duration-300 w-12 h-12 md:w-14 md:h-14">
        <i class="fa-solid fa-robot text-xl md:text-2xl"></i>
        <!-- Hiệu ứng ping đỏ (thu hút chú ý) -->
        <span class="absolute 0 top-0 right-0 flex h-3 w-3"><span
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span
                class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white"></span></span>
    </div>
</div>

<!-- ==========================================
         FOOTER WEBSITE
         ========================================== -->
<footer class="bg-primary text-gray-200 pt-16 pb-8 border-t-4 border-secondary mt-auto relative overflow-hidden">
    <!-- Decorative Element -->
    <div class="absolute top-0 right-0 w-64 h-64 bg-primary rounded-full mix-blend-multiply filter blur-3xl opacity-20 transform translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 left-0 w-80 h-80 bg-secondary rounded-full mix-blend-multiply filter blur-3xl opacity-10 transform -translate-x-1/2 translate-y-1/2"></div>

    <div class="container mx-auto px-4 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
            
            <!-- Cột 1: Thông tin chung -->
            <div>
                <div class="font-black text-3xl text-white mb-6 tracking-tight">
                    DIENMAY<span class="text-secondary">PRO</span>
                </div>
                <p class="text-sm text-gray-400 mb-6 leading-relaxed">
                    Hệ thống siêu thị điện máy hàng đầu Việt Nam. Chúng tôi cam kết mang đến cho khách hàng những sản phẩm chính hãng, chất lượng với dịch vụ hậu mãi vượt trội.
                </p>
                <!-- Social Links -->
                <div class="flex gap-3">
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-secondary hover:text-primary transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-red-500 hover:text-white transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-pink-500 hover:text-white transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-gray-300 hover:bg-blue-400 hover:text-white transition-all duration-300 shadow-lg transform hover:-translate-y-1"><i class="fa-brands fa-twitter"></i></a>
                </div>
            </div>

            <!-- Cột 2: Thông tin liên hệ -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    Liên Hệ
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3 group">
                        <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-secondary group-hover:text-primary transition-colors text-secondary">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <span class="text-sm text-gray-300 leading-snug">123 Đường Điện Máy, Phường Đổi Mới, Quận Công Nghệ, TP. Hồ Chí Minh</span>
                    </li>
                    <li class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-secondary group-hover:text-primary transition-colors text-secondary">
                            <i class="fa-solid fa-phone-volume"></i>
                        </div>
                        <div>
                            <span class="text-xs text-blue-200 block">Hotline mua hàng (24/7)</span>
                            <a href="tel:19009999" class="text-base text-white font-bold hover:text-secondary transition-colors">1900 9999</a>
                        </div>
                    </li>
                    <li class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-secondary group-hover:text-primary transition-colors text-secondary">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <span class="text-xs text-blue-200 block">Email hỗ trợ</span>
                            <a href="mailto:support@dienmaypro.vn" class="text-sm hover:text-secondary transition-colors">support@dienmaypro.vn</a>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Cột 3: Hỗ trợ khách hàng -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    Hỗ Trợ Khách Hàng
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <ul class="space-y-3">
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> Chính sách bảo hành</a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> Chính sách đổi trả 1-1</a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> Giao hàng & Lắp đặt</a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> Hướng dẫn mua trả góp</a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> Câu hỏi thường gặp (FAQ)</a></li>
                </ul>
            </div>

            <!-- Cột 4: Đăng ký & Thanh toán -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    Nhận Ưu Đãi
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <p class="text-sm text-gray-300 mb-4">Đăng ký email để nhận mã giảm giá và thông tin khuyến mãi mới nhất.</p>
                <form class="relative mb-8" onsubmit="event.preventDefault(); if(typeof showSuccessModal === 'function') showSuccessModal('Cảm ơn bạn đã đăng ký nhận tin!');">
                    <input type="email" placeholder="Nhập email của bạn..." required class="w-full bg-white/10 border border-white/10 text-sm text-white px-4 py-3 rounded-lg focus:outline-none focus:border-secondary focus:ring-1 focus:ring-secondary transition-all">
                    <button type="submit" class="absolute right-1 top-1 bottom-1 bg-secondary hover:bg-yellow-400 text-primary px-4 rounded-md font-medium transition-colors">
                        Gửi
                    </button>
                </form>
                
                <h4 class="text-sm font-bold text-white mb-3">Phương thức thanh toán</h4>
                <div class="flex gap-2 flex-wrap">
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-brands fa-cc-visa text-blue-800 text-xl"></i></div>
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-brands fa-cc-mastercard text-red-600 text-xl"></i></div>
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-brands fa-cc-paypal text-blue-500 text-xl"></i></div>
                    <div class="w-10 h-7 bg-white rounded flex items-center justify-center p-1 shadow"><i class="fa-solid fa-money-bill-wave text-green-600 text-base"></i></div>
                </div>
            </div>
        </div>

        <!-- Dòng bản quyền -->
        <div class="pt-6 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-xs text-blue-100">© 2026 DIENMAY<span class="text-white font-bold">PRO</span>. Tất cả các quyền được bảo lưu.</p>
            <div class="flex gap-4 text-xs text-blue-100">
                <a href="#" class="hover:text-white transition">Điều khoản sử dụng</a>
                <span class="text-white/20">|</span>
                <a href="#" class="hover:text-white transition">Bảo mật thông tin</a>
            </div>
        </div>
    </div>
</footer>

<!-- Thư viện SweetAlert2 - Hiệu ứng thông báo đẹp mắt -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ==========================================
         JAVASCRIPT: TẤT CẢ HÀM GLOBAL
         ========================================== -->
<script>
    // ==========================================
    // 1. HÀM HIỂN THỊ THÔNG BÁO SWEETALERT2
    // ==========================================

    /**
     * Hiện modal thông báo thành công (tự đóng sau 2 giây)
     * @param {string} message - Nội dung thông báo
     */
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

    // ==========================================
    // 2. XỬ LÝ ĐĂNG KÝ TRẢ GÓP (AJAX)
    // ==========================================

    /**
     * Gửi form đăng ký trả góp qua AJAX đến save_installment.php
     * Được gọi từ form #installmentForm trên product_detail.php
     * @param {Event} e - Sự kiện submit form
     */
    function submitInstallment(e) {
        e.preventDefault(); // Ngăn form submit mặc định
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

    // ==========================================
    // 3. AJAX THÊM VÀO GIỎ HÀNG
    // ==========================================

    /**
     * Thêm sản phẩm vào giỏ hàng bằng AJAX (không reload trang)
     * Cập nhật badge số lượng giỏ hàng trên header
     * Nếu chưa đăng nhập -> mở modal đăng nhập
     * @param {number} productId - ID sản phẩm cần thêm
     */
    function addToCartAjax(productId) {
        fetch('add_to_cart.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax=1&id=' + productId
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Cập nhật badge số lượng giỏ hàng trên header
                    let badge = document.getElementById('cart-count-badge');
                    if (badge) {
                        badge.innerText = data.cart_count;
                        badge.classList.remove('hidden');
                        badge.classList.add('animate-bounce'); // Hiệu ứng bounce
                        setTimeout(() => badge.classList.remove('animate-bounce'), 1000);
                    }
                    showSuccessModal('Đã thêm sản phẩm vào giỏ hàng!');
                } else if (data.message === 'not_logged_in') {
                    // Chưa đăng nhập -> mở modal đăng nhập
                    let loginModal = document.getElementById('loginModal');
                    if (loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: 'Thông báo', text: 'Bạn cần đăng nhập để thêm vào giỏ hàng!' });
                }
            })
            .catch(error => {
                Swal.fire({ icon: 'error', title: 'Lỗi!', text: 'Lỗi kết nối hoặc phiên đăng nhập!' });
            });
    }

    /**
     * Thêm SP vào giỏ rồi redirect sang trang giỏ hàng (Mua ngay)
     * @param {number} productId - ID sản phẩm
     */
    function buyNowAjax(productId) {
        fetch('add_to_cart.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax=1&id=' + productId
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) { window.location.href = 'cart.php'; } // Redirect sang giỏ hàng
                else if (data.message === 'not_logged_in') {
                    let loginModal = document.getElementById('loginModal');
                    if (loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: 'Thông báo', text: 'Bạn cần đăng nhập để mua hàng!' });
                }
            })
            .catch(error => { window.location.href = 'add_to_cart.php?id=' + productId; }); // Fallback: dùng GET
    }

    // ==========================================
    // 4. QUẢN LÝ SẢN PHẨM ĐÃ XEM (localStorage)
    // ==========================================

    const VIEWED_KEY = 'dienmay_viewed_products'; // Key lưu trong localStorage

    /**
     * Lưu sản phẩm vào lịch sử đã xem và redirect sang trang chi tiết
     * - Xóa SP trùng (nếu đã xem trước đó) -> đưa lên đầu
     * - Giới hạn tối đa 10 SP
     * @param {Object} product - Object chứa {id, name, price, old_price, discount, image, brand_name}
     */
    function viewProduct(product) {
        let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
        // Xóa SP trùng ID (nếu đã xem trước đó)
        viewed = viewed.filter(p => String(p.id) !== String(product.id));
        // Thêm vào đầu mảng (mới nhất ở trước)
        viewed.unshift(product);
        // Giới hạn tối đa 10 SP
        if (viewed.length > 10) viewed.pop();
        localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
        // Redirect sang trang chi tiết sản phẩm
        window.location.href = 'product_detail.php?id=' + product.id;
    }

    /**
     * Render danh sách sản phẩm đã xem từ localStorage
     * Tạo card SP nhỏ dạng scroll ngang
     */
    function renderViewedProducts() {
        let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
        const container = document.getElementById('viewed-products-container');
        const section = document.getElementById('recently-viewed-section');
        if (!section || !container) return;

        // Ẩn section nếu không có SP nào
        if (viewed.length === 0) { section.classList.add('hidden'); return; }
        section.classList.remove('hidden'); container.innerHTML = '';

        // Render từng thẻ SP
        viewed.forEach(p => {
            let priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
            let oldPriceHTML = '';
            if (p.old_price && p.discount > 0) {
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

    /**
     * Xóa 1 sản phẩm khỏi lịch sử đã xem
     * @param {string} id - ID sản phẩm cần xóa
     */
    function removeViewedProduct(id) {
        let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
        viewed = viewed.filter(p => String(p.id) !== String(id));
        localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
        renderViewedProducts(); // Re-render lại
    }

    /**
     * Xóa toàn bộ lịch sử SP đã xem (có hộp thoại xác nhận)
     */
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

    // Render SP đã xem ngay khi trang load xong
    document.addEventListener('DOMContentLoaded', renderViewedProducts);

    // ==========================================
    // 5. AI CHAT PRO - Tích hợp Google Gemini API
    // ==========================================
    <?php
    // 1. LẤY KHO HÀNG (Dùng query trực tiếp, giới hạn 50 SP để tránh quá tải AI)
    $productKnowledge = "";
    try {
        // Sử dụng biến $db đã được khởi tạo từ public/index.php
        $stmtProduct = $db->query("SELECT id, name, price FROM products ORDER BY id DESC LIMIT 50");
        $allProducts = $stmtProduct->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($allProducts)) {
            foreach ($allProducts as $p) {
                $pPrice = number_format($p['price'], 0, ',', '.');
                $productKnowledge .= "- " . htmlspecialchars($p['name']) . ": Giá {$pPrice}đ (Link: product_detail.php?id={$p['id']})\n";
            }
        }
    } catch (Exception $e) {
        $productKnowledge = "Hệ thống đang cập nhật kho hàng...";
    }

    // 2. LẤY DANH MỤC SẢN PHẨM
    $categoryString = "thiết bị điện máy, điện tử, gia dụng";
    try {
        $stmtCat = $db->query("SELECT name FROM categories");
        $cats = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($cats)) {
            $categoryNames = array_column($cats, 'name');
            $categoryString = implode(', ', $categoryNames);
        }
    } catch (Exception $e) {
        // Dùng giá trị mặc định nếu lỗi
    }
    ?>

    // API Key và model name từ config_api.php
    const apiKey = "<?= $GEMINI_API_KEY ?? '' ?>";
    const modelName = "gemini-3-flash-preview";

    // Lấy các DOM element cần thiết
    const chatWindow = document.getElementById('ai-chat-window'), chatMessages = document.getElementById('chat-messages'), aiInput = document.getElementById('ai-input');

    let hasWelcomed = false; // Cờ đánh dấu đã hiện lời chào chưa

    /**
     * Mở/đóng cửa sổ AI Chat
     * Lần đầu mở sẽ hiện lời chào (tuỳ context trang hiện tại)
     */
    function toggleAIChat() {
        chatWindow.classList.toggle('active');
        if (chatWindow.classList.contains('active')) {
            aiInput.focus();
            if (!hasWelcomed) {
                hasWelcomed = true;
                // Lời chào khác nhau nếu đang xem SP cụ thể hay ở trang chủ
                let greeting = "Xin chào! Tôi là Trợ lý AI của DIENMAYPRO. Tôi có thể giúp gì cho bạn hôm nay?";
                if (typeof currentProductContext !== 'undefined') {
                    greeting = `Chào bạn! Bạn đang xem <b>${currentProductName}</b> phải không? Bạn cần tôi tư vấn về tính năng, trả góp hay khuyến mãi của sản phẩm này?`;
                }
                appendMessage(greeting, 'ai');
            }
        }
    }

    /**
     * Thêm 1 tin nhắn vào khu vực chat
     * @param {string} text - Nội dung HTML tin nhắn
     * @param {string} role - 'user' hoặc 'ai'
     */
    function appendMessage(text, role) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `message ${role}`;
        msgDiv.innerHTML = text;
        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight; // Auto scroll xuống cuối
    }

    /**
     * Hiện animation loading (3 chấm nhảy) khi đang chờ AI trả lời
     */
    function showLoading() {
        const loader = document.createElement('div');
        loader.className = 'message ai loading-indicator';
        loader.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>';
        loader.id = 'ai-loading';
        chatMessages.appendChild(loader);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    /**
     * Xóa animation loading sau khi AI trả lời xong
     */
    function removeLoading() {
        const loader = document.getElementById('ai-loading');
        if (loader) loader.remove();
    }

    /**
     * Gửi tin nhắn nhanh (từ nút gợi ý)
     * Tự mở chat window nếu chưa mở
     * @param {string} text - Nội dung tin nhắn
     */
    function sendQuickMessage(text) {
        if (!chatWindow.classList.contains('active')) toggleAIChat();
        aiInput.value = text;
        sendMessage();
    }

    /**
     * Gọi Google Gemini API với cơ chế fallback qua nhiều model
     * Nếu model chính (gemini-3-flash-preview) lỗi 404 -> thử model tiếp theo
     * 
     * @param {string} prompt - Câu hỏi/yêu cầu của người dùng
     * @returns {string} - Câu trả lời từ AI
     */
    async function callGemini(prompt) {
        // Xây dựng context tuỳ theo trang hiện tại
        let contextInstruction = "Khách hàng đang ở Trang chủ hoặc xem danh mục chung. Hãy tư vấn tổng quan.";
        if (typeof currentProductContext !== 'undefined') {
            // Nếu đang ở trang chi tiết SP -> cung cấp thông tin SP cho AI
            contextInstruction = `ĐẶC BIỆT LƯU Ý: Khách hàng ĐANG XEM SẢN PHẨM NÀY:\n${currentProductContext}\n-> Nếu khách hỏi các câu đại từ như "Cái này", "Sản phẩm này xài tốt không", "Giá bao nhiêu", hãy sử dụng thông tin sản phẩm trên để trả lời chính xác giá và khuyến mãi.`;
        }

        // Nhận chuỗi danh mục động từ PHP
        const dynamicCategories = "<?= addslashes($categoryString) ?>";

        // System prompt đầy đủ cho AI (Đã cập nhật danh mục động)
        /// Đưa danh sách sản phẩm từ PHP vào JS
        const realProductData = `<?= addslashes($productKnowledge) ?>`;

        // System prompt mới: Yêu cầu AI tạo link clickable
        const fullPrompt = `BỐI CẢNH: Bạn là Trợ lý bán hàng thông minh của Điện Máy PRO.
KHO HÀNG THỰC TẾ CỦA CỬA HÀNG:
${realProductData}

YÊU CẦU TƯ VẤN:
1. Khi khách hỏi về một loại sản phẩm, hãy tra cứu trong "KHO HÀNG THỰC TẾ" ở trên.
2. NẾU CÓ SẢN PHẨM: Hãy liệt kê tên và giá.
3. QUY TẮC CHÈN LINK: Thay vì viết văn bản thuần, bạn PHẢI chèn link sản phẩm bằng thẻ HTML <a>. 
   Định dạng: <a href="product_detail.php?id=ID_SAN_PHAM" class="text-blue-600 font-bold hover:underline">Xem chi tiết tại đây</a>
4. Nếu không có mẫu chính xác, hãy báo "Hiện mẫu này bên em đang hết hàng" và gợi ý sản phẩm tương tự có trong kho.

QUY TẮC FORMAT: Trả lời thân thiện, GỌN GÀNG, trình bày các ý liền mạch. TUYỆT ĐỐI KHÔNG cách dòng thừa thãi giữa tên sản phẩm, giá và link. Dùng <b> để in đậm tên sản phẩm/giá, và <a> để tạo link clickable. KHÔNG dùng dấu sao (**).\n${contextInstruction}\n\nCÂU HỎI KHÁCH: ${prompt}`;

        // Danh sách model fallback: thử lần lượt cho đến khi thành công
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
                    return data.candidates[0].content.parts[0].text; // Trả về text từ AI
                }
                if (data.error && data.error.code === 404) { continue; } // Model không tồn tại -> thử model tiếp
                throw new Error(data.error?.message || "Lỗi Google API");

            } catch (e) {
                // Nếu là model cuối cùng mà vẫn lỗi -> throw ra ngoài
                if (model === fallbackModels[fallbackModels.length - 1]) throw e;
            }
        }
    }

    /**
     * Xử lý gửi tin nhắn: hiện tin user, gọi API, hiện tin AI
     */
    async function sendMessage() {
        const text = aiInput.value.trim(); if (!text) return;
        appendMessage(text, 'user');  // Hiện tin nhắn user
        aiInput.value = '';           // Xoá input
        showLoading();                // Hiện loading

        try {
            const aiResponse = await callGemini(text);  // Gọi Gemini API
            removeLoading();
            // Format response: thay \n thành <br>
            // Format response: Gộp các dấu xuống dòng liên tiếp để giao diện gọn gàng hơn
            const cleanResponse = (aiResponse || "Xin lỗi, tôi gặp chút trục trặc.")
                .trim()                     // Xóa khoảng trắng ở đầu/cuối
                .replace(/\n{3,}/g, '\n\n') // Nếu có 3+ dấu xuống dòng thì gộp thành 2
                .replace(/\n/g, '<br>');    // Chuyển đổi thành thẻ <br>
            appendMessage(cleanResponse, 'ai');
        } catch (error) {
            removeLoading();
            console.error("Lỗi:", error);
            appendMessage(`<b class="text-red-500">LỖI:</b> <br> <span class="text-xs text-gray-500">${error.message}</span>`, 'ai');
        }
    }

    /**
     * Hỏi AI về 1 sản phẩm cụ thể (từ nút "Hỏi AI" trên thẻ SP)
     * @param {string} productName - Tên sản phẩm
     */
    function askAIAboutProduct(productName) {
        if (!chatWindow.classList.contains('active')) toggleAIChat();
        aiInput.value = `Phân tích ưu nhược điểm của: ${productName}.`;
        sendMessage();
    }

    /**
     * Tìm kiếm bằng AI (được gọi từ 1 input tìm kiếm nào đó)
     * @param {string} inputId - ID của input element
     */
    function searchAssistant(inputId) {
        const query = document.getElementById(inputId).value;
        if (query) askAIAboutProduct(query);
    }

    // Gửi tin nhắn khi bấm phím Enter
    aiInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
</script>
</body>

</html>