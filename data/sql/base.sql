SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';

CREATE SCHEMA IF NOT EXISTS `farmnik_hh` DEFAULT CHARACTER SET utf8 ;
USE `farmnik_hh` ;

-- -----------------------------------------------------
-- Table `farms`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `farms` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  `status` ENUM('ACTIVE','CLOSED','TRIAL','OVERDUE') NOT NULL DEFAULT 'ACTIVE' ,
  `version` VARCHAR(10) NOT NULL DEFAULT '1.0' ,
  `name` VARCHAR(255) NOT NULL ,
  `address` VARCHAR(100) NOT NULL ,
  `address2` VARCHAR(100) NULL DEFAULT NULL ,
  `city` VARCHAR(100) NOT NULL ,
  `state` VARCHAR(45) NOT NULL ,
  `zipCode` VARCHAR(45) NOT NULL ,
  `country` CHAR(2) NOT NULL ,
  `timezone` VARCHAR(100) NULL DEFAULT NULL ,
  `telephone` VARCHAR(20) NULL DEFAULT NULL ,
  `fax` VARCHAR(20) NULL DEFAULT NULL ,
  `email` VARCHAR(150) NULL DEFAULT NULL ,
  `addedDatetime` DATETIME NOT NULL ,
  `updatedDatetime` DATETIME NOT NULL ,
  `primaryFarmerId` INT(10) UNSIGNED NULL ,
  `subdomain` varchar(100) DEFAULT NULL,
  `domain` varchar(253) DEFAULT NULL,
  `type` set('CSA','VENDOR','DISTRIBUTOR') NOT NULL DEFAULT 'CSA',
  PRIMARY KEY (`id`) ,
  INDEX `fkFarmsFarmers1` (`primaryFarmerId` ASC),
  UNIQUE INDEX `domain_UNIQUE` (`domain` ASC)
) ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `farmers`
-- -----------------------------------------------------
CREATE TABLE `farmers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email2` varchar(255) DEFAULT NULL,
  `userName` varchar(50) NOT NULL,
  `userToken` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `password` varchar(32) NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  `addedDatetime` datetime NOT NULL,
  `farmId` int(10) unsigned DEFAULT NULL,
  `role` enum('FARMER','ADMIN','MEMBER') NOT NULL DEFAULT 'FARMER',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userToken_UNIQUE` (`userToken`),
  UNIQUE KEY `username_UNIQUE` (`userName`,`farmId`,`role`),
  KEY `fkFarmersFarms` (`farmId`),
  KEY `emailPassword` (`email`,`password`),
  CONSTRAINT `fkFarmersFarms` FOREIGN KEY (`farmId`) REFERENCES `farms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE farms
ADD CONSTRAINT `fkFarmsFarmers1`
    FOREIGN KEY (`primaryFarmerId` )
    REFERENCES `farmers` (`id` )
    ON DELETE SET NULL
    ON UPDATE CASCADE;

CREATE  TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(64) NOT NULL ,
  `updatedTimestamp` INT(10)  NOT NULL ,
  `addedTimestamp` INT(10)  NOT NULL ,
  `data` TEXT NULL DEFAULT NULL ,
  `farmerId` INT(10) UNSIGNED NULL DEFAULT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `farmerId` (`farmerId` ASC) ,
  CONSTRAINT `fkSessionsFarmers`
    FOREIGN KEY (`farmerId` )
    REFERENCES `farmers` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;


CREATE TABLE IF NOT EXISTS errors (
    id int(10) unsigned NOT NULL auto_increment,
    addedDatetime DATETIME NOT NULL ,
    updatedDatetime DATETIME NOT NULL ,
    priority int(11) default NULL,
    code int(11) default NULL,
    message text collate utf8_general_ci,
    file varchar(255) collate utf8_general_ci default NULL,
    line INT(10) unsigned default NULL,
    backtrace longtext collate utf8_general_ci,
    context longtext collate utf8_general_ci,
    class varchar(255) collate utf8_general_ci default NULL,
    server longtext collate utf8_general_ci default NULL,
    post longtext collate utf8_general_ci,
    farmerId INT(10) unsigned default NULL,
    extra longtext collate utf8_general_ci,
    PRIMARY KEY  (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `message` (
  `message_id` bigint(20) unsigned NOT NULL auto_increment,
  `queue_id` int(10) unsigned NOT NULL,
  `handle` char(32) default NULL,
  `body` LONGTEXT NOT NULL,
  `md5` char(32) NOT NULL,
  `timeout` decimal(14,4) unsigned default NULL,
  `created` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`message_id`),
  UNIQUE KEY `message_handle` (`handle`),
  KEY `message_queueid` (`queue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `queue` (
  `queue_id` int(10) unsigned NOT NULL auto_increment,
  `queue_name` varchar(100) NOT NULL,
  `timeout` smallint(5) unsigned NOT NULL default '30',
  PRIMARY KEY  (`queue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`queue_id`) REFERENCES `queue` (`queue_id`) ON DELETE CASCADE ON UPDATE CASCADE;


CREATE TABLE IF NOT EXISTS keyvalues (
    id varchar(113) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    type varchar(100) NOT NULL,
    data text,
    addedTimestamp int(10) unsigned NOT NULL,
    ttl int(10) unsigned NOT NULL DEFAULT '604800',
    addedDatetime DATETIME NOT NULL,
    updatedDatetime DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `posts` (
  `id` varchar(255) NOT NULL,
  `crawlTimeMsec` varchar(20) DEFAULT NULL,
  `timestampUsec` varchar(20) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  `postUrl` text NOT NULL,
  `category` varchar(255) NOT NULL,
  `media` text,
  `summary` longtext,
  `content` longtext,
  `author` varchar(255) DEFAULT NULL,
  `streamId` text NOT NULL,
  `blogName` varchar(255) NOT NULL,
  `blogUrl` text NOT NULL,
  `tags` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `networks` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('DISTRIBUTION') NOT NULL DEFAULT 'DISTRIBUTION',
  `farmId` int(10) unsigned NOT NULL,
  `relationId` int(10) unsigned NOT NULL,
  `status` enum('APPROVED','PENDING','CLOSED') NOT NULL DEFAULT 'PENDING',
  `addedDatetime` datetime NOT NULL,
  `updatedDatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_networks_1_idx` (`farmId`),
  KEY `fk_networks_2_idx` (`relationId`),
  CONSTRAINT `fk_networks_1` FOREIGN KEY (`farmId`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_networks_2` FOREIGN KEY (`relationId`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
