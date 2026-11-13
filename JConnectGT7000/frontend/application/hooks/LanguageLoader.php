<?php
class LanguageLoader
{
    /*
    public function initialize()
    {
        $ci = &get_instance();
        $ci->load->helper('language');
        $ci->lang->load('message', getSetting('language'));
    }
    */
    function initialize() {
        $ci =& get_instance();
        $ci->load->helper('language');
        $siteLang = $ci->session->userdata('site_lang');
        if ($siteLang) {
            $ci->lang->load('message',$siteLang);
        } else {
            $ci->lang->load('message','english');
        }
    }
 
}
