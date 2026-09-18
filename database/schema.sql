-- solar.co.ke — database schema
-- Shared core tables (planning/00-portfolio/shared-database-schema.md) +
-- platform-specific extension tables (planning/04-solar-co-ke/database-schema.md).
-- MySQL/MariaDB dialect, per shared-architecture.md's stack assumption.

-- ============================================================
-- SHARED CORE TABLES — identical shape across all five platforms
-- ============================================================

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    national_id_number VARCHAR(20) NULL,
    account_type ENUM('customer', 'provider', 'admin') NOT NULL,
    status ENUM('active', 'suspended', 'banned', 'pending_verification') NOT NULL DEFAULT 'pending_verification',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    type ENUM('charge', 'payout', 'refund', 'commission') NOT NULL,
    method ENUM('mpesa_stk', 'mpesa_c2b', 'mpesa_b2c', 'card') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    external_reference VARCHAR(100) NULL,
    status ENUM('pending', 'completed', 'failed', 'reversed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_payments_external_reference (external_reference)
) ENGINE=InnoDB;

CREATE TABLE payment_callbacks_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checkout_request_id VARCHAR(100) NOT NULL UNIQUE,
    raw_payload JSON NOT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE escrow_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    held_amount DECIMAL(12,2) NOT NULL,
    retention_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    release_condition ENUM('auto_timeout', 'customer_confirmation', 'admin_release', 'dispute_resolution') NOT NULL,
    release_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    status ENUM('held', 'released', 'partially_released', 'refunded') NOT NULL DEFAULT 'held',
    FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE commission_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('laundry', 'rider', 'construction', 'solar', 'event') NOT NULL DEFAULT 'solar',
    category VARCHAR(100) NOT NULL,
    commission_type ENUM('percentage', 'flat_fee', 'tiered') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    min_transaction_value DECIMAL(12,2) NULL,
    max_transaction_value DECIMAL(12,2) NULL,
    effective_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_to TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE kyc_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('national_id', 'kra_pin', 'business_registration', 'insurance_certificate', 'professional_certification', 'proof_of_address') NOT NULL,
    file_reference VARCHAR(500) NOT NULL,
    verification_status ENUM('pending', 'verified', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    reviewer_id BIGINT UNSIGNED NOT NULL,
    reviewee_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (reviewee_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- category values for this platform: mis_sizing, performance_shortfall, installation_quality, hardware_defect, other
CREATE TABLE disputes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    raised_by BIGINT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    evidence_urls JSON NULL,
    status ENUM('open', 'under_review', 'resolved_refund', 'resolved_partial', 'resolved_no_action', 'escalated') NOT NULL DEFAULT 'open',
    resolved_by BIGINT UNSIGNED NULL,
    resolution_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (raised_by) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    before_state JSON NULL,
    after_state JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- PLATFORM-SPECIFIC TABLES — solar.co.ke
-- (planning/04-solar-co-ke/database-schema.md)
-- ============================================================

CREATE TABLE system_sizing_calculations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    appliance_profile JSON NOT NULL,
    average_daily_consumption_kwh DECIMAL(8,2) NOT NULL,
    desired_backup_duration_hours DECIMAL(6,2) NOT NULL,
    budget_range_min DECIMAL(12,2) NULL,
    budget_range_max DECIMAL(12,2) NULL,
    recommended_panel_capacity_kw DECIMAL(6,2) NOT NULL,
    recommended_battery_capacity_kwh DECIMAL(6,2) NOT NULL,
    recommended_inverter_rating_kw DECIMAL(6,2) NOT NULL,
    estimated_cost_range_min DECIMAL(12,2) NOT NULL,
    estimated_cost_range_max DECIMAL(12,2) NOT NULL,
    calculator_version VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE solar_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    installer_id BIGINT UNSIGNED NULL,
    sizing_calculation_id BIGINT UNSIGNED NULL,
    status ENUM('open_for_quotes', 'quote_accepted', 'site_survey_scheduled', 'site_survey_complete', 'installation_scheduled', 'installing', 'commissioning', 'awaiting_signoff', 'completed', 'cancelled', 'disputed') NOT NULL DEFAULT 'open_for_quotes',
    site_address VARCHAR(500) NOT NULL,
    site_lat DECIMAL(10,7) NOT NULL,
    site_lng DECIMAL(10,7) NOT NULL,
    contracted_panel_capacity_kw DECIMAL(6,2) NULL,
    contracted_battery_capacity_kwh DECIMAL(6,2) NULL,
    contracted_inverter_rating_kw DECIMAL(6,2) NULL,
    total_contract_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    deposit_paid_at TIMESTAMP NULL,
    final_payment_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (installer_id) REFERENCES users(id),
    FOREIGN KEY (sizing_calculation_id) REFERENCES system_sizing_calculations(id),
    FOREIGN KEY (final_payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE installation_quotes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    installer_id BIGINT UNSIGNED NOT NULL,
    quoted_panel_capacity_kw DECIMAL(6,2) NOT NULL,
    quoted_battery_capacity_kwh DECIMAL(6,2) NOT NULL,
    quoted_inverter_rating_kw DECIMAL(6,2) NOT NULL,
    quoted_amount DECIMAL(12,2) NOT NULL,
    deviation_flag BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('submitted', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'submitted',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES solar_bookings(id),
    FOREIGN KEY (installer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE site_surveys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    scheduled_at TIMESTAMP NOT NULL,
    roof_type VARCHAR(255) NULL,
    roof_condition_notes TEXT NULL,
    shading_assessment TEXT NULL,
    survey_photos JSON NOT NULL,
    suitability_confirmed BOOLEAN NULL,
    revised_recommendation_notes TEXT NULL,
    conducted_by BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES solar_bookings(id),
    FOREIGN KEY (conducted_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE post_installation_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    actual_panel_capacity_kw DECIMAL(6,2) NOT NULL,
    actual_battery_capacity_kwh DECIMAL(6,2) NOT NULL,
    actual_inverter_rating_kw DECIMAL(6,2) NOT NULL,
    commissioning_readings JSON NOT NULL,
    photo_urls JSON NOT NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    independently_verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES solar_bookings(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE installer_certifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installer_id BIGINT UNSIGNED NOT NULL,
    certification_kyc_document_id BIGINT UNSIGNED NOT NULL,
    certification_type VARCHAR(100) NOT NULL,
    expires_at DATE NOT NULL,
    status ENUM('valid', 'expiring_soon', 'expired') NOT NULL DEFAULT 'valid',
    FOREIGN KEY (installer_id) REFERENCES users(id),
    FOREIGN KEY (certification_kyc_document_id) REFERENCES kyc_documents(id)
) ENGINE=InnoDB;

CREATE TABLE maintenance_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    fulfilling_provider_id BIGINT UNSIGNED NOT NULL,
    frequency ENUM('quarterly', 'biannual', 'annual') NOT NULL,
    subscription_fee DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'paused', 'cancelled') NOT NULL DEFAULT 'active',
    next_service_due_at DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (booking_id) REFERENCES solar_bookings(id),
    FOREIGN KEY (fulfilling_provider_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE warranty_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    component ENUM('panels', 'inverter', 'battery', 'installation_labor') NOT NULL,
    warranty_provider ENUM('manufacturer', 'installer', 'platform_extended') NOT NULL,
    warranty_start_date DATE NOT NULL,
    warranty_end_date DATE NOT NULL,
    terms_document_url VARCHAR(500) NULL,
    FOREIGN KEY (booking_id) REFERENCES solar_bookings(id)
) ENGINE=InnoDB;

-- disputes.booking_id and reviews.booking_id both point at solar_bookings.id;
ALTER TABLE disputes ADD CONSTRAINT fk_disputes_booking FOREIGN KEY (booking_id) REFERENCES solar_bookings(id);
ALTER TABLE reviews ADD CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES solar_bookings(id);
ALTER TABLE escrow_transactions ADD CONSTRAINT fk_escrow_booking FOREIGN KEY (booking_id) REFERENCES solar_bookings(id);

-- ============================================================
-- E-COMMERCE STORE — shared shape across all five platforms
-- (see planning/00-portfolio/shared-architecture.md)
-- ============================================================

CREATE TABLE store_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id BIGINT UNSIGNED NULL,
    category VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    image_urls JSON NULL,
    status ENUM('active', 'out_of_stock', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE store_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    status ENUM('pending', 'paid', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_address VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE store_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES store_orders(id),
    FOREIGN KEY (product_id) REFERENCES store_products(id)
) ENGINE=InnoDB;

ALTER TABLE payments ADD CONSTRAINT fk_payments_store_order FOREIGN KEY (order_id) REFERENCES store_orders(id);
