<?php
/**
 * A support ticket class to email tasks into Asana.
 * Created by PhpStorm.
 * Author: Ray Winkelman
 * Date: 6/3/15
 * Time: 11:32 AM
 */

// TODO: Add the question "did this just happen?" ensued by a check box, to the form.
// Maybe let the users know that clicking it will attach the latest 10 lines of the HH error
// log to the support ticket. Or not and just attach them.

// TODO: Make a table to persist support tickets and append the scalar insert queries'
// return value to the title of the support ticket. (primary key)

// TODO: Make the sending of e-mails a cron job instead

// TODO: Autofill the input feilds with the users data.
// Ex: name, email, farm...

use Zend_Mail as TicketMailer;

class HH_Domain_SupportTicket {

    // Sends an e-mail to Asana's 'Bugs' Task List
    public function sendEmail($formData = array()){

        foreach($formData as $feild){
            if(empty($feild)){
                return false;
            }
        }

        ob_start();
        var_dump($_SESSION['Zend_Auth']);
        $session = ob_get_clean();

        $mail = new TicketMailer();
        $mail->setBodyHtml($formData['description'].'<br><hr><br>'.$session)
             ->setFrom('ray@harvesthand.com')
             ->addTo('x+10958621747524@mail.asana.com')
             ->setSubject($formData['user_type'].' Support Ticket: '.$formData['name']);

        $transport = new Zend_Mail_Transport_Sendmail();

        try {
            $transport->send($mail);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}