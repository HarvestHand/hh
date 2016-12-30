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
 * @copyright $Date: 2012-02-26 14:38:41 -0400 (Sun, 26 Feb 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Farm_Filter
 */

/**
 * Description of Token
 *
 * @package   HH_Farm_Filter
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Transliteration.php 447 2012-02-26 18:38:41Z farmnik $
 * @copyright $Date: 2012-02-26 14:38:41 -0400 (Sun, 26 Feb 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Filter_Transliteration implements Zend_Filter_Interface
{
    protected $_encoding = 'UTF-8';
    protected $_length = null;
    protected $_unique = false;
    protected $_checkReserved = false;
    protected $_reserved = array(
        'default',
        'customers',
        'members',
        'newsletter',
        'shares',
        'website',
        'public',
        'admin',
        'service',
        'blog',
        'contact',
        'login',
        'logout'
    );

    public function  __construct($length = null, $encoding = 'UTF-8', 
        $unique = false, $checkReserved = true)
    {
        if (!empty($encoding)) {
            $this->_encoding = $encoding;
        }

        if (!empty($length)) {
            $this->_length = $length;
        }

        if (!empty($unique)) {
            $this->_unique = $unique;
        }
        
        $this->_checkReserved = $checkReserved;
    }

    public function filter($value)
    {
        $value = iconv($this->_encoding, 'ASCII//TRANSLIT//IGNORE', $value);

        $value = preg_replace('/[ _+]/im', '-', $value);
        $value = preg_replace('/[^-a-z0-9]/im', '', $value);

        if (!empty($this->_length)) {
            $value = substr($value, 0, $this->_length);
        }

        if ($this->_checkReserved) {
            if (in_array(strtolower($value), $this->_reserved)) {
                $value .= '-1';
            }
        }

        if (is_array($this->_unique)) {
            $db = Bootstrap::getZendDb();

            $isUnique = false;

            do {

                $sql = 'SELECT
                            COUNT(' . $this->_unique['field'] . ')
                        FROM
                            ' . $this->_unique['table'] . '
                        WHERE
                            ' . $this->_unique['field'] . ' = ?';

                $bind = array($value);

                if (!empty($this->_unique['currentId'])) {
                    $sql .= ' AND ' . $this->_unique['idField'] . ' != ?';
                    $bind[] = $this->_unique['currentId'];
                }

                $isUnique = !(bool) $db->fetchOne(
                    $sql,
                    $bind
                );

                if (!$isUnique) {
                    if (!empty($this->_length) && strlen($value) == $this->_length) {
                        $value = substr($value, 0, -5);
                    }

                    $pos = strrpos($value, '-');

                    if ($pos !== false) {
                        $end = substr($value, $pos + 1);
                        if (is_numeric($end)) {
                            ++$end;
                            $value = substr($value, 0, $pos) . $end;
                        } else {
                            $value .= '-2';
                        }
                    } else {
                        $value .= '-2';
                    }
                }

            } while (!$isUnique);
        }

        return $value;
    }
}