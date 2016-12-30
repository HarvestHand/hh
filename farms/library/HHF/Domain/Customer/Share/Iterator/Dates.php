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
 * @copyright $Date: 2012-08-01 08:42:51 -0300 (Wed, 01 Aug 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of share date iterator
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 562 2012-08-01 11:42:51Z farmnik $
 * @copyright $Date: 2012-08-01 08:42:51 -0300 (Wed, 01 Aug 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */

class HHF_Domain_Share_Iterator_Dates implements Iterator
{
    protected $_dates = array();
    protected $_validIteration = false;

    /**
     * @var HHF_Domain_Share_Duration|null
     */
    protected $_duration;
    protected $_location;

    /**
     * @var HHF_Domain_Share
     */
    protected $_share;

    public function __construct(HHF_Domain_Share $share, $duration = null, $location = null)
    {
        $this->_share = $share;

        if ($duration instanceof HHF_Domain_Share_Duration) {
            $this->_duration = $share->getDurationById($duration['id']);
        } else if (is_numeric($duration)) {
            $this->_duration = $share->getDurationById($duration);
        }

        if ($location instanceof HHF_Domain_Location) {
            $this->_location = $location;
        } else if (is_numeric($location)) {
            foreach ($this->_duration->locations as $location) {
                /* @var $location HHF_Domain_Share_Duration_Location */
//                $location->
            }
        }

        $this->generate();
    }

    protected function generate()
    {

    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return current($this->_dates);
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->_validIteration = (false !== next($this->_dates));
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return key($this->_dates);
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->_validIteration;
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->_validIteration = (false !== reset($this->_dates));
    }
} 
