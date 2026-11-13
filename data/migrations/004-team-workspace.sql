-- Author: 林子媛 | Student ID: 2410936
-- Keep existing accounts and their role values; public registration uses user.
ALTER TABLE users MODIFY role ENUM('admin', 'member', 'user') NOT NULL DEFAULT 'user';
