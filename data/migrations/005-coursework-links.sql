-- Author: 林子媛 | Student ID: 2410936
ALTER TABLE coursework_files
  MODIFY stored_name VARCHAR(100) NULL,
  MODIFY mime_type VARCHAR(120) NULL,
  MODIFY file_size BIGINT UNSIGNED NULL,
  MODIFY sha256 CHAR(64) NULL,
  ADD source_type ENUM('file','link') NOT NULL DEFAULT 'file',
  ADD external_url VARCHAR(2048) NULL,
  ADD CONSTRAINT ck_coursework_source CHECK ((source_type='file' AND stored_name IS NOT NULL AND mime_type IS NOT NULL AND file_size IS NOT NULL AND sha256 IS NOT NULL AND external_url IS NULL) OR (source_type='link' AND external_url IS NOT NULL AND stored_name IS NULL AND mime_type IS NULL AND file_size IS NULL AND sha256 IS NULL));
