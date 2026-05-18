ALTER TABLE users
ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_banned,
ADD COLUMN two_factor_secret VARCHAR(255) NULL AFTER two_factor_enabled;

CREATE INDEX idx_users_two_factor_enabled ON users (two_factor_enabled);
