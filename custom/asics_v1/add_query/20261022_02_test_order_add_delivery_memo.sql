ALTER TABLE test_order ADD COLUMN delivery_memo VARCHAR(200) NULL;

UPDATE test_order SET delivery_memo = 'leave at door' WHERE id = 1;
