<?php

/**
 * Upload controller
 *
 */
class UploadController extends Zend_Controller_Action
{
    /**
     * Controller to handle file upload form
     *
     * @throws Exception
     */
    public function indexAction()
    {
        $response = new stdClass();

        try {
            $upload = new Zend_File_Transfer();
        } catch (Exception $e) {
            $response->error = $e->getMessage();
            $this->_helper->json->sendJson($response);
        }

        $upload->addValidator('Count', false, array('min' => 1, 'max' => 100));
        $upload->addValidator('IsImage', false);
        $upload->addValidator('Size', false, array('max' => '10MB', 'bytestring' => false));
        $translate = Zend_Registry::get('Zend_Translate');
        $updating  = false;

        try {
            if (!$upload->receive()) {
                throw new Exception($translate->translate('error_uploading'));
            } else {
                $files = $upload->getFileInfo();

                // Updating hash with new images
                if (!empty($_POST['hash']) && Unsee_Hash::isValid($_POST['hash'])) {
                    $hashDoc  = new Unsee_Hash($_POST['hash']);
                    $updating = true;
                    $response = array();

                    if (!Unsee_Session::isOwner($hashDoc) && !$hashDoc->allow_anonymous_images) {
                        die('[]');
                    }
                } else {
                    // Creating a new hash
                    $hashDoc = new Unsee_Hash();

                    if ($hashDoc->isValidTtl($_POST['time'])) {
                        $hashDoc->ttl       = (int) $_POST['time'];
                    } else {
                        throw new Exception($translate->translate('error_uploading'));
                    }

                    $response->hash = $hashDoc->key;
                }

                $imageAdded = false;

                foreach ($files as $file => $info) {
                    if (!$upload->isUploaded($file)) {
                        continue;
                    }

                    $imgDoc = new Unsee_Image($hashDoc);
                    $res    = $imgDoc->addFile($info['tmp_name']);
                    $imgDoc->setSecureParams(); //@todo: this is a hack to populate correct secureTtd, fix it

                    if ($updating) {
                        $ticket = new Unsee_Ticket();
                        $ticket->issue($imgDoc);

                        $newImg          = new stdClass();
                        $newImg->hashKey = $hashDoc->key;
                        $newImg->key     = $imgDoc->key;
                        $newImg->src     = '/image/' . $imgDoc->key . '/' . $imgDoc->secureMd5 . '/' . $imgDoc->secureTtd . '/';
                        $newImg->width   = $imgDoc->width;
                        $newImg->ticket  = md5(Unsee_Session::getCurrent() . $hashDoc->key);

                        $response[] = $newImg;
                    }

                    if ($res) {
                        $imageAdded = true;
                    }

                    // Remove uploaded file from temporary dir if it wasn't removed
                    if (file_exists($info['tmp_name'])) {
                        @unlink($info['tmp_name']);
                    }
                }

                if (!$imageAdded) {
                    // @todo: translate
                    throw new Exception('No images were added');
                }
            }
        } catch (Exception $e) {
            $response->error = $e->getMessage();
        }
        $this->_helper->json->sendJson($response);
    }
}
