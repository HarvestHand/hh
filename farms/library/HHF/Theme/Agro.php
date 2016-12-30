<?php

class HHF_Theme_Agro extends HHF_Theme
{
    public function  __construct(){

        $this->_styleSheets = array(
            'http://fonts.googleapis.com/css?family=Engagement',
            'http://fonts.googleapis.com/css?family=Julius+Sans+One',
            '/_farms/css/themes/agro/style.css',
            '/_farms/css/themes/agro/camera.css',
            '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css'
        );

        $this->_layout .= '.agro';
    }
}
