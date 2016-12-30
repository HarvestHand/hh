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
 * @copyright $Date: 2012-03-17 00:07:35 -0300 (Sat, 17 Mar 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Newsletter issue model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Issue.php 472 2012-03-17 03:07:35Z farmnik $
 * @copyright $Date: 2012-03-17 00:07:35 -0300 (Sat, 17 Mar 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Issue extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';

    protected $_recipients = null;
    
    public function __construct(HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_defaultObservers[] = 'HHF_Domain_Issue_Observer';
        
        parent::__construct($farm, $id, $data, $config);
    }
    
    public function getRecipients()
    {
        if ($this->_recipients === null && isset($this->_id)) {
            $this->_recipients = HHF_Domain_Issue_Recipient::fetch(
                $this->_farm,
                array(
                    'where' => array(
                        'issueId' => $this->_id
                    )
                )
            );
        }
        
        return $this->_recipients;
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

        $tokenFilter = new HHF_Filter_Transliteration(
            255,
            'UTF-8',
            array(
                'table' => 'farmnik_hh_' . $options['farm']->id . '.issues',
                'field' => 'token',
                'idField' => 'id',
                'currentId' => (($filter == self::FILTER_EDIT) ? 
                    $options['currentId'] : null)
            )
        );
        
        switch ($filter) {

            case self::FILTER_NEW :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        'title' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'content' => new HH_Filter_Html(
                            array(
                                'AutoFormat.AutoParagraph' => true,
                                'AutoFormat.Linkify' => true,
                                'AutoFormat.RemoveEmpty' => true,
                                'HTML.SafeEmbed' => true,
                                'HTML.SafeObject' => true,
                                'Output.FlashCompat' => true,
                                'URI.Base' => $options['farm']->getBaseUri(),
                                'URI.MakeAbsolute' => true,
                                'CSS.Trusted' => true,
                                'HTML.Trusted' => true,
                                'Filter.ExtractStyleBlocks.TidyImpl' => false,
                                'MyIframe' => true
                            )
                        )
                    ),
                    array(
                        'from' => array(
                            new Zend_Validate_EmailAddress(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid from email address is required')
                            )
                        ),
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
                        'content' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'publish' => array(
                            new Zend_Validate_InArray(array(0,1)),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Published status is required')
                            )
                        ),
                        'publishedDatetime' => array(
                            new Zend_Validate_Date('yyyy-M-d'),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Post published date is required')
                            )
                        ),
                        'archive' => array(
                            new Zend_Validate_InArray(array(0,1)),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Archive status is required')
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
                
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        'title' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'content' => new HH_Filter_Html(
                            array(
                                'AutoFormat.AutoParagraph' => true,
                                'AutoFormat.Linkify' => true,
                                'AutoFormat.RemoveEmpty' => true,
                                'HTML.SafeEmbed' => true,
                                'HTML.SafeObject' => true,
                                'Output.FlashCompat' => true,
                                'URI.Base' => $options['farm']->getBaseUri(),
                                'URI.MakeAbsolute' => true,
                                'CSS.Trusted' => true,
                                'HTML.Trusted' => true,
                                'Filter.ExtractStyleBlocks.TidyImpl' => false,
                                'MyIframe' => true
                            )
                        )
                    ),
                    array(
                        'from' => array(
                            new Zend_Validate_EmailAddress(),
                            Zend_Filter_Input::PRESENCE => (
                                $options['publish'] ? 
                                    Zend_Filter_Input::PRESENCE_OPTIONAL : 
                                    Zend_Filter_Input::PRESENCE_REQUIRED
                            ),
                            Zend_Filter_Input::ALLOW_EMPTY => (
                                $options['publish'] ? true : false
                            ),
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid from email address is required')
                            )
                        ),
                        'title' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE => (
                                $options['publish'] ? 
                                    Zend_Filter_Input::PRESENCE_OPTIONAL : 
                                    Zend_Filter_Input::PRESENCE_REQUIRED
                            ),
                            Zend_Filter_Input::ALLOW_EMPTY => (
                                $options['publish'] ? true : false
                            ),
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
                        'content' => array(
                            Zend_Filter_Input::PRESENCE => (
                                $options['publish'] ? 
                                    Zend_Filter_Input::PRESENCE_OPTIONAL : 
                                    Zend_Filter_Input::PRESENCE_REQUIRED
                            ),
                            Zend_Filter_Input::ALLOW_EMPTY => (
                                $options['publish'] ? true : false
                            )
                        ),
                        'publish' => array(
                            new Zend_Validate_InArray(array(0,1)),
                            Zend_Filter_Input::PRESENCE => (
                                $options['publish'] ? 
                                    Zend_Filter_Input::PRESENCE_OPTIONAL : 
                                    Zend_Filter_Input::PRESENCE_REQUIRED
                            ),
                            Zend_Filter_Input::ALLOW_EMPTY => (
                                $options['publish'] ? true : false
                            ),
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Published status is required')
                            )
                        ),
                        'publishedDatetime' => array(
                            new Zend_Validate_Date('yyyy-M-d'),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Post published date is required')
                            )
                        ),
                        'archive' => array(
                            new Zend_Validate_InArray(array(0,1)),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Archive status is required')
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
    
    public static function hasIssues(HH_Domain_Farm $farm)
    {
        $cache = self::_getStaticZendCache();
        if (($data = $cache->load((string) $farm . '_hasIssues')) !== false) {
            return (bool) $data;
        }
        
        $options['where'] = 'publish = 1 AND archive = 1';
        $options['columns'] = 'count(*) as count';
        
        $result = self::fetchOne($farm, $options);
        
        $cache->save($result['count'], (string) $farm . '_hasIssues');
        
        return (bool) $result['count'];
    }
}