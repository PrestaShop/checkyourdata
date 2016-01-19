<?php
/**
 * 2015 CheckYourData
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *  @author    Check Your Data <contact@checkyourdata.net>
 *  @copyright 2015 CheckYourData
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class CheckYourData extends Module
{
    private static $dcUrl = 'app.checkyourdata.net/';

    /**
     * Module instanciate
     */
    public function __construct()
    {
        if (!class_exists('CheckYourDataWSHelper')) {
            include_once dirname(__FILE__) . '/wshelper.inc.php';
        }
        // trackers
        if (!class_exists('CheckYourDataGAnalytics')) {
            include_once dirname(__FILE__) . '/trackers/ganalytics.inc.php';
        }

        // Environement configuration
        $host = $_SERVER['HTTP_HOST'];
        if (strpos($host, 'ribie.re')) {
            // RECETTE
            self::$dcUrl = 'app-preprod.checkyourdata.net/';
        } elseif (strpos($host, 'cyd.com') || strpos($host, 'dc.com')) {
            // LOCAL
            self::$dcUrl = 'app2.cyd.com/';
        }

        if (getenv('CYD_ENV') == 'dev') {
            self::$dcUrl = getenv('CYD_APP') . '/';
        }

        $this->name = 'checkyourdata';
        $this->tab = 'analytics_stats';

        $this->version = '1.2.7';

        $this->author = 'Check Your Data - http://www.checkyourdata.net';

        // warnings in admin
        $this->need_instance = 1;

        if (_PS_VERSION_ >= '1.5.6.2') {
            // BUG prestashop on v1.5.4.1 => compliancy not used properly
            // BUG fixed on v1.5.6.2.
            $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        }

        if (_PS_VERSION_ >= '1.6.0.0') {
            // from PS1.6, theme bootstrap
            $this->bootstrap = true;
        }

        parent::__construct();

        $this->displayName = $this->l('Check Your Data - Analytics reports 100 percent reliable');
        $this->description = $this->l('Discover the real return on your marketing investments: collect 100 percent of your sales data in Google Analytics to get the most of your reports with clean, accurate and reliable data.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        // Warning if token not set
        if (!Configuration::get('checkyourdata_token')) {
            $this->warning = $this->l('Access key to checkyourdata.net not configured');
        } else {
            // Warning if demo
            $demoEnd = Configuration::get('checkyourdata_demo_end');
            if (!empty($demoEnd)) {
                $dt = new DateTime();
                $dend = DateTime::createFromFormat('Y-m-d H:i:s', $demoEnd);
                if ($dt > $dend) {
                    $this->warning = $this->l('Demo ended. Your data are no more tracked. Please complete your account informations on Check Your Data App');
                } else {
                    $this->warning = $this->l('Demo active. Your data are tracked until').' '.$dend->format('d/m/Y H:i:s');
                }
            }
        }


        if (_PS_VERSION_ < '1.5.0.0') {
            /** Backward compatibility */
            require(_PS_MODULE_DIR_ . $this->name . '/backward_compatibility/backward.php');
        }

        // TRACKERS
        $trackers = Tools::jsonEncode(Configuration::get('checkyourdata_trackers'), true);
        if ($trackers != null && !empty($trackers['ganalytics']['active']) && $trackers['ganalytics']['active']) {
            $res = CheckYourDataGAnalytics::init($trackers['ganalytics']['ua'], $this);
            if ($res != '') {
                $this->warning = $res;
            }
        }

        // show error messages if present
        $errs = Configuration::get('checkyourdata_last_errors');
        if (!empty($errs)) {
            $this->warning = $errs;
        }
    }

    /**
     * Uninstall module
     * @return bool : true if uninstall OK, false otherwise
     */
    public function uninstall()
    {
        $ko = false;

        // Common Hooks
        $ko = $ko || !$this->unregisterHook('header');
        $ko = $ko || !$this->unregisterHook('paymentTop');
        $ko = $ko || !$this->unregisterHook('updateOrderStatus');

        if (_PS_VERSION_ >= '1.5.0.0' && _PS_VERSION_ < '1.6.0.0') {
            $ko = $ko || !$this->unregisterHook('displayMobileHeader');
        }

        if (_PS_VERSION_ < '1.5.0.0') {
            // REFUND
            $ko = $ko || !$this->unregisterHook('cancelProduct');
        } else {
            // REFUND
            if (_PS_VERSION_ < '1.6.0.0') {
                $ko = $ko || !$this->unregisterHook('displayAdminOrder');
            }

            $ko = $ko || !$this->unregisterHook('actionObjectOrderDetailUpdateAfter');
            $ko = $ko || !$this->unregisterHook('displayAdminOrderContentOrder');
        }
        
        $ko = $ko || !parent::uninstall();

        if (!$ko) {
            // reset conf data
            Configuration::updateValue('checkyourdata_token', '');
            Configuration::updateValue('checkyourdata_user_email', '');
            Configuration::updateValue('checkyourdata_last_errors', '');
            Configuration::updateValue('checkyourdata_demo_end', '');
        }

        return !$ko;
    }

    /**
     * Module install : HOOKs registration
     * @return bool : true if install OK, false otherwise
     */
    public function install()
    {
        $ko = !parent::install();

        // Commons Hooks
        $ko = $ko || !$this->registerHook('header');
        $ko = $ko || !$this->registerHook('paymentTop');
        $ko = $ko || !$this->registerHook('updateOrderStatus');

        if (_PS_VERSION_ >= '1.5.0.0' && _PS_VERSION_ < '1.6.0.0') {
            $ko = $ko || !$this->registerHook('displayMobileHeader');
        }

        if (_PS_VERSION_ < '1.5.0.0') {
            // REFUND
            $ko = $ko || !$this->registerHook('cancelProduct');
        } else {
            // REFUND
            if (_PS_VERSION_ < '1.6.0.0') {
                $ko = $ko || !$this->registerHook('displayAdminOrder');
            }

            $ko = $ko || !$this->registerHook('actionObjectOrderDetailUpdateAfter');
            $ko = $ko || !$this->registerHook('displayAdminOrderContentOrder');
        }

        return !$ko;
    }

    /* Alias for PS 1.5 of hookDisplayAdminOrderContentOrder of PS 1.6 */
    public function hookDisplayAdminOrder($params)
    {
        $params['order'] = new Order($params['id_order']);
        return $this->hookDisplayAdminOrderContentOrder($params);
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        // get CYD token
        $token = Configuration::get('checkyourdata_token');
        if (empty($token)) {
            return '';
        }

        $order = $params['order'];
        $oid = $order->id;

        // check if refund to send
        $ordersToRefound = Configuration::get('checkyourdata_ref_orders');
        if ($ordersToRefound === false) {
            return;
        }
        $ordersToRefound = Tools::jsonDecode($ordersToRefound, true);
        if (empty($ordersToRefound[$oid])) {
            return;
        }

        // data perpare
        $data = array(
            'token' => $token,
            'action' => 'partialRefound',
            'data' => array(
                'items' => Tools::jsonEncode($ordersToRefound[$oid]),
                'orderId' => $oid,
            ),
        );
        // send to APP
        $res = CheckYourDataWSHelper::send(self::$dcUrl, $data);

        // if ok, delete 'toRefund' in order
        if ($res['state'] == 'ok') {
            unset($ordersToRefound[$oid]);
            Configuration::updateValue('checkyourdata_ref_orders', Tools::jsonEncode($ordersToRefound));
        }
    }

    public function hookActionObjectOrderDetailUpdateAfter($params)
    {
        $orderDet = $params['object'];//OrderDetail

        $pid = $orderDet->product_id;
        $paid = $orderDet->product_attribute_id;
        // if no qty refunded, no action
        if (empty($orderDet->product_quantity_refunded)) {
            return;
        }
        $qtyRefound = $orderDet->product_quantity_refunded;

        // save to send after
        $ordersToRefound = Configuration::get('checkyourdata_ref_orders');
        if ($ordersToRefound == false) {
            $ordersToRefound = array();
        } else {
            $ordersToRefound = Tools::jsonDecode($ordersToRefound, true);
        }
        // set of order if not set
        $oid = $orderDet->id_order;
        if (!isset($ordersToRefound[$oid])) {
            $ordersToRefound[$oid] = array();
        }
        // set of qty to refund and product id
        $ordersToRefound[$oid][$pid . '_' . $paid] = $qtyRefound;

        Configuration::updateValue('checkyourdata_ref_orders', Tools::jsonEncode($ordersToRefound));
    }

    /**
     * HOOK displayHeader : add Google Analytics JS tracking code
     * @return string : html to add to header
     */
    public function hookDisplayMobileHeader()
    {
        return $this->hookHeader();
    }

    /**
     * HOOK displayHeader : add Google Analytics JS tracking code
     * @return string : html to add to header
     */
    public function hookHeader()
    {
        $token = Configuration::get('checkyourdata_token');
        // if module is not configured
        if (empty($token)) {
            return;
        }
        $out = '';

        // sur toutes les pages, sauf sur la confirmation
        $controller = $this->context->controller->php_self;
        if ($controller == 'order-confirmation') {
            return;
        }

        // trackers
        $trackers = Tools::jsonDecode(Configuration::get('checkyourdata_trackers'), true);
        // ganalytics is activated ?
        if ($trackers['ganalytics']['active']) {
            $this->trackerAction(
                CheckYourDataGAnalytics::hookHeader($trackers['ganalytics']['ua']),
                $out
            );
        }

        return $out;
    }


    /**
     * HOOK Payment choice page : JS call to APP, order init
     * @return string : html / JS to add to page
     */
    public function hookPaymentTop()
    {
        $error = array();
        // get CYD token
        $token = Configuration::get('checkyourdata_token');
        if (empty($token)) {
            return '';
        }

        $out = '';

        // get order (cart)
        $cart = $this->context->cart;

        // trackers
        $trackers = Tools::jsonDecode(Configuration::get('checkyourdata_trackers'), true);
        // ganalytics is activated ?
        if ($trackers['ganalytics']['active']) {

            if (!CheckYourDataGAnalytics::addTrackerData($trackers['ganalytics']['ua'])) {
                $error[] = 'No_GCID';
            };
        }


        if (count($error) == 0) {
            $trData = CheckYourDataWSHelper::getTrackersData();
            $res = $this->sendInitOrderToApp($cart->id, $trData);

            // errors
            $toResend = Configuration::get('checkyourdata_carts_in_error');
            if (!empty($toResend)) {
                $toResend = Tools::jsonDecode($toResend, true);
            } else {
                $toResend = array();
            }
            if ($res['state'] != 'ok') {
                // save cart to re send
                // add current order
                if (!isset($toResend[$cart->id])) {
                    $toResend[$cart->id] = CheckYourDataWSHelper::getTrackersData();
                    Configuration::updateValue('checkyourdata_carts_in_error', Tools::jsonEncode($toResend));
                }
                error_log('Checkyourdata WS Update Order error : ' . implode("\n", $res['errors']));
            } else {
                // all ok
                // remove current sent cart from carts in error if in
                if (isset($toResend[$cart->id])) {
                    unset($toResend[$cart->id]);
                }
                // try to resend others carts in error
                $newToResend = array();
                foreach ($toResend as $cid => $trData) {
                    if (empty($cid)) {
                        continue;
                    }
                    $r = $this->sendInitOrderToApp($cid, $trData);
                    if ($r['state'] != 'ok') {
                        // keep in resend array
                        $newToResend[$cid] = $trData;
                    }
                }
                Configuration::updateValue('checkyourdata_carts_in_error', Tools::jsonEncode($newToResend));
            }
        } else {
            if ($trackers['ganalytics']['active']) {

                $data = $this->formatDataToSend($cart->id);
                $enc = CheckYourDataWSHelper::encodeData($data);
                // add JS vars
                $this->trackerAction(
                    array(
                        'tpl' => array(
                            'file' => 'ganalytics/payment-top.tpl',
                            'smarty' => array(
                                'url' => '//' . self::$dcUrl . 'ws/',
                                'data' => 'k=' . $enc['key'] . '&d=' . $enc['data'],
                            ),
                        ),
                    ),
                    $out
                );

            }
        }

        return $out;
    }

    public function sendInitOrderToApp($cartId, $trData)
    {
        $data = $this->formatDataToSend($cartId);

        return CheckYourDataWSHelper::send(self::$dcUrl, $data, $trData);

    }

    /**
     * HOOK order state change
     * @param type $params : array containing order details
     */
    public function hookUpdateOrderStatus($params)
    {
        // get CYD token
        $token = Configuration::get('checkyourdata_token');
        if (empty($token)) {
            return;
        }

        // send to APP
        $res = $this->sendOrderToApp($params['id_order'], $params['newOrderStatus']->id);

        // errors
        $toResend = Configuration::get('checkyourdata_orders_in_error');
        if (!empty($toResend)) {
            $toResend = Tools::jsonDecode($toResend, true);
        } else {
            $toResend = array();
        }
        if ($res['state'] != 'ok') {
            // save order to re send
            // add current order
            if (!in_array($params['id_order'], $toResend)) {
                $toResend[] = $params['id_order'];
                Configuration::updateValue('checkyourdata_orders_in_error', Tools::jsonEncode($toResend));
            }
            error_log('Checkyourdata WS Update Order error : ' . implode("\n", $res['errors']));
        } else {
            // all ok
            $cartsToResend = Configuration::get('checkyourdata_carts_in_error');
            if (!empty($cartsToResend)) {
                $toResend = Tools::jsonDecode($cartsToResend, true);
            } else {
                $cartsToResend = array();
            }
            // try to resend old carts
            $newCartsToResend = array();
            foreach ($cartsToResend as $cid => $trData) {
                if (empty($cid)) {
                    continue;
                }
                $r = $this->sendInitOrderToApp($cid, $trData);
                if ($r['state'] != 'ok') {
                    // keep in resend array
                    $newCartsToResend[$cid] = $trData;
                }
            }
            Configuration::updateValue('checkyourdata_carts_in_error', Tools::jsonEncode($newCartsToResend));

            // and after carts, try to resend old orders
            $newToResend = array();
            foreach ($toResend as $oid) {
                if (empty($oid)) {
                    continue;
                }
                $r = $this->sendOrderToApp($oid);
                if ($r['state'] != 'ok') {
                    // keep in resend array
                    $newToResend[] = $oid;
                }
            }
            Configuration::updateValue('checkyourdata_orders_in_error', Tools::jsonEncode($newToResend));
        }
    }

    private function sendOrderToApp($orderId, $nextState = null)
    {
        $token = Configuration::get('checkyourdata_token');
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            // no order
            return;
        }

        if ($nextState === null) {
            $nextState = $order->getCurrentState();
        }

        // conversion rate
        $conversion_rate = 1;
        $currency = new Currency((int)$order->id_currency);
        /*if ($order->id_currency != Configuration::get('PS_CURRENCY_DEFAULT')) {
            $conversion_rate = (float) $currency->conversion_rate;
        }*/

        // amounts (with taxes on shipping)
        $tax = $order->getTotalProductsWithTaxes() - $order->getTotalProductsWithoutTaxes();
        $tax += $this->getShippingTotal($order);

        // Order general information
        $trans = array(
            'id' => (int)$order->id,
            'cartId' => (int)$order->id_cart,
            'store' => htmlentities(Configuration::get('PS_SHOP_NAME')),
            'total' => Tools::ps_round((float)$order->total_paid / (float)$conversion_rate, 2),
            'shipping' => Tools::ps_round((float)$order->total_shipping / (float)$conversion_rate, 2),
            'tax' => $tax,
            //'city' => addslashes($delivery_address->city),
            'state' => $nextState,
            //'country' => addslashes($delivery_address->country),
            'currency' => $currency->iso_code
        );


        $pms = PaymentModule::getInstalledPaymentModules();
        $pmid = '';
        foreach ($pms as $pm) {
            if ($pm['name'] == $order->module) {
                $pmid = $pm['id_module'];
                break;
            }
        }

        // Product information
        $products = $order->getProducts();
        $items = array();
        foreach ($products as $p) {
            $categ = new Category($this->getProductDefaultCategory($p));
            $items [$p['product_id'] . '_' . $p['product_attribute_id']] = array(
                'name' => $p['product_name'],
                'price' => Tools::ps_round((float)$p['product_price_wt'] / (float)$conversion_rate, 2),
                'code' => $this->getProductReference($p),
                'category' => implode(" ", $categ->name),
                'qty' => $p['product_quantity'],
            );
        }

        $data = array(
            'token' => $token,
            'action' => 'changeOrderState',
            'data' => array(
                'total' => $trans["total"],
                'tax' => $trans["tax"],
                'shipping' => $trans["shipping"],
                'cartId' => $trans["cartId"],
                'items' => Tools::jsonEncode($items),
                'orderId' => $trans["id"],
                'state' => $trans['state'],
                'paymentModuleId' => $pmid,
                'currency' => $trans['currency']
            ),
        );
        $res = CheckYourDataWSHelper::send(self::$dcUrl, $data);
        return $res;
    }

    private function trackerAction($trackRes, &$out)
    {
        if (!empty($trackRes['tpl'])) {
            // smarty assign
            if (!empty($trackRes['tpl']['smarty'])) {
                $this->context->smarty->assign($trackRes['tpl']['smarty']);
            }
            // template load
            if (!empty($trackRes['tpl']['file'])) {
                $out .= $this->display(__FILE__, 'views/templates/hook/' . $trackRes['tpl']['file']);
            }
        }
    }

    private function sendShopParamsToApp($token)
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Get order states
        $oss = OrderState::getOrderStates($default_lang);
        $states = array();
        foreach ($oss as $os) {
            $states [$os['id_order_state']] = $os['name'];
        }

        // Get payment modules
        $token = Configuration::get('checkyourdata_token');
        $modules = array();
        $pms = PaymentModule::getInstalledPaymentModules();
        foreach ($pms as $pm) {
            $p = Module::getInstanceByName($pm['name']);
            if (is_object($p)) {
                $modules [$pm['id_module']] = $p->displayName;
            }
        }

        // get confirmation page url
        $l = new Link();
        $shopUrl = $this->getShopUrl();
        $confirmUrl = str_replace($shopUrl, '', $l->getPageLink('order-confirmation'));

        // get confirmation page title
        $meta = MetaCore::getMetaByPage('order-confirmation', $default_lang);
        $confirmTitle = $meta['title'];

        $data = array(
            'token' => $token,
            'action' => 'setShopParams',
            'data' => array(
                'modules' => $modules,
                'states' => $states,
                'trackers' => Configuration::get('checkyourdata_trackers'),
                'confirm_url' => $confirmUrl,
                'confirm_title' => $confirmTitle,
                'cyd_module_version' => $this->version,
                'shop_type' => 'prestashop',
                'shop_version' => _PS_VERSION_,
            ),
        );
        return CheckYourDataWSHelper::send(self::$dcUrl, $data);
    }

    public function createAccountInApp($email)
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $lang = new Language($default_lang);

        // Get order states
        $oss = OrderState::getOrderStates($default_lang);
        $states = array();
        foreach ($oss as $os) {
            $states [$os['id_order_state']] = $os['name'];
        }

        // Get payment modules
        $modules = array();
        $pms = PaymentModule::getInstalledPaymentModules();
        foreach ($pms as $pm) {
            $p = Module::getInstanceByName($pm['name']);
            $modules [$pm['id_module']] = $p->displayName;
        }

        $data = array(
            'action' => 'createAccount',
            'data' => array(
                'shopUrl' => $this->getShopUrl(),
                'email' => $email,
                'lang' => $lang->iso_code,
                'modules' => $modules,
                'states' => $states,
            ),
        );

        return CheckYourDataWSHelper::send(self::$dcUrl, $data);
    }

    /**
     * Configuration page for module in back office
     * @return string : html content of page
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $isOk = true;
            // validation
            $ua = (string)Tools::getValue('checkyourdata_ganalytics_ua');
            $ua = trim($ua);
            if ($ua != '' && !preg_match('@^UA-[0-9\-]+$@', $ua)) {
                $output .= $this->displayError($this->l('Invalid UA value'));
                $isOk = false;
            }
            $token = (string)Tools::getValue('checkyourdata_token');
            $token = trim($token);
            if ($token != '' && !preg_match('@^[a-f0-9]{32}$@', $token)) {
                $output .= $this->displayError($this->l('Invalid token value'));
                $isOk = false;
            }

            if ($isOk) {
                $trackers = array('ganalytics' => array(), 'lengow' => array(), 'netaffiliation' => array());
                // TRACKERS
                // Google
                $trackers['ganalytics']['active'] = true;
                $trackers['ganalytics']['ua'] = $ua;
                // Lengow
                $trackers['lengow']['active'] = (string)Tools::getValue('checkyourdata_trackers_lengow') == 'on';
                $trackers['lengow']['id'] = (string)Tools::getValue('checkyourdata_lengow_id');
                // NetAffiliation
                if ((string)Tools::getValue('checkyourdata_trackers_netaffiliation') == 'on') {
                    $trackers['netaffiliation']['active'] = true;
                } else {
                    $trackers['netaffiliation']['active'] = false;
                }

                $trackers['netaffiliation']['id'] = (string)Tools::getValue('checkyourdata_netaffiliation_id');

                // save trackers conf
                Configuration::updateValue('checkyourdata_trackers', Tools::jsonEncode($trackers), true);

                // TOKEN
                if (empty($token) || !Validate::isGenericName($token)) {
                    // reset config data
                    Configuration::updateValue('checkyourdata_token', '');
                    Configuration::updateValue('checkyourdata_user_email', '');
                    Configuration::updateValue('checkyourdata_last_errors', '');
                    Configuration::updateValue('checkyourdata_demo_end', '');
                } else {
                    // set token
                    Configuration::updateValue('checkyourdata_token', $token);
                    $output .= $this->displayConfirmation($this->l('Token updated'));

                    // send params to APP if token set
                    $res = $this->sendShopParamsToApp($token);
                    if ($res['state'] == 'ok') {
                        $output .= $this->displayConfirmation(
                            sprintf($this->l('Configuration saved on %s'), 'https://' . self::$dcUrl)
                        );
                    }
                }
            }
        } elseif (Tools::isSubmit('submit' . $this->name . '_signin')) {
            // token
            $token = (string)Tools::getValue('checkyourdata_token');
            if (empty($token)) {
                // account creation
                // user email
                $userEmail = (string)Tools::getValue('checkyourdata_signin_email');
                // tos
                $tos = (string)Tools::getValue('checkyourdata_tos_check');

                // form validation
                $isOk = true;
                if (empty($userEmail) || !Validate::isEmail($userEmail)) {
                    $output .= $this->displayError($this->l('Invalid email value'));
                    $isOk = false;
                }
                if (empty($tos) || $tos != 'on') {
                    $output .= $this->displayError($this->l('You must accept Terms of Sales'));
                    $isOk = false;
                }

                // account creation on checkyourdata app
                if ($isOk) {
                    $ret = $this->createAccountInApp($userEmail);
                    if ($ret['state'] == 'ok') {
                        // set token
                        Configuration::updateValue('checkyourdata_token', $ret['data']['token']);

                        // set checkyourdata user
                        Configuration::updateValue('checkyourdata_user_email', $userEmail);

                        $output .= $this->displayConfirmation(
                            sprintf($this->l('Account created on %s'), 'https://' . self::$dcUrl)
                        );
                    }
                }
            } else {
                // set token
                Configuration::updateValue('checkyourdata_token', $token);

                // save trackers conf
                $trackers = array(
                    'ganalytics' => array('active' => true),
                    'lengow' => array('active' => false),
                    'netaffiliation' => array('active' => false)
                );

                Configuration::updateValue('checkyourdata_trackers', Tools::jsonEncode($trackers), true);

                // send to app
                $res = $this->sendShopParamsToApp($token);
                if ($res['state'] == 'ok') {
                    $output .= $this->displayConfirmation(
                        sprintf($this->l('Configuration saved on %s'), 'https://' . self::$dcUrl)
                    );
                }
            }
        }

        $errs = Configuration::get('checkyourdata_last_errors');
        if (!empty($errs)) {
            $output .= $this->displayError($errs);
        }
// Warning if demo
        $demoEnd = Configuration::get('checkyourdata_demo_end');
        if (!empty($demoEnd)) {
            $dt = new DateTime();
            $dend = DateTime::createFromFormat('Y-m-d H:i:s', $demoEnd);
            if ($dt > $dend) {
                $output .= $this->displayError($this->l('Demo ended. Your data are no more tracked. Please complete your account informations on Check Your Data App'));
            } else {
                $output .= $this->displayConfirmation($this->l('Demo active. Your data are tracked until') . ' ' . $dend->format('d/m/Y H:i:s'));
            }
        }

        $token = Configuration::get('checkyourdata_token');
        if (_PS_VERSION_ < '1.5.0.0') {
            // HelperForm not defined in PS 1.4
            $output .= $this->displayFormPs14();
        } else {
            if (empty($token)) {
                $output .= $this->displayFormNoAccount();
            } else {
                $output .= $this->displayForm();
            }
        }

// header image
        if (empty($token)) {
            $img = 'no_account.png';
            $link = '#fieldset_0';
        } else {
            // random image (from 1 to 5)
            $img = 'com' . rand(1, 5) . '.png';
            $link = '//' . self::$dcUrl . '?refer=PRESTA';
        }

        $this->context->smarty->assign(
            array(
                'img_url' => '//' . self::$dcUrl . 'img/' . $img,
                'link_url' => $link,
            )
        );

        if (_PS_VERSION_ < '1.6.0.0') {
            // no bootstrap
            $output = $this->display(__FILE__, 'views/templates/admin/configuration_ps15.tpl') . $output;
        } else {
            $output = $this->display(__FILE__, 'views/templates/admin/configuration.tpl') . $output;
        }

        return $output;
    }

    public function displayFormNoAccount()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Create account on') . ' http://' . self::$dcUrl,
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Shop Url'),
                    'name' => 'checkyourdata_signin_url',
                    'size' => 20,
                    'required' => true,
                    'disabled' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Account Email'),
                    'name' => 'checkyourdata_signin_email',
                    'size' => 20,
                    'required' => true,
                ),
                array(
                    'type' => 'checkbox',
                    'label' => $this->l('Terms of Sales'),
                    'desc' => $this->l('Viewable on') .
                        ' <a href="http://' . self::$dcUrl . 'cgv.php" target="_blank">http://'
                        . self::$dcUrl . 'cgv.php</a>',
                    'name' => 'checkyourdata_tos',
                    'required' => true,
                    'values' => array(
                        'query' => array(
                            array('id' => 'check', 'label' => $this->l('Check to accept terms of sales.'))
                        ),
                        'id' => 'id',
                        'name' => 'label'
                    ),
                ),

            ),
            'submit' => array(
                'title' => $this->l('Create account'),
                'class' => 'button'
            )
        );
        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Account already created ?'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Access key to CheckYourData'),
                    'name' => 'checkyourdata_token',
                    'size' => 20,
                    'required' => true,
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name . '_signin';

        // Load current value
        $helper->fields_value['checkyourdata_token'] = '';
        $helper->fields_value['checkyourdata_signin_url'] = $this->getShopUrl();
        $userEmail = (string)Tools::getValue('checkyourdata_signin_email');
        if ($userEmail == '') {
            $helper->fields_value['checkyourdata_signin_email'] = $this->context->employee->email;
        } else {
            $helper->fields_value['checkyourdata_signin_email'] = $userEmail;
        }
        $tos = (string)Tools::getValue('checkyourdata_tos_check');
        $helper->fields_value['checkyourdata_tos_check'] = ($tos == 'on');

        return $helper->generateForm($fields_form);
    }

    /**
     * Configuration form
     * => compatibility PS1.5+
     * @return string : form html
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('User Account on Check Your Data'),
                    'name' => 'checkyourdata_user_email',
                    'size' => 20,
                    'required' => true,
                    'disabled' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Access key to CheckYourData'),
                    'name' => 'checkyourdata_token',
                    'size' => 20,
                    'required' => true,
                    'hint' => $this->l('Available on http://app.checkyourdata.net'),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );
        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Setting Google Analytics'),
            ),
            'input' => array(
                // GANALYTICS
                array(
                    'type' => 'hidden',//checkbox
                    'label' => $this->l('Tracker activation'),
                    'name' => 'checkyourdata_trackers_ganalytics',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Google Analytics ID'),
                    'name' => 'checkyourdata_ganalytics_ua',
                    'size' => 20,
                    'required' => false,
                    'hint' => $this->l('You will find this information on Admin > Google Analytics Account Properties'),
                ),

                // LENGOW
                /*array(
                    'type'    => 'checkbox',
                    'label'   => $this->l('Tracker activation'),
                    //'desc'    => $this->l('Check to use tracker.'),
                    'name'    => 'checkyourdata_trackers',
                    'values'  => array(
                        'query' => array(array('id'=>'lengow','label'=>$this->l('Check to use tracker.'))),
                        'id'    => 'id',
                        'name'  => 'label'
                    ),
                    'tab' => 'lengow',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('ID Lengow'),
                    'name' => 'checkyourdata_lengow_id',
                    'size' => 20,
                    'required' => false,
                    'tab' => 'lengow',
                ),

                // NETAFF
                array(
                    'type'    => 'checkbox',
                    'label'   => $this->l('Tracker activation'),
                    //'desc'    => $this->l('Check to use tracker.'),
                    'name'    => 'checkyourdata_trackers',
                    'values'  => array(
                        'query' => array(array('id'=>'netaffiliation','label'=>$this->l('Check to use tracker.'))),
                        'id'    => 'id',
                        'name'  => 'label'
                    ),
                    'tab' => 'netaffiliation',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('ID NetAffiliation'),
                    'name' => 'checkyourdata_netaffiliation_id',
                    'size' => 20,
                    'required' => false,
                    'tab' => 'netaffiliation',
                ),*/
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $token = Configuration::get('checkyourdata_token');
        $helper->fields_value['checkyourdata_token'] = $token;

        // TRACKERS
        $trackers = Configuration::get('checkyourdata_trackers');
        if (!empty($trackers)) {
            $trackers = Tools::jsonDecode($trackers, true);
        } else {
            $trackers = array(
                'ganalytics' => array('active' => false),
                'lengow' => array('active' => false),
                'netaffiliation' => array('active' => false),
            );
        }
        // GOOGLE
        $helper->fields_value['checkyourdata_trackers_ganalytics'] = true;


        if (!empty($trackers['ganalytics']['ua'])) {
            $checkyourdata_ganalytics_ua = $trackers['ganalytics']['ua'];
        } else {
            $checkyourdata_ganalytics_ua = '';
        }
        $helper->fields_value['checkyourdata_ganalytics_ua'] = $checkyourdata_ganalytics_ua;
        // LENGOW
        /*$helper->fields_value['checkyourdata_trackers_lengow'] = $trackers['lengow']['active'];
        $helper->fields_value['checkyourdata_lengow_id'] = !empty($trackers['lengow']['id'])?$trackers['lengow']['id']:'';
        // NetAffiliation
        $helper->fields_value['checkyourdata_trackers_netaffiliation'] = $trackers['netaffiliation']['active'];
        $helper->fields_value['checkyourdata_netaffiliation_id'] = !empty($trackers['netaffiliation']['id'])?$trackers['netaffiliation']['id']:'';
        */
        $uEmail = Configuration::get('checkyourdata_user_email');
        if ($uEmail != '') {
            $helper->fields_value['checkyourdata_user_email'] = $uEmail;
        } else {
            // no user email, hiding field
            array_shift($fields_form[0]['form']['input']);
        }

        return $helper->generateForm($fields_form);
    }

    /**
     * Configuration form
     * => PS1.4 only (no HelperForm)
     * @return string : form html
     */
    public function displayFormPs14()
    {
        $this->context->smarty->assign(
            array(
                'action_url' => $_SERVER['REQUEST_URI'],
                'token' => Configuration::get('checkyourdata_token'),
                'trackers' => Tools::jsonDecode(Configuration::get('checkyourdata_trackers'), true),
                'submit_name' => 'submit' . $this->name,
            )
        );
        return $this->display(__FILE__, 'views/templates/admin/configuration_form_ps14.tpl');
    }

    /**
     * Aliases for PS1.4 hooks
     */
    public function getShopUrl()
    {
        if (_PS_VERSION_ < '1.5.0.0') {
            return $this->context->link->getPageLink('', true);
        }
        return $this->context->shop->getBaseURL();
    }

    public function hookCancelProduct($params)
    {
        // TODO : PS1.4 refund
    }

    /**
     * Fonctions for PS14
     */
    private function getShippingTotal($order)
    {
        if (_PS_VERSION_ < '1.5.0.0') {
            return $order->total_shipping;
        } else {
            $shipping = $order->getShippingTaxesBreakdown();
            if (count($shipping) > 0 && isset($shipping[0]['total_amount'])) {
                return $shipping[0]['total_amount'];
            }
        }
        return 0;
    }

    private function getProductDefaultCategory($prod)
    {
        if (_PS_VERSION_ < '1.5.0.0') {
            $p = new Product($prod['product_id']);
            return $p->id_category_default;
        }
        return $prod["id_category_default"];
    }

    private function getProductReference($prod)
    {
        if (_PS_VERSION_ < '1.5.0.0') {
            $p = new Product($prod['product_id']);
            return $p->reference;
        }
        return $prod['reference'];
    }

    /**
     * @param $cartId
     * @return array
     */
    protected function formatDataToSend($cartId)
    {
        $token = Configuration::get('checkyourdata_token');

        // preparation de l'appel vers APP pour initOrder
        $cart = new Cart($cartId);

        // data to send
        // amounts
        $trans = array();
        $totalWithoutTaxes = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
        $total = $cart->getOrderTotal();
        $trans["tax"] = $total - $totalWithoutTaxes;
        $trans["shipping"] = $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);
        $trans["total"] = $total;

        // items
        $products = $cart->getProducts();
        $items = array();

        foreach ($products as $p) {
            $items [$p['id_product'] . '_' . $p['id_product_attribute']] = array(
                'name' => $p['name'],
                'price' => $p['price'],
                'code' => $p['reference'],
                'category' => $p['category'],
                'qty' => $p['cart_quantity'],
            );
        }

        $data = array(
            'token' => $token,
            'action' => 'initOrder',
            'data' => array(
                'total' => $trans["total"],
                'tax' => $trans["tax"],
                'shipping' => $trans["shipping"],
                'cartId' => $cart->id,
                'items' => Tools::jsonEncode($items),
            ),
        );
        return $data;
    }
}
