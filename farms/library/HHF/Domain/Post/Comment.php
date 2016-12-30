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
 * @copyright $Date: 2012-04-09 18:46:56 -0300 (Mon, 09 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Web page blog post model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Comment.php 504 2012-04-09 21:46:56Z farmnik $
 * @copyright $Date: 2012-04-09 18:46:56 -0300 (Mon, 09 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Post_Comment extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    
    /**
     * @var HH_Domain_Farmer
     */
    protected $_farmer;
    
    /**
     * @return HH_Domain_Farmer
     */
    public function getFarmer()
    {
        if (!empty($this->farmerId)) {
            if ($this->_farmer instanceof HH_Domain_Farmer) {
                return $this->_farmer;
            }
            
            $farmer = HH_Domain_Farmer::fetchOne(
                array(
                    'where' => array(
                        'id' => $this->farmerId,
                        'role' => $this->farmerRole
                    )
                )
            );
            
            if (!$farmer->isEmpty()) {
            
                $this->_farmer = $farmer;
            }
        }
        
        return $this->_farmer;
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
                        'content' => new HH_Filter_Html(
                            array(
                                'AutoFormat.AutoParagraph' => true,
                                'AutoFormat.Linkify' => true,
                                'AutoFormat.RemoveEmpty' => true,
                                'HTML.MaxImgLength' => 600,
                                'HTML.SafeEmbed' => true,
                                'HTML.SafeObject' => true,
                                'Output.FlashCompat' => true,
                                'URI.Base' => $options['farm']->getBaseUri(),
                                'URI.MakeAbsolute' => true
                            )
                        )
                    ),
                    array(
                        'postId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Post'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid author is required')
                            )
                        ),
                        'content' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'farmerId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => 'farmnik_hh.farmers',
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb(),
                                    'exclude' => 'farmId = ' 
                                        . (int) $options['farm']['id']
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid author is required')
                            )
                        ),
                        'farmerRole' => array(
                            new Zend_Validate_InArray(
                                array(
                                    HH_Domain_Farmer::ROLE_FARMER,
                                    HH_Domain_Farmer::ROLE_MEMBER,
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::DEFAULT_VALUE => 
                                HH_Domain_Farmer::ROLE_FARMER,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid author is required')
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
}
