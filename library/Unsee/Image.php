<?php

/**
 * Hash image model
 */
class Unsee_Image extends Unsee_Redis
{

    const DB = 1;

    /**
     * Image Magic instance
     *
     * @var \imagick
     */
    protected $iMagick;

    /**
     * Secure link md5
     */
    public $secureMd5 = '';

    /**
     * Secure link unix time
     *
     * @var int
     */
    public $secureTtd = 0;

    public function __construct(Unsee_Hash $hash, $imgKey = null)
    {
        $newImage = is_null($imgKey);

        if ($newImage) {
            $imgKey = uniqid();
        }

        parent::__construct($hash->key . '_' . $imgKey);

        if ($newImage) {
            $keys      = Unsee_Image::keys($hash->key . '*');
            $this->num = count($keys);
            $this->expireAt($hash->timestamp + $hash->getTtl());
        }

        $this->setSecureParams();
    }

    /**
     * Sets the params needed for the secure link nginx module to work
     *
     * @see http://wiki.nginx.org/HttpSecureLinkModule
     * @return boolean
     */
    public function setSecureParams()
    {
        $linkTtl = Unsee_Ticket::$ttl;

        if (!$this->no_download) {
            $linkTtl = $this->ttl();
        }

        $this->secureTtd = ceil(microtime(true)) + $linkTtl;

        // Preparing a hash for nginx's secure link
        $md5 = base64_encode(md5($this->key . $this->secureTtd, true));
        $md5 = strtr($md5, '+/', '-_');
        $md5 = str_replace('=', '', $md5);

        $this->secureMd5 = $md5;

        return true;
    }

    /**
     * Adds file to the share
     *
     * @param string $filePath
     *
     * @return boolean
     */
    public function addFile($filePath)
    {
        Unsee_Timer::start(Unsee_Timer::LOG_IMG_ADD_FILE);

        if (!file_exists($filePath)) {
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_ADD_FILE, 'File does not exist');

            return false;
        }

        $info        = getimagesize($filePath);
        $imageWidth  = $info[0];
        $imageHeight = $info[1];
        $maxSize     = 1000; // @todo Should be either in config or dynamically set
        $image       = $this->getImagick();

        $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 1);

        Unsee_Timer::start(Unsee_Timer::LOG_IMG_READ_FILE);
        $image->readimage($filePath);
        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_READ_FILE);

        Unsee_Timer::start(Unsee_Timer::LOG_IMG_RESIZE);
        if ($imageWidth > $maxSize && $imageWidth > $imageHeight) {
            $image->thumbnailimage($maxSize, null);
        } elseif ($imageHeight > $maxSize && $imageHeight > $imageWidth) {
            $image->thumbnailimage(null, $maxSize);
        }
        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_RESIZE);

        // @todo Should be in config
        $image->setCompression(Imagick::COMPRESSION_JPEG2000);
        $image->setCompressionQuality(92);

        Unsee_Timer::start(Unsee_Timer::LOG_IMG_STRIP);
        $image->stripimage();
        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_STRIP);

        $image->setImageFormat('jpeg');

        $this->size   = filesize($filePath);
        $this->type   = $info['mime'];
        $this->width  = $image->getImageWidth();
        $this->height = $image->getImageHeight();

        Unsee_Timer::start(Unsee_Timer::LOG_IMG_READ_IM);
        $this->content = $image->getImageBlob();
        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_READ_IM);

        $this->expireAt(time() + static::EXP_DAY);

        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_ADD_FILE);

        return true;
    }

    /**
     * Instantiates and returns Image Magick object
     *
     * @return \imagick
     */
    protected function getImagick()
    {

        if (!$this->iMagick) {
            $iMagick = new Imagick();
            if ($this->content) {
                Unsee_Timer::start(Unsee_Timer::LOG_IMG_CREATE_IM);
                $iMagick->readimageblob($this->content);
                Unsee_Timer::stop(Unsee_Timer::LOG_IMG_CREATE_IM);
            }
            $this->iMagick = $iMagick;
        }

        return $this->iMagick;
    }

    /**
     * Strips exif data from image body
     *
     * @return boolean
     */
    public function stripExif()
    {
        Unsee_Timer::start(Unsee_Timer::LOG_IMG_STRIP);
        $this->getImagick()->stripImage();
        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_STRIP);

        return true;
    }

    /**
     * Watermars the image with the viewer's IP
     *
     * @return boolean
     */
    public function watermark()
    {
        Unsee_Timer::start(Unsee_Timer::LOG_IMG_WM);

        $text  = $_SERVER['REMOTE_ADDR'];
        $font  = $_SERVER['DOCUMENT_ROOT'] . '/pixel.ttf';
        $image = $this->getImagick();

        $width    = $image->getImageWidth();
        $fontSize = round($width / 30);

        if ($fontSize < 30) {
            $fontSize = 30;
        }

        $watermarkSize = $fontSize * 16;

        $watermark = new Imagick();
        $watermark->newImage($watermarkSize, $watermarkSize, new ImagickPixel('none'));

        $draw = new ImagickDraw();
        $draw->setFont($font);
        $draw->setfontsize($fontSize);
        $draw->setFillColor('gray');
        $draw->setFillOpacity(.2);

        $watermark->annotateimage($draw, round($fontSize / 2), $fontSize, 45, $text);
        $watermark->annotateimage($draw, round($watermarkSize / 1.9), round($watermarkSize / 1.2), -45, $text);

        $this->iMagick = $image->textureimage($watermark);

        Unsee_Timer::stop(Unsee_Timer::LOG_IMG_WM);

        return true;
    }

    /**
     * Embeds a comment into the image body
     *
     * @param string $comment
     *
     * @return boolean
     */
    public function comment($comment)
    {
        $dict = array(
            '%ip%'         => $_SERVER['REMOTE_ADDR'],
            '%user_agent%' => $_SERVER['HTTP_USER_AGENT']
        );

        $comment = str_replace(array_keys($dict), $dict, $comment);
        $this->getImagick()->commentimage($comment);

        return true;
    }

    /**
     * Returns image binary content
     *
     * @return type
     */
    public function getImageContent()
    {
        if ($this->iMagick) {

            Unsee_Timer::start(Unsee_Timer::LOG_IMG_READ_IM);
            $res = $this->iMagick->getimageblob();
            Unsee_Timer::stop(Unsee_Timer::LOG_IMG_READ_IM);

            return $res;
        } else {
            return $this->content;
        }
    }
}
