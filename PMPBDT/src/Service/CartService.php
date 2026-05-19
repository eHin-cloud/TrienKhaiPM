<?php

namespace App\Service;

use App\Repository\CartRepository;

class CartService {
    private CartRepository $cartRepo;

    public function __construct(CartRepository $cartRepo) {
        $this->cartRepo = $cartRepo;
    }

    public function addProductToCart(int $userId, int $productId, int $quantity = 1) {
        // Có thể bổ sung check hàng tồn kho tại đây
        $this->cartRepo->addToCart($userId, $productId, $quantity);
    }

    public function getUserCartItems(int $userId) {
        return $this->cartRepo->getCartItems($userId);
    }

    public function changeItemQuantityOrRemove(int $cartId, int $userId, string $action) {
        $this->cartRepo->modifyCartItem($cartId, $userId, $action);
    }

    public function getCartCount(int $userId): int {
        return $this->cartRepo->getCartCount($userId);
    }

    public function clearUserCart(int $userId) {
        $this->cartRepo->clearCart($userId);
    }
}
