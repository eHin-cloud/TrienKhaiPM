<?php

namespace App\Support;

/**
 * ============================================================
 * VALIDATOR CLASS
 * ============================================================
 * Lớp hỗ trợ kiểm tra tính hợp lệ của dữ liệu đầu vào.
 */
class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Kiểm tra trường bắt buộc
     */
    public function required(string $field, string $message = "Trường này là bắt buộc."): self {
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Kiểm tra định dạng Email
     */
    public function email(string $field, string $message = "Email không hợp lệ."): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Kiểm tra độ dài tối thiểu
     */
    public function min(string $field, int $length, string $message = null): self {
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? "Trường này phải có ít nhất $length ký tự.";
        }
        return $this;
    }

    /**
     * Kiểm tra số điện thoại (VN)
     */
    public function phone(string $field, string $message = "Số điện thoại không hợp lệ."): self {
        if (isset($this->data[$field]) && !preg_match('/^(0[3|5|7|8|9])+([0-9]{8})$/', $this->data[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Kiểm tra xem có lỗi không
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Lấy danh sách lỗi
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Lấy lỗi đầu tiên của một trường
     */
    public function getFirstError(string $field): ?string {
        return $this->errors[$field] ?? null;
    }
}
