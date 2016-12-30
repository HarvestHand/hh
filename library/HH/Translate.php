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
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Translate
 */

/**
 * Description of Translate
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Translate.php 302 2011-08-03 22:26:55Z farmnik $
 * @package   HH_Translate
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Translate extends Zend_Translate
{
    protected $_modulesLoaded = array();

    /**
     * Add translation files for a particular module
     * @param string $module Module name
     * @param string $farm Module farm
     * @return HH_Translate
     */
    public function addModuleTranslation($module, $farm = false)
    {
        if (!isset($this->_modulesLoaded[$module . (bool) $farm])) {

            try {

                if ($farm === null) {
                    $this->addTranslation(
                        Bootstrap::$root . 'data/locales/' . $module . '/en.mo',
                        'en'
                    );
                } else {
                    $this->addTranslation(
                        Bootstrap::$root . 'data/locales/' . $module . '/farm/en.mo',
                        'en'
                    );
                }
                
                $this->_modulesLoaded[$module . (bool) $farm] = true;

                return $this;

            } catch (Zend_Translate_Exception $e) {
                // ignore
                HH_Error::exceptionHandler($e, E_USER_WARNING);
            }
        }

        return $this;
    }
}