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
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of Page
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Redirect.php 323 2011-09-22 22:22:20Z farmnik $
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Redirect extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    public static $codes = array(
        300, 301, 302, 303, 305, 307
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

        $this->_setData(
            $this->_getZendDb()->fetchRow($sql, $this->_id)
        );

        $cache->save($this->_data, (string) $this);
        $cache->save(
            $this->_data,
            'HHF_Domain_Redirect_' . $this->_farm->id .
                hash('sha256', $this->_data['incomingPath'])
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
                ->remove('HHF_Domain_Redirect_' . $this->_farm->id .
                    hash('sha256', $this->_data['incomingPath'])
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

        $this->_getZendCache()->save($this->_data, (string) $this);
        $this->_getZendCache()->save(
            $this->_data,
            'HHF_Domain_Redirect_' . $this->_farm->id .
                hash('sha256', $this->_data['incomingPath'])
        );
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
                'HHF_Domain_Redirect_' . $this->_farm->id .
                    hash('sha256', $this->_data['incomingPath'])
            );
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
                    ),
                    array(
                        'incomingPath' => array(
                            new Zend_Validate_StringLength(0, 1024),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A URL path is required')
                            )
                        ),
                        'outgoingPath' => array(
                            new Zend_Validate_StringLength(0, 1024),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A URL path is required')
                            )
                        ),
                        'type' => array(
                            new Zend_Validate_InArray(
                                self::$codes
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => 302,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Redirect code is required')
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
     * Fetch redirect by incoming path
     *
     * @param HH_Domain_Farm $farm
     * @param string $incomingPath
     * @return HHF_Domain_Redirect
     */
    public static function fetchIncomingPath(HH_Domain_Farm $farm, $incomingPath)
    {
        $cache = self::_getStaticZendCache();
        $key = 'HHF_Domain_Redirect_' . $farm->id .
            hash('sha256', $incomingPath);

        if (($data = $cache->load($key)) !== false) {
            return new self($farm, $data['id'], $data);
        }

        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                WHERE
                    incomingPath = ?';

        $result = $db->fetchOne($sql, array($incomingPath));

        if (!empty($result)) {
            return new self($farm, $result['id'], $result);
        } else {
            return new self($farm);
        }
    }
}