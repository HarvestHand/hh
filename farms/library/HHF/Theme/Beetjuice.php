<?php

class HHF_Theme_Beetjuice extends HHF_Theme
{
    public function  __construct(){
        $this->_styleSheets = array(
            '/_farms/css/themes/beetjuice/style.css',
            '/_farms/css/themes/beetjuice/ie6.css'
        );

        $this->_layout .= '.beetjuice';

        $this->_overrides = array(
            'website' => array(
                'public' => array(
                    'index' => false
                )
            )
        );
    }
}
