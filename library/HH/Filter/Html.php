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
 * @copyright $Date: 2011-10-22 20:25:03 -0300 (Sat, 22 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Filter
 */

/**
 * Description of Html
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Html.php 339 2011-10-22 23:25:03Z farmnik $
 * @package   HH_Filter
 * @copyright $Date: 2011-10-22 20:25:03 -0300 (Sat, 22 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Filter_Html implements Zend_Filter_Interface
{    
    protected $_options = array();

    /**
     * Construct filter
     *
     * @param array $domains permissable domains
     */
    public function  __construct($options = array())
    {
        $this->_options = $options;
    }

    /**
     * @return HTMLPurifier 
     */
    protected function _getPurifier()
    {
        require_once Bootstrap::$library 
            . 'HTMLPurifier/HTMLPurifier.standalone.php';
                    
        $config = HTMLPurifier_Config::createDefault();
        $config->set(
            'Cache.SerializerPath',
            Bootstrap::$root . 'data/HTMLPurifier'
        );
        
        foreach ($this->_options as $option => $value) {
        
            if ($option == 'MyIframe') { 
                $config->set(
                    'Filter.Custom',
                    array(new HH_HTMLPurifier_Filter_MyIframe())
                );
            } else {
                $config->set($option, $value);
            }
        }
        
        return new HTMLPurifier($config);
    }
    
    /**
     * @see lib/Zend/Filter/Zend_Filter_Interface#filter()
     */
    public function filter($value)
    {
        $purifier = $this->_getPurifier();
        
        return $purifier->purify($value);
    }
}