<?php

class Unsee_Timer
{

    const LOG_IMG_CONSTRUCT    = 'Image constructor';

    const LOG_IMG_ADD_FILE     = 'Adding file';

    const LOG_IMG_RESIZE       = 'Resizing image';

    const LOG_IMG_STRIP        = 'Stripping image';

    const LOG_IMG_CREATE_IM    = 'Creating IM object';

    const LOG_IMG_WM           = 'Watermarking an image';

    const LOG_IMG_READ_IM      = 'Getting image content from IM';

    const LOG_IMG_READ_FILE    = 'Getting image content from file';

    const LOG_IMG_NEXT         = 'Getting next image information';

    const LOG_IMG_NEXT_CONTENT = 'Getting next image content';

    const LOG_DB_GET           = 'Getting key';

    const LOG_DB_SET           = 'Setting key';

    const LOG_DB_SELECT        = 'Selecting DB';

    const LOG_DB_EXISTS        = 'Key exists';

    const LOG_DB_DEL           = 'Deleting key';

    const LOG_DB_EXPORT        = 'Exporting hash';

    const LOG_DB_EXPIRE        = 'Setting key expire';

    const LOG_DB_TTL           = 'Getting key TTL';

    const LOG_DB_KEYS          = 'Getting KEYS';

    const LOG_REQUEST          = 'Request time';

    const PRECISION            = 3;

    const LOG_FILE             = '/tmp/time.log';

    static protected $timers = array();

    static public function start($name)
    {
        return static::$timers[$name] = microtime(true);
    }

    static public function stop($name, $extra = '')
    {
        if (!isset(static::$timers[$name])) {
            return false;
        }

        $delta   = microtime(true) - static::$timers[$name];
        $delta   = sprintf('%0.' . static::PRECISION . 'f', $delta);
        $message = $delta . ' ' . $name . ' ' . $extra . PHP_EOL;

        file_put_contents(static::LOG_FILE, $message, FILE_APPEND);

        return $delta;
    }

    static public function cut()
    {
        file_put_contents(static::LOG_FILE, PHP_EOL, FILE_APPEND);
    }
}
