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
 * @copyright $Date: 2015-06-04 17:01:06 -0300 (Thu, 04 Jun 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Domain
 */

/**
 * Description of Farmer
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Farmer.php 856 2015-06-04 20:01:06Z farmnik $
 * @package   HH_Domain
 * @copyright $Date: 2015-06-04 17:01:06 -0300 (Thu, 04 Jun 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Domain_Farmer extends HH_Object_Db
{
    const ROLE_ADMIN = 'ADMIN';
    const ROLE_FARMER = 'FARMER';
    const ROLE_MEMBER = 'MEMBER';
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'EDIT';
    const FILTER_LOGIN = 'login';
    const FILTER_PASSWORD = 'password';

    /**
     * @var HH_Domain_Farmer
     */
    public static $authenticated = null;

    public static $roles = array(
        self::ROLE_FARMER,
        self::ROLE_MEMBER
    );

    protected $_farm = null;
    protected $_customer = null;
    protected $_preferences = array();

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
            case self::FILTER_LOGIN :

                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        'password' => array(
                            new Zend_Validate_StringLength(0, 30),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid password is required')
                            )
                        ),
                        'userName' => array(
                            new Zend_Validate_StringLength(0, 50),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid user name is required')
                            )
                        ),
                        'role' => array(
                            new Zend_Validate_InArray(HH_Domain_Farmer::$roles),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                HH_Tools_Authentication::getLoginRole(),
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid user role is required')
                            )
                        ),
                        'farmId' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid farm is required')
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

            case self::FILTER_NEW :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        'firstName' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid first name is required')
                            )
                        ),
                        'lastName' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid last name is required')
                            )
                        ),
                        'email' => array(
                            new Zend_Validate_StringLength(0, 255),
                            new Zend_Validate_EmailAddress(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('The email address is not valid'),
                                $translate->_('The email address is not valid')
                            )
                        ),
                        'email2' => array(
                            new Zend_Validate_StringLength(0, 255),
                            new Zend_Validate_EmailAddress(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('The email address is not valid'),
                                $translate->_('The email address is not valid')
                            )
                        ),
                        'password' => array(
                            new Zend_Validate_StringLength(0, 30),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid password is required')
                            )
                        ),
                        'userName' => array(
                            new Zend_Validate_StringLength(0, 50),
                            new HH_Validate_UserNameUnique(
                                $options['role'],
                                (isset($options['farmer']) ? $options['farmer'] : null),
                                (isset($options['farm']) ? $options['farm'] : null)
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid user name is required'),
                                $translate->_('The user name is already taken')
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
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        'firstName' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid first name is required')
                            )
                        ),
                        'lastName' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid last name is required')
                            )
                        ),
                        'email' => array(
                            new Zend_Validate_StringLength(0, 255),
                            new Zend_Validate_EmailAddress(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('The email address is not valid'),
                                $translate->_('The email address is not valid')
                            )
                        ),
                        'email2' => array(
                            new Zend_Validate_StringLength(0, 255),
                            new Zend_Validate_EmailAddress(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('The email address is not valid'),
                                $translate->_('The email address is not valid')
                            )
                        ),
                        'password' => array(
                            new Zend_Validate_StringLength(0, 30),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid password is required')
                            )
                        ),
                        'userName' => array(
                            new Zend_Validate_StringLength(0, 50),
                            new HH_Validate_UserNameUnique(
                                $options['role'],
                                (isset($options['farmer']) ? $options['farmer'] : null),
                                (isset($options['farm']) ? $options['farm'] : null)
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid user name is required'),
                                $translate->_('The user name is already taken')
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
            case self::FILTER_PASSWORD :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        'password' => array(
                            new Zend_Validate_StringLength(4, 30),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid password is required')
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
     * Authenticate farmer
     *
     * @param string $username
     * @param string $password
     * @param string $role
     * @param string|HH_Domain_Farm $farmId
     * @return Zend_Auth_Result
     */
    public static function authenticate($username, $password, $role,
        $farmId = null)
    {
        if ($farmId instanceof HH_Domain_Farm) {
            $farmId = $farmId['id'];
        }

        $authAdapter = new HH_Auth_Adapter_DbTable(
            self::_getStaticZendDb(),
            self::_getStaticDatabase(),
            'username',
            'password',
            'MD5(?)'
        );

        $credential = $password .
            Bootstrap::getZendConfig()->resources->db->salt;

        $authAdapter
            ->setRole($role)
            ->setFarmId($farmId)
            ->setCredential($credential)
            ->setIdentity($username);

        $auth = Zend_Auth::getInstance();

        $result = $auth->authenticate($authAdapter);

        if ($result->isValid()) {
            $storage = $auth->getStorage();

            $storage->write(new self(null, $authAdapter->getResultRowObject()));

            Zend_Session::regenerateId();
        }

        return $result;
    }

    /**
     * Get authenticated user
     *
     * @return null|HH_Domain_Farmer
     */
    public static function getAuthenticated()
    {
        if (!empty(self::$authenticated)) {
            return self::$authenticated;
        }

        $auth = Zend_Auth::getInstance();

        if ($auth->hasIdentity()) {
            self::$authenticated = $auth->getIdentity();
        }

        return self::$authenticated;
    }

    /**
     * return an array of all users with the supplied user name
     * @param string $userName
     * @param string $role User role
     */
    public static function fetchByUserName($userName, $role = null,
        HH_Domain_Farm $farm = null)
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase() . '
            WHERE
                userName = ?';

        $bind = array($userName);

        if (!empty($role)) {
            $sql .= ' AND role = ?';
            $bind[] = $role;
        }

        if (!empty($farm)) {
            $sql .= ' AND farmId = ?';
            $bind[] = $farm->id;
        }

        $rawData = $db->fetchAll(
            $sql,
            $bind
        );

        $results = array();

        foreach ($rawData as $row) {
            $results[] = new self($row['id'], $row);
        }

        return $results;
    }

    /**
     * return user with the supplied user token
     * @param string $userToken
     * @param HH_Domain_Farm $farm
     * @return HH_Domain_Farmer
     */
    public static function fetchUserByToken($userToken,
        HH_Domain_Farm $farm = null)
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase() . '
            WHERE
                userToken = ?';

        $bind = array($userToken);

        if (!empty($farm)) {
            $sql .= ' AND farmId = ?';
            $bind[] = $farm->id;
        }

        $rawData = $db->fetchRow(
            $sql,
            $bind
        );

        if (!empty($rawData)) {
            return new self($rawData['id'], $rawData);
        }

        return new self();
    }

    /**
     * Fetch user by user credentials  (will change the authenticated user)
     *
     * @param string $username
     * @param string $password
     * @param string $role
     * @param string|HH_Domain_Farm $farmId
     * @return Zend_Auth_Result
     */
    public static function fetchSingleUserByCredentials($username, $password, $role, $farmId = null)
    {
        if ($farmId instanceof HH_Domain_Farm) {
            $farmId = $farmId['id'];
        }

        $authAdapter = new HH_Auth_Adapter_DbTable(
            self::_getStaticZendDb(),
            self::_getStaticDatabase(),
            'username',
            'password',
            'MD5(?)'
        );


        $credential = $password .
            Bootstrap::getZendConfig()->resources->db->salt;

        $authAdapter
            ->setRole($role)
            ->setFarmId($farmId)
            ->setCredential($credential)
            ->setIdentity($username);

        $auth = Zend_Auth::getInstance();

        $result = $auth->authenticate($authAdapter);

        if ($result->isValid()) {
            $storage = $auth->getStorage();

            $storage->write(new self(null, $authAdapter->getResultRowObject()));

            Zend_Session::regenerateId();

            return new self(null, $authAdapter->getResultRowObject());
        }

        return null;
    }

    /**
     * Insert data into object
     *
     * @param array $data
     * @return boolean
     * @throws HH_Object_Exception_Id If primary key needs to be defined
     * @throws HH_Object_Exception_NoData If no data to insert
     */
    public function insert($data){
        $db = $this->_getZendDb();

        $db->insert($this->_getDatabase(), $this->_prepareData($data));
        $data['id'] = $db->lastInsertId();

        if(isset($data['password'])){
            $data['password'] = $this->_hashPassword($data['password']);
        }

        $this->_setData($data);

        $this->_getZendCache()->save($this->_data, (string)$this);
    }

    /**
     * Prepare data to be entered into the database
     *
     * Add timestamps
     * Convert dates / times to proper formats
     * hashes password
     *
     * @param array $data Data to prepare
     * @param boolean $insert Is data to be inserted (false is updated)
     * @return array
     */
    protected function  _prepareData($data, $insert = true){
        if(array_key_exists('password', $data)){
            $data['password'] = $this->_hashPassword($data['password']);
        }

        return parent::_prepareData($data, $insert);
    }

    protected function _hashPassword($password){
        return hash('md5', $password . Bootstrap::getZendConfig()->resources->db->salt);
    }

    /**
     * Update data in current object
     *
     * @param array|null $data
     * @return boolean
     * @throws HH_Object_Exception_Id if object ID is not set
     */
    public function update($data = null){
        if(!empty($this->_id)){

            if(!$this->_isLoaded){
                $this->_get();
            }

            $this->_getZendDb()->update($this->_getDatabase(), $this->_prepareData($data, false), array('id = ?' => $this->_id));

            if(isset($data['password'])){
                $data['password'] = $this->_hashPassword($data['password']);
            }

            $this->_setData($data, false);

            $this->_getZendCache()->save($this->_data, (string)$this);
        }
    }

    public function getFullName(){
        $fullName = '';

        if(!empty($this->firstName)){
            $fullName = $this->firstName;

            if(!empty($this->lastName)){
                $fullName .= ' ' . $this->lastName;
            }
        } else{
            if(!empty($this->lastName)){
                $fullName = $this->lastName;
            }
        }

        return $fullName;
    }

    /**
     * Log existing user out
     */
    public function logout(){
        Zend_Auth::getInstance()->clearIdentity();

        if(Zend_Session::isStarted()){
            Zend_Session::destroy(true);
        }
    }

    /**
     * Get farm preferences
     *
     * @param string $resource
     * @return HHF_Preferences
     */
    public function getPreferences($resource = null){
        if(isset($this->_preferences[$resource]) && $this->_preferences[$resource] instanceof HHF_Preferences){

            return $this->_preferences[$resource];
        }

        $this->_preferences[$resource] = new HHF_Preferences($this->getFarm(), HHF_Domain_Preference::TYPE_FARMER, $this->_id, $resource);

        return $this->_preferences[$resource];
    }

    /**
     * Get Farmer farm
     *
     * @return HH_Domain_Farm
     */
    public function getFarm(){
        if($this->_farm instanceof HH_Domain_Farm){
            return $this->_farm;
        }

        return $this->_farm = HH_Domain_Farm::singleton($this->farmId);
    }

    /**
     * @return HHF_Domain_Customer
     */
    public function getCustomer(){
        if($this->role == self::ROLE_MEMBER){
            if($this->_customer instanceof HHF_Domain_Customer){
                return $this->_customer;
            }

            return $this->_customer = HHF_Domain_Customer::fetchCustomerByFarmer($this->getFarm(), $this);
        }
    }

    /**
     * return an array of all users with the supplied user name
     * @param string $userName
     * @param string $role User role
     */
    public function getEmailAddress(){
        $db = self::_getStaticZendDb();

        $sql = 'SELECT email FROM ' . self::_getStaticDatabase() . ' WHERE id = ?';

        $emailAddress = $db->fetchOne($sql, array($this->_id));

        return $emailAddress;
    }
}
