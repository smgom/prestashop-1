<?php

/**
 * 2007-2015 PrestaShop
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
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2015 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

// Autoload files using Composer autoload
require_once 'vendor/autoload.php';

// Import the required namespaces
use ChannelEngineApiClient\Client\ApiClient as CEApiClient;
use ChannelEngineApiClient\Helpers\Collection as CECollection;
use ChannelEngineApiClient\Helpers\JsonMapper as CEJsonMapper;
use ChannelEngineApiClient\Models\Address as CEAddress;
use ChannelEngineApiClient\Models\Cancellation as CECancellation;
use ChannelEngineApiClient\Models\CancellationLine as CECancellationLine;
use ChannelEngineApiClient\Models\Message as CEMessage;
use ChannelEngineApiClient\Models\Order as CE_Order;
use ChannelEngineApiClient\Models\OrderLine as CE_OrderLine;
use ChannelEngineApiClient\Models\OrderExtraDataItem as CEOrderExtraDataItem;
use ChannelEngineApiClient\Models\Product as CEProduct;
use ChannelEngineApiClient\Models\ReturnObject as CEReturnObject;
use ChannelEngineApiClient\Models\ReturnLine as CEReturnLine;
use ChannelEngineApiClient\Models\Shipment as CEShipment;
use ChannelEngineApiClient\Models\ShipmentLine as CEShipmentLine;
use ChannelEngineApiClient\Enums\CancellationLineStatus as CECancellationLineStatus;
use ChannelEngineApiClient\Enums\CancellationStatus as CECancellationStatus;
use ChannelEngineApiClient\Enums\Gender as CEGender;
use ChannelEngineApiClient\Enums\MancoReason as CEMancoReason;
use ChannelEngineApiClient\Enums\OrderStatus as CEOrderStatus;
use ChannelEngineApiClient\Enums\ReturnReason as CEReturnReason;
use ChannelEngineApiClient\Enums\ReturnStatus as CEReturnStatus;
use ChannelEngineApiClient\Enums\ReturnAcceptStatus as CEReturnAcceptStatus;
use ChannelEngineApiClient\Enums\ShipmentLineStatus as CEShipmentLineStatus;
use ChannelEngineApiClient\Enums\ShipmentStatus as CEShipmentStatus;

// Import the required namespaces
class Channelengine extends Module {

    protected $config_form = false;

    public function __construct() {
        $this->name = 'channelengine';
        $this->tab = 'market_place';
        $this->version = '1.1.0';
        $this->author = 'ChannelEngine (M. Gautam, C. de Ridder)';
        $this->need_instance = 1;


        $this->client = new CEApiClient(Configuration::get('CHANNELENGINE_ACCOUNT_API_KEY'), Configuration::get('CHANNELENGINE_ACCOUNT_API_SECRET'), Configuration::get('CHANNELENGINE_ACCOUNT_NAME', null));
        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;


        parent::__construct();

        $this->displayName = $this->l('ChannelEngine');
        $this->description = $this->l('ChannelEngine extension for Prestashop');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the module?');

        $this->ps_versions_compliancy = array('min' => '1.4', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install() {
        Configuration::updateValue('CHANNELENGINE_LIVE_MODE', false);

        $sql  = "ALTER TABLE `" . _DB_PREFIX_ . "order_detail` ";
        $sql .= "ADD `id_channelengine_product` int(10) NOT NULL, ";
        $sql .= "ADD `id_channelengine_shipment` int(10) NOT NULL, ";
        $sql .= "ADD `id_channelengine_return` int(10) NOT NULL";
        Db::getInstance()->Execute($sql);

        $sql  = "ALTER TABLE `" . _DB_PREFIX_ . "orders` ";
        $sql .= "ADD `id_channelengine_order` int(10) NOT NULL, ";
        $sql .= "ADD `id_channelengine_shipment` int(10) NOT NULL, ";
        $sql .= "ADD `id_channelengine_return` int(10) NOT NULL";
        Db::getInstance()->Execute($sql);

        return parent::install() &&
                $this->registerHook('header') &&
                $this->registerHook('backOfficeHeader') &&
                $this->registerHook('actionOrderStatusPostUpdate') &&
                $this->registerHook('actionOrderStatusUpdate') &&
                $this->registerHook('actionProductAdd') &&
                $this->registerHook('actionProductUpdate') &&
                $this->registerHook('actionObjectProductUpdateAfter') &&
                $this->registerHook('actionProductAttributeUpdate') &&
                $this->registerHook('actionOrderStatusPostUpdate') &&
                $this->registerHook('orderConfirmation') &&
                $this->registerHook('displayHeader') &&
                $this->registerHook('newOrder') &&
                $this->registerHook('actionOrderStatusPostUpdate') &&
                $this->registerHook('displayTop');
    }

    public function uninstall() {
        Configuration::deleteByName('CHANNELENGINE_LIVE_MODE');
        Configuration::deleteByName('CHANNELENGINE_ACCOUNT_API_KEY');
        Configuration::deleteByName('CHANNELENGINE_ACCOUNT_API_SECRET');
        Configuration::deleteByName('CHANNELENGINE_ACCOUNT_NAME');
        Configuration::deleteByName('CHANNELENGINE_EXPECTED_SHIPPING_PERIOD');

        $sql  = "ALTER TABLE `" . _DB_PREFIX_ . "order_detail` ";
        $sql .= "DROP `id_channelengine_product`, ";
        $sql .= "DROP `id_channelengine_shipment`, ";
        $sql .= "DROP `id_channelengine_return`";

        Db::getInstance()->Execute($sql);

        $sql  = "ALTER TABLE `" . _DB_PREFIX_ . "orders` ";
        $sql .= "DROP `id_channelengine_order`, ";
        $sql .= "DROP `id_channelengine_shipment`, ";
        $sql .= "DROP `id_channelengine_return`";

        Db::getInstance()->Execute($sql);

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent() {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitChannelengineModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm() {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitChannelengineModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm() {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('ENABLE MODULE'),
                        'name' => 'CHANNELENGINE_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
//                        'prefix' => '<i class="icon icon-envelope"></i>',
//                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'CHANNELENGINE_ACCOUNT_API_KEY',
                        'label' => $this->l('Api Key'),
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
//                        'prefix' => '<i class="icon icon-envelope"></i>',
//                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'CHANNELENGINE_ACCOUNT_API_SECRET',
                        'label' => $this->l('Api Secret'),
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
//                        'prefix' => '<i class="icon icon-envelope"></i>',
//                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'CHANNELENGINE_ACCOUNT_NAME',
                        'label' => $this->l('Account Name'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
//                        'prefix' => '<i class="icon icon-envelope"></i>',
//                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'CHANNELENGINE_EXPECTED_SHIPPING_PERIOD',
                        'label' => $this->l('Expected Shipping Period for back orders'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues() {
        return array(
            'CHANNELENGINE_LIVE_MODE' => Configuration::get('CHANNELENGINE_LIVE_MODE', null),
            'CHANNELENGINE_ACCOUNT_API_KEY' => Configuration::get('CHANNELENGINE_ACCOUNT_API_KEY'),
            'CHANNELENGINE_ACCOUNT_API_SECRET' => Configuration::get('CHANNELENGINE_ACCOUNT_API_SECRET', null),
            'CHANNELENGINE_ACCOUNT_NAME' => Configuration::get('CHANNELENGINE_ACCOUNT_NAME', null),
            'CHANNELENGINE_EXPECTED_SHIPPING_PERIOD' => Configuration::get('CHANNELENGINE_EXPECTED_SHIPPING_PERIOD', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess() {
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader() {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader() {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookDisplayHeader() {
        if (!Configuration::get('CHANNELENGINE_LIVE_MODE')) {
            return;
        } else {
            $script = "<script>
(function (T, r, i, t, a, c) {
T.ce = T.ce || function () { T.ce.eq = T.ce.eq || []; T.ce.eq.push(arguments); }, T.ce.url = t;
a = r.createElement(i); a.async = 1; a.src = t + '/content/scripts/ce.js';
c = r.getElementsByTagName(i)[0]; c.parentNode.insertBefore(a, c);
})(window, document, 'script', '//www.channelengine.net');
ce('set:account', '" . Configuration::get('CHANNELENGINE_ACCOUNT_NAME', null) . "');
ce('track:click');
</script>";
            return $script;
        }
    }

    public function hookActionProductAdd($params) {

        $prestaProducts = array($params['product']);
        $this->putPrestaProductsToChannelEngine($prestaProducts, $params['id_product']);
    }

    public function hookactionOrderStatusPostUpdate($params) {
        $prestaProducts = $params['cart']->getProducts();
        foreach ($prestaProducts as $prestaProduct) {
            $this->putPrestaProductsToChannelEngine($prestaProducts, $prestaProduct['id_product']);
        }
        $order = new Order($params['id_order']);
        $products = $order->getProducts();
        foreach ($products as $product) {
            if ($product['product_quantity_return'] != 0) {
                $this->postReturn($params);
            }
        }

        if ($params['newOrderStatus']->id == 4) {
            $this->postShipment();
        }
    }

    private function postShipment() {
        try {
            // Marking orders as shipped, partially shipped or not shipped is done
            // by posting shipments.
            // Note: Use data from an existing order here
            $id_order = Tools::getValue('id_order');
            $sql = 'SELECT id_channelengine_order FROM ' . _DB_PREFIX_ . 'orders WHERE id_order =' . $id_order;
            $channelengine_order_id = Db::getInstance()->getValue($sql);
            if ($channelengine_order_id != 0) {
                $orderToShip = new Order($id_order);
                $cart_id = $orderToShip->id_cart;
                $carrier = new Carrier($orderToShip->getIdOrderCarrier());
                $products = $orderToShip->getProducts();
                $shipment = new CEShipment();
                $shipment->setOrderId($channelengine_order_id);
                $shipment->setMerchantShipmentNo($orderToShip->shipping_number);
                $shipment->setTrackTraceNo($orderToShip->shipping_number);
                $shipment->setMethod($carrier->name);
                $shipments = new CECollection(new CEShipmentLine);

                foreach ($products as $key => $product) {
                    $sql = "SELECT id_channelengine_product FROM " . _DB_PREFIX_ . "order_detail  WHERE product_id ='" . $product['product_id'] . "'AND product_attribute_id ='" . $product['product_attribute_id'] . "'AND id_order ='" . $id_order . "'";
                    $product_id = Db::getInstance()->getValue($sql);
                    $shipmentLine = new CEShipmentLine();
                    $shippedQty = (int) $product['product_quantity'];
                    if ($shippedQty == 0)
                        continue;
                    $shipmentLine->setOrderLineId($product_id);
                    $shipmentLine->setQuantity($shippedQty);
                    $shipmentLine->setStatus(1);
//                $shipmentLine->setShipmentId($orderToShip->getIdOrderCarrier());
                    $shipments->append($shipmentLine);
                }


                $query = 'SELECT id_channelengine_shipment FROM ' . _DB_PREFIX_ . 'orders WHERE id_order =' . $id_order;
                $shipment_id = Db::getInstance()->getValue($query);
                if ($shipment_id == 0) {
                    $shipment->setLines($shipments);
                    $result = $this->client->postShipment($shipment);
                    Db::getInstance()->update('orders', array('id_channelengine_shipment' => $result->getId()), 'id_order = ' . $id_order);
                    $result_lines = $result->getLines();
                    foreach ($result_lines as $result_line) {
                        $query = "UPDATE `" . _DB_PREFIX_ . "order_detail` SET id_channelengine_shipment='" . $result_line->getId() . "'"
                                . "WHERE id_channelengine_product = ' " . $result_line->getOrderLineId() . "' ";
                        Db::getInstance()->Execute($query);
                    }
                }
            }
        } catch (Exception $e) {
            // Print the exception 
            echo ($e->getMessage());
        }
    }

    private function postReturn($params) {
        try {
            $id_order = Tools::getValue('id_order');
            $orderToReturn = new Order($id_order);
            $products = $orderToReturn->getProducts();
            // Some channels expect you to handle customer contact and the
            // return procedure of products yourself. In this case the return needs
            // to be registered to ChannelEngine.
            // The shipment that needs to be registered as being returned by the customer.
            // Note: Use an existing shipment with lines here.

            $sql = 'SELECT id_channelengine_shipment FROM ' . _DB_PREFIX_ . 'orders WHERE id_order =' . $id_order;
            $channelengine_Shippment_id = Db::getInstance()->getValue($sql);
            if ($channelengine_Shippment_id != 0) {
                $shipment = new CEShipment();
                $shipmentLines = $shipment->getLines();
                // Note: Act as if we have a real shipment with 1 line
                // Create the return

                $return = new CEReturnObject();
                $return->setOrderId($id_order);
                $return->setShipmentId($channelengine_Shippment_id);
                $return->setMerchantReturnNo($orderToReturn->shipping_number);
                $return->setCreatedAt($orderToReturn->date_add);
                $return->setUpdatedAt($orderToReturn->date_upd);
                $return->setRefundInclVat($orderToReturn->total_paid_tax_incl);
                $return->setRefundExclVat($orderToReturn->total_paid_tax_excl);
                $return->setMerchantComment('Product was dead on arrival');
                $return->setStatus(0);
                $return->setReason(3);
                $returns = new CECollection(new CEReturnLine);
                // Register the shipment line and amount of products being returned
                foreach ($products as $product) {
                    $sql = "SELECT id_channelengine_product FROM " . _DB_PREFIX_ . "order_detail  WHERE product_id ='" . $product['product_id'] . "'AND product_attribute_id ='" . $product['product_attribute_id'] . "'AND id_order ='" . $id_order . "'";
                    $product_id = Db::getInstance()->getValue($sql);
                    $sql = "SELECT id_channelengine_shipment FROM " . _DB_PREFIX_ . "order_detail  WHERE product_id ='" . $product['product_id'] . "'AND product_attribute_id ='" . $product['product_attribute_id'] . "'AND id_order ='" . $id_order . "'";
                    $shipment_line_id = Db::getInstance()->getValue($sql);
                    $returnLine = new CEReturnLine();
                    $returnLine->setQuantity($product['product_quantity']);
                    $returnLine->setRejectedQuantity($product['product_quantity_return']);
                    $returnLine->setOrderLineId($product_id);
                    $returnLine->setShipmentLineId($shipment_line_id);
                    $returnLine->setReturnId($orderToReturn->getIdOrderCarrier());
                    $returnLine->setRefundInclVat($product['total_price_tax_incl']);
                    $returnLine->setRefundExclVat($product['total_price_tax_excl']);
                    // Add the return line and send it to ChannelEngine
                    $returns->append($returnLine);
                }
                $query = 'SELECT id_channelengine_return FROM ' . _DB_PREFIX_ . 'orders WHERE id_order =' . $id_order;
                $return_id = Db::getInstance()->getValue($query);
                if ($return_id == 0) {
                    $return->setLines($returns);
                    $result = $this->client->postReturn($return);
                    Db::getInstance()->update('orders', array('id_channelengine_return' => $result->getId()), 'id_order = ' . $id_order);
                    $result_lines = $result->getLines();
                    foreach ($result_lines as $result_line) {
                        $query = "UPDATE `" . _DB_PREFIX_ . "order_detail` SET id_channelengine_return='" . $result_line->getId() . "'"
                                . "WHERE id_channelengine_shipment = ' " . $result_line->getShipmentLineId() . "' ";
                        Db::getInstance()->Execute($query);
                    }
                }
            }
        } catch (Exception $e) {
            // Print the exception
            pr($e->getMessage());
        }
    }

    public function cronReturnSync() {
        //  Check if client is initialized
        if (is_null($this->client))
            return false;
        //Retrieve returns
        $returns = $this->client->getReturns(array(0));
        //Check declared returns
        if (is_null($returns) || $returns->count() == 0)
            return false;
        $customizationQtyInput = Tools::getValue('customization_qty_input');
        $customizationIds = Tools::getValue('customization_ids');
        foreach ($returns as $return) {
            $return_lines = $return->getLines();
            $channelengine_order_id = $return->getOrderId();

            $sql = "SELECT id_order FROM " . _DB_PREFIX_ . "orders  WHERE id_channelengine_order ='" . $channelengine_order_id . "'";
            $prestashop_order_id = Db::getInstance()->getValue($sql);

            $sql = "SELECT id_order_detail FROM" . _DB_PREFIX_ . "order_detail  WHERE id_order ='" . $prestashop_order_id . "'";
            $ids_order_details = Db::getInstance()->executeS($sql);

            $ids_order_detail_array = array();
            foreach ($ids_order_details as $key => $ids_order_detail) {
                $ids_order_detail_array[$ids_order_detail['id_order_detail']] = $ids_order_detail['id_order_detail'];
            }
            $quantity_array = array();
            $keys = array_keys($ids_order_detail_array);
            foreach ($return_lines as $i => $return_line) {
                $quantity_array[$keys[$i]] = $return_line->getQuantity();
                $order = new Order((int) $prestashop_order_id);
                $orderReturn = new OrderReturn();
                $orderReturn->id_customer = (int) $order->id_customer;
                $orderReturn->id_order = $prestashop_order_id;
                $orderReturn->question = htmlspecialchars(Tools::getValue('returnText'));
                if (empty($orderReturn->question)) {
                    http_build_query(array(
                        'ids_order_detail' => $ids_order_detail_array,
                        'order_qte_input' => $quantity_array,
                        'id_order' => $prestashop_order_id,
                    ));
                }
            }
            $orderReturn->state = 1;
            $orderReturn->add();
            $orderReturn->addReturnDetail($ids_order_detail_array, $quantity_array, $customizationIds, $customizationQtyInput);
            Hook::exec('actionOrderReturn', array('orderReturn' => $orderReturn));
        }
    }

    /**
     * To track transactions
     */
    public function hookOrderConfirmation($params) {
        if (!Configuration::get('CHANNELENGINE_LIVE_MODE')) {
            return;
        }
        $id_order = Tools::getValue('id_order');
        $order = new Order((int) $id_order);
        $total = $order->total_paid;
        $shippingCost = $order->total_shipping;
        $vat = $order->total_paid_tax_incl - $order->total_paid_tax_excl;
        $invoiceAddress = new Address((int) $order->id_address_invoice);
        $city = $invoiceAddress->city;
        $country = $invoiceAddress->country;
        $products = $order->getProducts();
        $country_obj = new Country((int) $invoiceAddress->id_country);
        $country_code = $country_obj->iso_code;
        //push products to channel
        $this->putPrestaProductsToChannelEngine($products);
        $order_items_array_full = array();
        foreach ($products as $key => $items) {
            $prestaProducts = array($items['id_product']);
            $order_items_array['name'] = $items['product_name'];
            $order_items_array['price'] = $items['product_price'];
            $order_items_array['quantity'] = $items['product_quantity'];
            $order_items_array['category'] = $this->getProductCategories($items['id_product']);
            $order_items_array['merchantProductNo'] = $items['product_reference'];
            array_push($order_items_array_full, $order_items_array);
        }
        $script = "<script>ce('track:order', {
                merchantOrderNo: '" . $id_order . "',
                total: " . $total . ",
                vat: " . $vat . ",
                shippingCost: " . $shippingCost . ",
                city: '" . $city . "',
                country: '" . $country_code . "',
                orderLines: " . json_encode($order_items_array_full) . "
                }); </script>";
        return $script;
    }

    /**
     * getProductCategories return an array of categories which this product belongs to
     *
     * @return array of categories
     */
    public static function getProductCategories($id_product = '') {
        $ret = array();
        if ($row = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
            SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category_product`
            WHERE `id_product` = ' . (int) $id_product)
        )
            foreach ($row as $val) {
                $cat = new Category($val['id_category'], (int) 1);
                $ret[] = $cat->name;
            }
        $ret_text = implode(" > ", $ret);
        return $ret_text;
    }

    public function initalImport() {
        $prestashop_products = new Product();
        $prestaProducts = $prestashop_products->getProducts(1, 0, 0, 'id_product', 'DESC');
        $this->putPrestaProductsToChannelEngine($prestaProducts);
    }

    /**
     * - Syncing product data with ChannelEngine 
     * (initial import and updates when product info/stock/price etc. changes)
     * @global Product $productObj
     * @param type $prestaProducts
     * @param type $channelEngineObject
     */
    public function cronProductSync($lastUpdatedTimestamp) {
        $startDate = date('Y-m-d H:i:s', time());
        $endDate = date('Y-m-d H:i:s', $lastUpdatedTimestamp);
        $sql = "SELECT id_product FROM " . _DB_PREFIX_ . "product WHERE (date_upd between '" . $endDate . "'AND '" . $startDate . "')";
        $updated_product_ids = Db::getInstance()->executeS($sql);
        if (!empty($updated_product_ids)) {
            foreach ($updated_product_ids as $updated_product_id) {
                $prestashop_products = new Product((int) $updated_product_id['id_product']);
                $prestaProducts = array($prestashop_products);
                $this->putPrestaProductsToChannelEngine($prestaProducts, $updated_product_id['id_product']);
            }
        }
    }

    public function putPrestaProductsToChannelEngine($prestaProducts, $id_product = 0) {
        foreach ($prestaProducts as $product_presta) {
            $product_presta = (array) $product_presta;
            if ($id_product != 0) {
                $product_presta['id_product'] = $id_product;
            }
            $product_presta = (array) $product_presta;
            $prestaObject = new Product($product_presta['id_product'], false, Context::getContext()->language->id);
            $combinations[$prestaObject->id] = $prestaObject->getAttributeCombinations(1);
            foreach ($combinations[$prestaObject->id] as $combinations) {
                $product = new CEProduct();
                if ($id_product == 0 && isset($product_presta['name'])) {
                    $product->setName($product_presta['name']);
                    $product->setDescription(strip_tags($prestaObject->description));
                } elseif (isset($product_presta['name'][1])) {
                    $product->setName($product_presta['name'][1]);
                    $product->setDescription(strip_tags($prestaObject->description));
                } else {
                    $product->setName($prestaObject->name);
                    $product->setDescription(strip_tags($prestaObject->description));
                }

                $manufacturer = new Manufacturer($product_presta['id_manufacturer'], 1);
                $taxObject = new Tax($prestaObject->id_tax_rules_group, 1);
                $product->setBrand($manufacturer->name);
                if (!empty($product_presta['ean13']) && $this->validate_EAN13Barcode($product_presta['ean13'])) {
                    $product->setEan($product_presta['ean13']);
                } else {
                    $product->setEan("00000000");
                }
                $product->setMerchantProductNo($product_presta['id_product'] . "-" . $combinations['id_product_attribute']);
                $product->setPrice($product_presta['price']);
                $product->setListPrice($product_presta['price']);
                $product->setVatRate($taxObject->rate);
                $product->setStock(Product::getQuantity($product_presta['id_product']));
                $all_product_subs = Product::getProductCategoriesFull($product_presta['id_product'], 1);
                if (isset($all_product_subs) && count($all_product_subs) > 0) {
                    $max = 0;
                    foreach ($all_product_subs as $subcat) {
                        $category_trial = Tools::getPath($subcat['id_category'], '', true);
                        $substr_count = substr_count(strip_tags($category_trial), ">");
                        if ($max < $substr_count) {
                            $category_with_max_length = $category_trial;
                            $max = $substr_count;
                        }
                        $all_product_subs_path[] = Tools::getPath($subcat['id_category'], '', true);
                    }
                }
                $product->setCategoryTrail(str_replace(">", " > ", strip_tags($category_with_max_length)));
                $product->setShippingCost($product_presta['additional_shipping_cost']);

                $arrrs = $prestaObject->getFrontFeatures(1);
                $attributes = array();
                foreach ($arrrs as $single) {
                    $extra_data = array();
                    $extra_data['Key'] = $single['name'];
                    $extra_data['Value'] = $single['value'];
                    $extra_data['IsPublic'] = true;
                    $attributes[] = $extra_data;
                }
                $manufacturer = new Manufacturer($product_presta['id_manufacturer'], 1);
                $product->setExtraData($attributes);
                $link = new Link();
                $url = $link->getProductLink($product_presta['id_product']);
                $product->setUrl($url);
                $image = Image::getCover($product_presta['id_product']);

                $imagePath = $link->getImageLink($prestaObject->link_rewrite, $image['id_image'], '');
                if (Configuration::get("PS_SSL_ENABLED")) {
                    $imagePath = "https://" . $imagePath;
                } else {
                    $imagePath = "http://" . $imagePath;
                }
                $product->setImageUrl($imagePath);
                $productCollection[] = $product;
            }
        }
        try {
            $this->client->postProducts($productCollection);
        } catch (Exception $ex) {
            pr($ex);
        }
    }

    function validate_EAN13Barcode($barcode) {
        // check to see if barcode is 13 digits long
        if (!preg_match("/^[0-9]{13}$/", $barcode)) {
            return false;
        }

        $digits = $barcode;

        // 1. Add the values of the digits in the 
        // even-numbered positions: 2, 4, 6, etc.
        $even_sum = $digits[1] + $digits[3] + $digits[5] +
                $digits[7] + $digits[9] + $digits[11];

        // 2. Multiply this result by 3.
        $even_sum_three = $even_sum * 3;

        // 3. Add the values of the digits in the 
        // odd-numbered positions: 1, 3, 5, etc.
        $odd_sum = $digits[0] + $digits[2] + $digits[4] +
                $digits[6] + $digits[8] + $digits[10];

        // 4. Sum the results of steps 2 and 3.
        $total_sum = $even_sum_three + $odd_sum;

        // 5. The check character is the smallest number which,
        // when added to the result in step 4, produces a multiple of 10.
        $next_ten = (ceil($total_sum / 10)) * 10;
        $check_digit = $next_ten - $total_sum;

        // if the check digit and the last digit of the 
        // barcode are OK return true;
        if ($check_digit == $digits[12]) {
            return true;
        }

        return false;
    }

    function cronOrdersSync() {
        $orders = $this->client->getOrders(array(7));
        foreach ($orders as $order) {
            $channelOrderId = $order->getId();
            $billingAddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();
            if (empty($billingAddress)) {
                continue;
            }

            $id_customer = $this->createPrestashopCustomer($billingAddress, $order->getEmail());
            $lines = $order->getLines();
            $AddressObject = new Address();
            $AddressObject->id_customer = $id_customer;
            $AddressObject->firstname = $billingAddress->getfirstName();
            $AddressObject->lastname = $billingAddress->getlastName();
            $AddressObject->address1 = " " . $billingAddress->getHouseNr();
            $AddressObject->address1.= " " . $billingAddress->getHouseNrAddition();
            $AddressObject->address1.= " " . $billingAddress->getStreetName();
            $AddressObject->address1.= " " . $billingAddress->getZipCode();
            $AddressObject->address1.= " " . $billingAddress->getCity();
            $AddressObject->city = $billingAddress->getCity();
            $AddressObject->id_customer = $id_customer;
            $AddressObject->id_country = Country::getByIso($billingAddress->getCountryIso());
            $AddressObject->alias = ($billingAddress->getcompanyName() != "") ? "Company" : "Home";
            $AddressObject->add();
            $CarrierObject = new Carrier();
            $CarrierObject->delay[1] = "2-4";
            $CarrierObject->active = 1;
            $CarrierObject->name = "ChannelEngine Order";
            $CarrierObject->add();
            $id_carrier = $CarrierObject->id;
            $currency_object = new Currency();
            $default_currency_object = $currency_object->getDefaultCurrency();
            $id_currency = $default_currency_object->id;
            $id_address = $AddressObject->id;

            // Create Cart Object
            $cart = new Cart();
            $cart->id_customer = (int) $id_customer;
            $cart->id_address_delivery = $id_address;
            $cart->id_address_invoice = $id_address;
            $cart->id_lang = 1;
            $cart->id_currency = (int) $id_address;
            $cart->id_carrier = $id_carrier;
            $cart->recyclable = 0;
            $cart->id_shop_group = 1;
            $cart->gift = 0;
            $cart->add();
            if (!empty($lines)) {
                foreach ($lines as $item) {
                    $quantity = $item->getQuantity();
                    if (strpos($item->getmerchantProductNo(), '-') !== false) {
                        $getMerchantProductNo = explode("-", $item->getMerchantProductNo());
                        $cart->updateQty($quantity, $getMerchantProductNo[0], $getMerchantProductNo[1]);
                    } else {
                        $cart->updateQty($quantity, $item->getmerchantProductNo());
                    }
                }
            }

            $cart->update();
            $order_object = new Order();
            $order_object->id_address_delivery = $id_address;
            $order_object->id_address_invoice = $id_address;
            $order_object->id_cart = $cart->id;
            $order_object->id_currency = $id_currency;
            $order_object->id_customer = $id_customer;
            $order_object->id_carrier = $id_carrier;
            $order_object->payment = "Channel Engine Order";
            $order_object->module = "1";
            $order_object->valid = 1;
            $order_object->total_paid_tax_excl = $order->getTotalInclVat();
            $order_object->total_discounts_tax_incl = 0;
            $order_object->total_paid = $order->getTotalInclVat();
            $order_object->total_paid_real = $order->getTotalInclVat();
            $order_object->total_products = $order->getSubTotalInclVat() - $order->getSubTotalVat();
            $order_object->total_products_wt = $order->getSubTotalInclVat();
            $order_object->total_paid_tax_incl = $order->getSubTotalInclVat();
            $order_object->conversion_rate = 1;
            $order_object->id_shop = 1;
            $order_object->id_lang = 1;
            $order_object->id_shop_group = 1;
            $order_object->secure_key = md5(uniqid(rand(), true));
            $order_id = $order_object->add();

// Insert new Order detail list using cart for the current order

            $order_detail = new OrderDetail();
            $orderClass = new Order();
            $order_detail->createList($order_object, $cart, 1, $cart->getProducts(), 1);
            $order_detail_list[] = $order_detail;

// Adding an entry in order_carrier table
            if (!is_null($CarrierObject)) {
                $order_carrier = new OrderCarrier();
                $order_carrier->id_order = (int) $order_object->id;

                $order_carrier->id_carrier = (int) $id_carrier;
                $order_carrier->weight = (float) $order_object->getTotalWeight();
                $order_carrier->shipping_cost_tax_excl = (float) $order_object->total_shipping_tax_excl;
                $order_carrier->shipping_cost_tax_incl = (float) $order_object->total_shipping_tax_incl;
                $order_carrier->add();
            }
            foreach ($lines as $item) {
                $getMerchantProductNo = explode("-", $item->getMerchantProductNo());
                $query = "UPDATE `" . _DB_PREFIX_ . "order_detail` SET id_channelengine_product='" . $item->getId() . "'"
                        . "WHERE product_id ='" . $getMerchantProductNo[0] . "' AND product_attribute_id = ' " . $getMerchantProductNo[1] . "'AND id_order = ' " . $order_object->id . "' ";
                Db::getInstance()->Execute($query);
            }
            Db::getInstance()->update('orders', array('id_channelengine_order' => $channelOrderId), 'id_order = ' . $order_object->id);
        }
    }

    /**
     * Create a prestashop Customer
     * @param type $billingAddress
     * @param type $email
     * @return type
     */
    function createPrestashopCustomer($billingAddress, $email) {
        $customer_object = new Customer();
        $customer_object->firstname = $billingAddress->getfirstName();
        $customer_object->lastname = $billingAddress->getLastName();
        $customer_object->email = $email;
        $customer_object->passwd = md5(uniqid(rand(), true));
        $customer_object->add();
        return $customer_object->id;
    }

    /*
     * Function to handle request
     */
    function handleRequest() {
        try { 
            $this->client->validateCallbackHash();
        } catch(Exception $e) {
            http_response_code(403);
            exit($e->getMessage());
        }

        $type = isset($_GET['type']) ? $_GET['type'] : '';
        switch ($type) {
            case 'orders':
                $this->cronOrdersSync();
                break;
            case 'returns':
                $this->cronReturnSync();
                break;
            case 'products':
                $timestamp = $_GET['updatedSince'];
                $this->cronProductSync($timestamp);
                break;
        }
    }
}

function pr($data) {
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
}