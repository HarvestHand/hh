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
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */

/**
 * Description of Base
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Collection.php 723 2014-03-04 23:51:27Z farmnik $
 * @copyright $Date: 2014-03-04 19:51:27 -0400 (Tue, 04 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */
class HH_Object_Collection implements SeekableIterator, ArrayAccess, Countable
{
    /**
     * @var HH_Object_Service
     */
    protected $_service;
    protected $_position = 0;
    protected $_data = array();
    protected $_config = array();
    protected $_objectType = 'HH_Object';
    protected static $_staticConfig = array();

    public function __construct($data = array(), $config = array())
    {
        $this->_data = $data;
        $this->_position = 0;
        
        $this->setConfig($config);
        
        $collectionClass = get_called_class();
        
        if (strpos('Object_Collection', $collectionClass) === false) {
            $this->setObjectType(substr($collectionClass, 0, -11));
        }
    }
    
    public function setObjectType($objectType)
    {
        $this->_objectType = $objectType;
    }
    
    /**
     * set collection config
     *
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->_config = array_merge(
            $this->_config,
            self::$_staticConfig,
            $config
        );
    }

    /**
     * Set object config across all to be initialized object collections
     * 
     * @param array $config
     */
    public static function setStaticConfig($config)
    {
        self::$_staticConfig = $config;
    }
    
    /**
     * Get collection service layer
     * 
     * @return HH_Object_Collection_Service
     */
    public function getService()
    {
        if ($this->_service === null) {
            if (!empty($this->_objectType)) {
                $name = $this->_objectType . '_Collection_Service';
            } else {
            
                $name = get_called_class() . '_Service';
            }
            
            $this->_service = new $name($this);
        }
        
        return $this->_service;
    }
    
    /**
     * @param type $id
     * @return mixed
     */
    public function searchById($id)
    {
        foreach ($this->_data as $row) {
            if ($row['id'] == $id) {
                return $row;
            }
        }
    }
    
    public function count()
    {
        return count($this->_data);
    }
    
    public function seek($position) 
    {
      $this->_position = $position;
      
      if (!$this->valid()) {
          throw new OutOfBoundsException("invalid seek position ($position)");
      }
    }
    
    public function rewind() 
    {
        $this->_position = 0;
    }

    public function current() 
    {
        return $this->_data[$this->_position];
    }

    public function key() 
    {
        return $this->_position;
    }

    public function next()
    {
        ++$this->_position;
    }

    public function valid() 
    {
        return isset($this->_data[$this->_position]);
    }
    
    public function offsetSet($offset, $value) 
    {
        if (is_null($offset)) {
            $this->_data[] = $value;
        } else {
            $this->_data[$offset] = $value;
        }
    }
    
    public function offsetExists($offset) 
    {
        return isset($this->_data[$offset]);
    }
    
    public function offsetUnset($offset) 
    {
        unset($this->_data[$offset]);
        $this->_data = array_values($this->_data);
    }
    
    public function offsetGet($offset) 
    {
        return isset($this->_data[$offset]) ? $this->_data[$offset] : null;
    }
    
    public function usort($function)
    {
        $result = usort($this->_data, $function);
        
        $this->rewind();
        
        return $result;
    }

    public function setData($data)
    {
        $this->_data = $data;
    }

    public function filter($function){
        $this->_data = array_values(
            array_filter(
                $this->_data,
                $function
            )
        );
    }
    
    public function toArray()
    {
        $array = array();
        
        foreach ($this as $row) {
            $array[] = $row->toArray();
        }
        
        return $array;
    }
}
