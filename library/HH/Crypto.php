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
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH
 */

/**
 * Description of Crypto
 *
 * @package   HH
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Crypto.php 518 2012-04-25 02:25:26Z farmnik $
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Crypto
{
    protected static $_staticConfig = array();
    
    /**
     * Encrypt data
     *
     * @param mixed $data Data to be encrypted
     * @param boolean $baseEncode Base64 encode result
     * @return string
     */
    public static function encrypt($data, $baseEncode = true)
    {
        if (empty($data)) {
            return $data;
        }

        $cipher = mcrypt_module_open(
            MCRYPT_RIJNDAEL_256,
            '',
            MCRYPT_MODE_CBC,
            ''
        );
        
        $res = mcrypt_generic_init($cipher, self::_getKey(), self::_getIv());
        
        if ($res !== 0) {
            switch ($res) {
                case - 3:
                    throw new InvalidArgumentException('key length was incorrect', $res);
                    break;
                case - 4:
                    throw new InvalidArgumentException('memory allocation problem', $res);
                    break;
                default:
                    throw new InvalidArgumentException('unknown error', $res);
            }
        }
        $encrypted = mcrypt_generic($cipher, $data);
        mcrypt_generic_deinit($cipher);
        if ($baseEncode) {
            return base64_encode($encrypted);
        } else {
            return $encrypted;
        }
    }

    /**
     * Decrypt data
     *
     * @param string $data Data to be decrypted
     * @param boolean $baseDecode Base64 decode before decrypting
     * @return string
     */
    public static function decrypt ($data, $baseDecode = true)
    {
        if (empty($data)) {
            return $data;
        }

        if ($baseDecode) {
            $data = base64_decode($data);
        }
        $cipher = mcrypt_module_open(
            MCRYPT_RIJNDAEL_256,
            '',
            MCRYPT_MODE_CBC,
            ''
        );
        $res = mcrypt_generic_init($cipher, self::_getKey(), self::_getIv());
        if ($res !== 0) {
            switch ($res) {
                case - 3:
                    throw new InvalidArgumentException('key length was incorrect', $res);
                    break;
                case - 4:
                    throw new InvalidArgumentException('memory allocation problem', $res);
                    break;
                default:
                    throw new InvalidArgumentException('unknown error', $res);
            }
        }
        return rtrim(mdecrypt_generic($cipher, $data));
    }
    
    /**
     * Set object config across all to be initialized object classes
     * 
     * @param array $config
     */
    public static function setStaticConfig($config)
    {
        self::$_staticConfig = $config;
    }
    
    /**
     * Get private key
     *
     * @return string
     */
    private static function _getKey()
    {
        if (isset(self::$_staticConfig['key'])) {
            return self::$_staticConfig['key'];
        }
        
        return Bootstrap::getZendConfig()->resources->crypto->key;
    }
    
    /**
     * Get IV
     *
     * @return string
     */
    private static function _getIv()
    {
        if (isset(self::$_staticConfig['iv'])) {
            return self::$_staticConfig['iv'];
        }
        
        return Bootstrap::getZendConfig()->resources->crypto->iv;
    }
}
