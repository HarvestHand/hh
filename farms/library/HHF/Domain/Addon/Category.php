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
 * @copyright $Date: 2014-09-01 01:24:03 -0300 (Mon, 01 Sep 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Add on model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Category.php 798 2014-09-01 04:24:03Z farmnik $
 * @copyright $Date: 2014-09-01 01:24:03 -0300 (Mon, 01 Sep 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Addon_Category extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';


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
                $tokenCategoryFilter = new HHF_Filter_Transliteration(255);

                $dbOptions = array(
                    'table' => self::_getStaticDatabase(
                        $options['farm'],
                        'HHF_Domain_Addon_Category'
                    ),
                    'field' => 'name',
                    'adapter' => self::_getStaticZendDb()
                );

                if (!empty($options['id'])) {
                    $dbOptions['exclude'] = 'id != ' . self::_getStaticZendDb()->quote($options['id']);
                }

                $inputFilter = new Zend_Filter_Input(
                    array(
                        'name' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'image' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        )
                    ),
                    array(
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
                        'name' => array(
                            new Zend_Validate_StringLength(0, 255),
                            new Zend_Validate_Db_NoRecordExists($dbOptions),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => 'Other Goodies',
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid category is required')
                            )
                        ),
                        'id' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                $tokenCategoryFilter->filter($options['name']),
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid category token is required')
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
        $options['name'] = $dataToValidate['name'];

        $filterAddon = HHF_Domain_Addon_Category::getFilter(
            HHF_Domain_Addon::FILTER_NEW,
            $options
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
     * Fetched used categories
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchAllForm(HH_Domain_Farm $farm)
    {
        $pairs = array();

        $categories = self::fetch(
            $farm,
            array(
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        foreach ($categories as $category) {
            $pairs[$category['id']] = $category['name'];
        }

        return $pairs;
    }

    public static function fetchOneByName(HH_Domain_Farm $farm, $name)
    {
        $tokenCategoryFilter = new HHF_Filter_Transliteration(255);

        return new self($farm, $tokenCategoryFilter->filter($name));
    }
}
