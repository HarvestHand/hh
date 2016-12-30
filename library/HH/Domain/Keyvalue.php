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
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Domain
 */

/**
 * Description of Keystore
 *
 * @package   HH_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Keyvalue.php 409 2012-01-17 22:45:31Z farmnik $
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Domain_Keyvalue extends HH_Object_Db
{
    
    public function insert($data)
    {
        if (empty($data['id'])) {
            $data['id'] = $this->_generateId();
        }
        
        if (empty($data['addedTimestamp'])) {
            $data['addedTimestamp'] = time();
        }
        
        return parent::insert($data);
    }
    
    protected function _generateId()
    {
        $id = substr(md5(uniqid(mt_rand(), true)), 0, 13);
        
        while (1) {
        
            $result = self::fetch(
                array(
                    'where' => array(
                        'id' => $id
                    )
                )
            );
            
            if (count($result)) {
                $id = substr(md5(uniqid(mt_rand(), true)), 12);
            } else {
                break;
            }
        }
        
        return $id;
    }
    
    public static function getFilter($filter = null, $options = array())
    {
        
    }

        /**
     * Clean up stale data
     */
    public static function clean()
    {
        $stores = self::fetch(
            array(
                'where' => 'addedTimestamp + ttl < UNIX_TIMESTAMP(NOW())'
            )
        );
        
        if (count($stores)) {
            foreach ($stores as $store) {
                $store->delete();
            }
        }
    }
}