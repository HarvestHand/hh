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
 * @copyright $Date: 2016-07-01 09:23:45 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Domain
 */

/**
 * Description of Farm
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Farm.php 979 2016-07-01 12:23:45Z farmnik $
 * @package   HH_Domain
 * @copyright $Date: 2016-07-01 09:23:45 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Domain_Farm extends HH_Object_Db
{
    const STATUS_ACTIVE    = 'ACTIVE';
    const STATUS_CLOSED    = 'CLOSED';
    const STATUS_TRIAL     = 'TRIAL';
    const STATUS_OVERDUE   = 'OVERDUE';
    const TYPE_CSA         = 'CSA';
    const TYPE_VENDOR      = 'VENDOR';
    const TYPE_DISTRIBUTOR = 'DISTRIBUTOR';
    const FILTER_NEW       = 'new';

    protected $_primaryFarmer = null;
    protected $_farmers = null;
    protected $_preferences = array();

    public static $status = array(
        self::STATUS_ACTIVE,
        self::STATUS_TRIAL,
        self::STATUS_CLOSED,
        self::STATUS_OVERDUE
    );

    public static $types = array(
        self::TYPE_CSA,
        self::TYPE_DISTRIBUTOR,
        self::TYPE_VENDOR
    );

    /**
     * Get farm software latest version
     *
     * @return string
     */
    public static function getLatestVersion()
    {
        return '1.0';
    }

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

        $sql = 'SELECT * FROM ' . $this->_getDatabase() .  ' WHERE id = ?';

        $this->_setData(
            $this->_getZendDb()->fetchRow($sql, $this->_id)
        );

        $cache->save($this->_data, (string) $this);
        $cache->save(
            $this->_data,
            'HH_Domain_Farm_Subdomain' . $this->_data['subdomain']
        );
        if (!empty($this->_data['domain'])) {
            $cache->save(
                $this->_data,
                'HH_Domain_Farm_Domain'
                    . preg_replace(
                        '/[^a-zA-Z0-9_]/', '_', $this->_data['domain']
                    )
            );
        }
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

            $sql = 'DELETE FROM ' . $this->_getDatabase() .  ' WHERE id = ?';

            $this->_getZendDb()->query($sql, $this->_id);

            $this->_getZendCache()
                ->remove((string) $this);

            $this->_getZendCache()
                ->remove('HH_Domain_Farm_Subdomain' . $this->_data['subdomain']);

            if (!empty($this->_data['domain'])) {
                $this->_getZendCache()
                    ->remove(
                        'HH_Domain_Farm_Domain'
                            . preg_replace(
                                '/[^a-zA-Z0-9_]/', '_', $this->_data['domain']
                            )
                    );
            }
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

        $db->insert($this->_getDatabase(), $this->_prepareData($data));
        $data['id'] = $db->lastInsertId();

        $this->_setData($data);

        $this->_getZendCache()->save($this->_data, (string) $this);
        $this->_getZendCache()->save(
            $this->_data,
            'HH_Domain_Farm_Subdomain' . $this->_data['subdomain']
        );
        if (!empty($this->_data['domain'])) {
            $this->_getZendCache()->save(
                $this->_data,
                'HH_Domain_Farm_Domain'
                    . preg_replace(
                        '/[^a-zA-Z0-9_]/', '_', $this->_data['domain']
                    )
            );
        }

        // create db
        $this->_createDb();

        // create stats
        $this->_createStats();
    }

    protected function _createDb()
    {
        $db = $this->_getZendDb();

        if (Bootstrap::$env == 'production') {

            $url = 'http://localhost:2086/json-api/cpanel?';
            $key = Bootstrap::getZendConfig()->resources->whm->key;
            $context = stream_context_create(
                array(
                    'http' => array(
                        'header' => 'Authorization: WHM root:' . $key
                    )
                )
            );

            $params = array(
                'cpanel_jsonapi_user' => 'farmnik',
                'cpanel_jsonapi_module' => 'Mysql',
                'cpanel_jsonapi_func' => 'adddb',
                'cpanel_jsonapi_apiversion' => 1,
                'arg-0' => 'hh_' . $this->_id
            );

            file_get_contents(
                $url . http_build_query($params),
                false,
                $context
            );

            $params = array(
                'cpanel_jsonapi_user' => 'farmnik',
                'cpanel_jsonapi_module' => 'Mysql',
                'cpanel_jsonapi_func' => 'adduserdb',
                'cpanel_jsonapi_apiversion' => 1,
                'arg-0' => 'hh_' . $this->_id,
                'arg-1' => 'farmnik',
                'arg-2' => 'all'
            );

            file_get_contents(
                $url . http_build_query($params),
                false,
                $context
            );

        } else {
            $db->query('CREATE DATABASE farmnik_hh_' . $this->id);
        }

        $db->query('USE farmnik_hh_' . $this->_id);

        $sql = file_get_contents(Bootstrap::$root . 'data/sql/1.0/base.sql');

        $queries = preg_split(
            "/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/",
            $sql
        );

        foreach ($queries as $query) {

            if (strlen(trim($query)) > 0) {
                $db->query($query);
            }
        }

        $db->query('USE farmnik_hh');
    }

    function _createStats()
    {
        $piwikUrl = 'http://www.'. Bootstrap::$rootDomain .'/_stats/index.php?';

        $urls = array(
            'http://' . $this->subdomain . '.'. Bootstrap::$rootDomain .'/'
        );

        if (!empty($this->domain)) {
            $urls[] = 'http://' . $this->domain .'/';
        }

        $params = array(
            'module'     => 'API',
            'method'     => 'SitesManager.addSite',
            'format'     => 'PHP',
            'token_auth' => Bootstrap::getZendConfig()->resources->website->piwik->tokenAuth,
            'siteName'   => $this->name,
            'urls'       => $urls,
            'timezone'   => $this->timezone
        );

        $rawResult = file_get_contents(
            $piwikUrl . http_build_query($params)
        );

        $result = unserialize($rawResult);

        if ($result !== false) {
            $this->getPreferences()->replace(
                'piwikId',
                (int) $result,
                'website'
            );
        }
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

            $this->_getZendDb()->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array('id = ?' => $this->_id)
            );

            $this->_setData($data, false);

            $this->_getZendCache()->save($this->_data, (string) $this);
            $this->_getZendCache()->save(
                $this->_data,
                'HH_Domain_Farm_Subdomain' . $this->_data['subdomain']
            );
            if (!empty($this->_data['domain'])) {
                $this->_getZendCache()->save(
                    $this->_data,
                    'HH_Domain_Farm_Domain'
                        . preg_replace(
                            '/[^a-zA-Z0-9_]/', '_', $this->_data['domain']
                        )
                );
            }
        }
    }

    public function getFarmers()
    {
        if ($this->_farmers instanceof HH_Object_Collection) {
            return $this->_farmers;
        }

        $this->_farmers = HH_Domain_Farmer::fetch(
            array(
                'where' => array(
                    'role' => HH_Domain_Farmer::ROLE_FARMER,
                    'farmId' => $this->_id,
                )
            )
        );

        return $this->_farmers;
    }

    /**
     * Get types of farm
     *
     * @return array
     */
    public function getType()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return explode(',', $this->_data['type']);
    }

    /**
     * Check type of farm
     *
     * @param String $type
     * @return bool
     */
    public function isType($type)
    {
        return in_array($type, $this->getType());
    }

    /**
     * Check if this is the master farm
     *
     * @return bool
     */
    public function isMasterFarm()
    {
        $masterFarmID = Bootstrap::getZendConfig()->resources->hh->farm;
        return $masterFarmID == $this->id;
    }

    /**
     * Get networks where I am the head
     * @return HH_Object_Collection_Db
     */
    public function getParentNetworks($status = null)
    {
        $where = array(
            'farmId' => $this->_id
        );

        if (!empty($status)) {
            $where['status'] = $status;
        }

        return HH_Domain_Network::fetch(
            array(
                'where' => $where
            )
        );
    }

    /**
     * get network where I am a participant
     * @return HH_Object_Collection_Db
     */
    public function getChildNetworks($status = null)
    {
        $where = array(
            'relationId' => $this->_id
        );

        if (!empty($status)) {
            $where['status'] = $status;
        }

        return HH_Domain_Network::fetch(
            array(
                'where' => $where
            )
        );
    }

    public function getFarmerEmails()
    {
        $emails = array();

        foreach ($this->getFarmers() as $farmer) {
            if (!empty($farmer['email'])) {
                $emails[$farmer['email']] = $farmer['email'];
            }
            if (!empty($farmer['email2'])) {
                $emails[$farmer['email2']] = $farmer['email2'];
            }
        }

        if (!empty($this->email)) {
            $emails[$this->email] = $this->email;
        }

        return $emails;
    }

    /**
     * Get primary farmer
     *
     * @return HH_Domain_Farmer
     */
    public function getPrimaryFarmer()
    {
        if ($this->_primaryFarmer instanceof HH_Domain_Farmer) {
            return $this->_primaryFarmer;
        }

        $this->_primaryFarmer = HH_Domain_Farmer::singleton(
            $this->primaryFarmerId,
            null,
            $this->_config
        );

        return $this->_primaryFarmer;
    }

    /**
     * Check if farmer is primary farmer
     *
     * @param HH_Domain_Farmer $farmer
     * @return boolean
     */
    public function isPrimaryFarmer(HH_Domain_Farmer $farmer)
    {
        if ($this->primaryFarmerId == $farmer->id
            && $farmer->farmId == $this->id) {

            return true;
        }

        return false;
    }

    /**
     * Check if farmer is belongs with this farm
     *
     * @param HH_Domain_Farmer $farmer
     * @return boolean
     */
    public function isFarmer(HH_Domain_Farmer $farmer)
    {
        if ($farmer->farmId == $this->id) {

            return true;
        }

        return false;
    }

    /**
     * Get farm preferences
     *
     * @param string $resource
     * @return HHF_Preferences
     */
    public function getPreferences($resource = null)
    {
        if (isset($this->_preferences[$resource]) &&
            $this->_preferences[$resource] instanceof HHF_Preferences) {

            return $this->_preferences[$resource];
        }

        $this->_preferences[$resource] = new HHF_Preferences(
            $this,
            HHF_Domain_Preference::TYPE_FARM,
            null,
            $resource
        );

        return $this->_preferences[$resource];
    }

    public function getBaseUri($scheme = 'http', $forceSubdomain = false)
    {
        if (!empty($this->domain) && !$forceSubdomain) {
            return $scheme . '://www.' . $this->domain . '/';
        } else if (!empty($this->subdomain)) {
            return $scheme . '://' . $this->subdomain . '.'
                . Bootstrap::$rootDomain . '/';
        } else {
            return $scheme . '://' . $this->id . '.'
                . Bootstrap::$rootDomain . '/';
        }
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
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
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
                        'address' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('An address is required')
                            )
                        ),
                        'address2' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'city' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A city is required')
                            )
                        ),
                        'state' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A state is required')
                            )
                        ),
                        'zipCode' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A zip code is required')
                            )
                        ),
                        'country' => array(
                            new Zend_Validate_StringLength(2, 2),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A country is required')
                            )
                        ),
                        'timeZone' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                'America/Halifax',
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Time zone is invalid')
                            )
                        ),
                        'telephone' => array(
                            new Zend_Validate_StringLength(0, 20),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Telephone is invalid')
                            )
                        ),
                        'fax' => array(
                            new Zend_Validate_StringLength(0, 20),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Fax is invalid')
                            )
                        ),
                        'email' => array(
                            new Zend_Validate_EmailAddress(),
                            new Zend_Validate_StringLength(0, 150),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Email is invalid'),
                                $translate->_('Email is invalid'),
                            )
                        ),
                        'subdomain' => array(
                            new Zend_Validate_Regex('/^[^-][0-9a-z-]+[^-]$/i'),
                            new Zend_Validate_StringLength(0, 100),
                            new HH_Validate_SubdomainUnique(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Letters, numbers and dashes only please (no dashes at the beginning or end)'),
                                $translate->_('Name is to long'),
                                $translate->_('Name is taken')
                            )
                        ),
                        'domain' => array(
                            new Zend_Validate_Hostname(),
                            new HH_Validate_DomainUnique(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('This domain name does not appear to be valid'),
                                $translate->_('Domain name is already used')
                            )
                        ),
                        'type' => array(
                            new Zend_Validate_InArray(HH_Domain_Farm::$types),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
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
     * Fetch farm by subdomain
     *
     * @param string $subdomain
     * @return HH_Domain_Farm|null
     */
    public static function fetchSingleBySubdomain($subdomain)
    {
        $cache = self::_getStaticZendCache();
        $key = 'HH_Domain_Farm_Subdomain' . $subdomain;

        if (($data = $cache->load($key)) !== false) {
            return new self($data['id'], $data);
        }

        $db = self::_getStaticZendDb();

        if (!is_numeric($subdomain)) {
            $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase() .  '
            WHERE
                subdomain = ?';
        } else {
            $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase() .  '
            WHERE
                id = ?';
        }

        $bind = array($subdomain);

        $rawData = $db->fetchAll(
            $sql,
            $bind
        );

        $results = null;

        foreach ($rawData as $row) {
            if (is_numeric($subdomain)) {
                $cache->save(
                    $row,
                    'HH_Domain_Farm_Subdomain' . $row['id']
                );
            } else {
                $cache->save(
                    $row,
                    'HH_Domain_Farm_Subdomain' . $row['subdomain']
                );
            }

            return new self($row['id'], $row);
        }

        return $results;
    }

    /**
     * Fetch farm by domain
     *
     * @param string $domain
     * @return HH_Domain_Farm|null
     */
    public static function fetchSingleByDomain($domain)
    {
        $cache = self::_getStaticZendCache();
        $key = 'HH_Domain_Farm_Domain'
            . preg_replace('/[^a-zA-Z0-9_]/', '_', $domain);

        if (($data = $cache->load($key)) !== false) {
            return new self($data['id'], $data);
        }

        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase() .  '
            WHERE
                domain = ?';

        $bind = array($domain);

        $rawData = $db->fetchAll(
            $sql,
            $bind
        );

        $results = null;

        foreach ($rawData as $row) {
            $cache->save(
                $row,
                'HH_Domain_Farm_Domain'
                    . preg_replace('/[^a-zA-Z0-9_]/', '_', $row['domain'])
            );

            return new self($row['id'], $row);
        }

        return $results;
    }

    /**
     * Fetch available distributors for a farm
     *
     * @param HH_Domain_Farm $relation
     * @return HH_Object_Collection_Db
     */
    public static function fetchDistributors(HH_Domain_Farm $relation)
    {
        $distributors = self::fetch(
            array(
                'where' => array(
                    'FIND_IN_SET(\'' . HH_Domain_Farm::TYPE_DISTRIBUTOR . '\', type) > 0'
                )
            )
        );

        if (count($distributors)) {
            $networks = HH_Domain_Network::fetch(
                array(
                    'where' => array(
                        'relationId' => $relation->id
                    )
                )
            );

            if (count($networks)) {

                $distributors->filter(function($distributor) use ($relation, $networks) {
                    if ($distributor->id == $relation->id) {
                        return false;
                    }

                    foreach ($networks as $network) {
                        if ($distributor->id == $network->farmId) {
                            return false;
                        }
                    }

                    return true;
                });

            } else {
                $distributors->filter(function($distributor) use ($relation) {
                    if ($distributor->id == $relation->id) {
                        return false;
                    }

                    return true;
                });
            }
        }

        return $distributors;
    }

    function getId(){
        return $this->_id;
    }
}
