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
 * @copyright $Date: 2014-12-28 19:10:48 -0400 (Sun, 28 Dec 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Add on model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Addon.php 824 2014-12-28 23:10:48Z farmnik $
 * @copyright $Date: 2014-12-28 19:10:48 -0400 (Sun, 28 Dec 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Addon extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;
    const FETCH_ENABLED = 'ENABLED';
    const FETCH_PURCHASEABLE = 'PUCHASEABLE';

    const PRICE_BY_UNIT = 'UNIT';
    const PRICE_BY_WEIGHT = 'WEIGHT';

    const UNIT_TYPE_UNIT = 'UNIT';
    const UNIT_TYPE_OZ = 'OZ';
    const UNIT_TYPE_LB = 'LB';
    const UNIT_TYPE_G = 'G';
    const UNIT_TYPE_KG = 'KG';

    public static $priceBys = array(
        self::PRICE_BY_UNIT,
        self::PRICE_BY_WEIGHT
    );

    public static $unitTypes = array(
        self::UNIT_TYPE_G,
        self::UNIT_TYPE_KG,
        self::UNIT_TYPE_LB,
        self::UNIT_TYPE_OZ,
        self::UNIT_TYPE_UNIT
    );

    public function __construct(
        \HH_Domain_Farm $farm,
        $id = null,
        $data = null,
        $config = array()
    ) {
        $this->_defaultObservers[] = 'HHF_Domain_Addon_Observer';

        parent::__construct($farm, $id, $data, $config);
    }

    /**
     * @return HHF_Object_Collection_Db
     */
    public function getLocations()
    {
        return HHF_Domain_Addon_Location::fetch(
            $this->getFarm(),
            array(
                'where' => array(
                    'addonId' => $this->_id
                )
            )
        );
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
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        ),
                        'source' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        ),
                        'distributorId' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        ),
                        'inventory' => array(
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        ),
                        'inventoryMinimumAlert' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        ),
                        'unitOrderMinimum' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        ),
                        'expirationDate' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        ),
                        'externalId' => array(
                            new Zend_Filter_Null(Zend_Filter_Null::INTEGER)
                        )
                    ),
                    array(
                        'name' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
                            )
                        ),
                        'source' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Source looks too long')
                            )
                        ),
                        'details' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'image' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('This doesn\'t look like a valid image')
                            )
                        ),
                        'inventory' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid number is required')
                            )
                        ),
                        'inventoryMinimumAlert' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid number is required')
                            )
                        ),
                        'price' => array(
                            new Zend_Validate_Float(),
                            new Zend_Validate_Between(
                                array('min' => 0, 'max' => 9999.99)
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'priceBy' => array(
                            new Zend_Validate_InArray(self::$priceBys),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::DEFAULT_VALUE
                                => self::PRICE_BY_UNIT
                        ),
                        'pendingOnOrder' => array(
                            new Zend_Validate_InArray(
                                array(0, 1)
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::DEFAULT_VALUE => 0
                        ),
                        'unitType' => array(
                            new Zend_Validate_InArray(self::$unitTypes),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::DEFAULT_VALUE
                                => self::UNIT_TYPE_UNIT
                        ),
                        'unitOrderMinimum' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'categoryId' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid category token is required')
                            )
                        ),
                        'certification' => array(
                            new Zend_Validate_StringLength(0, 255),
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
                        ),
                        'expirationDate' => array(
                            new Zend_Validate_Date('yyyy-M-d'),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid availability date is required')
                            )
                        ),
                        'distributorId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                            null,
                                            'HH_Domain_Farm'
                                        ),
                                    'field' => 'id',
                                    'exclude' => 'FIND_IN_SET(\'' . HH_Domain_Farm::TYPE_DISTRIBUTOR . '\', type) > 0',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid distributor is required')
                            )
                        ),
                        'vendorId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                            null,
                                            'HH_Domain_Farm'
                                        ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid vendor is required')
                            )
                        ),
                        'externalId' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Invalid external ID')
                            )
                        ),
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
     * Validate addon data for data storage
     *
     * @param array $dataToValidate
     * @param array $options
     * @return array Validated data
     * @throws HH_Object_Exception_Validation
     */
    public static function validate(&$dataToValidate, $options)
    {
        $errors = array();

        $filterAddon = HHF_Domain_Addon::getFilter(
            HHF_Domain_Addon::FILTER_NEW
        );

        $filterAddon->setData($dataToValidate);

        if (!$filterAddon->isValid()) {
            $errors = $filterAddon->getMessages();
        } else {
            $data = $filterAddon->getUnescaped();
        }

        if (!empty($errors)) {
            throw new HH_Object_Exception_Validation($errors);
        }

        return $data;
    }

    /**
     * Fetch all pages
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchAddons(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    a.*,
                    c.name as categoryName
                FROM
                    ' . self::_getStaticDatabase($farm) . ' AS a' . '
                LEFT JOIN
                    ' . self::_getStaticDatabase($farm, 'HHF_Domain_Addon_Location') . ' AS al
                ON
                    al.addonId = a.id
                LEFT JOIN
                    ' . self::_getStaticDatabase($farm, 'HHF_Domain_Addon_Category') . ' AS c
                ON
                    c.id = a.categoryId';

        $bind = array();
        $orderBy = '';

        if (isset($options['fetch'])) {
            switch ($options['fetch']) {
                case self::FETCH_ENABLED :
                    $where[] = 'a.enabled = 1';

                    break;
                case self::FETCH_PURCHASEABLE :
                    $where[] = 'a.enabled = 1';
                    $where[] = '(a.inventory IS NULL OR a.inventory != 0)';
                    $where[] = '(a.expirationDate IS NULL OR a.expirationDate > ?)';
                    $bind[] = Zend_Date::now()->setTimezone('UTC')->toString('yyyy-MM-dd');

                    if (!empty($options['locations'])) {
                        $locationIds = array();

                        if (is_array($options['locations'])) {
                            foreach ($options['locations'] as $location) {
                                $locationIds[] = (int) $location['id'];
                            }
                        } else {
                            $locationIds[] = (int) $options['location']['id'];
                        }

                        $where[] = '(al.locationId IS NULL OR al.locationId IN(' . implode(',', $locationIds) . '))';
                    }

                    if (!empty($options['networkStatus'])) {
                        $networkFarms = $farm->getParentNetworks($options['networkStatus']);

                        $vendorIds = array();

                        foreach ($networkFarms as $networkFarm) {
                            $vendorIds[] = $networkFarm['relationId'];
                        }

                        if (!empty($vendorIds)) {
                            $where[] = '(a.vendorId IN(' . implode(',', $vendorIds) . ') OR a.vendorId IS NULL)';
                        } else {
                            $where[] = 'a.vendorId IS NULL';
                        }

                    }

                    if (!empty($options['search'])) {
                        $where[] = '(a.name like ' . $db->quote('%' . $options['search'] . '%') .
                            ' OR a.details like ' . $db->quote('%' . $options['search'] . '%') .  ')';
                    }

                    if (!empty($options['orderBy']) && $options['orderBy'] == 'source') {
                        $orderBy = ' ORDER BY a.source ASC, a.categoryId ASC, a.name ASC';
                    } else {
                        $orderBy = ' ORDER BY a.categoryId ASC, a.name ASC';
                    }

                    break;
            }
        }

        if (!empty($options['ids'])) {
            $where[] = 'a.id IN(' . implode(',', $options['ids']) . ')';
        }

        if (!empty($options['categoryId'])) {
            $where[] = 'categoryId = ?';
            $bind[] = $options['categoryId'];
        }

        if (!empty($options['source'])) {
            $where[] = 'source = ?';
            $bind[] = $options['source'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= $orderBy;

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $addon) {
            $return[] = new self(
                $farm,
                $addon['id'],
                $addon,
                $options
            );
        }

        return $return;
    }

    public static function fetchAllDisplay(HH_Domain_Farm $farm, $options = array())
    {
        return self::fetch(
            $farm,
            array(
                'countRows' => true,
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                        a.*,
                        CASE WHEN a.enabled = 0 THEN 0
                            WHEN a.inventory IS NOT NULL AND a.inventory = 0 THEN 0
                            WHEN a.expirationDate IS NOT NULL AND a.expirationDate < NOW() THEN 0
                            ELSE 1 END as active,
                        c.name as categoryName
                    FROM
                        __DATABASE__ as a
                    LEFT JOIN
                        __SCHEMA__.addonsCategories as c
                    ON
                        a.categoryId = c.id
                ',
                'columns' => array(
                    '*',
                    'CASE WHEN enabled = 0 THEN 0
                        WHEN inventory IS NOT NULL AND inventory = 0 THEN 0
                        WHEN expirationDate IS NOT NULL AND expirationDate < NOW() THEN 0
                        ELSE 1 END as active'
                ),
                'where' => $options['where'],
                'limit' => $options['limit'],
                'order' => (!empty($options['order']) ?
                    $options['order'] :
                    array(
                        array(
                            'column' => 'a.name',
                            'dir' => 'asc'
                        )
                    )
                )
            )
        );
    }

    /**
     * Add-on categories (depricated)
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchCategories(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
            category,
            categoryToken,
            count(*) as frequency
        FROM
            ' . self::_getStaticDatabase($farm) . '
        GROUP BY category
        ORDER BY frequency DESC';

        $result = $db->fetchAll($sql);

        $return = array();
        $translate = self::_getStaticZendTranslate();

        $defaultCategories = array(
            'Bread--Pastries' => 'Bread & Pastries',
            'Dairy' => 'Dairy',
            'Dry-Goods' => 'Dry Goods',  // ?
            'Fruit' => 'Fruit',  // ?
            'Herbs' => 'Herbs',  // ?
            'Meat-Eggs--Tofu' => 'Meat, Eggs & Tofu',
            'Other-Goodies' => 'Other Goodies',  // ?
            'Prepared-Foods' => 'Prepared Foods',  // ?
            'Vegetables' => 'Vegetables', // ?
            'Meals-for-Here-or-To-Go' => 'Meals for Here or To-Go',
            'Artisan-Products' => 'Artisan Products',
            'Health-Products' => 'Health Products',
            'Pantry--Preserves' => 'Pantry & Preserves',
            'Beverages' => 'Beverages'
        );

        if (!empty($result)) {
            foreach ($result as $row) {
                $return[$row['categoryToken']] = $row['category'];
            }

            if (!isset($options['onlyUnique'])) {

                foreach ($defaultCategories as $token => $defaultCategory) {
                    $category = $translate->_($defaultCategory);

                    if (!isset($return[$token])) {
                        $return[$token] = $category;
                    }
                }
            }

        } else {
            if (!isset($options['onlyUnique'])) {
                foreach ($defaultCategories as $token => $defaultCategory) {
                    $category = $translate->_($defaultCategory);

                    if (!isset($return[$token])) {
                        $return[$token] = $category;
                    }
                }
            }
        }

        asort($return);

        return $return;
    }

    /**
     * Add-on sources
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchSources(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
            source, vendorId
        FROM
            ' . self::_getStaticDatabase($farm) . '
        GROUP BY source, vendorId
        ORDER BY source ASC';

        $result = $db->fetchAll($sql);

        if (!empty($options['status'])) {
            $networks = $farm->getParentNetworks($options['status']);
        }

        $return = array();

        if (!empty($result)) {
            foreach ($result as $row) {
                if (empty($row['source'])) {
                    continue;
                }

                if (!empty($options['status']) && !empty($row['vendorId'])) {
                    $found = false;

                    foreach ($networks as $network) {
                        if ($network['relationId'] == $row['vendorId']) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        continue;
                    }
                }

                if (in_array($row['source'], $return)) {
                    continue;
                }

                $return[] = $row['source'];
            }
        }

        return $return;
    }
}
