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
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Web page model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Page.php 606 2012-12-27 04:25:36Z farmnik $
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Page extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const STATUS_DRAFT = 'DRAFT';
    const STATUS_PUBLISHED = 'PUBLISHED';
    const FETCH_ALL = null;
    const FETCH_TOPLEVEL = 'topLevel';
    const FETCH_SUBLEVEL = 'subLevel';
    const ORDER_ID = 'id';
    const ORDER_SORT = 'sort';
    const ORDER_PARENT_SORT = 'parent_sort';
    const TARGET_EXTERNAL = 'EXTERNAL';
    const TARGET_INTERNAL = 'INTERNAL';

    protected static $_collection = 'HHF_Domain_Page_Collection';

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

        $this->_setData(
            $this->_getZendDb()->fetchRow($sql, $this->_id)
        );

        $cache->save($this->_data, (string) $this);
        $cache->save(
            $this->_data,
            'HHF_Domain_Page_' . $this->_farm->id .
                preg_replace('/([^a-zA-Z0-9_])+/', '_', $this->_data['token'])
        );
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
                        id = ?';

            $this->_getZendDb()->query($sql, $this->_id);

            $this->_getZendCache()
                ->remove((string) $this);
            $this->_getZendCache()
                ->remove('HHF_Domain_Page_' . $this->_farm->id .
                    preg_replace('/([^a-zA-Z0-9_])+/', '_', $this->_data['token'])
                );
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

        $db->insert(
            $this->_getDatabase(),
            $this->_prepareData($data)
        );
        $data['id'] = $db->lastInsertId();

        $this->_setData($data);

        if (!isset($this->_config['summary'])) {

            $this->_getZendCache()->save($this->_data, (string) $this);
            $this->_getZendCache()->save(
                $this->_data,
                'HHF_Domain_Page_' . $this->_farm->id .
                    preg_replace('/([^a-zA-Z0-9_])+/', '_', $this->_data['token'])
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

            // check to see if we need to record a redirect
            $createRedirect = false;

            if (isset($data['token']) &&
                $data['token'] != $this->_data['token'] &&
                $this->isPublished()) {

                $updated = $this->_data['updatedDatetime']->get(
                    Zend_Date::TIMESTAMP
                );

                if ((time() - $updated) > 1) { //86400

                    $createRedirect = true;
                    $oldData = $this->_data;

                }
            }

            $this->_getZendDb()->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array('id = ?' => $this->_id)
            );

            $this->_setData($data, false);

            if ($createRedirect) {
                $this->_createRedirect($oldData);
            }

            if (!isset($this->_config['summary'])) {
                $this->_getZendCache()->save($this->_data, (string) $this);
                $this->_getZendCache()->save(
                    $this->_data,
                    'HHF_Domain_Page_' . $this->_farm->id .
                        preg_replace('/([^a-zA-Z0-9_])+/', '_', $this->_data['token'])
                );
            }
        }
    }

    /**
     * create redirect
     *
     * @param array $oldData
     */
    protected function _createRedirect($oldData)
    {
        if (!$oldData['parent']) {
            $path = '/' . $oldData['token'];
        } else {
            $path = '/';

            $parent = new self($this->_farm, $oldData['parent']);

            if (!$parent->isEmpty()){
                $path .= $parent->token . '/';
            }

            $path .= $oldData['token'];
        }

        if (!$this->_data['parent']) {
            $newPath = '/' . $this->_data['token'];
        } else {
            $newPath = '/';

            $parent = new self($this->_farm, $this->_data['parent']);

            if (!$parent->isEmpty()){
                $newPath .= $parent->token . '/';
            }

            $newPath .= $this->_data['token'];
        }

        $redirect = HHF_Domain_Redirect::fetchIncomingPath(
            $this->_farm,
            $path
        );

        if ($redirect->isEmpty()) {
            $redirect->insert(
                array(
                    'incomingPath' => $path,
                    'outgoingPath' => $newPath,
                    'type'         => 301
                )
            );
        } else {
            $redirect->update(
                array(
                    'outgoingPath' => $newPath,
                    'type'         => 301
                )
            );
        }
    }

    /**
     * Is page published
     *
     * @return boolean
     */
    public function isPublished()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return ($this->_data['publish'] == self::STATUS_PUBLISHED) ?
            true : false;
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
                $tokenFilter = new HHF_Filter_Transliteration(
                    255,
                    'UTF-8',
                    array(
                        'table' => 'farmnik_hh_' . $options['farm']->id . '.pages',
                        'field' => 'token',
                        'idField' => 'id',
                        'currentId' => (($filter == self::FILTER_EDIT) ?
                            $options['currentId'] : null)
                    )
                );

                $inputFilter = new Zend_Filter_Input(
                    array(
                        'title' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'parent' => array(
                            new Zend_Filter_Null()
                        ),
                        'sort' => array(
                            new Zend_Filter_Int()
                        )
                    ),
                    array(
                        'title' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid title is required')
                            )
                        ),
                        'token' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                $tokenFilter->filter($options['title']),
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A token is required')
                            )
                        ),
                        'target' => array(
                            new Zend_Validate_InArray(
                                array('INTERNAL', 'EXTERNAL')
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'url' => array(
                            new HH_Validate_Url(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true
                        ),
                        'content' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true
                        ),
                        'publish' => array(
                            new Zend_Validate_InArray(
                                array(
                                    self::STATUS_DRAFT,
                                    self::STATUS_PUBLISHED
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Published status is required')
                            )
                        ),
                        'parent' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Page placement is required')
                            )
                        ),
                        'sort' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => 0,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_(
                                    'Page order should be specified as a number'
                                )
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
     * Fetch home page
     *
     * @param HH_Domain_Farm $farm
     * @return HHF_Domain_Page
     */
    public static function fetchHomePage(HH_Domain_Farm $farm)
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                WHERE
                    parent IS NULL
                AND
                    sort = 0';

        $result = $db->fetchRow($sql);

        return new self(
            $farm,
            $result['id'],
            $result
        );
    }

    /**
     * Fetch single page by token
     *
     * @param HH_Domain_Farm $farm
     * @param string $token
     * @return HHF_Domain_Page
     */
    public static function fetchPageByToken(HH_Domain_Farm $farm, $token)
    {
        $cache = self::_getStaticZendCache();
        $key = 'HHF_Domain_Page_' . $farm->id .
            preg_replace('/([^a-zA-Z0-9_])+/', '_', $token);

        if (($data = $cache->load($key)) !== false) {
            return new self($farm, $data['id'], $data);
        }

        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                WHERE
                    token = ?';

        $result = $db->fetchRow($sql, array($token));

        if (!empty($result)) {
            return new self($farm, $result['id'], $result);
        } else {
            return new self($farm);
        }
    }

    /**
     * Fetch all pages
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchPages(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    ' . ((!isset($options['summary'])) ?
                        '*' :
                        'id,
                        parent,
                        token,
                        title,
                        target,
                        url,
                        publish,
                        sort,
                        addedDatetime,
                        updatedDatetime') . '
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();
        $where = array();

        if (isset($options['fetch'])) {

            switch ($options['fetch']) {
                case self::FETCH_SUBLEVEL :

                    if (!empty($options['fetchOptions'])) {
                        $where[] = 'parent = ?';
                        $bind[] = $options['fetchOptions'];
                    } else {
                        $where[] = 'parent IS NOT NULL';
                    }

                    break;

                case self::FETCH_TOPLEVEL :
                    $where[] = 'parent IS NULL';

                    break;
            }
        }

        if (isset($options['status'])) {
            $where[] = ' publish = ?';
            $bind[] = $options['status'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (isset($options['order'])) {
            switch ($options['order']) {
                case self::ORDER_ID :
                    $sql .= ' ORDER BY id';
                    break;

                case self::ORDER_SORT :
                    $sql .= ' ORDER BY sort';
                    break;

                case self::ORDER_PARENT_SORT :
                    $sql .= ' ORDER BY parent, sort';
                    break;
            }
        }

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $page) {
            $return[] = new self(
                $farm,
                $page['id'],
                $page,
                $options
            );
        }

        return $return;
    }
}