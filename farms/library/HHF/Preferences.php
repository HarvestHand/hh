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
 * @copyright $Date: 2012-09-02 20:22:40 -0300 (Sun, 02 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Preferences
 */

/**
 * Description of Preferences
 *
 * @package   HHF_Preferences
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Preferences.php 572 2012-09-02 23:22:40Z farmnik $
 * @copyright $Date: 2012-09-02 20:22:40 -0300 (Sun, 02 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Preferences implements IteratorAggregate
{
    protected $_data = array();
    protected $_type = null;
    protected $_user = null;
    protected $_resource = 'default';
    protected $_farm;
    protected $_isLoaded = false;


    /**
     * Constructor 
     * 
     * @param HH_Domain_Farm $farm
     * @param string $type
     * @param int $user
     * @param string $resource 
     */
    public function __construct(HH_Domain_Farm $farm, $type, $user = null,
        $resource = null)
    {
        $this->_type = $type;
        $this->_user = $user;
        $this->_farm = $farm;
        
        $options = array(
            'type' => $type
        );
        
        if (!empty($resource)) {
            $this->setDefaultResource($resource);
            
            $options['resource'] = $this->_resource;
        }
        
        
        if ($type == HHF_Domain_Preference::TYPE_CUSTOMER) {
            $options['customerId'] = $user;
        }
        
        if ($type == HHF_Domain_Preference::TYPE_FARMER) {
            $options['farmerId'] = $user;
        }
        
        $this->_get($options);
    }
    
    protected function _get($options)
    {
        $rawPreferences = HHF_Domain_Preference::fetchPreferences(
            $this->_farm,
            $options
        );
        
        foreach ($rawPreferences as $preference) {
            $key = $preference->resource . '-' . $preference->key;
            $this->_data[$key] = $preference;
        }
        
        $this->_isLoaded = true;
    }
    
    /**
     * Set the default resource
     *
     * @param string $resource
     * @return HHF_Preferences
     */
    public function setDefaultResource($resource)
    {
        $this->_resource = $resource;
        return $this;
    }

    /**
     * Get resource
     * 
     * @param string $key
     * @param string $resource
     * @return string 
     */
    protected function _getResource(&$key, $resource = null)
    {
        if (strpos($key, '-') !== false) {
            list($resource, $key) = explode('-', $key, 2);
            return $resource;
        } else if (!empty($resource)) {
            return $resource;
        } else {
            return $this->_resource;
        }
    }
    
    /**
     * Get prefence value
     * 
     * @param string $key
     * @param string $resource
     * @param string $default
     * @return mixed 
     */
    public function get($key, $resource = null, $default = null)
    {
        $resource = $this->_getResource($key, $resource);
        
        $hash = $resource . '-' . $key; 
        
        if (isset($this->_data[$hash])) {
            return $this->_data[$hash]->value;
        } else {
            return $default;
        }
    }
    
    public function getStructure($key, $resource = null, $default = null)
    {
        $default = serialize($default);
        
        $data = $this->get($key, $resource, $default);
        
        return unserialize($data);
    }
    
    /**
     * Insert or update a preference
     * 
     * @param string $key
     * @param string $value
     * @param string $resource
     * @return HHF_Preferences 
     */
    public function replace($key, $value, $resource = null)
    {
        $resource = $this->_getResource($key, $resource);
        
        $hash = $resource . '-' . $key;
        
        if (isset($this->_data[$hash])) {
            $this->_data[$hash]->update(
                array(
                    'value' => $value
                )
            );
        } else {
            $data = array(
                'type' => $this->_type,
                'key' => $key,
                'value' => $value,
                'resource' => $resource
            );
            
            switch ($this->_type) {
                case HHF_Domain_Preference::TYPE_CUSTOMER :
                    $data['customerId'] = $this->_user;
                    break;
                case HHF_Domain_Preference::TYPE_FARMER :
                    $data['farmerId'] = $this->_user;
                    break;
            }
            
            $filter = HHF_Domain_Preference::getFilter(
                HHF_Domain_Preference::FILTER_NEW
            );
            $filter->setData($data);
            
            if ($filter->isValid()) {
            
                $preference = new HHF_Domain_Preference($this->_farm);
                $preference->insert($filter->getUnescaped());
                
                $this->_data[$hash] = $preference;
                
            } else {
                throw new Exception('Invalid preference data');
            }
        }
        
        return $this;
    }
    
    public function replaceStructure($key, $value, $resource = null)
    {
        $value = serialize($value);
        
        return $this->replace($key, $value, $resource);
    }
    
    /**
     * Delete preference
     * 
     * @param string $key
     * @param string $resource
     * @return HHF_Preferences 
     */
    public function delete($key, $resource = null)
    {
        $resource = $this->_getResource($key, $resource);
        
        $hash = $resource . '-' . $key;
        
        if (isset($this->_data[$hash])) {
            $this->_data[$hash]->delete();
            unset($this->_data[$hash]);
        }
        
        return $this;
    }
    
    /**
	 * Retrieve an external iterator
	 * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
	 * @return Traversable An instance of an object implementing Iterator or
	 * Traversable
	 */
    public function getIterator()
    {
        return new ArrayIterator($this->_data);
    }
    
    /**
     * Convert object to array
     *
     * @return array
     */
    public function toArray()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return $this->_data;
    }
    
    /**
     * Convert object to json
     * 
     * @return string 
     */
    public function toJson() 
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return json_encode($this->_data);
    }
}