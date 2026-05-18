ALTER TABLE users
ADD COLUMN google_id VARCHAR(255) NULL AFTER email,
ADD COLUMN auth_provider ENUM('local', 'google') NOT NULL DEFAULT 'local' AFTER google_id;

CREATE INDEX idx_users_google_id ON users (google_id);
CREATE INDEX idx_users_auth_provider ON users (auth_provider);
