<?php
/**
 * ============================================================
 * CART.PHP - TRANG GIỎ HÀNG
 * ============================================================
 */

use App\Repository\CartRepository;
use App\Service\CartService;

$cartService = new CartService(new CartRepository($db));

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?login_required=1");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (isset($_POST['action']) && $_POST['action'] === 'clear') {
        $cartService->clearUserCart($user_id);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_selected') {
        if (isset($_POST['cart_ids']) && is_array($_POST['cart_ids'])) {
            foreach ($_POST['cart_ids'] as $cartId) {
                $cartService->changeItemQuantityOrRemove((int)$cartId, $user_id, 'delete');
            }
        }
    } else {
        $cartService->changeItemQuantityOrRemove((int)$_POST['cart_id'], $user_id, $_POST['action']);
    }
    header("Location: cart.php");
    exit;
}

$cart_items = $cartService->getUserCartItems($user_id);

require_once __DIR__ . '/../partials/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap');
.cart-wrap { font-family: 'Be Vietnam Pro', sans-serif; }
.cart-wrap * { box-sizing: border-box; }
.cart-wrap .fa, .cart-wrap .fa-solid, .cart-wrap .fa-regular, .cart-wrap .fa-brands, .cart-wrap [class*="fa-"] {
    font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "FontAwesome" !important;
}

/* ── Cart Hero ── */
.cart-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4c1d95 100%);
    padding: 36px 20px 80px;
    position: relative; overflow: hidden;
}
.cart-hero::before {
    content:''; position:absolute; inset:0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.cart-hero-orb { position:absolute; border-radius:50%; filter:blur(60px); pointer-events:none; }
.cart-hero-orb-1 { width:300px;height:300px;background:rgba(139,92,246,0.25);top:-100px;right:-50px; }
.cart-hero-orb-2 { width:200px;height:200px;background:rgba(99,102,241,0.2);bottom:-60px;left:10%; }
.cart-hero-inner { max-width:1100px;margin:0 auto;display:flex;align-items:center;gap:16px;position:relative;z-index:1; }
.cart-hero h1 { color:#fff;font-size:clamp(22px,4vw,32px);font-weight:800;margin:0; }
.cart-hero-sub { color:rgba(255,255,255,0.6);font-size:13px;margin-top:4px; }
.cart-hero-icon { width:52px;height:52px;background:rgba(255,255,255,0.1);border-radius:16px;
    display:flex;align-items:center;justify-content:center;font-size:22px;color:#c4b5fd;
    border:1px solid rgba(255,255,255,0.15);flex-shrink:0; }

/* ── Main Layout ── */
.cart-layout { max-width:1100px;margin:-48px auto 0;padding:0 16px 60px;display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;position:relative;z-index:5; }
@media(max-width:900px){
    .cart-layout{grid-template-columns:1fr;margin-top:-40px;padding-bottom:90px !important;}
    .cart-summary {
        position: fixed !important;
        bottom: 0;
        left: 0;
        right: 0;
        top: auto !important;
        z-index: 998;
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -8px 24px rgba(0,0,0,0.12);
        padding: 14px 20px;
        margin: 0;
        border-top: 1px solid #f1f5f9;
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 12px;
    }
    .cart-summary h3, 
    .cart-summary .free-ship-badge, 
    .cart-summary .summary-row:not(.total), 
    .cart-summary .cart-hint,
    .cart-summary a {
        display: none !important;
    }
    .cart-summary .summary-row.total {
        border-top: none;
        padding-top: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .cart-summary .summary-row.total span {
        font-size: 11px;
        color: #94a3b8;
    }
    .cart-summary .summary-row.total .amount {
        font-size: 18px;
        line-height: 1.2;
    }
    .cart-summary form {
        margin: 0;
        width: auto;
    }
    .cart-summary .btn-checkout,
    .cart-summary .btn-login-checkout {
        margin-top: 0;
        padding: 10px 24px;
        border-radius: 10px;
        font-size: 14px;
        height: 42px;
        width: auto;
    }
}

/* ── Select All Bar ── */
.cart-select-bar {
    background:#fff;border-radius:16px;padding:14px 20px;
    box-shadow:0 2px 12px rgba(0,0,0,0.06);border:1px solid #f1f5f9;
    display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;
}
.btn-select-all-toggle {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.btn-select-all-toggle:hover {
    background: #ede9fe;
    border-color: #c7d2fe;
    color: #6366f1;
}
.btn-select-all-toggle .count-badge {
    background: #6366f1;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 20px;
}
.btn-clear-all-submit {
    background: #fff5f5;
    border: 1.5px solid #fee2e2;
    border-radius: 12px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 700;
    color: #ef4444;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.btn-clear-all-submit:hover {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #dc2626;
}

/* ── Cart Item Card ── */
.cart-item {
    background:#fff;border-radius:18px;padding:16px 18px;
    box-shadow:0 2px 12px rgba(0,0,0,0.05);border:1.5px solid #f1f5f9;
    display:flex;align-items:center;gap:14px;position:relative;
    transition:border-color .2s, box-shadow .2s;margin-bottom:10px;
}
.cart-item:hover { border-color:#c7d2fe;box-shadow:0 6px 24px rgba(99,102,241,0.1); }
.cart-item.removing { opacity:0;transform:translateX(30px);transition:all .35s ease; }

.cart-item-check { width:20px;height:20px;accent-color:#6366f1;cursor:pointer;flex-shrink:0; }

.cart-item-img { width:84px;height:84px;flex-shrink:0;background:#f8fafc;border-radius:14px;
    border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;padding:8px;overflow:hidden; }
.cart-item-img img { max-width:100%;max-height:100%;object-fit:contain;transition:transform .3s; }
.cart-item:hover .cart-item-img img { transform:scale(1.06); }

.cart-item-info { flex:1;min-width:0; }
.cart-item-name { font-size:13.5px;font-weight:600;color:#1e293b;line-height:1.4;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
    text-decoration:none;transition:color .2s; }
.cart-item-name:hover { color:#6366f1; }
.cart-item-price { font-size:18px;font-weight:800;color:#ef4444;margin-top:6px; }
.cart-item-subtotal { font-size:11px;color:#94a3b8;font-weight:500;margin-top:2px; }

/* Qty Controls */
.qty-ctrl { display:flex;align-items:center;gap:0;margin-top:10px;width:fit-content;
    border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden; }
.qty-btn { width:32px;height:32px;background:#f8fafc;border:none;cursor:pointer;
    font-size:15px;font-weight:700;color:#475569;transition:background .2s;
    display:flex;align-items:center;justify-content:center; }
.qty-btn:hover { background:#ede9fe;color:#6366f1; }
.qty-num { width:38px;height:32px;display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:14px;color:#1e293b;background:#fff;border-left:1.5px solid #e2e8f0;border-right:1.5px solid #e2e8f0; }

/* Delete btn */
.cart-item-delete {
    position:absolute;top:10px;right:12px;
    width:30px;height:30px;border-radius:8px;border:none;background:transparent;
    color:#cbd5e1;cursor:pointer;display:flex;align-items:center;justify-content:center;
    font-size:14px;transition:all .2s;
}
.cart-item-delete:hover { background:#fff1f2;color:#ef4444; }

/* ── Empty State ── */
.cart-empty { background:#fff;border-radius:20px;padding:60px 20px;text-align:center;
    box-shadow:0 2px 16px rgba(0,0,0,0.06);border:1px solid #f1f5f9; }
.cart-empty-icon { width:100px;height:100px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);
    border-radius:30px;display:flex;align-items:center;justify-content:center;
    font-size:42px;margin:0 auto 20px;color:#7c3aed; }
.cart-empty h2 { font-size:20px;font-weight:800;color:#1e293b;margin:0 0 8px; }
.cart-empty p { color:#94a3b8;font-size:14px;margin:0 0 28px; }
.btn-shop-now { background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;
    padding:13px 32px;border-radius:14px;font-weight:700;font-size:15px;
    text-decoration:none;display:inline-flex;align-items:center;gap:8px;
    box-shadow:0 6px 20px rgba(99,102,241,0.35);transition:transform .2s,box-shadow .2s; }
.btn-shop-now:hover { transform:translateY(-2px);box-shadow:0 10px 28px rgba(99,102,241,0.45); }

/* ── Order Summary Sidebar ── */
.cart-summary { background:#fff;border-radius:20px;padding:24px;
    box-shadow:0 4px 20px rgba(0,0,0,0.07);border:1px solid #f1f5f9;
    position:sticky;top:80px; }
.cart-summary h3 { font-size:16px;font-weight:800;color:#1e293b;margin:0 0 18px;
    padding-bottom:14px;border-bottom:1px solid #f1f5f9; }
.summary-row { display:flex;justify-content:space-between;align-items:center;
    font-size:13.5px;color:#64748b;margin-bottom:10px; }
.summary-row b { color:#1e293b;font-weight:700; }
.summary-row.total { border-top:1px dashed #e2e8f0;padding-top:14px;margin-top:4px;
    font-size:15px;font-weight:700;color:#1e293b; }
.summary-row.total .amount { font-size:22px;font-weight:900;color:#ef4444; }

.btn-checkout {
    width:100%;background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);
    color:#fff;border:none;border-radius:14px;padding:15px;
    font-size:15px;font-weight:800;cursor:pointer;margin-top:16px;
    box-shadow:0 6px 20px rgba(239,68,68,0.35);transition:all .25s;
    display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-checkout:hover { transform:translateY(-2px);box-shadow:0 10px 28px rgba(239,68,68,0.45); }
.btn-checkout:active { transform:translateY(0); }

.btn-login-checkout {
    width:100%;background:linear-gradient(135deg,#6366f1,#8b5cf6);
    color:#fff;border:none;border-radius:14px;padding:15px;
    font-size:15px;font-weight:800;cursor:pointer;margin-top:16px;
    box-shadow:0 6px 20px rgba(99,102,241,0.3);transition:all .25s;
}
.btn-login-checkout:hover { transform:translateY(-2px); }

.free-ship-badge { background:linear-gradient(90deg,#ecfdf5,#d1fae5);
    border:1px solid #a7f3d0;border-radius:10px;padding:10px 14px;
    display:flex;align-items:center;gap:8px;font-size:12.5px;
    font-weight:600;color:#065f46;margin-bottom:16px; }

.cart-hint { font-size:12px;color:#94a3b8;text-align:center;margin-top:10px; }
.cart-hint i { color:#10b981;margin-right:4px; }

/* ── Checkbox custom style ── */
input[type=checkbox].item-checkbox, input[type=checkbox]#selectAll {
    width:18px;height:18px;accent-color:#6366f1;cursor:pointer;flex-shrink:0;
}
</style>

<div class="cart-wrap">

<!-- HERO -->
<div class="cart-hero">
    <div class="cart-hero-orb cart-hero-orb-1"></div>
    <div class="cart-hero-orb cart-hero-orb-2"></div>
    <div class="cart-hero-inner">
        <div class="cart-hero-icon"><i class="fa-solid fa-cart-shopping"></i></div>
        <div>
            <h1><?= __("your_cart") ?></h1>
            <div class="cart-hero-sub">
                <?php if (!empty($cart_items)): ?>
                    <?= count($cart_items) ?> <?= __("cart_items_waiting") ?>
                <?php else: ?>
                    <?= __("no_items_in_cart") ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MAIN -->
<div class="cart-layout">

    <!-- LEFT: Product List -->
    <div>
        <?php if (empty($cart_items)): ?>
        <!-- Empty State -->
        <div class="cart-empty">
            <div class="cart-empty-icon"><i class="fa-solid fa-cart-circle-xmark"></i></div>
            <h2><?= __("cart_empty") ?></h2>
            <p><?= __("cart_empty_desc") ?></p>
            <a href="index.php" class="btn-shop-now">
                <i class="fa-solid fa-bag-shopping"></i> <?= __("continue_shopping") ?>
            </a>
        </div>

        <?php else: ?>

        <!-- Select All Bar -->
        <div class="cart-select-bar">
            <!-- Left: Toggle select all button -->
            <button type="button" onclick="triggerSelectAll()" class="btn-select-all-toggle">
                <i class="fa-solid fa-square-check text-indigo-500" style="color:#6366f1"></i>
                <span><?= __("select_all") ?></span>
                <span class="count-badge" id="total-items-count"><?= count($cart_items) ?></span>
            </button>

            <!-- Right: Clear all button -->
            <form method="POST" style="margin: 0;" onsubmit="confirmClearCart(event);">
                <?= csrf_input_field() ?>
                <input type="hidden" name="update_cart" value="1">
                <input type="hidden" name="action" value="delete_selected">
                <button type="submit" class="btn-clear-all-submit">
                    <i class="fa-solid fa-trash-can"></i>
                    <span><?= __("delete_all") ?></span>
                </button>
            </form>
        </div>

        <!-- Product Items -->
        <?php foreach ($cart_items as $item): ?>
        <div class="cart-item" id="cart-row-<?= $item['cart_id'] ?>">

            <!-- Checkbox -->
            <input type="checkbox" class="item-checkbox cart-item-check"
                value="<?= $item['cart_id'] ?>"
                data-price="<?= $item['price'] ?>"
                data-qty="<?= $item['quantity'] ?>"
                onchange="calculateTotal()" checked>

            <!-- Image -->
            <div class="cart-item-img">
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="">
            </div>

            <!-- Info -->
            <div class="cart-item-info">
                <a href="product_detail.php?id=<?= $item['product_id'] ?>" class="cart-item-name">
                    <?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($item['name'], 'prod_name_' . $item['product_id']) : $item['name']) ?>
                </a>
                <div class="cart-item-price"><?= number_format($item['price']) ?>đ</div>

                <!-- Qty Controls -->
                <form method="POST" style="display:inline-flex;margin-top:10px;">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                    <input type="hidden" name="action" value="">
                    <div class="qty-ctrl">
                        <button type="submit" name="update_cart" value="1" class="qty-btn"
                            onclick="this.form.action.value = (<?= $item['quantity'] ?> <= 1) ? 'delete' : 'decrease'"><i class="fa-solid fa-minus" style="font-size: 10px;"></i></button>
                        <div class="qty-num"><?= $item['quantity'] ?></div>
                        <button type="submit" name="update_cart" value="1" class="qty-btn"
                            onclick="this.form.action.value='increase'"><i class="fa-solid fa-plus" style="font-size: 10px;"></i></button>
                    </div>
                </form>
            </div>

            <!-- Delete -->
            <form method="POST" style="position:absolute;top:10px;right:12px">
                <?= csrf_input_field() ?>
                <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" name="update_cart" class="cart-item-delete" title="<?= __("remove") ?>">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Order Summary -->
    <div class="cart-summary">
        <h3><i class="fa-solid fa-receipt mr-2" style="color:#6366f1"></i><?= __("order_summary") ?></h3>

        <div class="free-ship-badge">
            <i class="fa-solid fa-truck-fast" style="color:#10b981;font-size:16px"></i>
            <?= __("free_shipping_nationwide") ?>
        </div>

        <div class="summary-row">
            <span><?= __("subtotal") ?> (<span id="selected-count-display"><?= count($cart_items) ?></span> <?= __("pcs") ?>)</span>
            <b id="total-price-display" style="color:#ef4444;font-size:17px">0đ</b>
        </div>
        <div class="summary-row">
            <span><?= __("shipping_fee") ?></span>
            <b style="color:#10b981"><?= __("free") ?></b>
        </div>
        <div class="summary-row total">
            <span><?= __("final_total") ?></span>
            <span class="amount" id="final-total-display">0đ</span>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <form action="checkout.php" method="POST" id="checkoutForm" onsubmit="return validateCheckout()">
                <?= csrf_input_field() ?>
                <button type="submit" class="btn-checkout">
                    <i class="fa-solid fa-bolt"></i> <?= __("proceed_to_checkout") ?>
                </button>
            </form>
        <?php else: ?>
            <button class="btn-login-checkout" onclick="document.getElementById('loginModal').classList.remove('hidden')">
                <i class="fa-solid fa-right-to-bracket mr-2"></i><?= __("login_to_order") ?>
            </button>
        <?php endif; ?>

        <p class="cart-hint"><i class="fa-solid fa-shield-check"></i> <?= __("secure_checkout_hint") ?></p>

        <a href="index.php" style="display:flex;align-items:center;justify-content:center;gap:6px;
            margin-top:12px;font-size:13px;font-weight:600;color:#6366f1;text-decoration:none;">
            <i class="fa-solid fa-arrow-left text-xs"></i> <?= __("continue_shopping") ?>
        </a>
    </div>

</div><!-- /cart-layout -->
</div><!-- /cart-wrap -->

<script>
let allSelected = true;
function triggerSelectAll() {
    allSelected = !allSelected;
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = allSelected);
    updateSelectAllButton();
    calculateTotal();
}

function updateSelectAllButton() {
    const boxes = document.querySelectorAll('.item-checkbox');
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const btn = document.querySelector('.btn-select-all-toggle');
    if (btn) {
        if (checkedBoxes.length === boxes.length && boxes.length > 0) {
            allSelected = true;
            btn.innerHTML = `<i class="fa-solid fa-square-check text-indigo-500" style="color:#6366f1"></i> <span><?= __("select_all") ?></span> <span class="count-badge">${boxes.length}</span>`;
        } else {
            allSelected = false;
            btn.innerHTML = `<i class="fa-regular fa-square text-gray-400"></i> <span><?= __("select_all") ?></span> <span class="count-badge">${checkedBoxes.length}</span>`;
        }
    }
}

function calculateTotal() {
    let total = 0, count = 0;
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        if (cb.checked) {
            total += parseFloat(cb.dataset.price) * parseInt(cb.dataset.qty);
            count++;
        }
    });
    const fmt = n => new Intl.NumberFormat('vi-VN').format(n) + 'đ';
    document.getElementById('total-price-display').innerText = fmt(total);
    if (document.getElementById('final-total-display'))
        document.getElementById('final-total-display').innerText = fmt(total);
    document.getElementById('selected-count-display').innerText = count;
    
    updateSelectAllButton();
}

function confirmClearCart(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const checked = document.querySelectorAll('.item-checkbox:checked');
    
    if (checked.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '<?= __("no_items_selected_title") ?>',
                text: '<?= __("no_items_selected_desc") ?>',
                confirmButtonColor: '#6366f1'
            });
        } else {
            alert('<?= __("no_items_selected_desc") ?>');
        }
        return false;
    }
    
    const count = checked.length;
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '<?= __("confirm_delete_selected_title") ?>',
            text: '<?= __("confirm_delete_selected_desc") ?>'.replace('{count}', count),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<?= __("yes_delete_selected") ?>',
            cancelButtonText: '<?= __("cancel") ?>',
            borderRadius: '16px'
        }).then((result) => {
            if (result.isConfirmed) {
                form.querySelectorAll('input[name="cart_ids[]"]').forEach(el => el.remove());
                checked.forEach(cb => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'cart_ids[]';
                    inp.value = cb.value;
                    form.appendChild(inp);
                });
                Swal.fire({
                    title: '<?= __("deleted_success_title") ?>',
                    text: '<?= __("deleted_success_selected_desc") ?>',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
                setTimeout(() => {
                    form.submit();
                }, 1400);
            }
        });
    } else {
        if (confirm('<?= __("confirm_delete_selected_desc") ?>'.replace('{count}', count))) {
            checked.forEach(cb => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'cart_ids[]';
                inp.value = cb.value;
                form.appendChild(inp);
            });
            form.submit();
        }
    }
}

function validateCheckout() {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon:'warning', title:'<?= __("cart_empty") ?>', text:'<?= __("select_at_least_one") ?>', confirmButtonColor:'#6366f1' });
        } else alert('<?= __("select_at_least_one") ?>');
        return false;
    }
    const form = document.getElementById('checkoutForm');
    form.querySelectorAll('input[name="selected_items[]"]').forEach(el => el.remove());
    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'selected_items[]'; inp.value = cb.value;
        form.appendChild(inp);
    });
    return true;
}

document.addEventListener('DOMContentLoaded', calculateTotal);
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>