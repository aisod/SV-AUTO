-- Migration: add invoices, invoice_items and secondary contact
ALTER TABLE clients ADD COLUMN IF NOT EXISTS contact_person_secondary VARCHAR(100);

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id)
);

ALTER TABLE invoices 
    ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(50) UNIQUE,
    ADD COLUMN IF NOT EXISTS quotation_id INT,
    ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(10,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status_paid ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    ADD COLUMN IF NOT EXISTS issued_date DATE,
    ADD COLUMN IF NOT EXISTS due_date DATE,
    ADD COLUMN IF NOT EXISTS paid_at DATETIME,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
