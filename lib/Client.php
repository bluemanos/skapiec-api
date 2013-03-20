<?php

namespace mdurys\SkapiecAPI;

/**
 * @author Michał Durys <michal@durys.pl>
 *
 * @method mixed meta_whoAmI()
 * @method mixed meta_availableServices()
 */
class Client
{
    /**
     * Domain of the API host.
     */
    const API_HOST = 'api.skapiec.pl';

    /**
     * Image size IDs for beta_getProductPhoto()
     */
    const PHOTO_SIZE_ALL = 0;
    const PHOTO_SIZE_XSMALL = 1;
    const PHOTO_SIZE_SMALL = 2;
    const PHOTO_SIZE_NORMAL = 3;

    /**
     * @var string API user name.
     */
    protected $apiUser;

    /**
     * @var string API password.
     */
    protected $apiPassword;

    protected $outputFormat = 'json';

    /**
     * @var resource CURL handle.
     */
    protected $curlHandle;

    /**
     * @var int Timeout for API calls (seconds).
     */
    protected $timeout = 10;

    /**
     * @var array 
     */
    protected $queryParams = array();

    /**
     * @var int Stores URL used to make last API call.
     */
    protected $lastUrl;

    /**
     * @var int Stores HTTP status code returned by last API call.
     */
    protected $lastCode;

    /**
     * @var int Stores raw result returned by last API call.
     */
    protected $lastResult;

    /**
     * @var array Known API methods and their required arguments.
     */
    private static $apiMethods = array(
        'meta_whoAmI' => array(),
        'meta_availableServices' => array(),
        'beta_addOffer' => array('component', 'id_skapiec', 'url'),
        'beta_getOffersBestPrice' => array(),
        'beta_getOpinionsLatest' => array(),
        'beta_getOpinionsBestValue' => array(),
        'beta_listProducts' => array('category'),
        'beta_rebindOffer' => array('component', 'id_skapiec'),
        'beta_getFilters' => array('category'),
        'beta_getFilterOptions' => array('filter'),
        'beta_filterProducts' => array('filter', 'category'),
        'beta_searchOffers' => array('q'),
        'beta_listDepartments' => array(),
        'beta_listCategories' => array('department'),
        'beta_getDepartmentInfo' => array('id'),
        'beta_getCategoryInfo' => array('id'),
        'beta_getDealerInfo' => array('id'),
        'beta_getDealerCategories' => array('id'),
        'beta_getDealerOffers' => array('id'),
        'beta_addExpOpinion' => array('component_id_array', 'title', 'url', 'description'),
        'beta_addOpinion' => array('component', 'ocena', 'description', 'category'),
        'beta_listDealerProducts' => array('id'),
        'beta_addToFavorites' => array('id'),
        'beta_addPriceAlert' => array(),
        'beta_getProductMostPopular' => array(),
        'beta_getProductPhoto' => array('id', 'category'),
        'beta_getProductInfo' => array('id', 'category'),
        'beta_listProducers' => array('category'),
        'beta_listProducersProducts' => array('producer', 'sort', 'amount'),
        'beta_listNewProducts' => array(),
        'beta_searchOffersFilters' => array('q'),
        'beta_multiSearch' => array('json'),
        );

    public function __construct($apiUser, $apiPassword)
    {
        $this->apiUser = $apiUser;
        $this->apiPassword = $apiPassword;

        $this->curlHandle = curl_init();
        curl_setopt_array($this->curlHandle, array(
            CURLOPT_USERAGENT => 'SkapiecApiClient.php',
            CURLOPT_USERPWD => $this->apiUser . ':' . $this->apiPassword,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout));
    }

    public function __destruct()
    {
        if ($this->curlHandle)
        {
            curl_close($this->curlHandle);
        }
    }

    /**
     * Handle virtual API methods: meta_*, beta_* and set*.
     *
     * @param string $name Name of called method.
     * @param array $arguments Method arguments.
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // handle setXXX methods which set query parameters
        if (strncmp('set', $name, 3) == 0)
        {
            $this->queryParams[self::camelcaseToUnderscore(substr($name, 3))] = current($arguments);
            return $this;
        }
        // handle API methods
        else if (in_array($name, array_keys(self::$apiMethods)))
        {
            // check if this method has any required parameters
            if (!empty(self::$apiMethods[$name]))
            {
                // make sure that number of parameters is correct
                if (count(self::$apiMethods[$name]) <> count($arguments))
                {
                    throw new \BadMethodCallException(get_class($this) . '::' . $name . ' requires ' . count(self::$apiMethods[$name]) . ' argument(s)');
                }
                // add method parameters to query
                $this->queryParams += array_combine(self::$apiMethods[$name], $arguments);
            }
            $url = 'http://' . self::API_HOST . '/' . $name . '.' . $this->outputFormat;
            if (!empty($this->queryParams))
            {
                $url .= '?' . http_build_query($this->queryParams);
                $this->queryParams = array();
            }
            curl_setopt($this->curlHandle, CURLOPT_URL, $url);
            $this->lastUrl = $url;
            $this->lastResult = curl_exec($this->curlHandle);
            $this->lastCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
            if ($this->lastCode != 200)
            {
                throw new \Exception($this->lastResult, $this->lastCode);
            }

            switch ($this->outputFormat)
            {
                case 'json':
                    return json_decode($this->lastResult, true);
//                  return json_decode($this->lastResult, false);
                case 'xml':
                    return simplexml_load_string($this->lastResult);
            }
        }

        throw new \BadMethodCallException('Tried to call unknown method ' . get_class($this) . '::' . $name);
    }

/*
    public function beta_getOffersBestPriceBySkapiecId($idSkapiec)
    {
        $this->queryParams['id_skapiec'] => $idSkapiec;
        return $this->__call('beta_getOffersBestPrice', array());
    }

    public function beta_listProducts($categoryId)
    {
        return $this->__call(__FUNCTION__, array('category' => $categoryId));
    }
*/
    public function onlyField()
    {
        $this->queryParams['onlyField'] = implode(',', func_get_args());
        return $this;
    }

    public function includeField()
    {
        $this->queryParams['includeField'] = implode(',', func_get_args());
        return $this;
    }

    public function excludeField()
    {
        $this->queryParams['excludeField'] = implode(',', func_get_args());
        return $this;
    }

    /**
     * Returns URL used to make last API call.
     *
     * @return string URL used to make last API call.
     */
    public function getLastUrl()
    {
        return $this->lastUrl;
    }

    /**
     * Returns HTTP status code returned by last API call.
     *
     * @return int HTTP status code returned by last API call.
     */
    public function getLastCode()
    {
        return $this->lastCode;
    }

    /**
     * Returns raw reponse from last API call.
     *
     * @return string Response
     */
    public function getLastResult()
    {
        return $this->lastResult;
    }

    /**
     * Convert string in camelCase to underscore_notation.
     *
     * @param string $string
     * @return string
     */
    protected static function camelcaseToUnderscore($string)
    {
        return strtolower(preg_replace('|(?<=\\w)(?=[A-Z])|', '_$1', $string));
    }
}