<?php

/**
 * Form decorator to correctly render radio button list
 */
class Unsee_Form_Decorator_Radio extends Zend_Form_Decorator_Abstract
{
    public function render($content)
    {
        $el      = $this->getElement();
        $elName  = $el->getName();
        $res     = '';
        $options = $el->getMultiOptions();

        foreach ($options as $name => $title) {
            // @todo "delete" is hardcoded, dynamically get group of the field here
            $captionStr  = 'settings_delete_' . $elName . '_' . $name . '_caption';
            $lang        = Zend_Registry::get('Zend_Translate');
            $captionProp = $selectedProp = '';

            if ($lang->isTranslated($captionStr)) {
                $captionProp = " title='" . $lang->translate($captionStr) . "' ";
            }

            if ($name === $el->getValue()) {
                $selectedProp = "checked='checked'";
            }

            $res .= sprintf(
                '<div><input type="radio" name="%1$s" id="%1$s_%2$s" value="%2$s" %3$s/>' .
                '<label %4$s for="%1$s_%2$s">%5$s</label></div>',
                $elName,
                $name,
                $selectedProp,
                $captionProp,
                $title
            );
        }

        return $res;
    }
}
