<?php

class Unsee_Form_Element_Select_Model_Delete extends Unsee_Form_Element_Select_Model_Abstract
{
    static public function getValues(Zend_Translate $lang)
    {
        $vars   = Zend_Registry::get('ttls');
        $values = array();

        foreach ($vars as $item) {
            $elLangString = 'settings_delete_ttl_' . $item;

            if ($lang->isTranslated($elLangString)) {
                $values[$item] = $lang->translate($elLangString);
            }
        }

        return $values;
    }
}
