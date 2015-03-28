<?php

class Unsee_Image_Encrypted
{

    protected $image;

    public $passPhrase;

    public $salt;

    public $iv;

    public $key;

    public function __construct(Unsee_Image $imageDoc)
    {
        $this->image = $imageDoc;
        $this->salt = openssl_random_pseudo_bytes(8);
    }

    public function setPassphrase($passPhrase)
    {
        $this->passPhrase = $passPhrase;
        $saltedString     = $this->getSaltedString($passPhrase, $this->salt);
        $this->key        = $this->getKey($saltedString);
        $this->iv         = $this->getIv($saltedString);
    }

    protected function getSaltedString($passPhrase, $salt)
    {
        $salted = '';
        $dx     = '';

        while (strlen($salted) < 48) {
            $dx = md5($dx . $passPhrase . $salt, true);
            $salted .= $dx;
        }

        return $salted;
    }

    protected function getKey($saltedString)
    {
        return substr($saltedString, 0, 32);
    }

    protected function getIv($saltedString)
    {
        return substr($saltedString, 32, 16);
    }

    public function getImageContent()
    {
        $base64Image    = base64_encode($this->image->getImageContent());
        $encrypted_data = openssl_encrypt($base64Image, 'aes-256-cbc', $this->key, true, $this->iv);

        return base64_encode($encrypted_data);
    }
}
