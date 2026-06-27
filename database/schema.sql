CREATE TABLE restbinder_resources (
  id VARCHAR(191) PRIMARY KEY,
  type VARCHAR(80) NOT NULL,
  schema_version VARCHAR(40) NOT NULL,
  protocol_version VARCHAR(40) NOT NULL,
  state_json JSON NOT NULL,
  hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE restbinder_resource_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resource_id VARCHAR(191) NOT NULL,
  command_name VARCHAR(120) NOT NULL,
  input_json JSON NOT NULL,
  before_json JSON NOT NULL,
  after_json JSON NOT NULL,
  hash_after CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_restbinder_history_resource (resource_id),
  CONSTRAINT fk_restbinder_history_resource
    FOREIGN KEY (resource_id) REFERENCES restbinder_resources(id)
    ON DELETE CASCADE
);

CREATE TABLE rb_demo_grid_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_uuid CHAR(36) NOT NULL UNIQUE,
  content_type ENUM('text', 'image') NOT NULL DEFAULT 'text',
  title VARCHAR(160) NULL,
  body TEXT NULL,
  image_path VARCHAR(255) NULL,
  image_alt VARCHAR(255) NULL,
  upvote_count INT NOT NULL DEFAULT 0,
  origin_x INT NULL,
  origin_y INT NULL,
  origin_column INT NULL,
  origin_row INT NULL,
  origin_context VARCHAR(80) NULL,
  origin_created_from VARCHAR(120) NULL,
  created_by INT NULL,
  created_by_session_key VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_grid_scope_user (created_by, created_at),
  INDEX idx_grid_scope_session (created_by_session_key, created_at),
  INDEX idx_grid_sort (is_deleted, created_at, upvote_count),
  INDEX idx_grid_origin (origin_column, origin_row),
  INDEX idx_grid_created_at (created_at),
  INDEX idx_grid_upvotes (upvote_count)
);

CREATE TABLE rb_demo_grid_votes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  voter_key VARCHAR(128) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_item_voter (item_id, voter_key),
  INDEX idx_grid_votes_item (item_id),
  CONSTRAINT fk_grid_vote_item
    FOREIGN KEY (item_id)
    REFERENCES rb_demo_grid_items(id)
    ON DELETE CASCADE
);
