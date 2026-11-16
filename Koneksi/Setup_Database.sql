CREATE DATABASE IF NOT EXISTS `arb.wcite`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `arb.wcite`;

CREATE TABLE IF NOT EXISTS `login` (
  `Username` VARCHAR(50) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`Username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `account_profiles` (
  `Username` VARCHAR(50) NOT NULL,
  `FullName` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(254) NOT NULL,
  `Region` VARCHAR(100) NOT NULL DEFAULT '',
  `Country` VARCHAR(100) NOT NULL DEFAULT '',
  `Latitude` DECIMAL(10,7) NULL,
  `Longitude` DECIMAL(10,7) NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Username`),
  UNIQUE KEY `uq_account_profiles_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `account_profiles`
  ADD COLUMN IF NOT EXISTS `Region` VARCHAR(100) NOT NULL DEFAULT '' AFTER `Email`;

ALTER TABLE `account_profiles`
  ADD COLUMN IF NOT EXISTS `Country` VARCHAR(100) NOT NULL DEFAULT '' AFTER `Region`;
