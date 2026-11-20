USE `arb.wcite`;

CREATE TABLE IF NOT EXISTS `mahasiswa_profiles` (
  `Username` VARCHAR(50) NOT NULL,
  `NIK` VARCHAR(16) NULL,
  `NIM` VARCHAR(50) NULL,
  `FullName` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(254) NOT NULL,
  `Region` VARCHAR(100) NOT NULL DEFAULT '',
  `Country` VARCHAR(100) NOT NULL DEFAULT '',
  `Province` VARCHAR(100) NOT NULL DEFAULT '',
  `Regency` VARCHAR(100) NOT NULL DEFAULT '',
  `District` VARCHAR(100) NOT NULL DEFAULT '',
  `Village` VARCHAR(100) NOT NULL DEFAULT '',
  `Gender` VARCHAR(20) NOT NULL DEFAULT '',
  `BirthDate` DATE NULL,
  `PhotoPath` VARCHAR(255) NULL,
  `PublicDirectory` TINYINT(1) NOT NULL DEFAULT 0,
  `Latitude` DECIMAL(10,7) NULL,
  `Longitude` DECIMAL(10,7) NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Username`),
  UNIQUE KEY `uq_mahasiswa_profiles_email` (`Email`),
  KEY `idx_mahasiswa_profiles_nik` (`NIK`),
  KEY `idx_mahasiswa_profiles_nim` (`NIM`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `dosen_profiles` (
  `Username` VARCHAR(50) NOT NULL,
  `FullName` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(254) NOT NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Username`),
  UNIQUE KEY `uq_dosen_profiles_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `mahasiswa_profiles`
  (`Username`, `NIK`, `NIM`, `FullName`, `Email`, `Region`, `Country`, `Province`, `Regency`, `District`, `Village`, `Gender`, `BirthDate`, `PhotoPath`, `PublicDirectory`, `Latitude`, `Longitude`, `CreatedAt`)
SELECT profiles.`Username`, profiles.`NIK`, profiles.`NIM`, profiles.`FullName`, profiles.`Email`,
       profiles.`Region`, profiles.`Country`, profiles.`Province`, profiles.`Regency`,
       profiles.`District`, profiles.`Village`, profiles.`Gender`, profiles.`BirthDate`,
       profiles.`PhotoPath`, profiles.`PublicDirectory`, profiles.`Latitude`,
       profiles.`Longitude`, profiles.`CreatedAt`
FROM `account_profiles` AS profiles
INNER JOIN `login` ON `login`.`Username` = profiles.`Username`
WHERE `login`.`Role` = 'mahasiswa';

INSERT IGNORE INTO `dosen_profiles` (`Username`, `FullName`, `Email`, `CreatedAt`)
SELECT profiles.`Username`, profiles.`FullName`, profiles.`Email`, profiles.`CreatedAt`
FROM `account_profiles` AS profiles
INNER JOIN `login` ON `login`.`Username` = profiles.`Username`
WHERE `login`.`Role` = 'dosen';
