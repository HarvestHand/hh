<?php
/**
 * A support ticket form.
 * Created by PhpStorm.
 * Author: Ray Winkelman
 * Date: 6/3/15
 * Time: 9:59 AM
 */

class HH_View_SupportTicketForm extends Zend_Form
{
    protected $params = null;

    public function __construct($params = null){
        parent::__construct($params);

        $this->params = $params;

        $this->setMethod('post');
        $this->setName('report_issue');

        $title = new Zend_Form_Element_Select('user_type');
        $title->setLabel('Account Type')->setMultiOptions(array('Farmer' => 'Farmer', 'Customer' => 'Customer'))->setRequired(true)->addValidator('NotEmpty', true);

        $name = new Zend_Form_Element_Text('name');
        $name->setLabel('Your Name')->setRequired(true)->addValidator('NotEmpty');

        $email = new Zend_Form_Element_Text('email');
        $email->setLabel('Your Email Address')->addFilter('StringToLower')->setRequired(true)->addValidator('NotEmpty', true)->addValidator('EmailAddress');

        $description = new Zend_Form_Element_Textarea('description');
        $description->setLabel('Description Of Issue')->setRequired(true)->addValidator('NotEmpty');
        $description->setAttrib('cols', '55')->setAttrib('style', 'width:100%;margin:0;');

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Submit Report')->setAttrib('class', 'btn')->setAttrib('value', 'submit');

        $this->addElements(array($title, $name, $email, $description, $submit));
    }
}

