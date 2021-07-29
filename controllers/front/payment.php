<?php

class OplatiPaymentModuleFrontController extends ModuleFrontController
{

    protected $data;

    protected $response;

    public function __construct()
    {
        parent::__construct();

        $this->setTemplate('payment_execution.tpl');
        $this->response = unserialize($this->context->cookie->__get('response'));
        $this->data     = unserialize($this->context->cookie->__get('data'));
    }

    public function setMedia()
    {
        parent::setMedia();

        $this->addJS(_MODULE_DIR_.'/'.$this->module->name.'/assets/js/script.js');
        Media::addJsDefL('repeat_payment', $this->module->l('Repeat payment'));
        Media::addJsDefL('cancel_payment', $this->module->l('Cancel payment'));

        return true;
    }

    public function postProcess()
    {
        if ($this->ajax) {

            $return = [
                'success' => true,
                'data'    => [],
                'message' => ''
            ];

            try {
                if (Tools::getValue('action') === 'rePayment') {
                    $this->initContent();
                    $return['data'] = $this->context->smarty->fetch($this->template);

                } else {
                    $this->getStatusOplatiTransaction();
                    $return['data']    = $this->response;
                    $return['message'] = $this->module->l('STATUS_'.$this->response['status']);
                }

            } catch (Exception $e) {
                $return['success'] = false;
                $return['message'] = $e->getMessage();
            }


            $this->ajaxDie(Tools::jsonEncode($return));
        }

        if (Tools::getValue('action') === 'success') {
            $this->success();
        }

        if (Tools::getValue('action') === 'cancel') {
            $this->cancel();
        }
    }

    protected function success()
    {
        if (isset($this->response['status']) === false || $this->response['status'] !== 1) {
            $this->errors[] = Tools::displayError('Payment not completed');
        } else {

            $cart = $this->context->cart;

            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || ! $this->module->active) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            $customer = new Customer($cart->id_customer);
            if ( ! Validate::isLoadedObject($customer)) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            $order = new Order($this->response['orderNumber']);

            $history = new OrderHistory();

            $history->id_order = $order->id;
            $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $this->response['orderNumber']);
            $history->addWithemail(true, array(
                'order_name' => $this->response['orderNumber']
            ));

            $order = new Order($this->response['orderNumber']);
            $orderPayments = $order->getOrderPayments();
            if(count($orderPayments)) {
                foreach ($orderPayments as $orderPayment) {
                    $orderPayment->transaction_id = $this->response['paymentId'];
                    $orderPayment->save();
                }
            }

            $this->context->cookie->__unset('response');
            $this->context->cookie->__unset('data');

            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart='.$order->id_cart.
                '&id_module='.$this->module->id.
                '&id_order='.$order->id.
                '&key='.$customer->secure_key
            );
        }
    }

    protected function cancel()
    {
        $history           = new OrderHistory();
        $history->id_order = $this->response['orderNumber'];
        $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $this->response['orderNumber']);
        $history->addWithemail(true, array(
            'order_name' => $this->response['orderNumber']
        ));

        $this->context->cookie->__unset('response');
        $this->context->cookie->__unset('data');
        Tools::redirect('index.php?controller=order&step=1');
    }

    public function initContent()
    {
        parent::initContent();

        if ($this->ajax === false) {
            $this->module->validateOrder((int)$this->context->cart->id, _PS_OS_PREPARATION_, $this->context->cart->getOrderTotal(), $this->module->displayName);
            $this->prepareData();
        }

        $this->createOplatiTransaction();

        $this->context->smarty->assign(array(
            'dynamicQR'            => $this->response['dynamicQR'],
            'check_status_timeout' => $this->module->getOption('check_status_timeout'),
            'qrsize'               => $this->module->getOption('qrsize')
        ));

    }

    public function createOplatiTransaction()
    {
        if (empty($this->data)) {
            throw new Exception("Can't create transaction because empty data");
        }
        $this->response = $this->parseResponse($this->request('pos/webPayments', $this->data));
        $this->context->cookie->__set('response', serialize($this->response));
    }

    public function getStatusOplatiTransaction()
    {
        if (isset($this->response['paymentId']) === false) {
            throw new Exception("Can't verify the status because no payment id");
        }
        $this->response = $this->parseResponse($this->request('pos/payments/'.$this->response['paymentId']));
        $this->context->cookie->__set('response', serialize($this->response));
    }

    private function parseResponse(array $response)
    {
        $body = json_decode($response['body'], true);

        if (isset($response['httpcode']) && $response['httpcode'] >= 200 && $response['httpcode'] < 300) {
            return $body;
        } else {
            PrestaShopLogger::addLog("Response(".$response['httpcode']."): ".$response['body'], 3);
            throw new Exception('Bad response.');
        }
    }

    private function request(string $url, array $data = [], string $requestMethod = 'GET'): array
    {

        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'regNum: '.$this->module->getOption('regnum'),
            'password: '.$this->module->getOption('password'),
        ];

        if ($this->module->getOption('test') === '1') {
            $server = 'https://bpay-testcashdesk.lwo.by/ms-pay/';
        } else {
            $server = 'https://cashboxapi.o-plati.by/ms-pay/';
        }

        $ch = curl_init($server.$url);
        if ( ! empty($data) || $requestMethod === 'POST') {
            $data = json_encode($data);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $headers[] = 'Content-Length: '.strlen($data);
        }

        if (in_array($requestMethod, ['HEAD', 'PUT', 'DELETE', 'PATCH', 'TRACE', 'CONNECT', 'OPTIONS'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER, $headers
        );

        $response = curl_exec($ch);

        $time        = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $httpcode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header      = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);

        if (curl_errno($ch)) {
            $body = json_encode([curl_error($ch)]);
        }

        curl_close($ch);

        return compact('body', 'httpcode', 'header', 'time');
    }

    private function prepareData()
    {
        $data = [
            'sum'         => $this->context->cart->getOrderTotal(),
            'shift'       => 'smena 1',
            'orderNumber' => $this->module->currentOrder,
            'regNum'      => $this->module->getOption('regnum'),
            'details'     => [
                'amountTotal' => $this->context->cart->getOrderTotal(),
                'items'       => []
            ],
            'successUrl'  => '',
            'failureUrl'  => ''
        ];

        $items = $this->context->cart->getProducts();

        foreach ($items as $item) {
            $data['details']['items'][] = [
                'type'     => 1,
                'name'     => $item['name'],
                'price'    => (float)$item['price_wt'],
                'quantity' => (int)$item['cart_quantity'],
                'cost'     => $item['total_wt'],
            ];
        }

        $shippingCost = $this->context->cart->getTotalShippingCost();

        if ($shippingCost > 0) {
            $carrier = new Carrier((int)($this->context->cart->id_carrier), Tools::getValue('id_lang'));

            $data['details']['items'][] = [
                'type' => 2,
                'name' => Translate::getModuleTranslation('oplati', 'Доставка %s', 'oplati', $carrier->name),
                'cost' => $shippingCost,
            ];
        }

        $this->context->cookie->__set('data', serialize($data));
        $this->data = $data;
    }

}