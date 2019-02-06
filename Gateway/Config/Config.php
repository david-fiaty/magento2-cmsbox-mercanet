<?php
/**
 * Cmsbox.fr Magento 2 Payment module (https://www.cmsbox.fr)
 *
 * Copyright (c) 2017 Cmsbox.fr (https://www.cmsbox.fr)
 * Author: David Fiaty | contact@cmsbox.fr
 *
 * License GNU/GPL V3 https://www.gnu.org/licenses/gpl-3.0.en.html
 */
 
namespace Cmsbox\Mercanet\Gateway\Config;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Module\Dir;
use Cmsbox\Mercanet\Gateway\Processor\Connector;
use Cmsbox\Mercanet\Gateway\Config\Core;

class Config {
    const KEY_ENVIRONMENT = 'environment';
    const KEY_SIMU_MERCHANT_ID = 'simu_merchant_id';
    const KEY_TEST_MERCHANT_ID = 'test_merchant_id';
    const KEY_PROD_MERCHANT_ID = 'prod_merchant_id';
    const KEY_SIMU_SECRET_KEY = 'simu_secret_key';
    const KEY_TEST_SECRET_KEY = 'test_secret_key';
    const KEY_PROD_SECRET_KEY = 'prod_secret_key';
    const KEY_DEFAULT_LANGUAGE = 'en';

    /**
     * @var Reader
     */
    protected $moduleDirReader;

    /**
     * @var Parser
     */
    protected $xmlParser;

    /**
     * @var ScopeConfigInterface
     */    
    protected $scopeConfig;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var Cart
     */
    public $cart;

    /**
     * @var Core
     */
    protected $core;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var MethodHandlerService
     */
    public $methodHandler;

    /**
     * @var Resolver
     */    
    protected $localeResolver;

    /**
     * @var Array
     */    
    public $params = [];

    /**
     * @var Array
     */    
    public $base = [];

    /**
     * Config constructor.
     */
    public function __construct(
        \Magento\Framework\Module\Dir\Reader $moduleDirReader,
        \Magento\Framework\Xml\Parser $xmlParser,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Cmsbox\Mercanet\Model\Service\MethodHandlerService $methodHandler,
        \Magento\Framework\Locale\Resolver $localeResolver
    ) {
        $this->moduleDirReader = $moduleDirReader;
        $this->xmlParser       = $xmlParser;
        $this->scopeConfig     = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->cart            = $cart;
        $this->storeManager    = $storeManager;
        $this->methodHandler   = $methodHandler;
        $this->localeResolver  = $localeResolver;
        
        // Load the module config file
        $this->loadConfig();
    }

    /**
     * Loads the module configuration parameters.
     */
    public function loadConfig() {
        try {
            // Prepare the output container
            $output = [];

            // Get the config file
            $filePath = $this->moduleDirReader->getModuleDir(Dir::MODULE_ETC_DIR, Core::moduleName()) . '/config.xml';
            $fileData = $this->xmlParser->load($filePath)->xmlToArray()['config']['_value']['default'];

            // Set the base parameters array
            $this->base = $this->buildBase($fileData);

            // Get the config array
            $configArray = $fileData['payment'] ?? [];

            // Get the configured values
            if (!empty($configArray)) {
                foreach ($configArray as $methodId => $params) {
                    $lines = [];
                    foreach ($params as $key => $val) {
                        // Check a database value
                        $dbValue = $this->scopeConfig->getValue(
                            'payment/' . $methodId . '/' . $key, 
                            ScopeInterface::SCOPE_STORE
                        );

                        // Convert the value to string for empty testing
                        $testValue = stripslashes(trim(var_export($dbValue, true), "'"));

                        // Assign the value or override with db value
                        if (!empty($testValue)) {
                            $lines[$key] = $testValue;
                        }
                        else {
                            $lines[$key] = $val;
                        }
                    }

                    $output[$methodId] = $lines;  
                }
            }

            // Set the payment methods config array
            $this->params = $output;

        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The module configuration data could not be loaded'));
        }
    }

    /**
     * Builds the base module parameters.
     *
     * @return bool
     */
    public function buildBase($fileData) {
        try {
            $output = [];
            $exclude = explode(',', $fileData['base']['exclude']);

            foreach ($fileData['payment'][Core::moduleId()] as $key => $val) {
                if (!in_array($key, $exclude)) {
                    // Check a database value
                    $dbValue = $this->scopeConfig->getValue(
                        'payment/' . Core::moduleId() . '/' . $key, 
                        ScopeInterface::SCOPE_STORE
                    );

                    // Convert the value to string for empty testing
                    $testValue = stripslashes(trim(var_export($dbValue, true), "'"));

                    // Assign the value or override with db value
                    if (!empty($testValue)) {
                        $output[$key] = $testValue;
                    }
                    else {
                        $output[$key] = $val;
                    }
                }
            }
            
            unset($fileData['base']['exclude']);

            return array_merge($fileData['base'], $output);

        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The base configuration data could not be loaded.'));
        }
    }

    /**
     * Get supported currencies.
     *
     * @return string
     */
    public function getSupportedCurrencies() {
        try {
            $output = [];
            $arr = explode(';', $this->base[Core::KEY_SUPPORTED_CURRENCIES]);
            foreach ($arr as $val) {
                $parts = explode(',', $val);
                $output[$parts[0]] = $parts[1];
            }

            return $output;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('There was an error loading the currencies.'));
        }
    }

    /**
     * Builds the API URL.
     *
     * @return string
     */
    public function getApiUrl($action, $methodId) {
        $mode = $this->params[Core::moduleId()][self::KEY_ENVIRONMENT];
        $path = 'api_url' . '_' . $mode . '_' . $action;
        return $this->params[$methodId][$path];
    }

    /**
     * Provides the frontend config parameters.
     *
     * @return string
     */
    public function getFrontendConfig() {
        try {
            // Prepare the output
            $output = [];

            // Get request data for each method
            foreach ($this->params as $key => $val) {
                $arr = explode('_', $key);
                if ($this->methodIsValid($arr, $key, $val)) {
                    $methodInstance = $this->methodHandler->getStaticInstance($key);
                    if ($methodInstance && $methodInstance::isFrontend($this, $key)) {
                        $output[$key] = $val;
                        $output[$key][Connector::KEY_ACTIVE] = $methodInstance::isFrontend($this, $key);
                        if (isset($val['load_request_data']) && (int) $val['load_request_data'] == 1) {
                            $output[$key]['api_url'] = $this->getApiUrl('charge', $key);
                            $output[$key]['request_data'] = $methodInstance::getRequestData($this, $key);
                        }
                    } 
                }
            } 

            // Return the formatted config array
            return [
                'payment' => [
                    Core::moduleId() => array_merge(
                        $output, 
                        $this->base
                    )
                ]
            ];
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The frontend configuration could not be loaded.'));
        }
    }

    public function methodIsValid($arr, $key, $val) {
        return isset($arr[2]) && isset($arr[3]) 
        && isset($val['can_use_internal']) && (int) $val['can_use_internal'] != 1
        && !in_array($key, $arr);
    }

    /**
     * Returns the transaction reference.
     *
     * @return string
     */
    public function createTransactionReference() {
        return (string) time();   
    }

    /**
     * Retrieves the customer language.
     */
    public function getCustomerLanguage() {
        $lang = explode('_', $this->localeResolver->getLocale()) ;
        return (isset($lang[0]) && !empty($lang[0])) ? $lang[0] : self::KEY_DEFAULT_LANGUAGE;
    }

    /**
     * Formats an amount for a gateway request.
     */   
    public function formatAmount($amount) {
        return (int) (number_format($amount, 2))*100;
    }

    /**
     * Returns the merchant ID.
     *
     * @return string
     */
    public function getMerchantId() {
        switch ($this->base[self::KEY_ENVIRONMENT]) {
            case 'simu': 
            $id = $this->base[self::KEY_SIMU_MERCHANT_ID];
            break;

            case 'test': 
            $id = $this->base[self::KEY_TEST_MERCHANT_ID];
            break;

            case 'prod': 
            $id = $this->base[self::KEY_PROD_MERCHANT_ID];;
            break;
        }

        return (string) $id;
    }

    /**
     * Returns the active secret key.
     *
     * @return string
     */
    public function getSecretKey() {
        // Return the secret key
        switch ($this->base[self::KEY_ENVIRONMENT]) {
            case 'simu': 
            $key = $this->params[Core::moduleId()][self::KEY_SIMU_SECRET_KEY];
            break;

            case 'test': 
            $key = $this->params[Core::moduleId()][self::KEY_TEST_SECRET_KEY];
            break;

            case 'prod': 
            $key = $this->params[Core::moduleId()][self::KEY_PROD_SECRET_KEY];
            break;
        }

        return $key;
    }
}
