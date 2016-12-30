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
 * @copyright $Date: 2014-03-04 19:51:27 -0400 (Tue, 04 Mar 2014) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Domain
 */

/**
 * Description of Network
 *
 * @package   HH_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Network.php 723 2014-03-04 23:51:27Z farmnik $
 * @copyright $Date: 2014-03-04 19:51:27 -0400 (Tue, 04 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Domain_Network extends HH_Object_Db
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_CLOSED = 'CLOSED';
    const TYPE_DISTRIBUTION = 'DISTRIBUTION';

    public function getFarm()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return new HH_Domain_Farm($this->_data['farmId']);
    }

    public function getRelation()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return new HH_Domain_Farm($this->_data['relationId']);
    }

    public static function getFilter($filter = null, $options = array())
    {
        
    }
}
