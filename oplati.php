<?php

class Oplati extends PaymentModule
{

    private $settingsList
        = array(
            'OPLATI_REGNUM',
            'OPLATI_PASSWORD',
            'OPLATI_TEST',
            'OPLATI_CHECK_STATUS_TIMEOUT',
            'OPLATI_QRSIZE',
        );

    private $_postErrors = array();

    public function __construct()
    {
        $this->name      = 'oplati';
        $this->tab       = 'payments_gateways';
        $this->version   = '1.0.0';
        $this->author    = 'Dmytro Sakharuk';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName            = $this->l('Oplati');
        $this->description            = $this->l('Payments via Oplati');
        $this->confirmUninstall       = $this->l('Are you want to remove the module?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99.99');

    }

    public function install()
    {
        return parent::install()
            && Configuration::updateValue('OPLATI_TEST', true)
            && Configuration::updateValue('OPLATI_CHECK_STATUS_TIMEOUT', 3)
            && Configuration::updateValue('OPLATI_QRSIZE', 180)
            && $this->registerHook('payment');
    }

    public function uninstall()
    {
        foreach ($this->settingsList as $val) {
            if ( ! Configuration::deleteByName($val)) {
                return false;
            }
        }

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $err = '';
        if (((bool)Tools::isSubmit('submitOplatiModule')) == true) {
            $this->_postValidation();
            if ( ! sizeof($this->_postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $err .= $this->displayError($err);
                }
            }
        }

        return $err.$this->renderForm();
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('submitOplatiModule')) {
            if (empty(Tools::getValue('OPLATI_REGNUM'))) {
                $this->_postErrors[] = $this->l('Merchant ID is required.');
            }
            if (empty(Tools::getValue('OPLATI_PASSWORD'))) {
                $this->_postErrors[] = $this->l('Secret key is required.');
            }
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'OPLATI_REGNUM'               => Configuration::get('OPLATI_REGNUM', null),
            'OPLATI_PASSWORD'             => Configuration::get('OPLATI_PASSWORD', null),
            'OPLATI_TEST'                 => Configuration::get('OPLATI_TEST', null),
            'OPLATI_CHECK_STATUS_TIMEOUT' => Configuration::get('OPLATI_CHECK_STATUS_TIMEOUT', null),
            'OPLATI_QRSIZE'               => Configuration::get('OPLATI_QRSIZE', null),
        );
    }


    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar             = false;
        $helper->table                    = $this->table;
        $helper->module                   = $this;
        $helper->default_form_language    = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier    = $this->identifier;
        $helper->submit_action = 'submitOplatiModule';
        $helper->currentIndex  = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token         = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        global $cookie;

        $options = [];

        foreach (OrderState::getOrderStates($cookie->id_lang) as $state) {  // getting all Prestashop statuses
            if (empty($state['module_name'])) {
                $options[] = ['status_id' => $state['id_order_state'], 'name' => $state['name']." [ID: $state[id_order_state]]"];
            }
        }

        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Please specify the Oplati account details for customers'),
                    'icon'  => 'icon-cogs',
                ),
                'input'  => array(
                    array(
                        'col'    => 4,
                        'type'   => 'text',
                        'prefix' => '<i class="icon icon-user"></i>',
                        'name'   => 'OPLATI_REGNUM',
                        'desc'   => $this->l('Enter a regnum'),
                        'label'  => $this->l('Regnum'),
                    ),
                    array(
                        'col'    => 4,
                        'type'   => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'name'   => 'OPLATI_PASSWORD',
                        'desc'   => $this->l('Enter a password'),
                        'label'  => $this->l('Password'),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Test server'),
                        'name'    => 'OPLATI_TEST',
                        'is_bool' => true,
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col'   => 4,
                        'type'  => 'text',
                        'name'  => 'OPLATI_CHECK_STATUS_TIMEOUT',
                        'label' => $this->l('Check status timeout (sec.)'),
                    ),
                    array(
                        'col'   => 4,
                        'type'  => 'text',
                        'name'  => 'OPLATI_QRSIZE',
                        'label' => $this->l('QR size'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
    }

    public function getOption($name)
    {
        return Configuration::get("OPLATI_".Tools::strtoupper($name));
    }


    /**
     * @param $params
     */
    public function hookPayment($params)
    {
        if ( ! $this->active) {
            return;
        }
        if ( ! $this->_checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
        ));

        return $this->display(__FILE__, 'payment.tpl');
    }

    private function _checkCurrency($cart)
    {
        $currency_order    = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }


}