CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent` int(11) DEFAULT '0',
  `token` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `target` ENUM('INTERNAL','EXTERNAL') NOT NULL DEFAULT 'INTERNAL',
  `content` longtext NULL,
  `url` VARCHAR(255) NULL,
  `publish` enum('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  `sort` smallint(6) DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idToChild` (`parent`),
  CONSTRAINT `idToChild` FOREIGN KEY (`parent`) REFERENCES `pages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE  TABLE `redirects` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `incomingPath` VARCHAR(1024) NOT NULL ,
  `outgoingPath` VARCHAR(1024) NOT NULL ,
  `type` ENUM('300','301','302','303','305','307') NOT NULL DEFAULT '301' ,
    `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`) ,
  INDEX `incoming` (`incomingPath` ASC) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE TABLE `locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(100) DEFAULT NULL,
  `address2` varchar(100) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(45) NOT NULL,
  `zipCode` varchar(45) DEFAULT NULL,
  `country` char(2) NOT NULL,
  `latitudeDegrees` decimal(15,11) DEFAULT NULL,
  `longitudeDegrees` decimal(15,11) DEFAULT NULL,
  `latitudeDegreesTopRight` decimal(15,11) DEFAULT NULL,
  `longitudeDegreesTopRight` decimal(15,11) DEFAULT NULL,
  `latitudeDegreesBottomLeft` decimal(15,11) DEFAULT NULL,
  `longitudeDegreesBottomLeft` decimal(15,11) DEFAULT NULL,
  `latitudeDegreesBottomRight` decimal(15,11) DEFAULT NULL,
  `longitudeDegreesBottomRight` decimal(15,11) DEFAULT NULL,
  `dayOfWeek` smallint(1) NOT NULL DEFAULT '0',
  `timeStart` time NOT NULL,
  `timeEnd` time NOT NULL,
  `pricePerDelivery` decimal(5,2) DEFAULT NULL,
  `details` longtext,
  `memberLimit` smallint(5) unsigned DEFAULT NULL,
  `enabled` smallint(1) NOT NULL DEFAULT '1',
  `addOnCutOffTime` VARCHAR(6) NULL DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `shares` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `year` year(4) NOT NULL,
  `name` varchar(255) NOT NULL,
  `deliverySchedule` enum('WEEKLY','SEMI_MONTHLY','MONTHLY') NOT NULL DEFAULT 'WEEKLY',
  `purchaseStartDate` date NOT NULL,
  customerPurchaseStartDate DATE NULL,
  `locationPrice` SMALLINT(1) NOT NULL DEFAULT 1,
  `details` longtext,
  `image` varchar(255) DEFAULT NULL,
  `enabled` smallint(1) unsigned NOT NULL DEFAULT '1',
  `order` INT(10) NOT NULL DEFAULT 100,
  `planFixedDates` VARCHAR(255) DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sharesDurations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shareId` int(10) unsigned NOT NULL,
  `startWeek` tinyint(3) unsigned NOT NULL,
  `fullPaymentDueDate` DATE NULL DEFAULT NULL,
  `iterations` tinyint(3) unsigned NOT NULL,
  `cutOffWeek` tinyint(3) unsigned,
  `shares` smallint(6) NOT NULL DEFAULT '0',
  `sort` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`,`shareId`),
  KEY `shareToDurations` (`shareId`),
  CONSTRAINT `shareToDurations` FOREIGN KEY (`shareId`) REFERENCES `shares` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sharesDurationsLocations` (
  `shareId` int(10) unsigned NOT NULL,
  `shareDurationId` int(10) unsigned NOT NULL,
  `locationId` int(10) unsigned NOT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  KEY `shareDuration` (`shareId`),
  KEY `locationToDuration` (`locationId`),
  KEY `shareDurationToLocation` (`shareId`,`shareDurationId`),
  CONSTRAINT `locationToDuration` FOREIGN KEY (`locationId`) REFERENCES `locations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `shareDurationToLocation` FOREIGN KEY (`shareId`, `shareDurationId`) REFERENCES `sharesDurations` (`shareId`, `id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sharesSizes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shareId` int(10) unsigned NOT NULL,
  `size` decimal(4,2) NOT NULL DEFAULT '1.00',
  `name` varchar(255) NOT NULL,
  `details` longtext,
  `pricePerDelivery` decimal(6,2) unsigned NOT NULL DEFAULT '0.00',
  `fullPaymentDiscount` decimal(6,2) unsigned NOT NULL DEFAULT '0.00',
  `limitToShareDurationId` INT(10) UNSIGNED NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`,`shareId`),
  KEY `sizeToShare` (`shareId`),
  KEY `sizeToDuration` (`limitToShareDurationId` ASC),
  CONSTRAINT `sizeToShare` FOREIGN KEY (`shareId`) REFERENCES `shares` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sizeToDuration` FOREIGN KEY (`limitToShareDurationId` ) REFERENCES `sharesDurations` (`id` ) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `addonsCategories` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `image` VARCHAR(255) NULL,
  `addedDatetime` DATETIME NOT NULL,
  `updatedDatetime` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8;

INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Artisan-Products', 'Artisan Products', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Bread--Pastries', 'Bread & Pastries', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Dairy', 'Dairy', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Dry-Goods', 'Dry-Goods', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Fruit', 'Fruit', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Herbs', 'Herbs', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Meat-Eggs--Tofu', 'Meat, Eggs & Tofu', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Other-Goodies', 'Other Goodies', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Prepared-Foods', 'Prepared Foods', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Vegetables', 'Vegetables', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Meals-for-Here-or-To-Go', 'Meals for Here or To-Go', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Health-Products', 'Health Products', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Pantry--Preserves', 'Pantry & Preserves', null, now(), now());
INSERT INTO addonsCategories (id, name, image, addedDatetime, updatedDatetime) VALUES ('Beverages', 'Beverages', null, now(), now());

CREATE TABLE `addons` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `details` longtext,
  `inventory` DECIMAL(10,2) UNSIGNED NULL DEFAULT '0',
  `inventoryMinimumAlert` DECIMAL(10,2) NULL DEFAULT NULL,
  `price` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT '0',
  `priceBy` ENUM('UNIT','WEIGHT') NOT NULL DEFAULT 'UNIT',
  `pendingOnOrder` TINYINT(1) NOT NULL DEFAULT 0,
  `unitType` ENUM('UNIT','OZ','LB','G','KG') NOT NULL DEFAULT 'UNIT',
  `unitOrderMinimum` DECIMAL(6,2) NULL,
  `image` varchar(255) DEFAULT NULL,
  `enabled` smallint(1) unsigned NOT NULL DEFAULT '1',
  `categoryId` varchar(255) NOT NULL default 'Other-Goodies',
  `certification` VARCHAR(255) NULL DEFAULT NULL,
  `source` VARCHAR(255) NULL DEFAULT NULL,
  `distributorId` INT(10) UNSIGNED NULL DEFAULT NULL,
  `vendorId` INT(10) UNSIGNED NULL DEFAULT NULL,
  `externalId` INT(11) UNSIGNED NULL DEFAULT NULL,
  `expirationDate` DATE NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `available` (`enabled` ASC, `inventory` ASC, `expirationDate` ASC, `source` ASC),
  INDEX `fk_addons_1_idx1` (`categoryId` ASC),
  CONSTRAINT `fk_addons_1`
    FOREIGN KEY (`categoryId`)
    REFERENCES `addonsCategories` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `details` longtext,
  `categories` set('ADDONS','SHARES','BLOG','WEBSITE') DEFAULT NULL,
  `mimeType` varchar(255) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `customers` (
  `id` INT(10) unsigned NOT NULL AUTO_INCREMENT,
  `farmerId` INT(10) UNSIGNED DEFAULT NULL,
  `firstName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(45) NOT NULL,
  `zipCode` varchar(45) DEFAULT NULL,
  `country` char(2) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `secondaryEmail` varchar(255) DEFAULT NULL,
  `secondaryFirstName` varchar(100) DEFAULT NULL,
  `secondaryLastName` varchar(100) DEFAULT NULL,
  `enabled` smallint(1) NOT NULL DEFAULT '1',
  `balance` FLOAT(8,2) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `deliveries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shareId` int(10) unsigned NOT NULL,
  `week` char(7) NOT NULL,
  `enabled` smallint(1) unsigned NOT NULL DEFAULT '1',
  `facebookId` varchar(255) NULL,
  `twitterId` varchar(255) NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `deliveryToShare` (`shareId`),
  KEY `week` (`week`),
  CONSTRAINT `deliveryToShare` FOREIGN KEY (`shareId`) REFERENCES `shares` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE  TABLE `deliveriesItems` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `deliveryId` INT UNSIGNED NOT NULL ,
  `item` VARCHAR(255) NOT NULL ,
  `source` VARCHAR(255) NULL ,
  `certification` ENUM('ORGANIC', 'NATURAL', 'CERTIFIED_NATURAL', 'TRANSITIONAL', 'CONVENTIONAL', 'BIODYNAMIC', 'GRASS', 'SPRAY_FREE') NOT NULL DEFAULT 'ORGANIC' ,
  quantity_1 VARCHAR(45) NOT NULL ,
  quantity_0_5 VARCHAR(45) NULL ,
  quantity_2 VARCHAR(45) NULL ,
  `addedDatetime` DATETIME NOT NULL,
  `updatedDatetime` DATETIME NOT NULL,
  PRIMARY KEY (`id`) ,
  INDEX `itemsToDeliveries` (deliveryId) ,
  CONSTRAINT `itemsToDeliveries`
    FOREIGN KEY (`deliveryId`)
    REFERENCES `deliveries` (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE TABLE `preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('CUSTOMER','FARM','FARMER') NOT NULL,
  `resource` varchar(50) NOT NULL DEFAULT 'default',
  `key` varchar(50) NOT NULL,
  `value` text,
  `farmerId` int(10) unsigned DEFAULT NULL,
  `customerId` int(10) unsigned DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`,`type`,`resource`,`key`),
  KEY `customer` (`customerId`),
  KEY `farmer` (`farmerId`),
  KEY `preferenceToCustomer` (`customerId`),
  CONSTRAINT `preferenceToCustomer` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE  TABLE `customersShares` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `customerId` INT(10) UNSIGNED NOT NULL ,
  `shareId` INT(10) UNSIGNED NOT NULL ,
  `shareDurationId` INT(10) UNSIGNED NOT NULL ,
  `shareSizeId` INT(10) UNSIGNED NOT NULL ,
  `locationId` INT(10) UNSIGNED NOT NULL ,
  `quantity` TINYINT NOT NULL DEFAULT 1 ,
  `year` YEAR NOT NULL ,
  `startWeek` CHAR(7) NOT NULL,
  `startDate` DATE NOT NULL,
  `endWeek` CHAR(7) NOT NULL,
  `endDate` DATE NOT NULL,
  `payment` enum('CASH','PAYPAL') NOT NULL DEFAULT 'CASH',
  `paymentPlan` enum('NONE','WEEKLY','MONTHLY','FIXED') NOT NULL DEFAULT 'NONE',
  `paidInFull` TINYINT(2) NOT NULL DEFAULT 0,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`, `customerId`, `shareId`, `shareDurationId`, `shareSizeId`, `locationId`) ,
  INDEX `sharesToCustomer` (`customerId` ASC) ,
  INDEX `customersToShare` (`shareId` ASC) ,
  INDEX `customersToShareDurations` (`shareDurationId` ASC) ,
  INDEX `customersToShareSizes` (`shareSizeId` ASC) ,
  INDEX `customersToLocations` (`locationId` ASC) ,
  CONSTRAINT `sharesToCustomer`
    FOREIGN KEY (`customerId` )
    REFERENCES `customers` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `customersToShare`
    FOREIGN KEY (`shareId` )
    REFERENCES `shares` (`id` )
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `customersToShareDurations`
    FOREIGN KEY (`shareDurationId` )
    REFERENCES `sharesDurations` (`id` )
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `customersToShareSizes`
    FOREIGN KEY (`shareSizeId` )
    REFERENCES `sharesSizes` (`id` )
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `customersToLocations`
    FOREIGN KEY (`locationId` )
    REFERENCES `locations` (`id` )
    ON DELETE RESTRICT
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE TABLE `customersInvoices` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  `customerId` INT(10) UNSIGNED NOT NULL ,
  `type` ENUM('SHARES','ADDONS','MISC') NOT NULL DEFAULT 'MISC' ,
  `pending` TINYINT(1) NOT NULL DEFAULT 0,
  `dueDate` DATE NOT NULL ,
  `subTotal` DECIMAL(8,2) NOT NULL DEFAULT 0.00 ,
  `tax` DECIMAL(6,2) NULL DEFAULT NULL ,
  `total` DECIMAL(8,2) NOT NULL DEFAULT 0.00 ,
  `paid` TINYINT(1) NOT NULL DEFAULT 0 ,
  `outstandingAmount` DECIMAL(8,2) NOT NULL DEFAULT 0.00 ,
  `message` LONGTEXT NULL DEFAULT NULL ,
  `appliedToBalance` TINYINT(1) NOT NULL DEFAULT 0,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`) ,
  INDEX `invoiceToCustomer` (`customerId` ASC) ,
  CONSTRAINT `invoiceToCustomer`
    FOREIGN KEY (`customerId` )
    REFERENCES `customers` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE  TABLE `customersInvoicesLines` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  `customerInvoiceId` INT(10) UNSIGNED NOT NULL ,
  `type` ENUM('SHARE','ADDON','DELIVERY','ADMINISTRATION','MISC') NOT NULL DEFAULT 'MISC' ,
  `referenceId` INT(10) UNSIGNED NULL DEFAULT NULL ,
  `description` VARCHAR(255) NULL DEFAULT NULL ,
  `unitPrice` DECIMAL(8,2) NOT NULL DEFAULT 0.00 ,
  `quantity` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 1 ,
  `total` DECIMAL(8,2) NOT NULL DEFAULT 0.00 ,
  `taxable` TINYINT(1) NOT NULL DEFAULT 0 ,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`) ,
  INDEX `customersInvoicesToLines` (`customerInvoiceId` ASC) ,
  INDEX `customerOrderXRef` (`type` ASC, `referenceId` ASC) ,
  CONSTRAINT `customersInvoicesToLines`
    FOREIGN KEY (`customerInvoiceId` )
    REFERENCES `customersInvoices` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE TABLE `customersAddons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customerId` int(10) unsigned NOT NULL,
  `addonId` int(11) unsigned NOT NULL,
  `quantity` DECIMAL(10,2) UNSIGNED NOT NULL,
  `week` char(7) NOT NULL,
  `payment` enum('CASH','PAYPAL') NOT NULL DEFAULT 'CASH',
  `paidInFull` TINYINT(2) NOT NULL DEFAULT 0,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`,`customerId`,`addonId`),
  KEY `addonToCustomer` (`customerId`),
  KEY `customerOrderToAddon` (`addonId`),
  CONSTRAINT `addonToCustomer` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `customerOrderToAddon` FOREIGN KEY (`addonId`) REFERENCES `addons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE  TABLE `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag` VARCHAR(150) NOT NULL ,
  `token` VARCHAR(150) NOT NULL ,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE TABLE `tagsRelationships` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tagId` int(10) unsigned NOT NULL,
  `type` enum('POST','CUSTOMER') NOT NULL,
  `typeId` int(11) NOT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subprime` (`tagId`,`type`,`typeId`),
  KEY `fktagRelationshipsTagId` (`tagId`),
  KEY `type` (`type`,`typeId`),
  CONSTRAINT `fktagRelationshipsTagId` FOREIGN KEY (`tagId`) REFERENCES `tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

CREATE  TABLE `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `token` VARCHAR(255) NOT NULL ,
  `title` VARCHAR(255) NOT NULL ,
  `content` LONGTEXT NOT NULL ,
  `publish` ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT' ,
  `publishedDatetime` DATETIME NULL ,
  `category` VARCHAR(255) NOT NULL DEFAULT 'Uncategorized' ,
  `categoryToken` VARCHAR(255) NOT NULL DEFAULT 'uncategorized',
  `farmerId` int(10) NOT NULL,
  `farmerRole` enum('FARMER','MEMBER') NOT NULL DEFAULT 'FARMER',
  `facebookId` varchar(255) NULL,
  `twitterId` varchar(255) NULL,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `categories` (`categoryToken` ASC),
  KEY `farmer` (`farmerId`,`farmerRole`),
  KEY `token` (`token`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE  TABLE `postsComments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `postId` INT(10) UNSIGNED NOT NULL ,
  `content` LONGTEXT NOT NULL ,
  `farmerId` INT(10) NOT NULL ,
  `farmerRole` ENUM('FARMER','MEMBER') NOT NULL DEFAULT 'MEMBER' ,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `fk_postsComments_1` (`postId` ASC) ,
  CONSTRAINT `fk_postsComments_1`
    FOREIGN KEY (`postId` )
    REFERENCES `posts` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE  TABLE `links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `name` VARCHAR(30) NOT NULL ,
  `description` VARCHAR(255) NULL ,
  `url` VARCHAR(254) NOT NULL ,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE TABLE `transactions` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  `invoiceId` INT(10) UNSIGNED NULL ,
  `customerId` INT(10) UNSIGNED NULL ,
  `transactionDate` DATE NOT NULL ,
  `type` ENUM('CASH', 'CHEQUE', 'PAYPAL') NOT NULL DEFAULT 'CASH' ,
  `reference` VARCHAR(255) NULL ,
  `total` FLOAT(8,2) NOT NULL ,
  `remainingToApply` FLOAT(8,2) NOT NULL DEFAULT 0,
  `appliedToInvoices` VARCHAR(255) NOT NULL DEFAULT 0,
  `appliedToBalance` TINYINT(1) NOT NULL DEFAULT 0,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`) ,
  INDEX `transactionToInvoice` (`invoiceId` ASC) ,
  INDEX `reference` (`type` ASC, `reference` ASC) ,
  INDEX `transactionToCustomer` (`customerId` ASC) ,
  CONSTRAINT `transactionToInvoice`
    FOREIGN KEY (`invoiceId` )
    REFERENCES `customersInvoices` (`id` )
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `transactionToCustomer`
    FOREIGN KEY (`customerId` )
    REFERENCES `customers` (`id` )
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE  TABLE `transactionsInvoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `transactionId` INT UNSIGNED NOT NULL ,
  `invoiceId` INT UNSIGNED NOT NULL ,
  `amountApplied` FLOAT(8,2) NOT NULL ,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `toTransactions` (`transactionId` ASC) ,
  INDEX `transactionsToInvoices` (`invoiceId` ASC) ,
  CONSTRAINT `toTransactions`
    FOREIGN KEY (`transactionId` )
    REFERENCES `transactions` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `transactionsToInvoices`
    FOREIGN KEY (`invoiceId` )
    REFERENCES `customersInvoices` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE  TABLE `customersBalances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  `customerId` INT(10) UNSIGNED NOT NULL ,
  `amount` FLOAT(8,2) NOT NULL ,
  `source` ENUM('INVOICE','TRANSACTION','MISC') NOT NULL DEFAULT 'MISC' ,
  `sourceId` INT(10) NULL DEFAULT NULL ,
  `note` VARCHAR(255) NULL ,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `ledgerToCustomer` (`customerId` ASC) ,
  CONSTRAINT `ledgerToCustomer`
    FOREIGN KEY (`customerId` )
    REFERENCES `customers` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE TABLE `logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customerId` INT(10) UNSIGNED NULL ,
  `description` VARCHAR(255) NOT NULL ,
  `category` ENUM('DEFAULT','CUSTOMERS','SHARES','ADDONS','NEWSLETTER','WEBSITE') NOT NULL ,
  `event` ENUM('NEW') NOT NULL ,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `logToCustomer` (`customerId` ASC) ,
  CONSTRAINT `logToCustomer`
    FOREIGN KEY (`customerId` )
    REFERENCES `customers` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci
PACK_KEYS = 1;

CREATE TABLE `issues` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `publish` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `publishedDatetime` datetime DEFAULT NULL,
  `archive` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `facebookId` varchar(255) DEFAULT NULL,
  `twitterId` varchar(255) DEFAULT NULL,
  `updatedDatetime` datetime NOT NULL,
  `addedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `issuesRecipients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `issueId` int(10) unsigned NOT NULL,
  `list` varchar(255) NOT NULL,
  `params` longtext,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issuesToRecipients` (`issueId`),
  CONSTRAINT `issuesToRecipients` FOREIGN KEY (`issueId`) REFERENCES `issues` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `addonsLocations` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `addonId` INT(10) UNSIGNED NOT NULL,
  `locationId` INT(10) UNSIGNED NOT NULL,
  `addedDatetime` DATETIME NOT NULL,
  `updatedDatetime` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `addonsLocationsAddonsIndex` (`addonId` ASC),
  INDEX `addonsLocationsLocationsIndex` (`locationId` ASC),
  CONSTRAINT `addons`
    FOREIGN KEY (`addonId`)
    REFERENCES `addons` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `locations`
    FOREIGN KEY (`locationId`)
    REFERENCES `locations` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE);

CREATE TABLE `sharesVacationOptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shareId` int(11) NOT NULL,
  `vacationOption` varchar(250) DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `customersVacations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customerId` int(11) NOT NULL,
  `vacationOptionId` int(11) NOT NULL,
  `shareId` int(11) NOT NULL,
  `startWeek` varchar(7) NOT NULL,
  `endWeek` varchar(7) NOT NULL,
  `updatedDatetime` datetime DEFAULT NULL,
  `addedDatetime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `customersSharesNotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customerId` int(10) unsigned NOT NULL,
  `customerShareId` int(10) unsigned NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `week` char(7) NOT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `notesToShare` FOREIGN KEY (`customerShareId`) REFERENCES `customersShares` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `notesToCustomer` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
