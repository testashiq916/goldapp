-- ------------------------------------------------------------
-- Table structure for table `daybook`
-- FIXED: Removed DEFAULT curdate() — not supported on MySQL < 8.0.13
-- ------------------------------------------------------------

CREATE TABLE `daybook` (
  `slno` decimal(10,0) NOT NULL,
  `tdate` date DEFAULT NULL,
  `accode` char(8) DEFAULT NULL,
  `amount` decimal(11,2) DEFAULT 0.00,
  `control` smallint(6) DEFAULT 1,
  `sno` smallint(6) DEFAULT NULL,
  `opaccode` varchar(10) DEFAULT NULL,
  `note` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
