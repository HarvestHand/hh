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
 * @copyright $Date: 2012-09-11 22:03:28 -0300 (Tue, 11 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of transaction service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 576 2012-09-12 01:03:28Z farmnik $
 * @copyright $Date: 2012-09-11 22:03:28 -0300 (Tue, 11 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Issue_Service extends HH_Object_Service
{
    /**
     * @var HHF_Object_Db
     */
    protected $_object;
    
    /**
     * @var Twig_TemplateInterface
     */
    protected $_twig;
    
    public function save($data)
    {
        $database = Bootstrap::getZendDb();
        $send = false;
        
        if ($this->_object->isEmpty()) {
            $filter = HHF_Domain_Issue::getFilter(
                HHF_Domain_Issue::FILTER_NEW,
                array(
                    'farm' => $this->_object->getFarm(),
                    'title' => $data['title']
                )
            );
            
            $filter->setData($data);
            
            if ($filter->isValid()) {
            
                $database->beginTransaction();
                
                $toInsert = $filter->getUnescaped();
                
                if ($toInsert['publish']) {
                    $send = true;
                    $toInsert['publishedDatetime'] = Zend_Date::now()->toString('yyyy-MM-dd');
                }
                
                $this->_object->insert($toInsert);
                
                try {
                    $collection = new HHF_Object_Collection_Db($this->_object->getFarm());
                    $collection->setObjectType('HHF_Domain_Issue_Recipient');
                    $collection->getService()->save(
                        $this->_convertRecipients($data['recipients'], $this->_object->id)
                    );

                    $this->_object['recipients'] = $collection;
                } catch (Exception $exception) {
                    $database->rollBack();
                    
                    throw $exception;
                }
                
                $database->commit();
                
            } else {
                throw new HH_Object_Exception_Validation(
                    $filter->getMessages()
                );
            }
        } else {
            
            $filter = HHF_Domain_Issue::getFilter(
                HHF_Domain_Issue::FILTER_EDIT,
                array(
                    'farm' => $this->_object->getFarm(),
                    'publish' => $this->_object['publish'],
                    'title' => (isset($data['title']) ? $data['title'] : $this->_object['title']),
                    'currentId' => $this->_object->id
                )
            );
            
            $filter->setData($data);
            
            if ($filter->isValid()) {
                $database->beginTransaction();
                
                $toInsert = $filter->getUnescaped();
                
                if (isset($toInsert['publish']) && $toInsert['publish'] && !$this->_object['publish']) {
                    $send = true;
                    $toInsert['publishedDatetime'] = Zend_Date::now()->toString('yyyy-MM-dd');
                }
                
                $this->_object->update($toInsert);
                
                if (array_key_exists('recipients', $data)) {
                
                    try {
                        $collection = new HHF_Object_Collection_Db($this->_object->getFarm());
                        $collection->setObjectType('HHF_Domain_Issue_Recipient');
                        $collection->getService()->save(
                            $this->_convertRecipients($data['recipients'], $this->_object->id)
                        );
                    } catch (Exception $exception) {
                        $database->rollBack();

                        throw $exception;
                    }
                }
                
                $database->commit();
                
            } else {
                throw new HH_Object_Exception_Validation(
                    $filter->getMessages()
                );
            }
        }
        
        if ($send) {
            $this->_send();
        }
    }
    
    protected function _send()
    {
        $job = new HH_Job_Newsletter();
        $job->add($this->_object->getFarm(), $this->_object->id);
    }
    
    protected function _convertRecipients($recipients, $issueId)
    {
        $result = array();
        
        foreach ($recipients as $recipient) {
        
            $list = null;
            $params = null;

            @list ($list, $params) = explode(':', $recipient, 2);

            if (!empty($params)) {
                $params = explode('|', $params);
            }

            $result[] = array(
                'issueId' => $issueId,
                'list' => $list,
                'params' => Zend_Json::encode($params)
            );
        }
        
        return $result;
    }
    
    public function delete()
    {
        if (!$this->_object->isEmpty()) {
            $this->_object->delete();
        }
    }
    
    public function sendPreview($data)
    {
        $filter = HHF_Domain_Issue::getFilter(
            HHF_Domain_Issue::FILTER_NEW,
            array(
                'farm' => $this->_object->getFarm(),
                'title' => $data['title']
            )
        );
        
        $filter->setData($data);

        if ($filter->isValid()) {
            
            $layout = new Zend_Layout();
            $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
            $layout->setLayout('email');
            $layout->getView()->farm = $this->_object->getFarm();
            $layout->content = $this->processContent(
                $this->createTestVariables(),
                $filter->getUnescaped('content')
            );
            $bodyHtml = $layout->render();

            $htmlFilter = new HH_Filter_HtmlToText();
            $bodyText = $htmlFilter->filter($bodyHtml);

            $emailJob = new HH_Job_Email();

            $emailJob->add(
                array(
                    $filter->getUnescaped('from'),
                    $this->_object->getFarm()->name
                ), 
                $data['previewEmail'], 
                $filter->getUnescaped('title'),
                $bodyText,
                $bodyHtml,
                $filter->getUnescaped('from'),
                null,
                null,
                'farmnik@harvesthand.com',
                'farmnik@harvesthand.com',
                array(
                    array(
                        'name' => 'List-Unsubscribe',
                        'value' => $filter->getUnescaped('from')
                    )
                )
            );
            
        } else {
            throw new HH_Object_Exception_Validation(
                $filter->getMessages()
            );
        }
    }
    
    public function createTestVariables()
    {
        $firstName = array('Joel', 'Vandana', 'Murray', 'Peter', 'Patricia');
        $lastName = array('Salatin', 'Shiva', 'Bookchin', 'Schumacher', 'Kropotkin', 'Bishop');
        $address = array('Abby Road', 'Avenue Q', 'Easy Street');
        $address2 = array('', 'Apt. 5');
        $city = array('Findhorn', 'Damanhur', 'Oneida', 'Brook Farm', 'Arcosanti');
        $state = array('Nova Scotia', 'Vermont', 'South Yorkshire');
        $zipCode = array('H5B 4G9', 'Q3R 5T6');
        $country = array('CA', 'US', 'UK');
        $telephone = array('281-330-8004', '678-999-8212', '867-5309');
        $fax = array('867-5309', '');
        $email = array('jsalatin@example.org', 'vshiva@example.org', 'mbookchin@example.org', 'pkropotkin@example.org');
        $userName = array('rodale', 'polyface', 'pkopotkin', 'mbookchin', 'organic');
        
        $customerArray = array(
            'id' => rand(1, 1000),
            'farmerId' => rand(1, 1000),
            'firstName' => $firstName[array_rand($firstName)],
            'lastName' => $lastName[array_rand($lastName)],
            'address' => rand(2, 1000) . ' ' . $address[array_rand($address)],
            'address2' => $address2[array_rand($address2)],
            'city' => $city[array_rand($city)],
            'state' => $state[array_rand($state)],
            'zipCode' => $zipCode[array_rand($zipCode)],
            'country' => $country[array_rand($country)],
            'telephone' => $telephone[array_rand($telephone)],
            'fax' => $fax[array_rand($fax)],
            'email' => $email[array_rand($email)],
            'secondaryEmail' => $email[array_rand($email)],
            'secondaryFirstName' => $firstName[array_rand($firstName)],
            'secondaryLastName' => $lastName[array_rand($lastName)],
            'enabled' => rand(0, 1),
            'balance' => rand(0, 800) . '.' . rand(0, 99),
            'addedDatetime' => date('c'),
            'updatedDatetime' => date('c'),
            'userName' => $userName[array_rand($userName)]
        );
        
        $variables = array(
            'customer' => $customerArray,
            'farm' => $this->_object->getFarm()->toArray()
        );
        
        return $variables;
    }
    
    public function processContent($variables = array(), $content = null)
    {
        if ($content === null) {
            $content = $this->_object->content;
        }
        
        if (strpos($content, '{% ') !== false 
            || strpos($content, '{{ ') !== false) {
            
            try {
                return $this->_runTemplate($variables, $content);
                
            } catch (Twig_Sandbox_SecurityError $exception) {
                HH_Error::exceptionHandler($exception, E_USER_WARNING);
                
                return $content;
                
            } catch (Twig_Error_Syntax $exception) {
                HH_Error::exceptionHandler($exception, E_USER_WARNING);
                
                return $content;
            }
            
        } else {
            return $content;
        }
    }
    
    protected function _runTemplate($variables = array(), $content = null)
    {
        $twig = $this->_initTwig($content);
        
        return $twig->render($variables);
    }
    
    /**
     * @return Twig_TemplateInterface
     */
    protected function _initTwig($content)
    {
        if ($this->_twig instanceof Twig_TemplateInterface) {
            return $this->_twig;
        }
        
        $twig = new Twig_Environment(
            new Twig_Loader_String(),
            array(
                'autoescape' => 'html',
                'charset' => 'utf-8',
                'cache' => false
            )
        );
        
        $policy = new Twig_Sandbox_SecurityPolicy(
            array(
                'filter',
                'for',
                'if',
                'raw',
                'set',
                'spaceless'
            ),
            array(
                'abs',
                'capitalize',
                'date',
                'date_modify',
                'default',
                'escape',
                'format',
                'join',
                'keys',
                'length',
                'lower',
                'merge',
                'nl2br',
                'number_format',
                'replace',
                'reverse',
                'slice',
                'sort',
                'striptags',
                'title',
                'trim',
                'upper',
                'url_encode',
            ),
            array(),
            array(),
            array(
                'attribute',
                'cycle',
                'date',
                'random',
                'range'
            )
        );
        
        $twig->addExtension(
            new Twig_Extension_Sandbox($policy, true)
        );
        
        $this->_twig = $twig->loadTemplate($content);
        
        return $this->_twig;
    }
}
