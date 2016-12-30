<?php

class HHF_Domain_Customer_Vacation_Service extends HH_Object_Service
{
    public function saveMultiple($farm, $customer, $data){

        $this->_object->setFarm($farm);

        foreach($data as $shareId => $posted){
            foreach($posted as $vacation){

                $this->_object->insert(array(
                                  'shareId'          => $shareId,
                                  'customerId'       => $customer->id,
                                  'vacationOptionId' => $vacation['Delivery Option'],
                                  'startWeek'        => $vacation['Beginning Week'],
                                  'endWeek'          => $vacation['Ending Week']
                              ));
            }
        }
    }
}
