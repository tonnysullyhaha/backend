<?php

/**
 * Controller for the image viewing page
 */
class ViewController extends Zend_Controller_Action
{

    /**
     * @var Zend_Form   Settings form
     */
    protected $form;

    /**
     * @var Unsee_Hash  Hash instance
     */
    protected $hashDoc;

    public function init()
    {
        // This page should never be indexed by robots
        $this->getResponse()->setHeader('X-Robots-Tag', 'noindex');

        $this->view->headScript()->appendFile('js/vendor/jquery-1.8.3.min.js');
        $this->view->headScript()->appendFile('js/vendor/jquery.visibility.js');
        $this->view->headScript()->appendFile('js/vendor/jquery.iframe-transport.js');
        $this->view->headScript()->appendFile('js/vendor/jquery.ui.widget.js');
        $this->view->headScript()->appendFile('js/vendor/jquery.fileupload.js');
        $this->view->headScript()->appendFile('js/vendor/jquery.lazyload.min.js');
        $this->view->headScript()->appendFile('js/vendor/jpg.js');
        $this->view->headScript()->appendFile('js/vendor/aes.js');
        $this->view->headScript()->appendFile('js/vendor/enc-base64-min.js');
        $this->view->headScript()->appendFile('js/vendor/keypress.js');

        $this->view->headScript()->appendFile('js/crypt.js');
        $this->view->headScript()->appendFile('js/chat.js');
        $this->view->headScript()->appendFile('js/view.js');

        $this->view->headLink()->appendStylesheet('css/normalize.css');
        $this->view->headLink()->appendStylesheet('css/h5bp.css');
        $this->view->headLink()->appendStylesheet('css/view.css');
        $this->view->headLink()->appendStylesheet('css/subpage.css');
        $this->view->headLink()->appendStylesheet('css/chat.css');

        // Preheating the form
        $this->form = new Application_Form_Settings;
    }

    /**
     * Process image sharing settings form
     *
     * @param Zend_Form  $form
     * @param Unsee_Hash $hashDoc
     *
     * @todo  Refactor
     *
     * @return boolean
     */
    private function handleSettingsFormSubmit($form, $hashDoc)
    {
        // Don't try to process the form if the hash was deleted or the viewer is not the author
        if (!$hashDoc || !Unsee_Session::isOwner($hashDoc)) {
            return false;
        }

        if (!$form->isValid($_POST)) {
            return false;
        }

        $values = $form->getValues();

        // Changed value of TTL
        if (isset($values['ttl']) && $hashDoc->ttl === 0) {
            // Revert no_download to the value from DB, since there's no way
            // it could have changed. It's disabled when ttl == 'first'.
            unset($values['no_download']);
        }

        $expireAt = false;

        // Apply values from form to hash in Redis
        foreach ($values as $field => $value) {
            if ($field === 'strip_exif') {
                // But skip strip_exif, since it's always on
                continue;
            }

            // Don't change no_download if current ttl is till "first view"
            if ($field === 'no_download' && (int) $hashDoc->ttl === 0) {
                continue;
            }

            if ($field === 'ttl') {
                // Delete after view?
                if ($value == 0) {
                    $expireAt = $hashDoc->timestamp + Unsee_Redis::EXP_DAY;
                    // Set to expire within a day after upload
                } elseif ($hashDoc->isValidTtl($value)) {
                    // @todo check if this is even used - added/checked
                    $expireAt = $hashDoc->timestamp + $value;
                }
            }

            $hashDoc->$field = $value;
        }

        if ($expireAt) {
            $hashDoc->expireAt($expireAt);
        }
    }

    /**
     * Default controller for image view page
     *
     * @return boolean
     */
    public function indexAction()
    {

        Unsee_Timer::start(Unsee_Timer::LOG_CON_HASH);
        // Hash (bababa)
        $hashString = $this->getParam('hash', false);

        if (!$hashString) {
            return $this->deletedAction();
        }

        // Get hash document
        $hashDoc   = $this->hashDoc = new Unsee_Hash($hashString);
        $form      = $this->form;
        $block     = new Unsee_Block($hashDoc->key);
        $sessionId = Unsee_Session::getCurrent();

        /**
         * "Block" cookie detected. This means that viewer performed one of the restricted actions, like
         * opening a web developer tools (Firebug), pressed the print screen button, etc.
         */
        if (isset($_COOKIE['block'])) {
            // Remove the cookie
            setcookie('block', null, 1, '/' . $hashDoc->key . '/');
            // Register a block flag for current session
            $block->$sessionId = time();

            Unsee_Timer::stop(Unsee_Timer::LOG_CON_HASH, 'Blocked');

            // Act as if the image was deleted
            return $this->deletedAction();
        }

        // The block flag was previously set for the current session
        if (isset($block->$sessionId)) {
            Unsee_Timer::stop(Unsee_Timer::LOG_CON_HASH, 'Blocked');

            return $this->deletedAction();
        }

        // It was already deleted/did not exist/expired
        if (!$hashDoc->isViewable($hashDoc)) {
            Unsee_Timer::stop(Unsee_Timer::LOG_CON_HASH, 'Not viewable');
            $hashDoc->delete();

            return $this->deletedAction();
        }

        // Handle image settings form submission
        if ($this->getRequest()->isPost()) {
            $this->handleSettingsFormSubmit($form, $hashDoc);

            // Check again after settings change
            // It was already deleted/did not exist/expired
            if (!$hashDoc->isViewable($hashDoc)) {
                Unsee_Timer::stop(Unsee_Timer::LOG_CON_HASH, 'Not viewable');

                return $this->deletedAction();
            }
        }

        // Getting an array of hash settings
        $values = $hashDoc->export();
        // Populate form values
        $form->populate($values);

        $this->view->no_download = (int) $hashDoc->no_download;
        $images                  = $hashDoc->getImages();
        // Creating a set of "tickets" to view images related to current hash
        $ticket = new Unsee_Ticket();

        // Create a view "ticket" for every image of a hash
        foreach ($images as $image) {
            $ticket->issue($image);
        }

        // Handle current request based on what settings are set
        foreach ($values as $key => $value) {
            $key = explode('_', $key);

            foreach ($key as &$itemItem) {
                $itemItem = ucfirst($itemItem);
            }

            $method = 'process' . implode('', $key);

            if (method_exists($this, $method) && !$this->$method()) {
                Unsee_Timer::stop(Unsee_Timer::LOG_CON_HASH, 'Setting not processed?');

                return $this->deletedAction();
            }
        }

        $this->view->isOwner = Unsee_Session::isOwner($hashDoc);

        // If viewer is the creator - don't count their view
        if (!Unsee_Session::isOwner($hashDoc)) {
            $hashDoc->views++;
        } else {
            // Owner - include extra webpage assets
            $this->view->headScript()->appendFile('js/settings.js');
            $this->view->headLink()->appendStylesheet('css/settings.css');
        }

        // @todo this looks funny - refactor it
        // Don't show the 'other party' text for the 'other party'
        if (Unsee_Session::isOwner($hashDoc) || $hashDoc->ttl) {
            if (!$hashDoc->ttl) {
                $deleteTimeStr         = '';
                $deleteMessageTemplate = 'delete_first';
            } else {
                $deleteTimeStr         = $hashDoc->getTtlWords();
                $deleteMessageTemplate = 'delete_time';
            }

            $this->view->deleteTime = $this->view->translate($deleteMessageTemplate, array($deleteTimeStr));
        }

        $this->view->groups = $form->getDisplayGroups();

        $message = '';
        if (Unsee_Session::isOwner($this->hashDoc)) {
            $message = $this->view->translate('upload_more_owner');
        } elseif ($hashDoc->allow_anonymous_images) {
            $message = $this->view->translate('upload_more_anonymous');
        }

        $this->view->welcomeMessage = $message;
        $this->view->hash           = $hashDoc->key;
        $this->view->report         = '<li><a id="report" href="/' . $hashDoc->key . '/report/">Take this page down</a></li>';

        Unsee_Timer::stop(Unsee_Timer::LOG_CON_HASH);

        return true;
    }

    public function noContentAction()
    {
        return $this->_response->setHttpResponseCode(204);
    }

    /**
     * Sets the hash title if available
     *
     * @return boolean
     */
    private function processTitle()
    {
        if (!empty($this->hashDoc->title)) {
            $this->view->title = $this->hashDoc->title;
        }

        return true;
    }

    /**
     * Sets the hash description if available
     *
     * @return boolean
     */
    private function processDescription()
    {
        if (!empty($this->hashDoc->description)) {
            $this->view->description = $this->hashDoc->description;
        }

        return true;
    }

    /**
     * Sets up things affected by the no_download setting
     *
     * @return boolean
     */
    private function processNoDownload()
    {
        // If it's a one-time view image
        if (!$this->hashDoc->ttl) {
            // Disable the "no download" checkbox
            // And set it to "checked"
            $this->form->getElement('no_download')->setAttrib('disabled', 'disabled')->setAttrib('checked', 'checked');
        }

        // Don't allow download if the setting is set accordingly or the image is a one-timer
        $this->view->no_download = $this->hashDoc->no_download;

        return true;
    }

    /**
     * Returns true if IP is allowed or the allow_ip setting is not set
     *
     * @return boolean
     */
    private function processAllowIp()
    {
        if (!empty($this->hashDoc->allow_ip) && !Unsee_Session::isOwner($this->hashDoc)) {
            $ip = $this->getRequest()->getServer('REMOTE_ADDR');

            return fnmatch($this->hashDoc->allow_ip, $ip);
        }

        return true;
    }

    /**
     * Returns true if the referring domain is not set or equals the one from the allow_domain setting
     *
     * @return boolean
     */
    private function processAllowDomain()
    {
        if (!empty($this->hashDoc->allow_domain) && !Unsee_Session::isOwner($this->hashDoc)) {
            if (empty($_SERVER['HTTP_REFERER'])) {
                return false;
            }

            $expectedDomain = $this->hashDoc->allow_domain;

            $ref = parse_url($_SERVER['HTTP_REFERER']);

            if (!isset($ref['host'])) {
                return false;
            }

            $actualDomain = $ref['host'];

            if (!preg_match("~^([\\w]+.)?$expectedDomain$~", $actualDomain)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Action for deleted hashes, displays a "Deleted" message
     *
     * @return bool
     */
    public function deletedAction()
    {
        $this->render('deleted');

        return $this->getResponse()->setHttpResponseCode(410);
    }

    /**
     * Returns information about the next image to load
     *
     * @return void
     */
    public function nextImageAction()
    {
        Unsee_Timer::start(Unsee_Timer::LOG_IMG_NEXT);

        $nextImage = $this->getRequest()->getParam('next', false);
        $hash      = $this->getRequest()->getParam('hash');

        if (!$hash) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT, 'No hash provided');
            die('No hash provided');
        }

        // Use next instead of prev

        $hashDoc     = new Unsee_Hash($hash);
        $images      = $hashDoc->getImages();
        $return      = null;
        $targetImage = null;

        // No images in the hash, empty return
        if (!$images) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT, 'No images!');
            Unsee_Timer::stop(Unsee_Timer::LOG_REQUEST, $_SERVER['REQUEST_URI']);
            Unsee_Timer::cut();

            $this->_helper->json($return);
        }

        if ($nextImage == 'first') {
            $targetImage = current($images);
        } elseif (!empty($images[$nextImage])) {
            $targetImage = $images[$nextImage];
        }

        if ($targetImage) {
            $nextTarget = null;
            $keys       = array_keys($images);
            $position   = array_search($nextImage, $keys);

            if (!empty($keys[$position + 1])) {
                $nextTarget = $keys[$position + 1];
            }

            $ticket = new Unsee_Ticket();
            $ticket->issue($targetImage);
            $return['ttd']   = $targetImage->secureTtd;
            $return['md5']   = $targetImage->secureMd5;
            $return['key']   = $targetImage->key;
            $return['width'] = $targetImage->width;
            $return['next']  = $nextTarget;
        }

        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT);
        Unsee_Timer::stop(Unsee_Timer::LOG_REQUEST, $_SERVER['REQUEST_URI']);
        Unsee_Timer::cut();

        $this->_helper->json($return);
    }

    /**
     * Action that handles image requests
     */
    public function imageAction()
    {
        Unsee_Timer::start(Unsee_Timer::LOG_IMG_NEXT_CONTENT);
        // We would just print out the image, no need for the renderer
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        // Getting request params
        $imageId = $this->getParam('id');
        $ticket  = $this->getParam('ticket');
        $time    = $this->getParam('time');

        // Dropping request if params are not right or the image is too old
        if (!$imageId || !$ticket || !$time || $time < time()) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT_CONTENT, 'Params are not right');

            return $this->noContentAction();
        }

        list($hashStr, $imgKey) = explode('_', $imageId);

        if (!$hashStr) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT_CONTENT, 'No hash string');

            return $this->noContentAction();
        }

        // Fetching the parent hash
        $hashDoc = new Unsee_Hash($hashStr);

        if (!$hashDoc) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT_CONTENT, 'Hash does not exist');

            return $this->noContentAction();
        }

        // Fetching the image Redis hash
        $imgDoc = new Unsee_Image($hashDoc, $imgKey);

        if (!$imgDoc) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT_CONTENT, 'Image doc does not exist');

            return $this->noContentAction();
        }

        /**
         * Restricting image download also means that it has to be requested by the page, e.g. no
         * direct access. No referrer means direct access.
         */
        if ($hashDoc->no_download && empty($_SERVER['HTTP_REFERER'])) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT_CONTENT, 'No referrer');

            return $this->noContentAction();
        }

        // Fetching ticket list for the hash, it should have a ticket for the requested image
        $ticketDoc = new Unsee_Ticket();

        // Looks like a gatecrasher, no ticket and image is not allowed to be downloaded directly
        if (!$ticketDoc->isAllowed($imgDoc) && $hashDoc->no_download) {
            // Delete the ticket
            $ticketDoc->invalidate($imgDoc);

            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT_CONTENT, 'Not allowed to view');

            return $this->noContentAction();
        } else {
            // Delete the ticket
            $ticketDoc->invalidate($imgDoc);
        }

        // Watermark viewer's IP if required and if the viewer is not the owner
        if ($hashDoc->watermark_ip && !Unsee_Session::isOwner($hashDoc)) {
            $imgDoc->watermark();
        }

        // Embed comment if required
        if ($hashDoc->comment) {
            $imgDoc->comment($hashDoc->comment);
        }

        // Download restricted
        if ($hashDoc->no_download) {
            $this->getResponse()->setHeader('Content-type', 'text/json');
            $imgDocEnc = new Unsee_Image_Encrypted($imgDoc);
            $imgDocEnc->setPassphrase('test');

            $salt = bin2hex($imgDocEnc->salt);
            $iv   = bin2hex($imgDocEnc->iv);

            $data = array(
                "ct" => $imgDocEnc->getImageContent(),
                "iv" => $iv,
                "s"  => $salt
            );

            print json_encode($data);
        } else {
            $this->getResponse()->setHeader('Content-type', 'image/jpeg');
            print $imgDoc->getImageContent();
        }

        // The hash itself was already outdated for one of the reasons.
        if (!$hashDoc->isViewable()) {
            // This means the image should not be available, so delete it
            $imgDoc->delete();
        }

        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_NEXT_CONTENT);
    }
}
