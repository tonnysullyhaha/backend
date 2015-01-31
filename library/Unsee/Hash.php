<?php

/**
 * Hash Redis model. Stores Unsee hashes
 */
class Unsee_Hash extends Unsee_Redis
{

    const DB = 0;

    /**
     * Associative array of periods of life for hashes
     *
     * @todo Should go to config
     * @var array
     */
    public function __construct($key = null)
    {
        parent::__construct($key);

        // @todo Default values should go to config
        // Creating a new share
        if (empty($key)) {
            $this->setNewHash();
            $this->timestamp              = time();
            $this->ttl                    = -1;
            $this->max_views              = 1;
            $this->views                  = 0;
            $this->no_download            = true;
            $this->strip_exif             = true;
            $this->comment                = Zend_Registry::get('config')->image_comment;
            $this->sess                   = Unsee_Session::getCurrent();
            $this->watermark_ip           = true;
            $this->allow_anonymous_images = false;
        }
    }

    /**
     * Returns the number of seconds the hash has to exist
     *
     * @return mixed
     */
    public function getTtl()
    {
        $ttl = $this->ttl ?: 84600;

        return $ttl - (time() - $this->timestamp);
    }

    /**
     * Generates a new unique hash
     *
     * @todo Make this a getter
     *
     * @return boolean
     */
    protected function setNewHash()
    {
        $hashConf = Zend_Registry::get('config')->hash->toArray();

        $vowels      = str_split($hashConf['vowels']);
        $consonants  = str_split($hashConf['consonants']);
        $syllableNum = (int) $hashConf['syllables'];

        shuffle($vowels);
        shuffle($consonants);

        $hash = '';

        for ($x = 1; $x <= $syllableNum; $x++) {
            $hash .= array_pop($consonants) . array_pop($vowels);
        }

        // This is all it takes to set a hash
        $this->key = $hash;

        // Check if the found hash exists
        if ($this->exists()) {
            // Delete it if it's outdated.
            if (!$this->isViewable()) {
                $this->delete();
            }

            // Anyway try generating a new one
            return $this->setNewHash();
        }

        return true;
    }

    /**
     * Returns an array of image models assigned to the hash
     *
     * @return \Unsee_Image[]
     */
    public function getImages()
    {
        $imagesKeys = Unsee_Image::keys($this->key . '*');
        $imageDocs  = array();

        foreach ($imagesKeys as $key) {
            list(, $imgKey) = explode('_', $key);
            $imageDocs[] = new Unsee_Image($this, $imgKey);
        }

        usort($imageDocs, function ($a, $b) {
            if ($a->num === $b->num) {
                return 0;
            }

            return ($a->num < $b->num) ? -1 : 1;
        });

        return $imageDocs;
    }

    /**
     * Deletes the hash
     */
    public function delete()
    {
        // Delete images
        $images = $this->getImages();

        foreach ($images as $item) {
            $item->delete();
        }

        parent::delete();
    }

    /**
     * Returns true if hash is not yet outdated
     *
     * @return boolean
     */
    public function isViewable()
    {
        $exists  = $this->exists();
        $viewsOk = !$this->max_views || $this->max_views > $this->views;
        $ttlOk   = $this->getTtl() > 0;

        return $exists && $viewsOk && $ttlOk;
    }

    /**
     * Returns human readable representation of number of seconds the hash is left to live
     *
     * @todo Might use refactoring to be more readable
     *
     * @return string
     */
    public function getTtlWords()
    {
        $secondsLeft = $this->getTtl();
        $lang        = Zend_Registry::get('Zend_Translate');

        if ($secondsLeft < 60) {
            return $lang->translate('moment');
        }

        $times           = array();
        $timeStrings     = array();
        $foundNonEmpty   = false;
        $times['day']    = strtotime('+1 day', 0);
        $times['hour']   = $times['day'] / 24;
        $times['minute'] = $times['hour'] / 60;

        foreach ($times as $timeFrame => &$seconds) {
            // Days/hours/minutes left
            $itemsLeft = floor($secondsLeft / $seconds);

            // Recalculate number of seconds left - minus seconds in current day/hour/minute
            $secondsLeft -= $itemsLeft * $seconds;
            // Trying to translate the number correctly
            $modRes = $itemsLeft % 10;
            if ($modRes === 1) {
                $timeFrame .= '_one';
            } elseif ($modRes > 1 && $modRes < 5) {
                $timeFrame .= '_couple';
            } else {
                $timeFrame .= '_many';
            }

            if ($itemsLeft || $foundNonEmpty) {
                $foundNonEmpty = true;
                $timeStrings[] = $itemsLeft . ' ' . $lang->translate($timeFrame);
            }
        }

        // Use last element anyway
        $deleteTime = array_pop($timeStrings);

        // If it's not the only one - prepend others
        if ($timeStrings) {
            $deleteTime = implode(', ', $timeStrings) . ' ' . $lang->translate('and') . ' ' . $deleteTime;
        }

        return $deleteTime;
    }

    static public function isValid($hash)
    {
        $hashConf    = Zend_Registry::get('config')->hash->toArray();
        $vowels      = $hashConf['vowels'];
        $consonants  = $hashConf['consonants'];
        $syllableNum = (int) $hashConf['syllables'];

        return preg_match('~([' . $consonants . '][' . $vowels . ']){' . $syllableNum . '}~', $hash);
    }

    public function isValidTtl($value)
    {
        return is_numeric($value) && in_array($value, Zend_Registry::get('ttls'));
    }
}
