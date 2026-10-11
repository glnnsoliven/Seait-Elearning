-- SEAIT E-Learning — Migration 001: Initial Schema
-- Run: source this file in phpMyAdmin or via MySQL CLI
-- Date: 2026-09-21
-- Description: Initial tables from seait_elearning.sql

-- This migration is tracked. After running the full seait_elearning.sql,
-- record this migration as applied:
-- INSERT INTO migrations (version, description) VALUES ('001', 'Initial schema');

-- To track migrations, create this table first:
CREATE TABLE IF NOT EXISTS migrations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version     VARCHAR(10)  NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
