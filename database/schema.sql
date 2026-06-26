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
