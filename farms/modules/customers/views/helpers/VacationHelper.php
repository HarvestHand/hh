<?php

class Zend_View_Helper_VacationHelper extends Zend_View_Helper_FormSelect
{
    public function vacationHelper(){
        return $this;
    }

    public function buildForm($share, $options, $vacationId, $vacation = null){
        $form = '<div id="vacation-' . $this->view->escape($vacationId) . '">';
        $form .= '<fieldset style="border-left: none;border-right: none;">';
        $form .= '<legend>&nbsp;<b>Vacation ' . $this->view->escape($vacationId) . '</b></legend>';
        $form .= '<table>';
        $form .= $this->buildSelect($vacationId, $share, 'Beginning Week', $vacation == null ? null:$vacation->startWeek);
        $form .= $this->buildSelect($vacationId, $share, 'Ending Week', $vacation == null ? null:$vacation->endWeek);
        $form .= $this->buildOptSelect($vacationId, $share->id, 'Delivery Option', $options, $vacation == null ?
            null:$vacation->vacationOptionId);
        $form .= '<tr><td colspan="2">';
        $form .= '<button id="remove-vacation" data-vacation="';
        $form .=  $this->view->escape($vacationId) . '" class="btn" ';
        $form .= 'style="float:right;">Remove</button>';
        $form .= '</td></tr></table></fieldset></div>';

        return $form;
    }

    public function buildSelect($elemId, $share, $title, $current = null){

        $select = '<tr><td>';
        $select .= '<label for="' . $this->view->escape($elemId) . '">&nbsp;' . $this->view->escape($title) .
                   '</label>&nbsp;&nbsp;</td><td>';
        $select .= '<select id="' . $this->view->escape($elemId) . '" name="' . $this->view->escape($share->id) .
        '[' .
                   $this->view->escape($elemId) . '][' . $this->view->escape($title) . ']">';

        foreach($share->getShare()->getWeeks() as $week){

            $nextWeek = date('o') . 'W' . date('W', time() + (7 * 24 * 60 * 60));
            $daynum = date("N", strtotime(date('D')));

            if($week < $nextWeek){
                continue;
            }

            $time = strtotime($week);
            $pieces = explode("W", $week);

            if(strlen((int) $pieces[1]) == 1){
                $pieces[1] = '0' . '' . ((int) $pieces[1] + 1);
            } else {
                $pieces[1] = (int) $pieces[1] + 1;
            }

            $endWeek = strtotime($pieces[0] . 'W' .  $pieces[1]);
            $date = date('d F', $time) . ' - ' .  date('d F Y', $endWeek);

            if($week == $current){
                $select .= '<option value="' . $this->view->escape($week) . '" selected>' . $this->view->escape($date) .
                           '</option>';
            } else{
                $select .= '<option value="' . $this->view->escape($week) . '">' . $this->view->escape($date) . '</option>';
            }
        }

        $select .= '</select>';
        $select .= '</td></tr>';

        return $select;
    }

    function buildOptSelect($elemId, $share, $title, $options, $current = null){
        $select = '<tr><td>';
        $select .= '<label for="' . $this->view->escape($elemId) . '">&nbsp;' . $this->view->escape($title) .
                   '</label>&nbsp;&nbsp;';
        $select .= '</td><td>';
        $select .= '<select id="' . $this->view->escape($elemId) . '" name="' . $this->view->escape($share) . '[' .
                   $this->view->escape($elemId) . '][' . $this->view->escape($title) . ']">';

        foreach($options as $option){

            if($option->id == $current){
                $select .= '<option value="' . $this->view->escape($option->id) . '" selected>' .
                           $this->view->escape($option->vacationOption) . '</option>';
            } else{
                $select .=
                    '<option value="' . $this->view->escape($option->id) . '">' . $this->view->escape($option->vacationOption) .
                    '</option>';
            }
        }

        $select .= '</select>';
        $select .= '</td></tr>';

        return $select;
    }

    public function buildMessage($type, $strong, $msg){
        $message = '<section class="' . $this->view->escape($type) . ' ui-widget ui-state-'.
                   ($type == 'info'?'highlight':'error').' ui-corner-all"><div>';
        $message .= '<i class="fa fa-exclamation-triangle"></i>';
        $message .= '<strong>&nbsp' . $this->view->escape($strong) . '&nbsp</strong>';
        $message .= $this->view->escape($msg);
        $message .= '</div></section>';

        return $message;
    }
}
