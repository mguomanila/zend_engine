<?php
/**
 * Abstract class for all content adapters
 *
 * When writing a new adapter, it's a good idea to extend this abstract class
 * to ensure you are writing your adapter properly
 *
 * This file cannot be deleted!
 *
 * @author BlackHatCloaker
 */
abstract class Content_Adapter_Abstract
{
    const CONTENT_STATUS_PASSED     = 'passed';
    const CONTENT_STATUS_NO_RESULTS = 'no_results';
    const CONTENT_STATUS_FAILED     = 'failed';

    /**
     * Options passed from the primary content class
     *
     * @var array
     */
    private $_options = array();

    /**
     * Passed Zend HTTP Client Instance
     *
     * @var Zend_Http_Client
     */
    private $_client;

    /**
     * @var string
     */
    private $_contentStatus;

    /**
     * HTTP Status Code returned
     *
     * @var string
     */
    private $_httpStatusCode;

    /**
     * Constructor
     *
     * @param  unknown_type $options
     * @param  unknown_type $client
     * @return void
     */
    public function __construct(array $options = null, Zend_Http_Client $client = null)
    {
        if (null === $client)
        {
            return;
        }

        /*
         * set the adapter options
         */
        $this->setOptions($options);

        /*
         * set the client
         */
        $this->_client = $client;

        /*
         * reset the parameters
         */
        $this->getClient()->resetParameters($clearAll = true);
    }

    /**
     * Set adapter options
     *
     * @param  array $options
     * @return Content_Adapter_Abstract
     */
    final public function setOptions(array $options)
    {
        $this->_options = array_merge($this->_options, $options);
        return $this;
    }

    /**
     * Set an individual configuration option
     *
     * @param  string $name
     * @param  mixed $value
     * @return Content_Adapter_Abstract
     */
    final public function setOption($name, $value)
    {
        $this->_options[(string) $name] = $value;
        return $this;
    }

    /**
     * Returns the configuration options for the queue
     *
     * @return array
     */
    final public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Determine if a requested option has been defined
     *
     * @param  string $name
     * @return bool
     */
    final public function hasOption($name)
    {
        return array_key_exists($name, $this->_options);
    }

    /**
     * Retrieve a single option
     *
     * @param  string $name
     * @return null|mixed Returns null if option does not exist; option value otherwise
     */
    final public function getOption($name)
    {
        if ($this->hasOption($name))
        {
            return $this->_options[$name];
        }
        return null;
    }

    /**
     * Returns the Zend_Http_Client instance
     *
     * @return Zend_Http_Client
     */
    final public function getClient()
    {
        return $this->_client;
    }

    /**
     * Returns the content status of a specific
     * content source
     *
     * @return string
     */
    final public function getContentStatus()
    {
        return $this->_contentStatus;
    }

    /**
     * Sets the content status. See the defined
     * constants for available statuses
     *
     * @param  string $status
     * @return Content_Adapter_Abstract
     */
    final protected function _setContentStatus($status = self::CONTENT_STATUS_PASSED)
    {
        $this->_contentStatus = (string) $status;
        return $this;
    }

    /**
     * Returns the last HTTP status code returned
     * from the client
     *
     * @return int
     */
    final public function getHttpStatusCode()
    {
        return $this->_httpStatusCode;
    }

    /**
     * Sets the HTTP Status Code from the client
     *
     * @param  int $code
     * @return Content_Adapter_Abstract
     */
    final protected function _setHttpStatusCode($code)
    {
        $this->_httpStatusCode = (int) $code;
        return $this;
    }

    /**
     * Sends Zend_Http_Client request
     *
     * @return mixed
     */
    final protected function _request($method = null)
    {
        try
        {
            $response = $this->getClient()->request($method);
            return $response;
        }
        catch (Zend_Exception $e)
        {
            if (stristr($e->getMessage(), 'Connection timed out'))
            {
                $this->_setHttpStatusCode(500);
                return false;
            }

            if (stristr($e->getMessage(), 'Unable to connect'))
            {
                $this->_setHttpStatusCode(599);
                return false;
            }

            throw $e;
        }
    }

    /**
     * Fetches the content and/or determine
     * what kind of response was returned
     *
     * When the response finds content, be sure to set
     * the content status to:
     *
     * $this->_setContentStatus(Content_Adapter_Abstract::CONTENT_STATUS_PASSED);
     *
     * If the content source returned no results:
     *
     * $this->_setContentStatus(Content_Adapter_Abstract::CONTENT_STATUS_NO_RESULTS);
     *
     * And if the response cannot be preg matched, set a failed response:
     *
     * $this->_setContentStatus(Content_Adapter_Abstract::CONTENT_STATUS_FAILED);
     *
     * IMPORTANT: In the event of a failed response or no results, return false
     *
     * @param  string $keyword The non-encoded keyword to search by
     * @return false|string
     */
    abstract public function fetchContent($keyword);
}
