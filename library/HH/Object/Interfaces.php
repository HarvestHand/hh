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
 * @copyright $Date: 2012-04-03 21:58:41 -0300 (Tue, 03 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */

/**
 * Description of Interfaces
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Interfaces.php 482 2012-04-04 00:58:41Z farmnik $
 * @package   HH_Object
 * @copyright $Date: 2012-04-03 21:58:41 -0300 (Tue, 03 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Object_Interfaces implements ArrayAccess, IteratorAggregate,
    Countable, HH_Observer_Subject
{
    /**
     * magic data value set
     *
     * @param string $name
     * @param mixed $value
     */
    public function  __set($name, $value)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        $this->_data[$name] = $value;
        $this->_isEmpty = empty($this->_data);
    }

    /**
     * magic data value get
     *
     * @param string $name
     * @return mixed
     */
    public function  __get($name)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return $this->_data[$name];
    }

    /**
     * magic isset
     *
     * @param mixed $name
     * @return boolean
     */
    public function __isset($name)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return isset($this->_data[$name]);
    }

    /**
     * magic unset
     *
     * @param mixed $name
     */
    public function __unset($name)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        unset($this->_data[$name]);
    }

    /**
     * magic to string
     */
    public function  __toString()
    {
        return get_class($this) . '_' .
            ((is_array($this->_id)) 
                ? HH_Tools_String::convertToCacheSafe(implode('_', $this->_id)) 
                : HH_Tools_String::convertToCacheSafe($this->_id));
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset An offset to check for.
     * @return boolean Returns true on success or false on failure.
     */
    public function offsetExists ($name)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        if (!is_array($this->_data) || !array_key_exists($name, $this->_data)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     */
    public function offsetGet ($name)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return $this->_data[$name];
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @return void
     */
    public function offsetSet ($name, $value)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        $this->_data[$name] = $value;
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset
     * @return void
     */
    public function offsetUnset ($name)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        unset($this->_data[$name]);
    }

    /**
     * String representation of object
     *
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or &null;
     */
//    public function serialize()
//    {
//        return serialize($this->_id);
//    }

    public function __wakeup()
    {
        if (!empty($this->_defaultObservers)) {
            foreach ($this->_defaultObservers as $observer) {
                if (is_string($observer)) {
                    $this->attach(new $observer);
                } else {
                    $this->attach($observer);
                }
            }
        }
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized  The string representation of the object.
     * @return mixed the original value unserialized.
     */
//    public function unserialize($serialized)
//    {
//        if (!empty($this->_defaultObservers)) {
//            foreach ($this->_defaultObservers as $observer) {
//                $this->attach($observer);
//            }
//        }
//        $this->_id = unserialize($serialized);
//        $this->_get();
//
//    }

    /**
     * Attach an observer
     * @see HH_Observer::attach
     * @param HH_Observer $observer The observer to attach.
     * @return void
     */
    public function attach(HH_Observer $observer)
    {
        $this->_observers[spl_object_hash($observer)] = $observer;
    }

    /**
     * Detach an observer
     * @see HH_Observer_Subject::detach
     * @param HH_Observer $observer The observer to detach.
     * @return void
     */
    public function detach(HH_Observer $observer)
    {
        unset($this->_observers[spl_object_hash($observer)]);
    }
    
    /**
     * Detach an observer
     *
     * @param HH_Observer|string $observer The observer to detach.
     * @return void
     */
    public function detachByType($observer)
    {
        if (is_object($observer)) {
            $observer = get_class($observer);
        }
        
        if (is_string($observer)) {
            foreach ($this->_observers as $key => $observerInstance) {
                if ($observerInstance instanceof $observer) {
                    unset($this->_observers[$key]);
                }
            }
        }
    }
    
    /**
     * Detach an observer
     *
     * @return void
     */
    public function detachAll()
    {
        $this->_observers = array();
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing Iterator or
     * Traversable
     */
    public function getIterator()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return new ArrayIterator($this->_data);
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     */
    public function count()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return count($this->_data);
    }
}