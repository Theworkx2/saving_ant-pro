-- Update any incorrect payment method values
UPDATE transactions 
SET payment_method = CASE 
    WHEN payment_method IN ('airtelmoney', 'Airtel', 'AirtelMoney') THEN 'airtel'
    WHEN payment_method IN ('equity', 'Equity', 'bank', 'Bank') THEN 'bank'
    ELSE 'momo'
END;

-- Add a check constraint to ensure only valid payment methods are used
ALTER TABLE transactions
MODIFY COLUMN payment_method VARCHAR(50) NOT NULL CHECK (payment_method IN ('momo', 'airtel', 'bank'));