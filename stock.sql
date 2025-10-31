-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Oct 31, 2025 at 11:30 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stock`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_recalc_purchase_total` (IN `p_purchase_id` INT)   BEGIN
  UPDATE purchases p
  JOIN (
    SELECT purchase_id, COALESCE(SUM(amount),0) AS tot
    FROM purchase_lines
    WHERE purchase_id = p_purchase_id
    GROUP BY purchase_id
  ) x ON x.purchase_id = p.id
  SET p.total_amount = x.tot
  WHERE p.id = p_purchase_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_recalc_sale_total` (IN `p_sale_id` INT)   BEGIN
  UPDATE sales s
  JOIN (
    SELECT sale_id, COALESCE(SUM(amount),0) AS tot
    FROM sale_lines
    WHERE sale_id = p_sale_id
    GROUP BY sale_id
  ) x ON x.sale_id = s.id
  SET s.total_amount = x.tot
  WHERE s.id = p_sale_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('customer','supplier','other') NOT NULL DEFAULT 'other',
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `name`, `type`, `phone`, `email`, `address`, `created_at`) VALUES
(18, 'GANESH MANE', 'supplier', '9842982451', '', '', '2025-09-20 16:16:04'),
(19, 'KIRTI GOLD PVT LTD LATUR.', 'customer', '9876543212', '', 'LATUR', '2025-09-21 12:14:52'),
(20, 'VAIBHAV SANGAVE', 'supplier', '900096733', '', '', '2025-09-22 08:19:33'),
(21, 'NITIN SANGAVE', 'supplier', '', '', '', '2025-09-22 10:51:39'),
(22, 'ANIKET PATIL', 'customer', '', '', 'Kolhapur', '2025-10-30 04:29:27'),
(23, 'Ram Charupe', 'supplier', '', '', 'Latur', '2025-10-30 04:30:06');

-- --------------------------------------------------------

--
-- Table structure for table `bill_settings`
--

CREATE TABLE `bill_settings` (
  `id` int(11) NOT NULL,
  `report_id` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `x_pos` int(11) DEFAULT 0,
  `y_pos` int(11) DEFAULT 0,
  `width` int(11) DEFAULT 0,
  `align` char(1) DEFAULT 'L',
  `size` int(11) DEFAULT 10,
  `field_type` varchar(20) DEFAULT 'Text',
  `decimals` int(11) DEFAULT 0,
  `active` char(1) DEFAULT 'Y',
  `bold` char(1) DEFAULT 'N',
  `color` varchar(20) DEFAULT 'Black'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_receipts`
--

CREATE TABLE `customer_receipts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `receipt_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_types`
--

CREATE TABLE `expense_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `home_expenses`
--

CREATE TABLE `home_expenses` (
  `id` int(11) NOT NULL,
  `expense_type_text` varchar(255) NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `paid_by` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `home_expenses`
--

INSERT INTO `home_expenses` (`id`, `expense_type_text`, `expense_date`, `amount`, `description`, `paid_by`, `created_at`) VALUES
(2, 'KIRANA', '2025-09-20', 2000.00, 'kirana sep month', 'cash', '2025-09-20 21:51:25');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `expense` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `purchase_rate` decimal(10,2) DEFAULT NULL,
  `sale_rate` decimal(10,2) DEFAULT NULL,
  `expenses` decimal(10,2) DEFAULT NULL,
  `stock` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `sku`, `name`, `unit`, `expense`, `created_at`, `description`, `purchase_rate`, `sale_rate`, `expenses`, `stock`) VALUES
(9, NULL, 'सोया', '', NULL, '2025-09-20 16:15:26', '', 0.00, 0.00, 0.00, 10.00),
(10, NULL, 'ला.तूर', '', NULL, '2025-09-22 10:51:00', '', 0.00, 0.00, 0.00, 4.00),
(11, NULL, 'चणा', '', NULL, '2025-09-23 06:24:00', '', 0.00, 0.00, 0.00, 4.00),
(12, NULL, 'गहू', '', NULL, '2025-09-23 06:24:09', '', 0.00, 0.00, 0.00, 0.00),
(13, NULL, 'तांदूळ', '', NULL, '2025-09-23 06:24:14', '', 0.00, 0.00, 0.00, 0.00),
(14, NULL, 'खपली', '', NULL, '2025-09-23 06:24:23', '', 0.00, 0.00, 0.00, 0.00),
(15, NULL, 'मूग', '', NULL, '2025-09-23 06:24:30', '', 0.00, 0.00, 0.00, 0.00),
(16, NULL, 'उडीद', '', NULL, '2025-09-23 06:24:38', '', 0.00, 0.00, 0.00, 3.00),
(17, NULL, 'पां.तूर', '', NULL, '2025-09-23 06:33:46', '', 0.00, 0.00, 0.00, 0.00),
(18, NULL, 'डागी.सोया', '', NULL, '2025-09-23 06:37:24', '', 0.00, 0.00, 0.00, 0.00),
(19, NULL, 'अडकन', '', NULL, '2025-09-23 06:37:49', '', 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `other_expenses`
--

CREATE TABLE `other_expenses` (
  `id` int(11) NOT NULL,
  `expense_type_text` varchar(255) NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `paid_by` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `purchase_date` date NOT NULL,
  `hamali` decimal(10,2) DEFAULT 0.00,
  `freight` decimal(10,2) DEFAULT 0.00,
  `bill_no` varchar(100) DEFAULT NULL,
  `total_amount` decimal(14,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `uchal` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `supplier_id`, `invoice_number`, `purchase_date`, `hamali`, `freight`, `bill_no`, `total_amount`, `created_at`, `uchal`) VALUES
(12, 18, '1', '2025-09-20', 120.00, 240.00, NULL, 33000.00, '2025-09-20 16:17:19', 240.00),
(13, 20, '2', '2025-09-22', 120.00, 240.00, NULL, 13640.00, '2025-09-22 08:20:10', 0.00),
(14, 21, '3', '2025-09-22', 80.00, 160.00, NULL, 8120.00, '2025-09-22 10:52:56', 0.00),
(15, 23, '4', '2025-10-30', 100.00, 500.00, NULL, 16320.00, '2025-10-30 04:30:57', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_details`
--

CREATE TABLE `purchase_details` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_details`
--

INSERT INTO `purchase_details` (`id`, `purchase_id`, `item_id`, `quantity`, `weight`, `rate`, `total`) VALUES
(32, 12, 9, 12, 600.00, 56.00, 33600.00),
(33, 13, 9, 5, 250.00, 56.00, 14000.00),
(34, 14, 10, 4, 23.00, 70.00, 1610.00),
(35, 14, 9, 3, 150.00, 45.00, 6750.00),
(36, 15, 11, 4, 200.00, 65.00, 13000.00),
(37, 15, 16, 3, 112.00, 35.00, 3920.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_lines`
--

CREATE TABLE `purchase_lines` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty` int(11) DEFAULT 0,
  `weight` decimal(14,3) DEFAULT 0.000,
  `rate` decimal(14,2) DEFAULT 0.00,
  `amount` decimal(14,2) GENERATED ALWAYS AS (`qty` * `rate` + `weight` * `rate`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `purchase_lines`
--
DELIMITER $$
CREATE TRIGGER `trg_purchase_lines_after_delete` AFTER DELETE ON `purchase_lines` FOR EACH ROW BEGIN
  UPDATE stock SET
    qty = GREATEST(0, qty - OLD.qty),
    weight = GREATEST(0, weight - OLD.weight)
  WHERE item_id = OLD.item_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_lines_after_insert` AFTER INSERT ON `purchase_lines` FOR EACH ROW BEGIN
  -- If stock row exists, add to it; otherwise insert
  INSERT INTO stock (item_id, qty, weight)
  VALUES (NEW.item_id, NEW.qty, NEW.weight)
  ON DUPLICATE KEY UPDATE
    qty = qty + NEW.qty,
    weight = weight + NEW.weight;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_lines_after_update` AFTER UPDATE ON `purchase_lines` FOR EACH ROW BEGIN
  -- If same item_id, apply difference
  IF NEW.item_id = OLD.item_id THEN
    UPDATE stock SET
      qty = GREATEST(0, qty + NEW.qty - OLD.qty),
      weight = GREATEST(0, weight + NEW.weight - OLD.weight)
    WHERE item_id = NEW.item_id;
  ELSE
    -- Different items: subtract from old, add to new
    UPDATE stock SET
      qty = GREATEST(0, qty - OLD.qty),
      weight = GREATEST(0, weight - OLD.weight)
    WHERE item_id = OLD.item_id;
    INSERT INTO stock (item_id, qty, weight)
      VALUES (NEW.item_id, NEW.qty, NEW.weight)
    ON DUPLICATE KEY UPDATE
      qty = qty + NEW.qty,
      weight = weight + NEW.weight;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `bill_no` varchar(100) DEFAULT NULL,
  `total_amount` decimal(14,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `invoice_number` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `hamali` decimal(10,2) DEFAULT 0.00,
  `freight` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `customer_id`, `bill_no`, `total_amount`, `created_at`, `invoice_number`, `date`, `hamali`, `freight`) VALUES
(5, 19, NULL, 6000.00, '2025-09-21 12:15:46', '1', '2025-09-21', 0.00, 0.00),
(6, 19, NULL, 24400.00, '2025-09-21 12:18:33', '2', '2025-09-21', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_details`
--

CREATE TABLE `sale_details` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_details`
--

INSERT INTO `sale_details` (`id`, `sale_id`, `item_id`, `quantity`, `weight`, `rate`, `total`) VALUES
(5, 5, 9, 2, 100.00, 60.00, 6000.00),
(6, 6, 9, 8, 400.00, 61.00, 24400.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_lines`
--

CREATE TABLE `sale_lines` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty` int(11) DEFAULT 0,
  `weight` decimal(14,3) DEFAULT 0.000,
  `rate` decimal(14,2) DEFAULT 0.00,
  `amount` decimal(14,2) GENERATED ALWAYS AS (`qty` * `rate` + `weight` * `rate`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `sale_lines`
--
DELIMITER $$
CREATE TRIGGER `trg_sale_lines_after_delete` AFTER DELETE ON `sale_lines` FOR EACH ROW BEGIN
  INSERT INTO stock (item_id, qty, weight)
  VALUES (OLD.item_id, OLD.qty, OLD.weight)
  ON DUPLICATE KEY UPDATE
    qty = qty + OLD.qty,
    weight = weight + OLD.weight;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_lines_after_insert` AFTER INSERT ON `sale_lines` FOR EACH ROW BEGIN
  UPDATE stock SET
    qty = GREATEST(0, qty - NEW.qty),
    weight = GREATEST(0, weight - NEW.weight)
  WHERE item_id = NEW.item_id;
  -- If no stock row exists, create with zero or negative-safe values (we keep zero floor)
  INSERT INTO stock (item_id, qty, weight)
  SELECT NEW.item_id, 0, 0.000
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM stock s WHERE s.item_id = NEW.item_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_lines_after_update` AFTER UPDATE ON `sale_lines` FOR EACH ROW BEGIN
  IF NEW.item_id = OLD.item_id THEN
    -- reduce by new, add back old => net change = OLD - NEW (because sales subtract)
    UPDATE stock SET
      qty = GREATEST(0, qty + OLD.qty - NEW.qty),
      weight = GREATEST(0, weight + OLD.weight - NEW.weight)
    WHERE item_id = NEW.item_id;
  ELSE
    -- different items: add back old to old item, subtract new from new item
    INSERT INTO stock (item_id, qty, weight)
      VALUES (OLD.item_id, OLD.qty, OLD.weight)
      ON DUPLICATE KEY UPDATE qty = qty + OLD.qty, weight = weight + OLD.weight;
    UPDATE stock SET
      qty = GREATEST(0, qty - NEW.qty),
      weight = GREATEST(0, weight - NEW.weight)
    WHERE item_id = NEW.item_id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `shop_expenses`
--

CREATE TABLE `shop_expenses` (
  `id` int(11) NOT NULL,
  `expense_type_text` varchar(100) DEFAULT NULL,
  `expense_type_id` int(11) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `paid_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_expenses`
--

INSERT INTO `shop_expenses` (`id`, `expense_type_text`, `expense_type_id`, `expense_date`, `amount`, `description`, `paid_by`, `created_at`) VALUES
(5, 'Hamali', NULL, '2025-09-20', 1111.00, '', 'cash', '2025-09-20 16:20:48');

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty` int(11) DEFAULT 0,
  `weight` decimal(14,3) DEFAULT 0.000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_payments`
--

INSERT INTO `supplier_payments` (`id`, `supplier_id`, `purchase_id`, `payment_date`, `amount`, `description`, `created_at`) VALUES
(7, 18, 12, '2025-09-20', 20000.00, 'By hand .....', '2025-09-20 16:19:05'),
(8, 23, 15, '2025-10-30', 16320.00, 'Automatic payment for purchase invoice #4', '2025-10-30 04:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_receipts`
--

CREATE TABLE `supplier_receipts` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'vtc', 'vtc5744', 'admin', '2025-09-19 12:15:55');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_item_movement`
-- (See below for the actual view)
--
CREATE TABLE `vw_item_movement` (
`item_id` int(11)
,`item_name` varchar(255)
,`purchased_qty` decimal(32,0)
,`purchased_weight` decimal(36,3)
,`sold_qty` decimal(32,0)
,`sold_weight` decimal(36,3)
,`stock_qty` int(11)
,`stock_weight` decimal(14,3)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_item_movement`
--
DROP TABLE IF EXISTS `vw_item_movement`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_item_movement`  AS SELECT `i`.`id` AS `item_id`, `i`.`name` AS `item_name`, coalesce(`pq`.`purchased_qty`,0) AS `purchased_qty`, coalesce(`pq`.`purchased_weight`,0.000) AS `purchased_weight`, coalesce(`sq`.`sold_qty`,0) AS `sold_qty`, coalesce(`sq`.`sold_weight`,0.000) AS `sold_weight`, coalesce(`st`.`qty`,0) AS `stock_qty`, coalesce(`st`.`weight`,0.000) AS `stock_weight` FROM (((`items` `i` left join (select `purchase_lines`.`item_id` AS `item_id`,sum(`purchase_lines`.`qty`) AS `purchased_qty`,sum(`purchase_lines`.`weight`) AS `purchased_weight` from `purchase_lines` group by `purchase_lines`.`item_id`) `pq` on(`pq`.`item_id` = `i`.`id`)) left join (select `sale_lines`.`item_id` AS `item_id`,sum(`sale_lines`.`qty`) AS `sold_qty`,sum(`sale_lines`.`weight`) AS `sold_weight` from `sale_lines` group by `sale_lines`.`item_id`) `sq` on(`sq`.`item_id` = `i`.`id`)) left join `stock` `st` on(`st`.`item_id` = `i`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_accounts_name_type` (`name`,`type`);

--
-- Indexes for table `bill_settings`
--
ALTER TABLE `bill_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_receipts`
--
ALTER TABLE `customer_receipts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_types`
--
ALTER TABLE `expense_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `home_expenses`
--
ALTER TABLE `home_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_items_sku` (`sku`),
  ADD KEY `idx_items_name` (`name`);

--
-- Indexes for table `other_expenses`
--
ALTER TABLE `other_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchases_supplier` (`supplier_id`);

--
-- Indexes for table `purchase_details`
--
ALTER TABLE `purchase_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_lines`
--
ALTER TABLE `purchase_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_lines_purchase` (`purchase_id`),
  ADD KEY `idx_purchase_lines_item` (`item_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_customer` (`customer_id`);

--
-- Indexes for table `sale_details`
--
ALTER TABLE `sale_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_lines`
--
ALTER TABLE `sale_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_lines_sale` (`sale_id`),
  ADD KEY `idx_sale_lines_item` (`item_id`);

--
-- Indexes for table `shop_expenses`
--
ALTER TABLE `shop_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expense_type_id` (`expense_type_id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_id` (`item_id`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_receipts`
--
ALTER TABLE `supplier_receipts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `bill_settings`
--
ALTER TABLE `bill_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_receipts`
--
ALTER TABLE `customer_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `expense_types`
--
ALTER TABLE `expense_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `home_expenses`
--
ALTER TABLE `home_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `other_expenses`
--
ALTER TABLE `other_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `purchase_details`
--
ALTER TABLE `purchase_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `purchase_lines`
--
ALTER TABLE `purchase_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sale_details`
--
ALTER TABLE `sale_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sale_lines`
--
ALTER TABLE `sale_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shop_expenses`
--
ALTER TABLE `shop_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `supplier_receipts`
--
ALTER TABLE `supplier_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `purchase_lines`
--
ALTER TABLE `purchase_lines`
  ADD CONSTRAINT `purchase_lines_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_lines_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `sale_lines`
--
ALTER TABLE `sale_lines`
  ADD CONSTRAINT `sale_lines_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_lines_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `shop_expenses`
--
ALTER TABLE `shop_expenses`
  ADD CONSTRAINT `shop_expenses_ibfk_1` FOREIGN KEY (`expense_type_id`) REFERENCES `expense_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
