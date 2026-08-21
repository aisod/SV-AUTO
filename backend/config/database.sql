-- SQLBook: Code


-- Create tables in dependency order
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    permissions TEXT
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    avatar_url VARCHAR(255) NULL DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role_id INT,
    client_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    contact_person VARCHAR(100)
);

CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    vin_no VARCHAR(50),
    fiscal_no VARCHAR(50),
    reg_no VARCHAR(50),
    model VARCHAR(50),
    last_service_date DATE,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(50),
    certificates TEXT,
    licenses TEXT,
    ids TEXT,
    uniforms TEXT,
    photo_url VARCHAR(255),
    event_locations TEXT,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE job_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_number VARCHAR(50) NOT NULL,
    vehicle_id INT,
    technician_id INT,
    description TEXT,
    parts_supply TEXT,
    requester_for_parts VARCHAR(100),
    status ENUM('pending', 'in_progress', 'complete') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (technician_id) REFERENCES employees(id)
);
CREATE INDEX idx_card_number ON job_cards(card_number);

CREATE TABLE quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT,
    client_id INT,
    amount DECIMAL(10,2),
    details TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES job_cards(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT,
    supplier_name VARCHAR(100),
    amount DECIMAL(10,2),
    status ENUM('pending', 'approved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES job_cards(id)
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT,
    amount DECIMAL(10,2),
    status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id)
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT,
    amount DECIMAL(10,2),
    remittance TEXT,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT,
    description TEXT,
    amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
);

CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_name VARCHAR(100) NOT NULL,
    stock INT DEFAULT 0,
    price DECIMAL(10,2)
);

CREATE TABLE hr_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT,
    type ENUM('overtime', 'loan', 'leave', 'warning'),
    details TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    authorized_by INT,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (authorized_by) REFERENCES users(id)
);

CREATE TABLE statutory_docs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    file_url VARCHAR(255),
    type ENUM('founding', 'good_standing', 'recommendation', 'afs', 'tender')
);

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT,
    service_date DATE,
    status ENUM('approved', 'pending') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action TEXT,
    entity_type VARCHAR(50),
    entity_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    value TEXT
);

-- Insert sample data in dependency order
-- Roles first
INSERT INTO roles (name, permissions) VALUES 
('Admin', 'full_access'),
('Technician', 'job_cards,requests'),
('Finance User', 'invoices,payments,reports'),
('HR User', 'employees,forms'),
('Manager', 'authorize,reports'),
('Client', 'view_own_documents,respond_quotations,view_invoices,update_profile');

-- Users next, referencing roles
INSERT INTO users (username, email, password, role_id, client_id) VALUES 
('admin_user', 'admin@svauto.com', 'hashed_password1', 1, NULL),
('tech_john', 'john@svauto.com', 'hashed_password2', 2, NULL),
('fin_mary', 'mary@svauto.com', 'hashed_password3', 3, NULL),
('hr_peter', 'peter@svauto.com', 'hashed_password4', 4, NULL),
('mgr_susan', 'susan@svauto.com', 'hashed_password5', 5, NULL),
('client_joe', 'john.doe@email.com', 'hashed_password6', 6, 1);

-- Clients
INSERT INTO clients (name, email, phone, address, contact_person) VALUES 
('John Doe', 'john.doe@email.com', '1234567890', '123 Main St', 'John Doe'),
('Jane Smith', 'jane.smith@email.com', '0987654321', '456 Oak Ave', 'Jane Smith');

-- Vehicles, referencing clients
INSERT INTO vehicles (client_id, vin_no, fiscal_no, reg_no, model, last_service_date) VALUES 
(1, '1HGCM82633A123456', 'FISC123456', 'ABC123', 'Toyota Camry', '2024-10-01'),
(2, '2HGFG12803H123789', 'FISC987654', 'XYZ789', 'Honda Civic', '2024-09-15');

-- Employees, referencing users
INSERT INTO employees (name, position, certificates, licenses, ids, uniforms, photo_url, event_locations, user_id) VALUES 
('John Tech', 'Technician', 'Cert1,Cert2', 'LicenseA', 'ID123', 'Uniform1', 'photo1.jpg', 'Loc1,Loc2', 2),
('Mary Finance', 'Accountant', 'Cert3', 'LicenseB', 'ID456', 'Uniform2', 'photo2.jpg', 'Loc3', 3),
('Peter HR', 'HR Manager', 'Cert4', 'LicenseC', 'ID789', 'Uniform3', 'photo3.jpg', 'Loc4', 4);

-- Job cards, referencing vehicles and employees
INSERT INTO job_cards (card_number, vehicle_id, technician_id, description, parts_supply, requester_for_parts, status) VALUES 
('JC001', 1, 1, 'Oil change and filter replacement', 'Oil,Filter', 'John Tech', 'pending'),
('JC002', 2, 1, 'Brake pad replacement', 'BrakePads', 'John Tech', 'in_progress');

-- Quotations, referencing job_cards and clients
INSERT INTO quotations (job_card_id, client_id, amount, details, status, submitted_at) VALUES 
(1, 1, 150.00, 'Oil change service', 'approved', '2025-10-03 14:30:00'),
(2, 2, 200.00, 'Brake pad replacement', 'pending', '2025-10-03 15:00:00');

-- Purchase orders, referencing job_cards
INSERT INTO purchase_orders (job_card_id, supplier_name, amount, status) VALUES 
(1, 'Auto Parts Ltd', 100.00, 'approved'),
(2, 'Brake Solutions', 150.00, 'pending');

-- Invoices, referencing quotations
INSERT INTO invoices (quotation_id, amount, status, paid_at) VALUES 
(1, 150.00, 'paid', '2025-10-03 16:00:00'),
(2, 200.00, 'unpaid', NULL);

-- Payments, referencing invoices
INSERT INTO payments (invoice_id, amount, remittance) VALUES 
(1, 150.00, 'Cash payment');

-- Expenses, referencing purchase_orders
INSERT INTO expenses (purchase_order_id, description, amount) VALUES 
(1, 'Oil and filter purchase', 100.00),
(2, 'Brake pads purchase', 150.00);

-- Inventory
INSERT INTO inventory (part_name, stock, price) VALUES 
('Engine Oil', 50, 10.00),
('Oil Filter', 30, 5.00),
('Brake Pads', 20, 15.00);

-- HR forms, referencing employees and users
INSERT INTO hr_forms (employee_id, type, details, status, authorized_by) VALUES 
(1, 'overtime', '2 hours overtime', 'approved', 4),
(2, 'leave', '3 days leave', 'pending', 4);

-- Statutory documents
INSERT INTO statutory_docs (name, file_url, type) VALUES 
('Founding Doc', 'doc1.pdf', 'founding'),
('Good Standing', 'doc2.pdf', 'good_standing');

-- Services, referencing vehicles
INSERT INTO services (vehicle_id, service_date, status, notes) VALUES 
(1, '2025-10-05', 'pending', 'Scheduled oil change'),
(2, '2025-10-06', 'approved', 'Brake inspection');

-- Audit logs, referencing users
INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES 
(1, 'created', 'job_card', 1),
(2, 'updated', 'quotation', 1);

-- Settings
INSERT INTO settings (`key`, value) VALUES 
('company_name', 'SV Auto'),
('service_rate', '50.00');

-- Add and update status column for employees
ALTER TABLE employees ADD COLUMN status ENUM('active', 'on_leave', 'on_field') DEFAULT 'active';
UPDATE employees SET status = 'active' WHERE id = 1;
UPDATE employees SET status = 'on_leave' WHERE id = 2;
UPDATE employees SET status = 'on_field' WHERE id = 3;



-- Add email column to users table + make it unique (recommended)
ALTER TABLE users 
ADD COLUMN email VARCHAR(100) NULL AFTER password,
ADD UNIQUE KEY unique_email (email);



-- Create currencies table
CREATE TABLE currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code CHAR(3) NOT NULL UNIQUE,        -- ISO 4217 code (e.g. NAD, USD)
    name VARCHAR(50) NOT NULL,           -- Full name
    symbol VARCHAR(10) NOT NULL,          -- Symbol: N$, $, R, €
    format ENUM('left', 'right') DEFAULT 'left',  -- Where symbol appears
    is_active TINYINT(1) DEFAULT 1,       -- Enable/disable
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert common currencies (you can add more anytime)
INSERT INTO currencies (code, name, symbol, format) VALUES
('NAD', 'Namibian Dollar', 'N$', 'left'),
('ZAR', 'South African Rand', 'R', 'left'),
('USD', 'United States Dollar', '$', 'left'),
('EUR', 'Euro', '€', 'right'),
('GBP', 'British Pound', '£', 'left'),
('AUD', 'Australian Dollar', 'A$', 'left');




CREATE TABLE business (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(30),
    address TEXT,
    tax_number VARCHAR(50),
    logo_url VARCHAR(255) DEFAULT NULL,
    website VARCHAR(100),
    slogan VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default business (only once)
INSERT INTO business (name, email, phone, address, tax_number) VALUES 
('SV Auto Services', 'info@svauto.com', '+264 61 123 4567', '123 Main Street, Windhoek', 'NAM123456789')
ON DUPLICATE KEY UPDATE name = name;





-- SQLBook: Code
