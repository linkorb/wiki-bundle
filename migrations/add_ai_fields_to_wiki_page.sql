-- Migration: Add AI generation and Twig processing fields to wiki pages
-- Date: 2026-01-30

ALTER TABLE `linkorb_wiki_wiki_page` 
    ADD COLUMN `context` TEXT NULL AFTER `parent_id`,
    ADD COLUMN `ai_generated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `context`,
    ADD COLUMN `twig_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ai_generated`,
    ADD COLUMN `generated` TEXT NULL AFTER `twig_enabled`,
    ADD COLUMN `generated_at` INT NULL AFTER `generated`;

-- Indexes (optional, for performance if needed)
-- CREATE INDEX idx_wiki_page_ai_generated ON `linkorb_wiki_wiki_page` (`ai_generated`);
-- CREATE INDEX idx_wiki_page_twig_enabled ON `linkorb_wiki_wiki_page` (`twig_enabled`);
