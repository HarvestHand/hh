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
 * @copyright $Date: 2015-12-11 14:46:07 -0400 (Fri, 11 Dec 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Button
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Button.php 965 2015-12-11 18:46:07Z farmnik $
 * @copyright $Date: 2015-12-11 14:46:07 -0400 (Fri, 11 Dec 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Paypal_Button
{
    const BUTTON_RECURRING = 'recurring';
    const BUTTON_TOTAL    = 'total';

    protected $_config = array();
    protected $_type;
    
    /**
     * @var HH_Domain_Farm
     */
    protected $_farm;

    public function __construct(HH_Domain_Farm $farm, $config, $type)
    {
        $this->_farm = $farm;
        
        $this->_config = $config;
        $this->_type = $type;
    }

    public function setConfig($params)
    {
        foreach ($params as $key => $value) {
            $this->_config[$key] = $value;
        }
    }

    public function  __toString()
    {
        return $this->toHTML();
    }

    public function hasMicroAccount()
    {
        $toCheck = array(
            'microBusiness',
            'microCertId',
            'microSigncert',
            'microPrivkey'
        );
        
        $preferences = $this->_farm->getPreferences();
        
        foreach ($toCheck as $key) {
        
            $check = $preferences->get($key, 'paypal', false);

            if (empty($check)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function toHTML()
    {
        $preferences = $this->_farm->getPreferences();
        
        $this->_config['domain'] = Bootstrap::get('Zend_Config')
            ->resources->paypal->button->url;
        
        $buttonParams = array(
            'item_name'     => $this->_config['item_name'],
            'item_number'   => $this->_config['item_number'],
            'currency_code' => ($this->_farm->country != 'CA' ? 'USD' : 'CAD'),
            'charset'       => 'UTF-8'
        );

        if ($this->_config['amount'] < 12 && $this->hasMicroAccount()) {
            $buttonParams['business'] = $preferences->get('microBusiness', 'paypal');
            $buttonParams['cert_id']  = $preferences->get('microCertId', 'paypal');
            $this->_config['signcert'] = $preferences->get('microSigncert', 'paypal');
            $this->_config['privkey'] = $preferences->get('microPrivkey', 'paypal');
            $this->_config['cert'] = $preferences->get('cert', 'paypal');
        } else {
            $buttonParams['business'] = $preferences->get('business', 'paypal');
            $buttonParams['cert_id']  = $preferences->get('certId', 'paypal');
            $this->_config['signcert'] = $preferences->get('signcert', 'paypal');
            $this->_config['privkey'] = $preferences->get('privkey', 'paypal');
            $this->_config['cert'] = $preferences->get('cert', 'paypal');
        }
        
        if ($this->_type == self::BUTTON_TOTAL) {
            $buttonParams['bn'] = 'HarvestHand_BuyNow_WPS_CA';
            $buttonParams['amount'] = $this->_config['amount'];
            $buttonParams['cmd'] = '_xclick';
        } else {
            $buttonParams['bn'] = 'HarvestHand_Subscribe_WPS_CA';
            $buttonParams['cmd'] = '_xclick-subscriptions';
            $buttonParams['no_note'] = 1;
            $buttonParams['src'] = 1;
            $buttonParams['a3'] = $this->_config['amount'];
            $buttonParams['srt'] = $this->_config['srt'];
            $buttonParams['t3'] = 'W';
            $buttonParams['p3'] = $this->_config['p3'];
        }
        
        $contentBytes = array();
        foreach ($buttonParams as $name => $value) {
            $contentBytes[] = "$name=$value";
        }
        $contentBytes = implode("\n", $contentBytes);

        $encryptedButton = '<form action="' . $this->_config['domain'] . '/cgi-bin/webscr" method="post">';
        $encryptedButton .= '<input type="hidden" name="cmd" value="_s-xclick">';
        $encryptedButton .= '<input type="hidden" name="encrypted" value="' . htmlspecialchars($this->_signAndEncrypt($contentBytes)) . '">';
        $encryptedButton .= '<button name="Order" id="Save" type="submit" class="paypal">Pay Now!</button>';
        $encryptedButton .= '</form>';

        return $encryptedButton;
    }

    protected function _signAndEncrypt($contentBytes)
    {
        $dataStrFile  = realpath(tempnam('/tmp', 'pp_'));
        $fd = fopen($dataStrFile, 'w');
        if(!$fd) {
            unlink($dataStrFile);
            throw new Exception('Could not open temporary file ' . $dataStrFile);
        }
        fwrite($fd, $contentBytes);
        fclose($fd);

        $signedDataFile = realpath(tempnam('/tmp', 'pp_'));
        
        $res = !@openssl_pkcs7_sign(
            $dataStrFile,
            $signedDataFile,
            $this->_config['signcert'],
            array($this->_config['privkey'], ''),
            array(),
            PKCS7_BINARY
        );
        
        if ($res) {
            unlink($dataStrFile);
            unlink($signedDataFile);
            throw new Exception('Could not sign data: ' . openssl_error_string());
        }

        unlink($dataStrFile);

        $signedData = file_get_contents($signedDataFile);
        $signedDataArray = explode("\n\n", $signedData);
        $signedData = $signedDataArray[1];
        $signedData = base64_decode($signedData);

        unlink($signedDataFile);

        $decodedSignedDataFile = realpath(tempnam('/tmp', 'pp_'));
        $fd = fopen($decodedSignedDataFile, 'w');
        if(!$fd) {
            throw new Exception('Could not open temporary file ' . $decodedSignedDataFile);
        }
        fwrite($fd, $signedData);
        fclose($fd);

        $encryptedDataFile = realpath(tempnam('/tmp', 'pp_'));
        
        $res = !@openssl_pkcs7_encrypt(
            $decodedSignedDataFile,
            $encryptedDataFile,
            $this->_config['cert'],
            array(),
            PKCS7_BINARY
        );
        
        if($res) {
            unlink($decodedSignedDataFile);
            unlink($encryptedDataFile);
            throw new Exception('Could not encrypt data: ' . openssl_error_string());
        }

        unlink($decodedSignedDataFile);

        $encryptedData = file_get_contents($encryptedDataFile);
        if(!$encryptedData) {
            throw new Exception('Encryption and signature of data failed.');
        }

        unlink($encryptedDataFile);

        $encryptedDataArray = explode("\n\n", $encryptedData);
        $encryptedData = trim(str_replace("\n", '', $encryptedDataArray[1]));

        return '-----BEGIN PKCS7-----' . $encryptedData . '-----END PKCS7-----';
    }
}
