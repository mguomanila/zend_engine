<?php
/**
 * Deep linking abstract class
 *
 * Any class that will attempt to crawl links found in search results should
 * extend this class to make use of deep link crawling
 *
 * This file cannot be deleted!
 *
 * @author SSES Mike
 */
abstract class Content_Adapter_DeepLinkingAbstract extends Content_Adapter_Abstract
{
    /**
     * Array of URLs found for deep crawling
     *
     * @var array
     */
    private $_multiUrls = array();

    /**
     * Returns array of URLs to crawl
     *
     * @return array
     */
    public function getMultiUrls()
    {
        $urls = array_unique($this->_multiUrls);
        shuffle($urls);
        return $urls;
    }

    /**
     * Appends a url to the multi url array
     *
     * @param  string|array $url
     * @return Content_Adapter_DeepLinkingAbstract
     */
    public function appendMultiUrl($url)
    {
        if (is_array($url))
        {
            $this->_multiUrls = array_merge($this->_multiUrls, $url);
            return $this;
        }

        $this->_multiUrls[] = $url;
        return $this;
    }

    /**
     * Fetches multiple sources simultaneously
     *
     * @param  array $urls
     * @return void
     */
    protected function _fetchMulti(array $urls = null)
    {
        if (null === $urls)
        {
            $urls = $this->getMultiUrls();
        }

        $curlHandles = array();
        $resultCurlResponses = array();

        $mh = curl_multi_init();

        foreach ($urls as $k => $url)
        {
            $url = trim($url);

            if (!preg_match('/^http:/i', $url))
            {
                continue;
            }

            $curlHandles[$k] = curl_init($url);

            $opts = array(
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_TIMEOUT        => 2
            );

            if (null != ($ipUsed = $this->getOption('networkInterfaceIpUsed')))
            {
                $opts[CURLOPT_INTERFACE] = $ipUsed;
            }

            //set options
            curl_setopt_array($curlHandles[$k], $opts);

            //set handle
            curl_multi_add_handle($mh, $curlHandles[$k]);
        }

        // execute the handles
        $active = null;
        do
        {
            $mrc = curl_multi_exec($mh, $active);
        }
        while ($mrc == CURLM_CALL_MULTI_PERFORM);

        //run in parallel
        while ($active && $mrc == CURLM_OK)
        {
            if (curl_multi_select($mh) != -1)
            {
                do
                {
                    $mrc = curl_multi_exec($mh, $active);
                }
                while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        // get content and remove handles
        $result = '';
        $failedResults = array();
        foreach ($curlHandles as $k => $c)
        {
            $info = curl_getinfo($c);

            if (200 != $info['http_code'])
            {
                $failedResults[] = $info;
                continue;
            }

            $html = curl_multi_getcontent($c);

            if (null == $html)
            {
                $failedResults[] = $info;
                continue;
            }

            if (preg_match('/<body.*?>(.*?)<\/body>/si', $html, $matches))
            {
                $result .= $matches[1];
            }

            curl_multi_remove_handle($mh, $c);
        }

        //all done
        curl_multi_close($mh);

        //if all the results failed, then return false
        if (count($failedResults) == count($urls))
        {
            return false;
        }

        return $result;
    }
}
