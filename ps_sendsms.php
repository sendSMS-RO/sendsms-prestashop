<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Sendsms extends Module
{
    protected $configValues = array(
        'PS_SENDSMS_USERNAME',
        'PS_SENDSMS_PASSWORD',
        'PS_SENDSMS_LABEL',
        'PS_SENDSMS_SIMULATION',
        'PS_SENDSMS_SIMULATION_PHONE',
        'PS_SENDSMS_OPTOUT',
        'PS_SENDSMS_STATUS'
    );

    public function __construct()
    {
        $this->name = 'ps_sendsms';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Any Place Media SRL';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('SendSMS');
        $this->description = $this->l('Folositi solutia noastra de expedieri SMS pentru a livra informatia corecta la momentul potrivit.');

        $this->confirmUninstall = $this->l('Sunteti sigur ca doriti sa dezinstalati?');

        if (!Configuration::get('PS_SENDSMS_USERNAME') || !Configuration.get('PS_SENDSMS_PASSWORD')) {
            $this->warning = $this->l('Nu au fost setate numele de utilizator si/sau parola');
        }
    }

    private function installDb()
    {
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'ps_sendsms_history`;');

        if (!Db::getInstance()->Execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ps_sendsms_history` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `phone` varchar(255) DEFAULT NULL,
            `status` varchar(255) DEFAULT NULL,
            `message` varchar(255) DEFAULT NULL,
            `details` longtext,
            `content` longtext,
            `type` varchar(255) DEFAULT NULL,
            `sent_on` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8')
        ) {
            return false;
        }
        return true;
    }

    private function uninstallDb()
    {
        if (!Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'ps_sendsms_history`;')) {
            return false;
        }
        return true;
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!$this->installDb()) {
            return false;
        }

        if (!parent::install()) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() || !$this->uninstallDb()) {
            return false;
        }

        foreach ($this->configValues as $config) {
            if (!Configuration::deleteByName($config)) {
                return false;
            }
        }

        return true;
    }
}
