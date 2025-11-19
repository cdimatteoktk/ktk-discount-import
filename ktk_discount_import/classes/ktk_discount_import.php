<?php
/**
* 2007-2021 PrestaShop
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
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';

class Ktk_discount_import extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'ktk_discount_import';
        $this->tab = 'quick_bulk_update';
        $this->version = '1.0.1';
        $this->author = 'Kotuko';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Kotuko: CSV Discount Import');
        $this->description = $this->l('This module allow to bulk import discount for Main Shop (id 1) and MiniShop using a CSV. It must be activated calling the exposed API that can also be scheduled with a cronjob. Path of the file must be set in configuration.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        // $tab_id = Tab::getIdFromClassName('ImportCsv');
        // if ($tab_id == false){
        //     $this->installCSVTab();
        // }

    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_ALLOW_EXECUTION', false);
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CRON_JOB_TRACK_OUTPUT_CSV', true);
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL', false);
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CSV_SEPARATOR_CHAR', ";");
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CSV_FILE_PATH_NUM', "NO_PATH");
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CSV_ROW_TO_SPLIT', "");
        Configuration::updateValue('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT', "");


        if (parent::install() && $this->registerHook('header') 
            && $this->registerHook('backOfficeHeader') && $this->registerHook('moduleRoutes') 
            //&& $this->installCSVTab()
            ) {
                return true;
        } else { // if something wrong return false
            $this->_errors[] = $this->l('There was an error during the installation. Please contact us through Addons website.');
            return false;
        }
    }

    public function installCSVTab() {
        $tab_id = Tab::getIdFromClassName('ImportCsv');
        if (empty($tab_id)) {
            $languages = Language::getLanguages(false);
            $tab = new Tab();
            $tab->class_name = 'ImportCsv';
            $tab->id_parent = (int)Tab::getIdFromClassName('DEFAULT');
            $tab->module = $this->name;
            foreach ($languages as $language) {
                $tab->name[$language['id_lang']] = "CSV Discount Import";
            }
            $tab->add();
        }
        return true;
    }

    public function uninstallCSVTab() {
        $tab_id = Tab::getIdFromClassName('ImportCsv');
        if ($tab_id) {
            $tab = new Tab($tab_id);
            $tab->delete();
        }
        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_ALLOW_EXECUTION');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_API_KEY');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CRON_JOB_TRACK_OUTPUT_CSV');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL_RECIPIENT');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CSV_SEPARATOR_CHAR');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CSV_FILE_PATH_NUM');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CSV_ROW_TO_SPLIT');
        Configuration::deleteByName('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT');

        return parent::uninstall() && $this->uninstallCSVTab();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitKtk_discount_importModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl') . $this->renderForm();

        return $output;
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
        $helper->submit_action = 'submitKtk_discount_importModule';
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
                        'type' => 'switch',
                        'label' => $this->l('Allow Discount Import'),
                        'name' => 'KTK_DISCOUNT_IMPORT_ALLOW_EXECUTION',
                        'is_bool' => true,
                        'desc' => $this->l('Disallow execution of Discount importing'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Allow')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disallow')
                            )
                        ),
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Generate a complex APIKEY to authenticate API Call for CronJob execution. Leave empty if in need to skip authentication'),
                        'name' => 'KTK_DISCOUNT_IMPORT_API_KEY',
                        'label' => $this->l('Api Key'),
                    ),
                    array(
                        'type' => 'text',
                        'col' => 6,
                        'name' => 'KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH',
                        'prefix' => '<i class="icon icon-folder"></i>',
                        'desc' => $this->l('Indicate the relative path where the module will search the file when called by CronJob or by API. Root folder of the website will automatically added by the controller executing the request.'),
                        'label' => $this->l('Filepath'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Track output result in CSV'),
                        'name' => 'KTK_DISCOUNT_IMPORT_CRON_JOB_TRACK_OUTPUT_CSV',
                        'is_bool' => true,
                        'desc' => $this->l('Enable if to write down a full result of import for each line in a CSV. If Result Mail are enabled, this file will be attached to it'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Send Mail with Import Result'),
                        'name' => 'KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL',
                        'is_bool' => true,
                        'desc' => $this->l('Enable to send a mail to specified recipient for each import cronjob execution'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                    array(
                        'col' => 8,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Recipient that will receive result mail. This field must be filled if "Send Mail with Import Result" is set to "ON". Multiple address can be separated by semicolon (;). If set, this address will also receive mail for generic error during import, such as in case of invalid or not found file.'),
                        'name' => 'KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL_RECIPIENT',
                        'label' => $this->l('Result Mail Recipients'),
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
    protected function getConfigFormValues()
    {
        return array(
            'KTK_DISCOUNT_IMPORT_ALLOW_EXECUTION' => Configuration::get('KTK_DISCOUNT_IMPORT_ALLOW_EXECUTION', false),
            'KTK_DISCOUNT_IMPORT_API_KEY' => Configuration::get('KTK_DISCOUNT_IMPORT_API_KEY', null),
            'KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH' => Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH', null),
            'KTK_DISCOUNT_IMPORT_CRON_JOB_TRACK_OUTPUT_CSV' => Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_TRACK_OUTPUT_CSV', null),
            'KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL' => Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL', null),
            'KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL_RECIPIENT' => Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_NOTIFY_RESULT_MAIL_RECIPIENT', null),
            'KTK_DISCOUNT_IMPORT_CSV_SEPARATOR_CHAR' => Configuration::get('KTK_DISCOUNT_IMPORT_CSV_SEPARATOR_CHAR', null),
            'KTK_DISCOUNT_IMPORT_CSV_ROW_TO_SPLIT' =>  Configuration::get('KTK_DISCOUNT_IMPORT_CSV_ROW_TO_SPLIT'),
            'KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT'=>Configuration::get('KTK_DISCOUNT_IMPORT_CRON_JOB_FILE_PATH_SPLIT')
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

    public function hookModuleRoutes()
    {
        //$head = Configuration::get('your_config', $this->language->id);
        $routes = [];
        $routes = [
            'module-ktk_csv-process' => [ //module-[MOCULE_NAME]-[CONTROLLER_FILE_NAME]
                'rule' => 'ktk-import-discount/process-csv', // any path
                'keywords' => [],
                'controller' => 'import', // [CONTROLLER_FILE_NAME]
                'params' => [
                    'fc' => 'module', //mandatory
                    'module' => 'ktk_discount_import', // [MOCULE_NAME] mandatory
                ]
            ],
        ];
        return $routes;
    }
}
