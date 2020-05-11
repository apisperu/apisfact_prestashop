<?php
/**
* 2007-2020 PrestaShop
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
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/


if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/helpers/Curl.php');

class Ap_Facturacion extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'ap_facturacion';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'ApisPeru';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Facturación Electrónica SUNAT');
        $this->description = $this->l('Emisión electrónica mediante el servicio https://apisperu.com/servicios/facturacion/');

        $this->confirmUninstall = $this->l('¿Está seguro que desea desinstalar este modulo?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('AP_FACTURACION_TOKEN', '');

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayAdminOrderLeft') &&
            $this->registerHook('displayAdminOrderMain');
    }

    public function uninstall()
    {
        Configuration::deleteByName('AP_FACTURACION_TOKEN');

        include(dirname(__FILE__).'/sql/uninstall.php');

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
        if (((bool)Tools::isSubmit('submit_ap_facturacion')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_ap_facturacion';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
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
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Ingrese un token de empresa - Vea la documentación'),
                        'name' => 'AP_FACTURACION_TOKEN',
                        'label' => $this->l('Token'),
                    )
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
    protected function getConfigFormValues()
    {
        return array(
            'AP_FACTURACION_TOKEN' => Configuration::get('AP_FACTURACION_TOKEN', true)
        );
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
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookDisplayAdminOrderMain()
    {
        return $this->hookDisplayAdminOrderLeft();
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        if (((bool)Tools::isSubmit('submit_ap_facturacion_invoice_send')) == true)
        {
            $this->postProcessFacturacion($params);
        }
        
        $this->context->smarty->assign([
            'token' => Configuration::get('AP_FACTURACION_TOKEN', true)
        ]);   

        return $this->display(__FILE__, 'views/templates/hook/displaySunatOrder.tpl');
    }

    function postProcessFacturacion($params)
    {
        $db = \Db::getInstance();
        $token = Configuration::get('AP_FACTURACION_TOKEN', true);

        $data = [
            "tipoOperacion" => "0101",
            "tipoDoc" => "03",
            "serie" => "B001",
            "correlativo" => "1",
            "fechaEmision" => "2019-10-27T00:00:00-05:00",
            "tipoMoneda" => "PEN",
            "client" => [
                "tipoDoc" => "1",
                "numDoc" => 47602928,
                "rznSocial" => "EDGAR ANTONIO FLORES",
                "address" => [
                    "direccion" => "AV LOS GERUNDIOS"
                ]
            ],
            "company" => [
                "ruc" => 20000000007,
                "razonSocial" => "Mi empresa",
                "address" => [
                    "direccion" => "Direccion empresa"
                ]
            ],
            "mtoOperGravadas" => 100,    
            "mtoIGV" => 18,
            "totalImpuestos" => 18,
            "valorVenta" => 100,
            "mtoImpVenta" => 118,
            "ublVersion" => "2.1",
            "details" => [
                
                    "codProducto" => "P001",
                    "unidad" => "NIU",
                    "descripcion" => "PRODUCTO 1",
                    "cantidad" => 2,
                    "mtoValorUnitario" => 50,
                    "mtoValorVenta" => 100,
                    "mtoBaseIgv" => 100,
                    "porcentajeIgv" => 18,
                    "igv" => 18,
                    "tipAfeIgv" => 10,
                    "totalImpuestos" => 18,
                    "mtoPrecioUnitario" => 59
                
            ],
            "legends" => [
                [
                    "code" => "1000",
                    "value" => "SON CIENTO DIECIOCHO CON 00/100 SOLES"
                ]
            ]
        ];

        $curl = new Curl\Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Authorization', 'Bearer ' . $token);
        $curl->post('https://facturacion.apisperu.com/api/v1/invoice/send', $data, true);

        if ($curl->error) {
            $this->context->controller->errors[] = $this->l('Error ') . $curl->error_code . ' ' . $curl->error_message;
        }
        else {
            $result = json_decode($curl->response); 
            if ($result->sunatResponse) {
                $result = $db->insert('ap_facturacion', array(
                    'id_order' => $params['id_order'],
                    'success' => $result->sunatResponse->success,
                    'response_id' => $result->sunatResponse->cdrResponse->id,
                    'response_code' => $result->sunatResponse->cdrResponse->code,
                    'response_description' => $result->sunatResponse->cdrResponse->description,
                    'response_notes' => serialize($result->sunatResponse->cdrResponse->notes),
                ));

                $this->context->controller->errors[] = $result->sunatResponse->cdrResponse->description;
            }
        }
    }
}
