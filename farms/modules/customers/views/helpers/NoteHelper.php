<?php

class Zend_View_Helper_NoteHelper extends Zend_View_Helper_FormSelect
{
    public function noteHelper(){
        return $this;
    }

    public function buildForm($share){

        $thisWeek = date('o') . 'W' . date('W');

        $form = "<form method='POST' action='/admin/customers/notes'>";
        $form .= "<table><tr><td>";
        $form .= $this->view->translate("Week");
        $form .= "</td><td>";
        $form .= "<select name='week'>";
            foreach($share->getShare()->getWeeks() as $week){

                if($week < $thisWeek){
                    continue;
                }

                $date = $this->convertWeekToDateRange($week);

                $form .= "<option value='". $week ."'>";
                $form .= "(".$week.") - " . $this->view->translate($date);
                $form .= "</option>";
            }
        $form .= "</select>";
        $form .= "</td></tr>";
        $form .= "<tr><td>";
        $form .= $this->view->translate("Note");
        $form .= "</td><td>";
        $form .= '<input type="text" name="note" size="35">';
        $form .= '<input type="hidden" name="customerId" value="'.$share->customerId.'">';
        $form .= '<input type="hidden" name="customerShareId" value="'.$share->id.'">';
        $form .= "</td><td>";
        $form .= '<input class="btn" type="submit" value="'.$this->view->translate("Add").'">';
        $form .= "</td></tr>";
        $form .= "</table>";

        $form .= "</form>";
        return $form;
    }

    public function convertWeekToDateRange($week){
        $pieces = explode("W", $week);

        if(strlen((int) $pieces[1]) == 1){
            $endweekShort = '0' . '' . ((int) $pieces[1] + 1);
            $pieces[1] = '0' . '' . $pieces[1];
        } else {
            $endweekShort = (int) $pieces[1] + 1;
            $pieces[1] = $pieces[1];
        }

        $time = strtotime($pieces[0] . 'W' .  $pieces[1]);

        $endWeek = strtotime($pieces[0] . 'W' .  $endweekShort);
        $date = date('d F', $time) . ' - ' .  date('d F Y', strtotime('-1 day', $endWeek));

        return $date;
    }

}
