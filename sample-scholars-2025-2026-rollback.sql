-- Select the same ScholarHub database used for the sample import.
-- Remove only rows tagged by the matching SQL import.
SET NAMES utf8mb4;
START TRANSACTION;
-- Acquire the write lock without returning a result set to phpMyAdmin.
UPDATE record_write_lock SET id = id WHERE id = 1;

DELETE FROM records
WHERE kind IN ('scholars', 'discountHistory')
  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$._sampleBatch')) = 'scholarhub-sample-2025-2026';

SELECT ROW_COUNT() AS sample_rows_removed;
COMMIT;
