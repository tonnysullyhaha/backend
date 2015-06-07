<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initEnv()
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $_SERVER['HTTP_USER_AGENT'] = 'cli';
        }

        if (empty($_SERVER['SERVER_NAME'])) {
            $_SERVER['SERVER_NAME'] = 'unsee.cc';
        }
    }

    protected function _initDocType()
    {
        // Strangely it works just like this
        $this->bootstrap('View');

        $doctypeHelper = new Zend_View_Helper_Doctype();
        $doctypeHelper->doctype('XHTML1_STRICT');
    }

    protected function _initConfig()
    {
        $config = new Zend_Config($this->getOptions(), true);
        Zend_Registry::set('config', $config);

        return $config;
    }

    public function _initViewVars()
    {
        $this->bootstrap('layout');
    }

    public function _initFront()
    {
        $this->bootstrap('frontController');
        $front = Zend_Controller_Front::getInstance();
        $front->registerPlugin(new Unsee_Controller_Plugin_Headers());
        $front->registerPlugin(new Unsee_Controller_Plugin_Dnt());
    }

    public function _initTimezone()
    {
        date_default_timezone_set(Zend_Registry::get('config')->timezone);
    }

    public function _initTranslate()
    {
        $locale = new Zend_Locale(Zend_Locale::findLocale());

        $localeName = $locale->getLanguage();

        if (!in_array($localeName, array('en', 'ru'))) {
            $localeName = 'en';
        }

        $translate = new Zend_Translate(
            array(
                'adapter' => 'tmx',
                'content' => APPLICATION_PATH . '/configs/lang.xml',
                'locale'  => $localeName
            )
        );

        Zend_Registry::set('Zend_Translate', $translate);
    }

    /**
     * @todo Make it lazy
     */
    protected function _initDb()
    {
        $redisServers = Zend_Registry::get('config')->redis->servers;

        // Both Redis instances would be one master server
        $redisMasterConfig = $redisSlaveConfig = $redisServers->{$redisServers->master};

        if (isset($_SERVER['GEOIP_CONTINENT'])) {
            $targetConfig = $redisServers->{'redis-' . $_SERVER['GEOIP_CONTINENT']};
            if ($targetConfig) {
                $redisSlaveConfig = $targetConfig;
            }
        }

        $redis = new Redis();
        $redis->connect($redisMasterConfig->host, $redisMasterConfig->port, 2);

        Zend_Registry::set('RedisMaster', $redis);

        if ($redisMasterConfig === $redisSlaveConfig) {
            Zend_Registry::set('RedisSlave', $redis);
        } else {
            $redis = new Redis();
            $redis->connect($redisSlaveConfig->host, $redisSlaveConfig->port);
            Zend_Registry::set('RedisSlave', $redis);
        }
    }

    /**
     * Defines the image's time to live
     *
     * @throws Zend_Exception
     */
    protected function _initTtl()
    {
        $ttls = Zend_Registry::get('config')->ttl->toArray();
        Zend_Registry::set('ttls', $ttls);
    }

    /**
     * Adds backend IP to response headers
     *
     * @author Michael Gorianskyi <michael.gorianskyi@westwing.de>
     */
    protected function _initBackendIpHeader()
    {
        header('X-Backend: ' . gethostname());
    }
}
