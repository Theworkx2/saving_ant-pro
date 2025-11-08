USE saving_ant;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS payment_method VARCHAR(10) NOT NULL DEFAULT 'momo';