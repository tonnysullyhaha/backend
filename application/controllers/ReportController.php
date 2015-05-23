<?php

class ReportController extends Zend_Controller_Action
{
    public function indexAction()
    {
    }

    public function hashAction()
    {

        $hash = $this->getParam('hash');
        $hashDoc = new Unsee_Hash($hash);

        $hashDoc->delete();

        $this->redirect('/' . $hash . '/');
    }
}
