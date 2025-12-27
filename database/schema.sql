-- DBMS Lab Project: Monthly Expense Tracker
-- Schema Definition

CREATE DATABASE IF NOT EXISTS expense_tracker;
USE expense_tracker;

-- Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories Table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    user_id INT DEFAULT NULL, -- NULL for global default categories
    is_default BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Budgets Table (One active per month)
CREATE TABLE budgets (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_month DATE NOT NULL, -- Stored as first day of month (e.g., '2023-10-01')
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_budget_month (user_id, budget_month)
);

-- Budget Category Allocations
CREATE TABLE budget_categories (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    category_id INT NOT NULL,
    allocated_amount DECIMAL(10, 2) NOT NULL,
    spent_amount DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Expenses Table
CREATE TABLE expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_id INT NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    expense_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    receipt_url VARCHAR(255),
    receipt_text TEXT, -- OCR extracted text
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Indexing for Performance
CREATE INDEX idx_user_expenses ON expenses(user_id, expense_date);
CREATE INDEX idx_budget_month ON budgets(user_id, budget_month);
CREATE INDEX idx_category_spending ON budget_categories(budget_id, category_id);
