<?php

/**
 * HarvestHand
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License Version 3
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to farmnik@harvesthand.com so we can send you a copy immediately.
 *
 * @copyright $Date: 2015-08-10 12:51:34 -0300 (Mon, 10 Aug 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Help
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Upgrade.php 927 2015-08-10 15:51:34Z farmnik $
 * @copyright $Date: 2015-08-10 12:51:34 -0300 (Mon, 10 Aug 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_Upgrade extends HH_Tools_Console_Task
{
    public function run()
    {
        if (($pid = $this->_console->isLocked('upgrade')) !== false) {
            $this->_console->outputText(
                'Task \'upgrade\' locked with a PID of ' . $pid
            );
            return HH_Tools_Console::ERROR_LOCK;
        }

        $this->_console->setLock('upgrade');

        try {
            $farms = HH_Domain_Farm::fetch();
            $db = Bootstrap::getZendDb();

            $total = 0;
            foreach ($farms as $farm){

                //echo $farm['name'] . PHP_EOL;

                $sql = 'SELECT SUM(total) FROM farmnik_hh_' . $farm['id'] . '.transactions';

                $result = $db->fetchCol($sql);

                $total += reset($result);
            }

            echo 'The total money: '.$total;

        } catch (Exception $e) {
            $this->_console->removeLock('upgrade');
            throw $e;
        }

        $this->_console->removeLock('upgrade');

        return HH_Tools_Console::ERROR_NONE;
    }
}



//$sql = 'CREATE TABLE IF NOT EXISTS farmnik_hh_' . $farm['id'] . '.customersSharesNotes (
//                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
//                      `customerId` int(10) unsigned NOT NULL,
//                      `customerShareId` int(10) unsigned NOT NULL,
//                      `note` varchar(255) DEFAULT NULL,
//                      `week` char(7) NOT NULL,
//                      `addedDatetime` datetime NOT NULL,
//                      `updatedDatetime` datetime NOT NULL,
//                      PRIMARY KEY (`id`),
//                      CONSTRAINT `notesToShare` FOREIGN KEY (`customerShareId`) REFERENCES `customersShares` (`id`) ON UPDATE CASCADE,
//                      CONSTRAINT `notesToCustomer` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
//                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
//
//$result = $db->query($sql);


// Commented by Ray on July 28th
//$sql = 'DROP TABLE IF EXISTS farmnik_hh_' . $farm['id'] . '.sharesVacationOptions;';
//$result = $db->query($sql);
//
//$sql = 'CREATE TABLE IF NOT EXISTS farmnik_hh_' . $farm['id'] . '.sharesVacationOptions (
//                          id int(11) NOT NULL AUTO_INCREMENT,
//                          shareId int(11) NOT NULL,
//                          vacationOption varchar(250) DEFAULT NULL,
//                          addedDatetime DATETIME DEFAULT NULL,
//                          updatedDatetime DATETIME DEFAULT NULL,
//                          PRIMARY KEY (id)
//                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
//
//$result = $db->query($sql);



// Commented by Ray on July 7th
//$sql = 'CREATE TABLE IF NOT EXISTS `farmnik_hh_' . $farm['id'] . '`.`addonsCategories` (
//      `id` VARCHAR(255) NOT NULL,
//      `name` VARCHAR(255) NOT NULL,
//      `image` VARCHAR(255) NULL,
//      `addedDatetime` DATETIME NOT NULL,
//      `updatedDatetime` DATETIME NOT NULL,
//      PRIMARY KEY (`id`))
//    ENGINE = InnoDB
//    DEFAULT CHARACTER SET = utf8';
//
//$result = $db->query($sql);
//
//
//
//$categories = HHF_Domain_Addon::fetchCategories($farm);
//$tokens = array();
//$tokenCategoryFilter = new HHF_Filter_Transliteration(255);
//
//foreach ($categories as $token => $name) {
//    if (empty($token)) {
//        $token = $tokenCategoryFilter->filter($name);
//
//        $sql = 'UPDATE `farmnik_hh_' . $farm['id'] . '`.`addons`
//            SET
//            `categoryToken` = ?
//            WHERE `category` = ?';
//
//        $db->query($sql, array($token, $name));
//    }
//
//    if (in_array($token, $tokens)) {
//        continue;
//    }
//
//    $catObj = new HHF_Domain_Addon_Category($farm, $token);
//
//    $catObj->getService()->save(
//        array(
//            'name' => $name
//        )
//    );
//
//    $tokens[] = $catObj['id'];
//}
//
//// make sure we got em all
//$sql = 'SELECT DISTINCT
//      categoryToken,
//      category
//    FROM
//      `farmnik_hh_' . $farm['id'] . '`.addons
//    WHERE
//        categoryToken NOT IN(
//            SELECT
//                id
//            FROM
//                `farmnik_hh_' . $farm['id'] . '`.addonsCategories
//        )';
//
//$strays = $db->fetchPairs($sql);
//
//foreach ($strays as $token => $name) {
//    if (empty($token)) {
//        continue;
//    }
//
//    $catObj = new HHF_Domain_Addon_Category($farm, $token);
//
//    $catObj->getService()->save(
//        array(
//            'name' => $name
//        )
//    );
//}
//
//// update addons table;
//$sql = 'ALTER TABLE `farmnik_hh_' . $farm['id'] . '`.`addons`
//    DROP COLUMN `category`,
//    CHANGE COLUMN `categoryToken` `categoryId` VARCHAR(255) NULL DEFAULT \'Other-Goodies\',
//    ADD INDEX `fk_addons_1_idx1` (`categoryId` ASC);';
//
//$db->query($sql);
//
//
//$sql = 'ALTER TABLE `farmnik_hh_' . $farm['id'] . '`.`addons`
//    ADD CONSTRAINT `fk_addons_1`
//      FOREIGN KEY (`categoryId`)
//      REFERENCES `farmnik_hh_' . $farm['id'] . '`.`addonsCategories` (`id`)
//      ON DELETE RESTRICT
//      ON UPDATE CASCADE;';
//
//$db->query($sql);

// added by GW June6 2014
//$sql = 'ALTER TABLE `farmnik_hh_' . $farm['id'] . '`.`transactions`
//			ADD COLUMN `note` VARCHAR(255) NULL DEFAULT NULL AFTER `total`';
//
// $result = $db->query($sql);