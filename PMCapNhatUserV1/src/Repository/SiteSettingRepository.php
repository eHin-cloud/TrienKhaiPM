<?php

namespace App\Repository;

use PDO;

/**
 * SiteSettingRepository
 * Quản lý các cài đặt site (site_settings) trong cơ sở dữ liệu.
 */

class SiteSettingRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO instance.
     * @param PDO $db
     */

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Lấy toàn bộ cài đặt site dưới dạng mảng key=>value.
     * @return array
     */
    public function getSiteSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM site_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Cập nhật hoặc tạo mới một cài đặt site.
     * @param string $key   Tên cài đặt
     * @param string $value Giá trị cài đặt
     */
    public function updateSiteSetting(string $key, string $value) {
        $stmt = $this->db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }
}
