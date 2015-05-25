<?php

class ReportController extends Zend_Controller_Action
{
    public function indexAction()
    {
    }

    public function hashAction()
    {

        try {
            $hash    = $this->getParam('hash');
            $hashDoc = new Unsee_Hash($hash);
            $hashDoc->delete();
        } catch (Exception $e) {
            // Don't care
        }

        $this->redirect('/' . $hash . '/');
    }
}
