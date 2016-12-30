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
 * @copyright $Date: 2016-07-01 11:42:00 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Location model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Share.php 987 2016-07-01 14:42:00Z farmnik $
 * @copyright $Date: 2016-07-01 11:42:00 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Share extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;
    const FETCH_ENABLED = 'ENABLED';
    const FETCH_PURCHASABLE = 'PURCHASABLE';
    const ORDER_NAME = 'NAME';

    const DELIVERY_SCHEDULE_WEEKLY = 'WEEKLY';
    const DELIVERY_SCHEDULE_SEMI_MONTHLY = 'SEMI_MONTHLY';
    const DELIVERY_SCHEDULE_MONTHLY = 'MONTHLY';

    public static $deliverySchedules = array(
        self::DELIVERY_SCHEDULE_WEEKLY,
        self::DELIVERY_SCHEDULE_SEMI_MONTHLY,
        self::DELIVERY_SCHEDULE_MONTHLY,
    );


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

        $cache = $this->_getZendCache();
        if (($data = $cache->load((string) $this)) !== false) {
            $this->_setData($data);
            return;
        }

        $sql = 'SELECT
                  *
                FROM
                    ' . $this->_getDatabase() . '
                WHERE
                    id = ?';

        $data = $this->_getZendDb()->fetchRow($sql, $this->_id);

        // Fetch durations
        $data['durations'] = HHF_Domain_Share_Duration::fetchByParent(
            $this->_farm,
            $this->_id
        );

        // fetch sizes
        $data['sizes'] = HHF_Domain_Share_Size::fetchByParent(
            $this->_farm,
            $this->_id
        );

        $this->_setData(
            $data
        );

        $cache->save($this->_data, (string) $this);
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

        $db->beginTransaction();

        try {

            $durations = (!empty($data['durations'])) ?
                $data['durations'] : null;

            $sizes = (!empty($data['sizes'])) ?
                $data['sizes'] : null;

            unset($data['durations']);
            unset($data['sizes']);


            $db->insert(
                $this->_getDatabase(),
                $this->_prepareData($data)
            );
            $data['id'] = $db->lastInsertId();

            $data['durations'] = array();

            if (!empty($durations)) {

                foreach ($durations as $key => $duration) {
                    $duractionObj = new HHF_Domain_Share_Duration(
                        $this->_farm
                    );

                    $duration['shareId'] = $data['id'];

                    $duractionObj->insert($duration);

                    $data['durations'][$key] = $duractionObj;
                }
            }

            $data['sizes'] = array();

            if (!empty($sizes)) {
                foreach ($sizes as $key => $size) {
                    $sizeObj = new HHF_Domain_Share_Size(
                        $this->_farm
                    );

                    $size['shareId'] = $data['id'];

                    $sizeObj->insert($size);

                    $data['sizes'][$key] = $sizeObj;
                }
            }

            $db->commit();

        } catch(Exception $e) {

            $db->rollBack();

            throw $e;
        }

        $this->_setData($data);

        $this->_getZendCache()->save($this->_data, (string) $this);
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

            $durations = (!empty($data['durations'])) ?
                $data['durations'] : null;

            $sizes = (!empty($data['sizes'])) ?
                $data['sizes'] : null;

            unset($data['durations']);
            unset($data['sizes']);


            $db = $this->_getZendDb();

            $db->beginTransaction();

            try {

                $db->update(
                    $this->_getDatabase(),
                    $this->_prepareData($data, false),
                    array('id = ?' => $this->_id)
                );

                $data['durations'] = $this->_updateRelations(
                    $this->_data['durations'],
                    $durations,
                    'HHF_Domain_Share_Duration'
                );

                $data['sizes'] = $this->_updateRelations(
                    $this->_data['sizes'],
                    $sizes,
                    'HHF_Domain_Share_Size'
                );

                $db->commit();

            } catch (Exception $e) {

                $db->rollBack();

                throw $e;
            }

            $this->_setData($data, false);

            $this->_getZendCache()->save($this->_data, (string) $this);
        }
    }

    // Delete all shares at a location.
    function deleteByLocation($farmId, $locationId){
        $db = $this->_getZendDb();
        $db->beginTransaction();

        try {
            $db->delete('farmnik_hh_'.$farmId.'.customersShares', 'locationId='.$locationId);
            $db->commit();

        } catch (Exception $e) {

            $db->rollBack();

            throw $e;
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

        foreach ($newData as $row) {

            $row['shareId'] = $this->_data['id'];

            $object = new $class($this->_farm);
            $object->insert($row);

            $relations[] = $object;
        }

        return $relations;
    }

    /**
     * Get share duration by duration ID
     * @param int $id
     * @return HHF_Domain_Share_Duration|null
     */
    public function getDurationById($id)
    {
        foreach ($this->durations as $duration) {
            if ($duration->id == $id) {
                return $duration;
            }
        }
    }

    /**
     * Get share size by size ID
     *
     * @param type $id
     * @return HHF_Domain_Share_Size|null
     */
    public function getSizeById($id)
    {
        foreach ($this->sizes as $size) {
            if ($size->id == $id) {
                return $size;
            }
        }
    }

    /**
     * is this share in season?
     *
     * @param Zend_Date $date
     * @return boolean
     */
    public function isInSeason(Zend_Date $date = null)
    {
        return true;

        $parentDuration = $this->getParentDuration();

        if (empty($parentDuration)) {
            return false;
        }

        $currentDate = ($date instanceof Zend_Date) ? $date : new Zend_Date();

        /* @var $startDate Zend_Date */
        $startDate = clone $currentDate;
        $startDate->setWeek($parentDuration->startWeek);
        $startDate->set(1, Zend_Date::WEEKDAY_8601);

        /* @var $endDate Zend_Date */
        $endDate = clone $startDate;
        $endDate->addWeek($parentDuration->iterations);
        $endDate->set(1, Zend_Date::WEEKDAY_8601);

        if ($currentDate->compareDate($startDate) >= 0 &&
            $currentDate->compareDate($endDate) <= 0) {

            return true;
        }

        return false;
    }

    /**
     * Get the parent (longest running) duration
     *
     * @return HHF_Domain_Share_Duration
     */
    public function getParentDuration()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        if (empty($this->_data['durations'])) {
            return null;
        }

        $parent = null;
        $greatestIteration = -1;

        foreach ($this->_data['durations'] as $duration) {
            if ($greatestIteration < $duration->iterations) {
                $parent = $duration;
                $greatestIteration = $duration->iterations;
            }
        }

        return $parent;
    }

    public function getDates($durationId, $locationId = null)
    {
        return new HHF_Domain_Share_Iterator_Dates($this, $durationId, $locationId);
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
                        'name' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'details' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        ),
                        'image' => array(
                            new Zend_Filter_Null()
                        ),
                        'planFixedDates' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        ),
                        'customerPurchaseStartDate' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        )
                    ),
                    array(
                        'year' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid year is required')
                            )
                        ),
                        'name' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
                            )
                        ),
                        'deliverySchedule' => array(
                            new Zend_Validate_InArray(self::$deliverySchedules),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid delivery schedule is required')
                            )
                        ),
                        'purchaseStartDate' => array(
                            new Zend_Validate_Date('yyyy-M-d'),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid start date is required')
                            )
                        ),
                        'customerPurchaseStartDate' => array(
                            new Zend_Validate_Date('yyyy-M-d'),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid start date is required')
                            )
                        ),
                        'locationPrice' => array(
                            new Zend_Validate_InArray(
                                array(
                                    0, 1
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => 0
                        ),
                        'details' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'image' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'planFixedDates' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'enabled' => array(
                            new Zend_Validate_InArray(
                                array(
                                    0, 1
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Enabled status is required')
                            )
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
     * Validate share data for data storage
     *
     * @param array $dataToValidate
     * @param array $options
     * @return array Validated data
     * @throws HH_Object_Exception_Validation
     */
    public static function validate(&$dataToValidate, $options)
    {
        $errors = array();

        $filterShare = HHF_Domain_Share::getFilter(
            HHF_Domain_Location::FILTER_NEW
        );

        $filterDuration = HHF_Domain_Share_Duration::getFilter(
            HHF_Domain_Location::FILTER_NEW
        );

        $filterDurationLocation = HHF_Domain_Share_Duration_Location::getFilter(
            HHF_Domain_Location::FILTER_NEW,
            array(
                'farm' => $options['farm']
            )
        );

        $filterSize = HHF_Domain_Share_Size::getFilter(
            HHF_Domain_Location::FILTER_NEW
        );

        $durations = array();
        $durationsLocations = array();

        if (isset($dataToValidate['durations'])) {
            $durations = $dataToValidate['durations'];
        }

        $sizes = array();

        if (isset($dataToValidate['sizes'])) {
            $sizes = $dataToValidate['sizes'];
        }

        $filterShare->setData($dataToValidate);

        if (!$filterShare->isValid()) {
            $errors = $filterShare->getMessages();
        } else {

            $data = $filterShare->getUnescaped();

            if (!empty($_FILES['imageUpload']['name'])) {

                if (!empty($options['share']['image'])) {

                    $file = new HHF_Domain_File(
                        $options['farm'],
                        $options['share']['image']
                    );

                } else {

                    $file = new HHF_Domain_File(
                        $options['farm']
                    );
                }

                try {

                    $file->upload(
                        'imageUpload',
                        HHF_Domain_File::TYPE_IMAGE,
                        HHF_Domain_File::CATEGORY_SHARES,
                        $data['name']
                    );

                    $data['image'] = $file->id;

                } catch (Exception $e) {

                    $errors['imageUpload'] = array(
                        self::_getStaticZendTranslate()->_(
                            'Unable to receive uploaded file.'
                        )
                    );

                }
            }
        }

        if (!empty($durations)) {
            $count = 0;

            foreach ($durations as $duration) {
                $filterDuration->setData($duration);

                if (!$filterDuration->isValid()) {
                    if (!isset($errors['durations'])) {
                        $errors['durations'] = array();
                    }

                    $errors['durations'][$count] = $filterDuration->getMessages();
                } else {
                    if (!isset($data['durations'])) {
                        $data['durations'] = array();
                    }
                    $data['durations'][$count] = $filterDuration->getUnescaped();

                    if (!empty($duration['locations'])) {
                        foreach ($duration['locations'] as $location) {
                            if (empty($location)) {
                                continue;
                            }

                            $filterDurationLocation->setData(
                                array('locationId' => $location)
                            );

                            if (!$filterDurationLocation->isValid()) {
                                if (!isset($errors['durations'])) {
                                    $errors['durations'] = array();
                                }

                                $errors = $filterDurationLocation->getMessages();

                                $errors['durations'][$count]['locations'] = $errors['locationId'];
                            } else {
                                if (!isset($data['durations'][$count]['locations'])) {
                                    $data['durations'][$count]['locations'] = array();
                                }

                                $data['durations'][$count]['locations'][] = array(
                                    'locationId' => $filterDurationLocation->getUnescaped('locationId')
                                );
                            }
                        }
                    }

                }

                ++$count;
            }
        }

        if (!empty($sizes)) {
            $count = 0;

            foreach ($sizes as $size) {
                $filterSize->setData($size);

                if (!$filterSize->isValid()) {
                    if (!isset($errors['sizes'])) {
                        $errors['sizes'] = array();
                    }

                    $errors['sizes'][$count] = $filterSize->getMessages();
                } else {
                    if (!isset($data['sizes'])) {
                        $data['sizes'] = array();
                    }
                    $data['sizes'][$count] = $filterSize->getUnescaped();
                }
                ++$count;
            }
        }

        if (!empty($errors)) {
            throw new HH_Object_Exception_Validation($errors);
        }

        return $data;
    }

    public static function fetchSingle(HH_Domain_Farm $farm, $options = array())
    {
        return self::fetchShares($farm, $options)[0];
    }

    /**
     * Fetch all pages
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchShares(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();

        if (isset($options['fetch'])) {

            switch ($options['fetch']) {
                case self::FETCH_ENABLED :
                    $sql .= ' WHERE enabled = 1';

                    break;
                case self::FETCH_PURCHASABLE :
                    if (empty($options['farmer']) || $options['farmer']->isEmpty()) {
                        $sql .= ' WHERE enabled = 1 AND purchaseStartDate <= DATE(NOW()) AND year >= YEAR(DATE_SUB(NOW(), INTERVAL 1 YEAR))';
                    } else {
                        $sql .= ' WHERE enabled = 1 AND (purchaseStartDate <= DATE(NOW()) || customerPurchaseStartDate <= DATE(NOW())) AND year >= YEAR(DATE_SUB(NOW(), INTERVAL 1 YEAR))';
                    }

                    break;
            }
        }

        if (isset($options['order'])) {

            switch ($options['order']) {
                case self::ORDER_NAME :
                    $sql .= ' ORDER BY `order` DESC, name DESC';

                    break;
            }
        }

        if (isset($options['shareId'])) {
            $sql .= ' WHERE id = ?' ;
            $bind = $options['shareId'];
        }

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        $currentDate = Zend_Date::now();

        foreach ($result as $share) {

            if (isset($options['fetch']) && $options['fetch'] == self::FETCH_PURCHASABLE) {
                // Fetch durations
                $durations = HHF_Domain_Share_Duration::fetchByParent(
                    $farm,
                    $share['id'],
                    array(
                        'order' => HHF_Domain_Share_Duration::ORDER_ITERATIONS
                    )
                );

                $soldOut = false;

                foreach ($durations as $key => $duration) {

                    // check that we are not passed cutoff week
                    if (is_numeric($duration->cutOffWeek)
                        && $currentDate->compareDate(
                            HHF_Domain_Share_Duration::staticGetStartDate(
                                $farm,
                                $duration->cutOffWeek,
                                $share['year']
                            )
                        ) >= 0) {

                        unset($durations[$key]);
                        continue;
                    }

                    // check that we are not passed the end date
                    $endDate = HHF_Domain_Share_Duration::staticGetEndDate(
                        $farm,
                        $duration->startWeek,
                        $share['year'],
                        $share['deliverySchedule'],
                        $duration->iterations
                    );

                    if ($currentDate->compareDate($endDate) >= 0) {
                        unset($durations[$key]);
                        continue;
                    }

                    //check that we still have shares left
                    if (is_numeric($duration->shares)) {
                        $durationCount = HHF_Domain_Customer_Share::fetchSharesCount(
                            $farm,
                            array(
                                'shareId' => $share['id'],
                                'shareDurationId' => $duration->id,
                                'year' => $share['year']
                            )
                        );

                        if ($durationCount >= $duration->shares) {
                            unset($durations[$key]);
                            $soldOut = true;
                            continue;
                        }
                    }
                }

                if (empty($durations) && !$soldOut) {
                    continue;
                } else if (empty($durations) && $soldOut) {
                    $share['durations'] = array();
                } else {
                    $share['durations'] = array_values($durations);
                }
            } else {
                $share['durations'] = HHF_Domain_Share_Duration::fetchByParent(
                    $farm,
                    $share['id'],
                    array(
                        'order' => HHF_Domain_Share_Duration::ORDER_ITERATIONS
                    )
                );
            }

            // fetch sizes
            $share['sizes'] = HHF_Domain_Share_Size::fetchByParent(
                $farm,
                $share['id'],
                array(
                    'order' => HHF_Domain_Share_Size::ORDER_SIZE
                )
            );

            $return[] = new self(
                $farm,
                $share['id'],
                $share,
                $options
            );
        }

        return $return;
    }

    function getWeeks(){

        // Fetch durations
        $data['durations'] = HHF_Domain_Share_Duration::fetchByParent(
            $this->_farm,
            $this->_id
        );

        $weeks = array();

        foreach($data['durations'] as $duration){
            $year = $this->year;
            $iterations = 0;

            for($i = $duration->startWeek; 1==1 ; $i++){
                if($i > 52){
                    $i = 1;
                    $year++;
                }
                array_push($weeks, $year . 'W' . sprintf('%\'02d', $i));
                $iterations++;

                // I thought the cutOffWeek was the end of a delivery cycle. No?
                // If so, add this:
                // || ((!empty($duration->cutOffWeek) && $duration->cutOffWeek == $i)
                if($iterations == $duration->iterations){
                    break;
                }
            }
        }

        return $weeks;
    }

}
