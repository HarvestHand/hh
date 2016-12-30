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
 * @copyright $Date: 2016-10-30 16:21:36 -0300 (Sun, 30 Oct 2016) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of Email
 *
 * @package   HH_Job 
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Email.php 1010 2016-10-30 19:21:36Z farmnik $
 * @copyright $Date: 2016-10-30 16:21:36 -0300 (Sun, 30 Oct 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job_Email extends HH_Job
{
    /**
     * @var Zend_Mail
     */
    protected $_mail;
    
    public function __construct($config = array())
    {
        parent::__construct($config);
    }
    
    public function add($from, $to, $subject, $bodyText = null,
        $bodyHtml = null, $replyTo = null, $cc = null, $bcc = null, 
        $sender = null, $returnPath = null, $headers = array())
    {

        if (!is_array($to) && empty($to)) {
            return;
        } else {
            if (is_array($to[0])) {
                foreach ($to as $toNested) {

                    if (!is_array($toNested) && empty($toNested)) {
                        return;
                    } else if (is_array($toNested) && empty($toNested[0])) {
                        return;
                    }

                }
            } else if (empty($to[0])) {
                return;
            }
        }

        parent::add('email', func_get_args());
    }    
    
    protected function _initMail()
    {
        Bootstrap::get('Zend_Mail_Transport');
        $this->_mail = new Zend_Mail('utf-8');
    }
    
    public function process($from, $to, $subject, $bodyText = null,
        $bodyHtml = null, $replyTo = null, $cc = null, $bcc = null, 
        $sender = null, $returnPath = null, $headers = array())
    {
        $this->_initMail();

        $this->_setFrom($from, $replyTo, $returnPath);
        
        $this->_addTo($to);

        if (!empty($sender)) {
            $this->_setSender($sender);
        }
        
        if (!empty($cc)) {
            $this->_addCc($cc);
        }
        
        if (!empty($bodyHtml)) {
            $this->_mail->setBodyHtml($bodyHtml);
        }
        
        if (!empty($bcc)) {
            $this->_mail->addBcc($bcc);
        }
        
        if (!empty($bodyText)) {
            $this->_mail->setBodyText($bodyText);
        } else if (!empty($bodyHtml)) {
            $filter = new HH_Filter_HtmlToText();
            
            $text = $filter->filter($bodyHtml);
            
            if (!empty($text)) {
                $this->_mail->setBodyText($text);
            }
        }

        if (!empty($headers)) {
            foreach ($headers as $header) {
                $this->_mail->addHeader($header['name'], $header['value']);
            }
        }
        
        $this->_mail->setSubject($subject)
            ->addHeader('X-Mailer', 'HarvestHand')
            ->setMessageId();
        
        $this->_mail->send();
    }
    
    protected function _setFrom($from, $replyTo, $returnPath)
    {
        if (is_array($from)) {

            if (stripos($from[0], Bootstrap::getZendConfig()->resources->domains->root) === false) {
                if (empty($replyTo)) {
                    $this->_setReplyTo($from[0], $from[1]);
                } else {
                    $this->_setReplyTo($replyTo);
                }

                $this->_setReturnPath($from[0]);
                $this->_mail->setFrom('team@' . Bootstrap::getZendConfig()->resources->domains->root, $from[1]);
            } else {

                if (!empty($replyTo)) {
                    $this->_setReplyTo($replyTo);
                }

                if (!empty($returnPath)) {
                    $this->_setReturnPath($returnPath);
                }

                $this->_mail->setFrom($from[0], $from[1]);
            }
        } else {

            if (stripos($from, Bootstrap::getZendConfig()->resources->domains->root) === false) {

                if (empty($replyTo)) {
                    $this->_setReplyTo($from);
                } else {
                    $this->_setReplyTo($replyTo);
                }

                $this->_setReturnPath($from);
                $this->_mail->setFrom('team@' . Bootstrap::getZendConfig()->resources->domains->root);

            } else {
                if (!empty($replyTo)) {
                    $this->_setReplyTo($replyTo);
                }

                if (!empty($returnPath)) {
                    $this->_setReturnPath($returnPath);
                }

                $this->_mail->setFrom($from);
            }

        }
        
    }

    protected function _addTo($to)
    {
        if (!is_array($to)) {
            $this->_mail->addTo($to);
        } else {
            if (is_array($to[0])) {
                foreach ($to as $toNested) {
                    $this->_addTo($toNested);
                }
            } else {
                if (!empty($to[1])) {
                    $this->_mail->addTo($to[0], $to[1]);
                } else {
                    $this->_mail->addTo($to[0]);
                }
            }
        }
    }
    
    protected function _addCc($cc)
    {
        if (!is_array($cc)) {
            $this->_mail->addCc($cc);
        } else {
            if (is_array($cc[0])) {
                foreach ($cc as $ccNested) {
                    $this->_addCc($ccNested);
                }
            } else {
                if (!empty($cc[1])) {
                    $this->_mail->addCc($cc[0], $cc[1]);
                } else {
                    $this->_mail->addCc($cc[0]);
                }
            }
        }
    }
    
    protected function _setReplyTo($replyTo)
    {
        if (!is_array($replyTo)) {
            $this->_mail->setReplyTo($replyTo);
        } else {
            $this->_mail->setReplyTo($replyTo[0], $replyTo[1]);
        }
    }
    
    protected function _setReturnPath($email)
    {
        $this->_mail->setReturnPath($email);
    }

    protected function _setSender($sender)
    {
        $this->_mail->addHeader('Sender', $sender);
    }
}
