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
 * @copyright $Date: 2013-05-20 22:32:50 -0300 (Mon, 20 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Location model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Duration.php 663 2013-05-21 01:32:50Z farmnik $
 * @copyright $Date: 2013-05-20 22:32:50 -0300 (Mon, 20 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Share_Duration extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;
    const ORDER_ITERATIONS = 'ITERATIONS';

    /**
     * Get data (lazy loader)
     *
     * @return null
     */
    protected function _get()
    {
        if (empty($this->_id)) {
            $this->_setData();
            return;
        }

        $sql = 'SELECT
                  *
                FROM
                    ' . $this->_getDatabase() . '
                WHERE
                    id = ?
                AND
                    shareId = ?';

        $data = $this->_getZendDb()->fetchRow($sql, $this->_id);

        // Fetch durations
        $data['locations'] = HHF_Domain_Share_Duration::fetchByParent(
            $this->_farm,
            $this->_id
        );

        $this->_setData($data);
    }

    /**
     * Delete current object
     *
     * @throws HH_Object_Exception_Id if object ID is not set
     * @return boolean
     */
    public function delete()
    {
        if (!empty($this->_id)) {

            $sql = 'DELETE FROM
                        ' . $this->_getDatabase() . '
                    WHERE
                        id = ?
                    AND
                        shareId = ?';

            $this->_getZendDb()->query($sql, $this->_id);
        }

        $this->_reset();
    }

    /**
     * Insert data into object
     *
     * @param array $data
     * @return boolean
     * @throws HH_Object_Exception_Id If primary key needs to be defined
     * @throws HH_Object_Exception_NoData If no data to insert
     */
    public function insert($data)
    {
        $db = $this->_getZendDb();

        $locations = (!empty($data['locations'])) ?
            $data['locations'] : null;

        unset($data['locations']);

        $db->insert(
            $this->_getDatabase(),
            $this->_prepareData($data)
        );
        $data['id'] = $db->lastInsertId();
        $this->_id = array(
            $data['id'],
            $data['shareId']
        );

        $data['locations'] = array();

        if (!empty($locations)) {

            foreach ($locations as $key => $location) {
                $locationObj = new HHF_Domain_Share_Duration_Location(
                    $this->_farm
                );

                $location['shareId'] = $data['shareId'];
                $location['shareDurationId'] = $data['id'];

                $locationObj->insert($location);

                $data['locations'][$key] = $locationObj;
            }
        }

        $this->_setData($data);
    }

    /**
     * Update data in current object
     *
     * @param array|null $data
     * @return boolean
     * @throws HH_Object_Exception_Id if object ID is not set
     */
    public function update($data = null)
    {
        if (!empty($this->_id)) {

            if (!$this->_isLoaded) {
                $this->_get();
            }

            $locations = (!empty($data['locations'])) ?
                $data['locations'] : null;

            unset($data['locations']);

            $db = $this->_getZendDb();

            $db->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array(
                    'id = ?' => $this->_id[0],
                    'shareId = ?' => $this->_id[1]
                )
            );

            $data['locations'] = $this->_updateRelations(
                $this->_data['locations'],
                $locations,
                'HHF_Domain_Share_Duration_Location'
            );

            $this->_setData($data, false);
        }
    }

    /**
     * Update relational data
     *
     * @param array $orginalData Original stored relational data
     * @param array $newData New relational data to be stored
     * @param string $class Model class name
     * @return array Array of updated relational data
     */
    protected function _updateRelations($orginalData, $newData, $class) {
        $relations = array();

        foreach ($orginalData as $rowOriginal) {

            $found = false;

            if (is_array($newData)) {
                foreach ($newData as $key => $rowNew) {
                    if (!isset($rowNew['id'])) {
                        continue;
                    }

                    if ($rowOriginal['id'] == $rowNew['id']) {

                        $rowOriginal->update($rowNew);
                        $relations[] = $rowOriginal;

                        unset($newData[$key]);

                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $rowOriginal->delete();
            }
        }

        if (is_array($newData)) {
            foreach ($newData as $row) {

                $row['shareId'] = $this->_data['shareId'];
                $row['shareDurationId'] = $this->_data['id'];

                $object = new $class($this->_farm);
                $object->insert($row);

                $relations[] = $object;
            }
        }

        return $relations;
    }

    /**
     * What locations is this duration restricted to
     * 
     * @return array Array of locations to restrict to.  Empty if no restriction
     */
    public function restrictLocationsTo()
    {
        $restrict = array();
        
        foreach ($this->locations as $location) {
            $restrict[] = $location->locationId;
        }
        
        return $restrict;
    }

    /**
     * Get start date for this duration 
     * 
     * @param HHF_Domain_Location|int $location Optional location that will be delivered to
     * @return Zend_Date
     */
    public function getStartDate($location = null, $year = null)
    {
        static $dates = array();
        
        $key = implode('', $this->_id) . $location . (string) $year;
        
        if (isset($dates[$key]) && $dates[$key] instanceof Zend_Date) {
            return $dates[$key];
        }
        
        if ($year === null) {
            $year = date('Y');
        } else if ($year instanceof Zend_Date) {
            $year = $year->get(Zend_Date::YEAR);
        }
        
        $dates[$key] = self::staticGetStartDate(
            $this->_farm,
            $this->startWeek,
            $year,
            $location
        );
        
        return clone $dates[$key];
    }
    
    /**
     * get start date
     * 
     * @param HH_Domain_Farm $farm
     * @param int $startWeek
     * @param int $year
     * @param int $location
     * @return Zend_Date
     */
    public static function staticGetStartDate(HH_Domain_Farm $farm, $startWeek,
        $year, $location = null)
    {
        $date = Zend_Date::now()->setYear($year);
        if ($date->get(Zend_Date::MONTH_SHORT) == 1 && $date->get(Zend_Date::WEEK) > 51) {
            $date->setWeek(1)->setYear($year);
        }

        $date->setWeek($startWeek)
            ->setWeekday(1);
        
        // shift weekday by location
        if ($location !== null) {
            if (is_numeric($location)) {
                $location = new HHF_Domain_Location($farm, $location);
            }
            
            if (!$location->isEmpty()) {
                $date->setWeekday($location->dayOfWeek);
                $date->setTime($location->timeStart);
            }
        }
        
        return $date;
    }
    
    /**
     * Get end date for this duration
     * 
     * @param string $deliverySchedule
     * @param HHF_Domain_Location|int $location Optional location that will be delivered to
     * @return Zend_Date 
     */
    public function getEndDate($deliverySchedule, $location = null,
        $year = null) 
    {
        static $dates = array();
        
        $key = implode('', $this->_id) . $deliverySchedule . $location . (string) $year;
        
        if (isset($dates[$key]) && $dates[$key] instanceof Zend_Date) {
            return $dates[$key];
        }
        
        if ($year === null) {
            $year = date('Y');
        } else if ($year instanceof Zend_Date) {
            $year = $year->get(Zend_Date::YEAR);
        }
        
        $dates[$key] = self::staticGetEndDate(
            $this->_farm,
            $this->startWeek,
            $year,
            $deliverySchedule,
            $this->iterations,
            $location
        );
        
        return clone $dates[$key];
    }
    
    public static function staticGetEndDate(HH_Domain_Farm $farm, $startWeek,
        $year, $deliverySchedule, $iterations, $location = null)
    {
        $date = self::staticGetStartDate(
            $farm,
            $startWeek,
            $year,
            $location
        );
        
        if ($location === null) {
            $date->setWeekday(7);
        }

        switch ($deliverySchedule) {
            case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                $date->addWeek($iterations - 1);
                break;
            case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                for ($c = 1; $c < $iterations; ++$c) {
                    $date->addWeek(2);
                }
                break;
            case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                for ($c = 1; $c < $iterations; ++$c) {
                    $date->addMonth(1);
                }
                break;
        }

        return $date;
    }
    
    /**
     * Get Zend_Filter_Input for model
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array())
    {
        $inputFilter = null;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        switch ($filter) {

            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'cutOffWeek' => array(
                            new Zend_Filter_Null()
                        ),
                        'fullPaymentDueDate' => array(
                            new Zend_Filter_Null()
                        )
                    ),
                    array(
                        'id' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true
                        ),
                        'startWeek' => array(
                            new Zend_Validate_InArray(range(1, 52)),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid start week is required')
                            )
                        ),
                        'fullPaymentDueDate' => array(
                            new Zend_Validate_Date('yyyy-M-d'),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid date is required')
                            )
                        ),
                        'iterations' => array(
                            new Zend_Validate_InArray(range(1, 52)),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Number of deliveries is required')
                            )
                        ),
                        'cutOffWeek' => array(
                            new Zend_Validate_Between(
                                array('min' => 1, 'max' => 52)
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'shares' => array(
                            new Zend_Validate_Int(),
                            new Zend_Validate_Between(
                                array('min' => 0, 'max' => 999999)
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('The total number of shares is required')
                            )
                        ),
                        'sort' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => 1
                        )
                    ),
                    null,
                    array(
                        Zend_Filter_Input::MISSING_MESSAGE   =>
                            $translate->_("'%field%' is required"),
                        Zend_Filter_Input::NOT_EMPTY_MESSAGE =>
                            $translate->_("'%field%' is required"),
                    )
                );
                break;
        }

        return $inputFilter;
    }

    /**
     * Fetch all durations by parent share
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchByParent(HH_Domain_Farm $farm, $parent, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                WHERE
                    shareId = ?';

        $bind = array($parent);

        if (isset($options['order'])) {

            switch ($options['order']) {
                case self::ORDER_ITERATIONS :
                    $sql .= ' ORDER BY iterations DESC';

                    break;
            }
        }
        
        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $duration) {

            $duration['locations'] = HHF_Domain_Share_Duration_Location::fetchByParent(
                $farm,
                array(
                    $parent,
                    $duration['id']
                )
            );

            $return[] = new self(
                $farm,
                array(
                    $duration['id'],
                    $duration['shareId'],
                ),
                $duration
            );
        }

        return $return;
    }
}
