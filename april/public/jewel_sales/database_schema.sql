-- ============================================================
-- Jewelry Sales Management System - Database Schema
-- Converted from PowerBuilder w_sales window
-- ============================================================

CREATE DATABASE IF NOT EXISTS jewel_sales CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jewel_sales;

-- ============================================================
-- MASTER TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS salesm (
    slno INT PRIMARY KEY,
    billno VARCHAR(30) NOT NULL,
    billdate DATE NOT NULL,
    custcode VARCHAR(20) DEFAULT '',
    custname VARCHAR(100) DEFAULT '',
    custaddr VARCHAR(200) DEFAULT '',
    custphone VARCHAR(20) DEFAULT '',
    custgstin VARCHAR(20) DEFAULT '',
    statecd VARCHAR(5) DEFAULT '',
    eb CHAR(1) DEFAULT 'E' COMMENT 'E=Estimate, B=Bill',
    billtype VARCHAR(10) DEFAULT '',
    salestype VARCHAR(10) DEFAULT '',
    salesman VARCHAR(20) DEFAULT '',
    counter VARCHAR(10) DEFAULT '',
    scheme VARCHAR(10) DEFAULT '',
    remark VARCHAR(200) DEFAULT '',
    totalamt DECIMAL(15,2) DEFAULT 0,
    discount DECIMAL(15,2) DEFAULT 0,
    discperc DECIMAL(8,2) DEFAULT 0,
    taxamt DECIMAL(15,2) DEFAULT 0,
    sgst DECIMAL(15,2) DEFAULT 0,
    cgst DECIMAL(15,2) DEFAULT 0,
    igst DECIMAL(15,2) DEFAULT 0,
    cess DECIMAL(15,2) DEFAULT 0,
    tcs DECIMAL(15,2) DEFAULT 0,
    addless DECIMAL(15,2) DEFAULT 0,
    netamt DECIMAL(15,2) DEFAULT 0,
    exchgoldwt DECIMAL(10,3) DEFAULT 0,
    exchgoldamt DECIMAL(15,2) DEFAULT 0,
    exchgoldtouch DECIMAL(8,2) DEFAULT 0,
    exchgoldrate DECIMAL(12,2) DEFAULT 0,
    exchsilverwt DECIMAL(10,3) DEFAULT 0,
    exchsilveramt DECIMAL(15,2) DEFAULT 0,
    exchsilvertouch DECIMAL(8,2) DEFAULT 0,
    exchsilverrate DECIMAL(12,2) DEFAULT 0,
    excholdamt DECIMAL(15,2) DEFAULT 0,
    sreturnamt DECIMAL(15,2) DEFAULT 0,
    advance DECIMAL(15,2) DEFAULT 0,
    roundoff DECIMAL(10,2) DEFAULT 0,
    grandtotal DECIMAL(15,2) DEFAULT 0,
    cashamt DECIMAL(15,2) DEFAULT 0,
    bankamt DECIMAL(15,2) DEFAULT 0,
    cardamt DECIMAL(15,2) DEFAULT 0,
    chequeamt DECIMAL(15,2) DEFAULT 0,
    pdcamt DECIMAL(15,2) DEFAULT 0,
    walletamt DECIMAL(15,2) DEFAULT 0,
    onlineamt DECIMAL(15,2) DEFAULT 0,
    bankcharge DECIMAL(15,2) DEFAULT 0,
    pandession VARCHAR(50) DEFAULT '',
    stktype VARCHAR(20) DEFAULT '',
    refno INT DEFAULT 0,
    userid VARCHAR(20) DEFAULT '',
    edituser VARCHAR(20) DEFAULT '',
    entrydate DATETIME DEFAULT CURRENT_TIMESTAMP,
    editdate DATETIME NULL,
    status CHAR(1) DEFAULT 'A' COMMENT 'A=Active, C=Cancelled',
    INDEX idx_billno (billno),
    INDEX idx_billdate (billdate),
    INDEX idx_custcode (custcode),
    INDEX idx_eb (eb)
);

CREATE TABLE IF NOT EXISTS salesd (
    slno INT NOT NULL,
    srlno INT NOT NULL,
    icode VARCHAR(20) DEFAULT '',
    iname VARCHAR(100) DEFAULT '',
    bcode INT DEFAULT 0,
    qty INT DEFAULT 1,
    grosswgt DECIMAL(10,3) DEFAULT 0,
    stonewgt DECIMAL(10,3) DEFAULT 0,
    netwgt DECIMAL(10,3) DEFAULT 0,
    wastage DECIMAL(8,2) DEFAULT 0,
    wastagewgt DECIMAL(10,3) DEFAULT 0,
    totalwgt DECIMAL(10,3) DEFAULT 0,
    rate DECIMAL(12,2) DEFAULT 0,
    mc DECIMAL(12,2) DEFAULT 0,
    mcrate DECIMAL(12,2) DEFAULT 0,
    stprice DECIMAL(12,2) DEFAULT 0,
    vap DECIMAL(8,2) DEFAULT 0,
    vaamt DECIMAL(12,2) DEFAULT 0,
    taxperc DECIMAL(8,2) DEFAULT 0,
    taxamt DECIMAL(12,2) DEFAULT 0,
    sgst DECIMAL(12,2) DEFAULT 0,
    cgst DECIMAL(12,2) DEFAULT 0,
    igst DECIMAL(12,2) DEFAULT 0,
    amount DECIMAL(15,2) DEFAULT 0,
    qtype CHAR(5) DEFAULT 'G',
    touch DECIMAL(8,2) DEFAULT 0,
    cost DECIMAL(15,2) DEFAULT 0,
    costmc DECIMAL(12,2) DEFAULT 0,
    dmdamt DECIMAL(12,2) DEFAULT 0,
    dmdwgt DECIMAL(10,3) DEFAULT 0,
    model VARCHAR(30) DEFAULT '',
    huid VARCHAR(30) DEFAULT '',
    hsncode VARCHAR(20) DEFAULT '',
    part VARCHAR(20) DEFAULT '',
    stkinnos CHAR(1) DEFAULT 'N',
    stktype VARCHAR(20) DEFAULT '',
    PRIMARY KEY (slno, srlno),
    INDEX idx_icode (icode),
    INDEX idx_bcode (bcode)
);

CREATE TABLE IF NOT EXISTS salesexch (
    slno INT NOT NULL,
    srlno INT NOT NULL,
    itype CHAR(1) DEFAULT 'G',
    weight DECIMAL(10,3) DEFAULT 0,
    touch DECIMAL(8,2) DEFAULT 0,
    rate DECIMAL(12,2) DEFAULT 0,
    amount DECIMAL(15,2) DEFAULT 0,
    icode VARCHAR(20) DEFAULT '',
    remark VARCHAR(100) DEFAULT '',
    PRIMARY KEY (slno, srlno)
);

CREATE TABLE IF NOT EXISTS salesrd (
    mslno INT NOT NULL,
    srlno INT NOT NULL,
    icode VARCHAR(20) DEFAULT '',
    qty INT DEFAULT 1,
    weight DECIMAL(10,3) DEFAULT 0,
    rate DECIMAL(12,2) DEFAULT 0,
    amount DECIMAL(15,2) DEFAULT 0,
    refbillno VARCHAR(30) DEFAULT '',
    taxperc DECIMAL(8,2) DEFAULT 0,
    taxamt DECIMAL(12,2) DEFAULT 0,
    PRIMARY KEY (mslno, srlno)
);

-- ============================================================
-- INVENTORY TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS items (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    itype VARCHAR(10) DEFAULT 'G' COMMENT 'G=Gold, S=Silver, P=Platinum, D=Diamond',
    hsncode VARCHAR(20) DEFAULT '',
    taxinternal DECIMAL(8,2) DEFAULT 3,
    defquality VARCHAR(5) DEFAULT '',
    vaoffer DECIMAL(8,2) DEFAULT 0,
    groupcode VARCHAR(20) DEFAULT '',
    category VARCHAR(20) DEFAULT '',
    INDEX idx_name (name),
    INDEX idx_itype (itype)
);

CREATE TABLE IF NOT EXISTS itemsstk (
    code VARCHAR(20) NOT NULL,
    stktype VARCHAR(20) NOT NULL DEFAULT 'MAIN',
    qty INT DEFAULT 0,
    qtyb INT DEFAULT 0,
    weight DECIMAL(12,3) DEFAULT 0,
    weightb DECIMAL(12,3) DEFAULT 0,
    stonewgt DECIMAL(12,3) DEFAULT 0,
    stonewgtb DECIMAL(12,3) DEFAULT 0,
    wac DECIMAL(12,2) DEFAULT 0,
    PRIMARY KEY (code, stktype)
);

CREATE TABLE IF NOT EXISTS barcode (
    bcode INT PRIMARY KEY,
    icode VARCHAR(20) NOT NULL,
    qty INT DEFAULT 1,
    weight DECIMAL(10,3) DEFAULT 0,
    stweight DECIMAL(10,3) DEFAULT 0,
    stprice DECIMAL(12,2) DEFAULT 0,
    wastage DECIMAL(8,2) DEFAULT 0,
    mcrate DECIMAL(12,2) DEFAULT 0,
    mc DECIMAL(12,2) DEFAULT 0,
    rate DECIMAL(12,2) DEFAULT 0,
    vap DECIMAL(8,2) DEFAULT 0,
    stk CHAR(1) DEFAULT 'Y' COMMENT 'Y=In stock, S=Sold, N=Not available',
    tamt DECIMAL(15,2) DEFAULT 0,
    stkinnos CHAR(1) DEFAULT 'N',
    part VARCHAR(20) DEFAULT '',
    qtype CHAR(5) DEFAULT 'G',
    stktouch DECIMAL(8,2) DEFAULT 0,
    cost DECIMAL(15,2) DEFAULT 0,
    transtouch DECIMAL(8,2) DEFAULT 0,
    smithcode VARCHAR(20) DEFAULT '',
    costmc DECIMAL(12,2) DEFAULT 0,
    costperc DECIMAL(8,2) DEFAULT 0,
    model VARCHAR(30) DEFAULT '',
    huid VARCHAR(30) DEFAULT '',
    salesslno INT DEFAULT 0,
    salesdate DATE NULL,
    purdate DATE NULL,
    INDEX idx_icode (icode),
    INDEX idx_stk (stk)
);

CREATE TABLE IF NOT EXISTS barcodedmd (
    bcode INT PRIMARY KEY,
    dmdamt DECIMAL(12,2) DEFAULT 0,
    dmdwgt DECIMAL(10,3) DEFAULT 0,
    dmdunit VARCHAR(10) DEFAULT '',
    dmdnos INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS mctable (
    code VARCHAR(20) NOT NULL,
    weight1 DECIMAL(10,3) DEFAULT 0,
    weight2 DECIMAL(10,3) DEFAULT 0,
    mcperqty DECIMAL(12,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS wstg (
    llimit DECIMAL(10,3) DEFAULT 0,
    ulimit DECIMAL(10,3) DEFAULT 0,
    wastage DECIMAL(8,2) DEFAULT 0
);

-- ============================================================
-- ACCOUNTING TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS daybook (
    slno INT PRIMARY KEY AUTO_INCREMENT,
    accode VARCHAR(20) NOT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    ddate DATE NOT NULL,
    narration VARCHAR(200) DEFAULT '',
    vtype VARCHAR(5) DEFAULT '',
    vno INT DEFAULT 0,
    userid VARCHAR(20) DEFAULT '',
    INDEX idx_accode (accode),
    INDEX idx_ddate (ddate),
    INDEX idx_vtype_vno (vtype, vno)
);

-- ============================================================
-- CUSTOMER & MASTER TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS clients (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT '',
    address VARCHAR(200) DEFAULT '',
    place VARCHAR(50) DEFAULT '',
    gstin VARCHAR(20) DEFAULT '',
    pan VARCHAR(15) DEFAULT '',
    statecd VARCHAR(5) DEFAULT '',
    creditlimit DECIMAL(15,2) DEFAULT 0,
    points INT DEFAULT 0,
    INDEX idx_name (name),
    INDEX idx_phone (phone)
);

CREATE TABLE IF NOT EXISTS salesman (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS counter (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS scheme (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS salestype (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    prefix VARCHAR(10) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS states (
    code VARCHAR(5) PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

-- ============================================================
-- CONFIGURATION TABLES (replaces PB generali/generals/generald)
-- ============================================================

CREATE TABLE IF NOT EXISTS generali (
    code VARCHAR(30) PRIMARY KEY,
    cvalue INT DEFAULT 0,
    description VARCHAR(100) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS generals (
    code VARCHAR(30) PRIMARY KEY,
    cvalue VARCHAR(200) DEFAULT '',
    description VARCHAR(100) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS generald (
    code VARCHAR(30) PRIMARY KEY,
    cvalue DECIMAL(15,4) DEFAULT 0,
    description VARCHAR(100) DEFAULT ''
);

-- ============================================================
-- USER TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS userm (
    code VARCHAR(20) PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    password VARCHAR(100) DEFAULT '',
    level INT DEFAULT 1,
    incharge VARCHAR(20) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS userd (
    code VARCHAR(20) NOT NULL,
    menuitem VARCHAR(50) NOT NULL,
    PRIMARY KEY (code, menuitem)
);

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT IGNORE INTO generali (code, cvalue, description) VALUES
('SALESE', 0, 'Sales Estimate counter'),
('SALESB', 0, 'Sales Bill counter'),
('TSALESE', 0, 'Short Sales Estimate counter'),
('TSALESB', 0, 'Short Sales Bill counter'),
('QTNNO', 0, 'Quotation number counter');

INSERT IGNORE INTO generals (code, cvalue, description) VALUES
('SBPREF', 'SLB/', 'Sales Bill prefix'),
('SEPREF', 'SLE/', 'Sales Estimate prefix'),
('SBLEN', '5', 'Sales Bill number length'),
('SELEN', '5', 'Sales Estimate number length'),
('SALESAC', 'SALES', 'Sales account code'),
('CASHAC', 'CASH', 'Cash account code'),
('BANKAC', 'BANK', 'Bank account code'),
('CARDAC', 'CARD', 'Card account code'),
('SGSTAC', 'SGST', 'SGST output account'),
('CGSTAC', 'CGST', 'CGST output account'),
('IGSTAC', 'IGST', 'IGST output account'),
('TCSAC', 'TCS', 'TCS account'),
('EXCHAC', 'EXCHANGE', 'Exchange account'),
('SRETAC', 'SALESRETURN', 'Sales return account'),
('ROAC', 'ROUNDOFF', 'Round off account'),
('DISCAC', 'DISCOUNT', 'Discount account');

INSERT IGNORE INTO userm (code, name, password, level) VALUES
('ADMIN', 'Administrator', 'admin', 1);

-- Sample states (Indian)
INSERT IGNORE INTO states (code, name) VALUES
('32', 'Kerala'), ('33', 'Tamil Nadu'), ('29', 'Karnataka'),
('27', 'Maharashtra'), ('36', 'Telangana'), ('28', 'Andhra Pradesh'),
('07', 'Delhi'), ('09', 'Uttar Pradesh'), ('06', 'Haryana');
