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
 * @package   HH_Filter
 */

/**
 * Description of HHUrl
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: HHUrl.php 302 2011-08-03 22:26:55Z farmnik $
 * @package   HH_Filter
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Filter_HHUrl implements Zend_Filter_Interface
{
    protected $_domains = array();

    /**
     * Construct filter
     *
     * @param array $domains permissable domains
     */
    public function  __construct($domains = array())
    {
        $this->_domains = $domains;
    }

    /**
     * @see lib/Zend/Filter/Zend_Filter_Interface#filter()
     */
    public function filter($value)
    {
        $urlParts = @parse_url($value);

        if ($urlParts === false) {
            return false;
        }

        $url = $urlParts['path'];
        if (!empty($urlParts['query'])) {
            $url .= '?' . $urlParts['query'];
        }
        if (!empty($urlParts['fragment'])) {
            $url .= '#' . $urlParts['fragment'];
        }
        if (!empty($urlParts['host'])) {
            // dump www sub domain
            $host = $urlParts['host'];
            if (strpos($host, 'www.') === 0) {
                $host = substr($host, 4);
            }

            if (array_key_exists($host, $this->_domains)) {
                // direct parent domain match, no farm
                $url = $urlParts['host'] . $url;

                $scheme = strtolower(substr($urlParts['scheme'], 0, 4));

                if (!empty($urlParts['scheme']) && $scheme == 'http') {
                    $url = $urlParts['scheme'] . '://' . $url;
                }
            } else {
                // test for farm
                list(, $host) = explode('.', $host, 2);

                if (array_key_exists($host, $this->_domains)) {
                    // direct parent domain match, no farm
                    $url = $urlParts['host'] . $url;

                    $scheme = strtolower(substr($urlParts['scheme'], 0, 4));

                    if (!empty($urlParts['scheme']) && $scheme == 'http') {
                        $url = $urlParts['scheme'] . '://' . $url;
                    }
                }
            }
        }

        return $url;
    }
}