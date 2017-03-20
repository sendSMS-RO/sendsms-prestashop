<?php
class AdminCampaign extends ModuleAdminController
{
    protected $index;

    public function __construct()
    {
        $this->table = 'sendsms_campaign';
        $this->bootstrap = true;
        $this->meta_title = 'Campanie SMS';
        $this->display = 'add';

        $this->context = Context::getContext();

        parent::__construct();

        $this->index = count($this->_conf) + 1;
        $this->_conf[$this->index] = 'Mesajul a fost trimis';
    }

    public function renderForm()
    {
        $products = array();
        $products[] = array('id_product' => 0, 'name' => '- toate -');
        $productsDb = $this->getListOfProducts();
        $products = array_merge($products, $productsDb);

        $states = array();
        $states[] = array('id_state' => 0, 'name' => '- toate -');
        $statesDb = $this->getListOfBillingStates();
        $states = array_merge($states, $statesDb);

        $this->fields_form = array(
            'legend' => array(
                'title' => 'Filtrare clienti'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Perioada comenzii',
                    'name' => 'sendsms_period',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => 'Suma minima pe comanda',
                    'name' => 'sendsms_amount',
                    'size' => 40,
                    'required' => false
                ),
                array(
                    'type' => 'select',
                    'label' => 'Produs cumparat',
                    'name' => 'sendsms_product',
                    'required' => false,
                    'options' => array(
                        'query' => $products,
                        'id' => 'id_product',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => 'Judet facturare',
                    'name' => 'sendsms_billing_state',
                    'required' => false,
                    'options' => array(
                        'query' => $states,
                        'id' => 'id_state',
                        'name' => 'name'
                    )
                )
            ),
            'submit' => array(
                'title' => 'Filtreaza',
                'class' => 'button'
            )
        );

        if (!($obj = $this->loadObject(true))) {
            return;
        }

        $this->context->controller->addJS(
            Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/count.js'
        );

        return parent::renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitAdd' . $this->table)) {
//            $phone = strval(Tools::getValue('sendsms_phone'));
//            $message = strval(Tools::getValue('sendsms_message'));
//            $phone = $this->module->validatePhone($phone);
//            if (!empty($phone) && !empty($message)) {
//                $this->module->sendSms($phone, $message, 'test');
//                Tools::redirectAdmin(self::$currentIndex . '&conf=' . $this->index . '&token=' . $this->token);
//            } else {
//                $this->errors[] = Tools::displayError('Numarul de telefon nu este valid');
//            }
        }
    }

    private function getListOfProducts()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $sql = new DbQuery();
        $sql->select('id_product, name');
        $sql->from('product_lang');
        $sql->where('id_lang = '.$default_lang);
        $sql->orderBy('name ASC');
        return Db::getInstance()->executeS($sql);
    }

    private function getListOfBillingStates()
    {
        $sql = new DbQuery();
        $sql->select('id_state, name');
        $sql->from('state');
        $sql->where('active = 1');
        $sql->orderBy('name ASC');
        return Db::getInstance()->executeS($sql);
    }
}
