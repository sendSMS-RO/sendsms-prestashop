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

        if (!Configuration::get('PS_SENDSMS_USERNAME') || !Configuration::get('PS_SENDSMS_PASSWORD')) {
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

        # register hooks
        if (!$this->registerHook('actionOrderStatusPostUpdate')) {
            return false;
        }

        # install tabs
        $tabNames = array();
        $result = Db::getInstance()->ExecuteS("SELECT * FROM " . _DB_PREFIX_ . "lang order by id_lang");
        if (is_array($result)) {
            foreach ($result as $row) {
                $tabNames['main'][$row['id_lang']] = 'SendSMS';
                $tabNames['history'][$row['id_lang']] = 'Istoric';
                $tabNames['campaign'][$row['id_lang']] = 'Campanie';
                $tabNames['test'][$row['id_lang']] = 'Trimitere test';
            }
        }
        $this->installModuleTab('SendSMSTab', $tabNames['main'], 0);
        $idTab = Tab::getIdFromClassName("IMPROVE");
        $this->installModuleTab('SendSMSTab', $tabNames['main'], $idTab);
        $idTab = Tab::getIdFromClassName("SendSMSTab");
        $this->installModuleTab('AdminHistory', $tabNames['history'], $idTab);
        $this->installModuleTab('AdminCampaign', $tabNames['campaign'], $idTab);
        $this->installModuleTab('AdminSendTest', $tabNames['test'], $idTab);

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

        // Uninstall Tabs
        $this->uninstallModuleTab('SendSMSTab');
        $this->uninstallModuleTab('AdminHistory');
        $this->uninstallModuleTab('AdminCampaign');
        $this->uninstallModuleTab('AdminSendTest');

        return true;
    }

    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit'.$this->name)) {
            # get info
            $username = strval(Tools::getValue('PS_SENDSMS_USERNAME'));
            $password = strval(Tools::getValue('PS_SENDSMS_PASSWORD'));
            $label = strval(Tools::getValue('PS_SENDSMS_LABEL'));
            $isSimulation = strval(Tools::getValue('PS_SENDSMS_SIMULATION_'));
            $simulationPhone = strval(Tools::getValue('PS_SENDSMS_SIMULATION_PHONE'));
            $optout = strval(Tools::getValue('PS_SENDSMS_OPTOUT_'));
            $statuses = array();

            $orderStatuses = OrderState::getOrderStates($this->context->language->id);
            foreach ($orderStatuses as $status) {
                $statuses[$status['id_order_state']] = strval(Tools::getValue('PS_SENDSMS_STATUS_'.$status['id_order_state']));
            }

            # validate and update settings
            if (empty($username) || empty($label) || (empty($password) && !Configuration::get('PS_SENDSMS_PASSWORD'))) {
                $output .= $this->displayError($this->l('Trebuie sa completati numele de utilizator, parola si label expeditor'));
            } else {
                # validate phone number
                if (!empty($simulationPhone) && !Validate::isPhoneNumber($simulationPhone)) {
                    $output .= $this->displayError($this->l('Numarul de telefon nu este valid'));
                } else {
                    Configuration::updateValue('PS_SENDSMS_SIMULATION_PHONE', $simulationPhone);
                }
                Configuration::updateValue('PS_SENDSMS_USERNAME', $username);
                if (!empty($password)) {
                    Configuration::updateValue('PS_SENDSMS_PASSWORD', $password);
                }
                Configuration::updateValue('PS_SENDSMS_LABEL', $label);
                Configuration::updateValue('PS_SENDSMS_SIMULATION', !empty($isSimulation)?1:0);
                Configuration::updateValue('PS_SENDSMS_OPTOUT', !empty($optout)?1:0);
                Configuration::updateValue('PS_SENDSMS_STATUS', serialize($statuses));
                $output .= $this->displayConfirmation($this->l('Setarile au fost actualizate'));
            }
        }
        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Nume utilizator'),
                    'name' => 'PS_SENDSMS_USERNAME',
                    'required' => true
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('Parola'),
                    'name' => 'PS_SENDSMS_PASSWORD',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Label expeditor'),
                    'name' => 'PS_SENDSMS_LABEL',
                    'required' => true,
                    'desc' => 'maxim 11 caractere alfa numerice'
                ),
                array(
                    'type' => 'checkbox',
                    'label' => $this->l('Simulare trimitere SMS'),
                    'name' => 'PS_SENDSMS_SIMULATION',
                    'required' => false,
                    'values' => array(
                        'query' => array(
                            array(
                                'simulation' => null,
                            )
                        ),
                        'id' => 'simulation',
                        'name' => 'simulation'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Numar telefon simulare'),
                    'name' => 'PS_SENDSMS_SIMULATION_PHONE',
                    'required' => false
                ),
                array(
                    'type' => 'checkbox',
                    'label' => $this->l('Opt-out in cos'),
                    'name' => 'PS_SENDSMS_OPTOUT',
                    'required' => false,
                    'values' => array(
                        'query' => array(
                            array(
                                'optout' => null,
                            )
                        ),
                        'id' => 'optout',
                        'name' => 'optout'
                    )
                ),
            )
        );

        # add order statuses to options
        $orderStatuses = OrderState::getOrderStates($this->context->language->id);
        foreach ($orderStatuses as $status) {
            $fields_form[0]['form']['input'][] = array(
                'type' => 'textarea',
                'rows' => 7,
                'label' => $this->l('Mesaj: '.$status['name']),
                'desc' => $this->l('Variabile disponibile: {billing_first_name}, {billing_last_name}, {shipping_first_name}, {shipping_last_name}, {order_number}, {order_date}, {order_total}'),
                'name' => 'PS_SENDSMS_STATUS_'.$status['id_order_state'],
                'required' => false
            );
        }

        # add submit button
        $fields_form[0]['form']['submit'] = array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['PS_SENDSMS_USERNAME'] = Configuration::get('PS_SENDSMS_USERNAME');
        $helper->fields_value['PS_SENDSMS_PASSWORD'] = Configuration::get('PS_SENDSMS_PASSWORD');
        $helper->fields_value['PS_SENDSMS_LABEL'] = Configuration::get('PS_SENDSMS_LABEL');
        $helper->fields_value['PS_SENDSMS_SIMULATION_'] = Configuration::get('PS_SENDSMS_SIMULATION');
        $helper->fields_value['PS_SENDSMS_SIMULATION_PHONE'] = Configuration::get('PS_SENDSMS_SIMULATION_PHONE');
        $helper->fields_value['PS_SENDSMS_OPTOUT_'] = Configuration::get('PS_SENDSMS_OPTOUT');
        $statuses = unserialize(Configuration::get('PS_SENDSMS_STATUS'));
        foreach ($orderStatuses as $status) {
            $helper->fields_value['PS_SENDSMS_STATUS_'.$status['id_order_state']] = isset($statuses[$status['id_order_state']]) ? $statuses[$status['id_order_state']] : '';
        }

        return $helper->generateForm($fields_form);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        # get params
        $orderId = $params['id_order'];
        $statusId = $params['newOrderStatus']->id;

        # get configuration
        $statuses = unserialize(Configuration::get('PS_SENDSMS_STATUS'));
        if (isset($statuses[$statusId])) {
            # get order details
            $order = new Order($orderId);
            $billingAddress = new Address($order->id_address_invoice);
            $shippingAddress = new Address($order->id_address_delivery);

            # get billing phone number
            $phone = $this->validatePhone($this->selectPhone($billingAddress->phone, $billingAddress->phone_mobile));

            # transform variables
            $message = $statuses[$statusId];
            $replace = array(
                '{billing_first_name}' => $this->cleanDiacritice($billingAddress->firstname),
                '{billing_last_name}' => $this->cleanDiacritice($billingAddress->lastname),
                '{shipping_first_name}' => $this->cleanDiacritice($shippingAddress->firstname),
                '{shipping_last_name}' => $this->cleanDiacritice($shippingAddress->lastname),
                '{order_number}' => $order->reference,
                '{order_date}' => date('d.m.Y', strtotime($order->date_add)),
                '{order_total}' => number_format($order->total_paid, 2, '.', '')
            );
            foreach ($replace as $key => $value) {
                $message = str_replace($key, $value, $message);
            }

            if (!empty($phone)) {
                # send sms
                $this->sendSms($phone, $message);
            }
        }
    }

    private function selectPhone($phone, $mobile)
    {
        # if both, prefer mobile
        if (!empty($phone) && !empty($mobile)) {
            return $mobile;
        }

        if (!empty($mobile)) {
            return $mobile;
        }

        return $phone;
    }

    public function validatePhone($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (substr($phone, 0, 1) == '0' && strlen($phone) == 10) {
            $phone = '4'.$phone;
        } elseif (substr($phone, 0, 1) != '0' && strlen($phone) == 9) {
            $phone = '40'.$phone;
        } elseif (strlen($phone) == 13 && substr($phone, 0, 2) == '00') {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) < 11) {
            return false;
        }
        return $phone;
    }

    public function sendSms($phone, $message, $type = 'order')
    {
        $username = Configuration::get('PS_SENDSMS_USERNAME');
        $password = Configuration::get('PS_SENDSMS_PASSWORD');
        $isSimulation = Configuration::get('PS_SENDSMS_SIMULATION');
        $simulationPhone = $this->validatePhone(Configuration::get('PS_SENDSMS_SIMULATION_PHONE'));
        $from = Configuration::get('PS_SENDSMS_LABEL');
        if (empty($username) || empty($password)) {
            return false;
        }
        if ($isSimulation && empty($simulationPhone)) {
            return false;
        } elseif ($isSimulation && !empty($simulationPhone)) {
            $phone = $simulationPhone;
        }
        $message = $this->cleanDiacritice($message);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_URL, 'https://hub.sendsms.ro/json?action=message_send&username='.urlencode($username).'&password='.urlencode($password).'&from='.urlencode($from).'&to='.urlencode($phone).'&text='.urlencode($message));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Connection: keep-alive"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $status = curl_exec($curl);
        $status = json_decode($status, true);

        # history
        Db::getInstance()->insert('ps_sendsms_history', array(
            'phone' => pSQL($phone),
            'status' => isset($status['status'])?pSQL($status['status']):pSQL(''),
            'message' => isset($status['message'])?pSQL($status['message']):pSQL(''),
            'details' => isset($status['details'])?pSQL($status['details']):pSQL(''),
            'content' => $message,
            'type' => $type,
            'sent_on' => date('Y-m-d H:i:s')
        ));
    }

    function cleanDiacritice($string)
    {
        $balarii = array(
            "\xC4\x82",
            "\xC4\x83",
            "\xC3\x82",
            "\xC3\xA2",
            "\xC3\x8E",
            "\xC3\xAE",
            "\xC8\x98",
            "\xC8\x99",
            "\xC8\x9A",
            "\xC8\x9B",
            "\xC5\x9E",
            "\xC5\x9F",
            "\xC5\xA2",
            "\xC5\xA3",
            "\xC3\xA3",
            "\xC2\xAD",
            "\xe2\x80\x93");
        $cleanLetters = array("A", "a", "A", "a", "I", "i", "S", "s", "T", "t", "S", "s", "T", "t", "a", " ", "-");
        return str_replace($balarii, $cleanLetters, $string);
    }

    private function installModuleTab($tabClass, $tabName, $idTabParent)
    {
        $tab = new Tab();
        $tab->name = $tabName;
        $tab->class_name = $tabClass;
        $tab->module = $this->name;
        $tab->id_parent = $idTabParent;

        if (!$tab->save()) {
            return false;
        }
        return Tab::getIdFromClassName($tabClass);
    }

    private function uninstallModuleTab($tabClass)
    {
        $idTab = Tab::getIdFromClassName($tabClass);
        if ($idTab != 0) {
            $tab = new Tab($idTab);
            $tab->delete();
            return true;
        }
        return false;
    }
}
