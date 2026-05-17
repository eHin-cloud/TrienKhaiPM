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
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i> <?= __('recently_viewed') ?>
                </h2>
                <!-- Nút xóa toàn bộ lịch sử đã xem -->
                <button onclick="clearAllViewed()"
                    class="text-xs text-red-500 hover:text-red-700 font-medium bg-red-50 px-3 py-1.5 rounded-lg transition"><?= __('clear_all') ?></button>
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
        max-width: 420px;
        height: 600px;
        max-height: 85vh;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
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
            height: 700px;
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
                <div class="font-bold text-sm"><?= __("ai_assistant_title") ?></div>
                <div class="text-[10px] text-blue-200 flex items-center gap-1"><span
                        class="w-1.5 h-1.5 bg-green-400 rounded-full inline-block"></span> <?= __("online_status") ?></div>
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
        <button onclick="sendQuickMessage('<?= addslashes(__("ai_quick_msg_1")) ?>')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1"><?= __("ai_quick_btn_1") ?></button>
        <button onclick="sendQuickMessage('<?= addslashes(__("ai_quick_msg_2")) ?>')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition mr-1"><?= __("ai_quick_btn_2") ?></button>
        <button onclick="sendQuickMessage('<?= addslashes(__("ai_quick_msg_3")) ?>')"
            class="inline-block px-3 py-1.5 bg-blue-50 text-primary text-[11px] font-medium rounded-full border border-blue-100 hover:bg-blue-100 transition"><?= __("ai_quick_btn_3") ?></button>
    </div>

    <!-- Input gửi tin nhắn -->
    <div class="p-3 bg-white border-t border-gray-100 flex gap-2 items-center">
        <input type="text" id="ai-input" placeholder="<?= __("ai_input_placeholder") ?>"
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
                    <?= __("footer_about_desc") ?>
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
                    <?= __('contact') ?>
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
                            <span class="text-xs text-blue-200 block"><?= __("hotline_label") ?></span>
                            <a href="tel:19009999" class="text-base text-white font-bold hover:text-secondary transition-colors">1900 9999</a>
                        </div>
                    </li>
                    <li class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded bg-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-secondary group-hover:text-primary transition-colors text-secondary">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <span class="text-xs text-blue-200 block"><?= __("email_support_label") ?></span>
                            <a href="mailto:support@dienmaypro.vn" class="text-sm hover:text-secondary transition-colors">support@dienmaypro.vn</a>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Cột 3: Hỗ trợ khách hàng -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    <?= __('customer_support') ?>
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <ul class="space-y-3">
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("warranty_policy") ?></a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("return_policy_1_1") ?></a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("delivery_install") ?></a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("installment_guide") ?></a></li>
                    <li><a href="#" class="text-sm text-gray-300 hover:text-secondary transition-colors flex items-center gap-2 group"><i class="fa-solid fa-chevron-right text-[10px] text-secondary group-hover:translate-x-1 transition-transform"></i> <?= __("faq") ?></a></li>
                </ul>
            </div>

            <!-- Cột 4: Đăng ký & Thanh toán -->
            <div>
                <h3 class="text-white font-bold text-lg mb-6 uppercase tracking-wider relative inline-block">
                    <?= __('get_deals') ?>
                    <span class="absolute -bottom-2 left-0 w-12 h-1 bg-secondary rounded"></span>
                </h3>
                <p class="text-sm text-gray-300 mb-4"><?= __("newsletter_desc") ?></p>
                <form class="relative mb-8" onsubmit="event.preventDefault(); if(typeof showSuccessModal === 'function') showSuccessModal('<?= __("newsletter_success_swal") ?>');">
                    <input type="email" placeholder="<?= __("newsletter_placeholder") ?>" required class="w-full bg-white/10 border border-white/10 text-sm text-white px-4 py-3 rounded-lg focus:outline-none focus:border-secondary focus:ring-1 focus:ring-secondary transition-all">
                    <button type="submit" class="absolute right-1 top-1 bottom-1 bg-secondary hover:bg-yellow-400 text-primary px-4 rounded-md font-medium transition-colors">
                        <?= __("send") ?>
                    </button>
                </form>
                
                <h4 class="text-sm font-bold text-white mb-3"><?= __("payment_methods") ?></h4>
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
            <p class="text-xs text-blue-100">© 2026 DIENMAY<span class="text-white font-bold">PRO</span>. <?= __("all_rights_reserved") ?></p>
            <div class="flex gap-4 text-xs text-blue-100">
                <a href="#" class="hover:text-white transition"><?= __("terms_of_use") ?></a>
                <span class="text-white/20">|</span>
                <a href="#" class="hover:text-white transition"><?= __("privacy_policy") ?></a>
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
    // BIẾN TOÀN CỤC: CSRF TOKEN CHO AJAX
    // ==========================================
    const csrfToken = '<?= generate_csrf_token() ?>';

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
            title: '<?= __("success") ?>!',
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
        formData.append('csrf_token', csrfToken);
        fetch('save_installment.php', { method: 'POST', body: new URLSearchParams(formData) })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal('<?= __("installment_success_swal") ?>');
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
            body: 'ajax=1&id=' + productId + '&csrf_token=' + encodeURIComponent(csrfToken)
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
                    showSuccessModal('<?= __("add_to_cart_success_swal") ?>');
                } else if (data.message === 'not_logged_in') {
                    // Chưa đăng nhập -> mở modal đăng nhập
                    let loginModal = document.getElementById('loginModal');
                    if (loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: '<?= __("notice") ?>', text: '<?= __("login_required_cart_swal") ?>' });
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
            body: 'ajax=1&id=' + productId + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) { window.location.href = 'cart.php'; } // Redirect sang giỏ hàng
                else if (data.message === 'not_logged_in') {
                    let loginModal = document.getElementById('loginModal');
                    if (loginModal) loginModal.classList.remove('hidden');
                    else Swal.fire({ icon: 'warning', title: '<?= __("notice") ?>', text: '<?= __("login_required_buy_swal") ?>' });
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
            title: '<?= __("clear_history_confirm_title") ?>',
            text: '<?= __("clear_history_confirm_text") ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<?= __("agree") ?>',
            cancelButtonText: '<?= __("cancel") ?>'
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
    // ==========================================
    // 5. AI CHAT PRO - Tích hợp RAG Backend
    // ==========================================
    // KHÔNG gán cứng API Key ở giao diện nữa (đã chuyển xuống Backend)
    // KHÔNG tải trước kho hàng vào prompt nữa (Backend RAG sẽ tự tìm theo câu hỏi)
    
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
                let greeting = '<?= __("ai_greeting_default") ?>';
                if (typeof currentProductContext !== 'undefined') {
                    greeting = '<?= sprintf(__("ai_greeting_product"), "' + currentProductName + '") ?>';
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
        
        const now = new Date();
        const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
        const senderName = role === 'user' ? 'Bạn' : 'AI Assistant';

        msgDiv.innerHTML = `
            <div class="flex flex-col">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] font-bold ${role === 'user' ? 'text-blue-100' : 'text-primary'}">${senderName}</span>
                    <span class="text-[9px] ${role === 'user' ? 'text-blue-200' : 'text-gray-400'} font-normal">${timeStr}</span>
                </div>
                <div>${text}</div>
            </div>
        `;
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
     * Gọi Backend RAG Chatbot
     * Thay vì gọi trực tiếp Google API (lộ key, tốn token), ta gọi server của mình.
     * 
     * @param {string} prompt - Câu hỏi/yêu cầu của người dùng
     * @returns {string} - Câu trả lời từ AI
     */
    async function callGemini(prompt) {
        // Ngữ cảnh hiện tại (nếu đang ở chi tiết SP)
        let currentContext = typeof currentProductContext !== 'undefined' ? currentProductContext : '';

        try {
            const response = await fetch('ai_assist.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken // Token bảo vệ
                },
                body: JSON.stringify({ 
                    prompt: prompt,
                    context: currentContext,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (data.success) {
                return data.response;
            } else {
                throw new Error(data.message || "Lỗi phản hồi từ máy chủ");
            }

        } catch (e) {
            console.error("Chatbot Backend Error:", e);
            throw e;
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
    <!-- THANH SO SÁNH STICKY BOTTOM (Thiết kế linh hoạt: Bar hoặc Ô vuông) -->
    <div id="compare-sticky-bar" class="fixed bottom-0 left-0 right-0 z-[1000] bg-white border-t border-gray-200 shadow-[0_-10px_30px_rgba(0,0,0,0.15)] hidden transition-all duration-500 ease-in-out overflow-hidden">
        <div class="container mx-auto px-4 py-3 h-full">
            <div id="compare-bar-main-flex" class="flex flex-col md:flex-row items-center justify-between gap-4 h-full">
                
                <!-- Khu vực danh sách SP (Sẽ ẩn khi thu gọn) -->
                <div id="compare-bar-content" class="flex items-center gap-3 flex-1 w-full md:w-auto">
                    <div id="compare-bar-items" class="flex items-center gap-3 flex-1 overflow-x-auto hide-scrollbar py-1">
                        <!-- Items sẽ được render bằng JS -->
                    </div>
                </div>

                <!-- Khu vực Actions & Info -->
                <div id="compare-bar-actions" class="flex items-center gap-3 shrink-0 w-full md:w-auto justify-center md:justify-end">
                    <!-- Text thông tin (Sẽ ẩn khi thu gọn để thành ô vuông) -->
                    <div id="compare-bar-info" class="text-right hidden md:block">
                        <p class="text-[13px] font-bold text-gray-800"><?= sprintf(__("products_compared"), '<span id="compare-bar-count" class="text-primary">0</span>') ?></p>
                    </div>
                    
                    <div class="flex items-center gap-2 w-full md:w-auto justify-center">
                        <!-- Nút Mở rộng/Thu gọn (Sẽ đổi style khi thu gọn) -->
                        <button id="compare-toggle-btn" onclick="toggleCompareBar()" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-bold text-[13px] hover:bg-gray-50 transition-all flex items-center gap-2 bg-white shadow-sm">
                            <i id="compare-bar-toggle-icon" class="fa-solid fa-chevron-down"></i>
                            <span id="compare-bar-toggle-text"><?= __("collapse") ?></span>
                        </button>

                        <!-- Nút So sánh (Màu đỏ đặc trưng) -->
                        <a id="compare-main-btn" href="compare.php" class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-extrabold text-[13px] hover:bg-red-700 transition-all shadow-lg shadow-red-200 flex items-center gap-2 whitespace-nowrap">
                            <?= __("compare") ?> <i class="fa-solid fa-right-left"></i>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <style>
        /* Animation slide up khi hiện lần đầu */
        .animate-slide-up { animation: slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slide-up {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Mode Thu gọn (Ô vuông Floating) */
        #compare-sticky-bar.is-collapsed {
            left: auto !important;
            right: 24px !important;
            bottom: 24px !important;
            width: 160px !important;
            height: auto !important;
            border-radius: 24px !important;
            border: 2px solid #f3f4f6 !important;
            padding: 16px 12px !important;
        }

        #compare-sticky-bar.is-collapsed .container {
            padding: 0 !important;
            width: 100% !important;
        }

        #compare-sticky-bar.is-collapsed #compare-bar-main-flex {
            flex-direction: column !important;
            gap: 12px !important;
        }

        /* Ẩn danh sách SP và Text thừa khi thu gọn */
        #compare-sticky-bar.is-collapsed #compare-bar-content,
        #compare-sticky-bar.is-collapsed #compare-bar-info {
            display: none !important;
        }

        /* Chỉnh lại các nút thành dạng Stack (cột) trong ô vuông */
        #compare-sticky-bar.is-collapsed #compare-bar-actions,
        #compare-sticky-bar.is-collapsed #compare-bar-actions > div {
            width: 100% !important;
            flex-direction: column !important;
        }

        #compare-sticky-bar.is-collapsed #compare-main-btn,
        #compare-sticky-bar.is-collapsed #compare-toggle-btn {
            width: 100% !important;
            justify-content: center !important;
            padding: 10px 8px !important;
            font-size: 12px !important;
        }

        #compare-sticky-bar.is-collapsed #compare-toggle-btn {
            order: 2; /* Nút mở rộng nằm dưới */
            border: none !important;
            background: #f9fafb !important;
            color: #6b7280 !important;
        }

        #compare-sticky-bar.is-collapsed #compare-main-btn {
            order: 1; /* Nút so sánh nằm trên */
        }

        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</body>

</html>