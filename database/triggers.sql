-- Database Triggers (Passive Mode)
-- These triggers update the 'spent_amount' in budget_categories automatically
-- but DO NOT block the transaction if the user overspends.

DELIMITER //

-- 1. DROP OLD TRIGGERS (Cleanup)
-- Removes any existing triggers that might cause conflicts or errors
DROP TRIGGER IF EXISTS after_expense_insert;
DROP TRIGGER IF EXISTS update_spent_on_insert;
DROP TRIGGER IF EXISTS update_spent_on_delete;
DROP TRIGGER IF EXISTS prevent_category_overspending; -- This was the blocking one

-- 2. UPDATE SPENT AMOUNT ON INSERT
-- When a new expense is added, add the amount to the category's total spent.
CREATE TRIGGER update_spent_on_insert 
AFTER INSERT ON expenses
FOR EACH ROW
BEGIN
    UPDATE budget_categories 
    SET spent_amount = spent_amount + NEW.amount
    WHERE budget_id = NEW.budget_id AND category_id = NEW.category_id;
END //

-- 3. UPDATE SPENT AMOUNT ON DELETE
-- If an expense is deleted, subtract the amount from the category's total spent.
CREATE TRIGGER update_spent_on_delete 
AFTER DELETE ON expenses
FOR EACH ROW
BEGIN
    UPDATE budget_categories 
    SET spent_amount = spent_amount - OLD.amount
    WHERE budget_id = OLD.budget_id AND category_id = OLD.category_id;
END //

-- 4. UPDATE SPENT AMOUNT ON UPDATE (Optional but recommended)
-- If an expense amount is edited, adjust the total spent accordingly.
CREATE TRIGGER update_spent_on_update
AFTER UPDATE ON expenses
FOR EACH ROW
BEGIN
    -- Subtract old amount, add new amount
    UPDATE budget_categories 
    SET spent_amount = spent_amount - OLD.amount + NEW.amount
    WHERE budget_id = NEW.budget_id AND category_id = NEW.category_id;
END //

DELIMITER ;