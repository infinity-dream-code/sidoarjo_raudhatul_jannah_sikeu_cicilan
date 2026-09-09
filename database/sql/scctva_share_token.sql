-- Jalankan sekali di database billing (DATA_MYSQL / scctva).
ALTER TABLE `scctva`
  ADD COLUMN `SHARE_TOKEN` CHAR(64) NULL DEFAULT NULL COMMENT 'Token URL publik cara bayar' AFTER `STATUS`,
  ADD UNIQUE KEY `uq_scctva_share_token` (`SHARE_TOKEN`);
