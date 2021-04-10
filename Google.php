<?php
/**
 * Content scraper for google.com
 *
 * This file cannot be deleted!
 *
 * @author SSES Mike
 * @label  Google
 */
    

    
class Content_Adapter_Google extends Content_Adapter_DeepLinkingAbstract
{
    /**
     * Fetch content
     *
     * @see    Content_Adapter_Abstract::fetchContent()
     *
     * @param  string $keyword
     * @return false|string
     */
    
    
    
    
    public function fetchContent($keyword)
    {
        $client = $this->getClient()
            ->setHeaders('Referer', 'https://www.google.com/')
	    ->setHeaders('User-Agent','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.111 Safari/537.36')
            ->setUri('https://www.google.com/search')
            //->setProxyHost ('$proxyip')
            //->setProxyPort ('$proxyport')
            ->setParameterGet(array(
		'site'   => 'collection',
                'num'    => '100',
		'client' => 'psy-ab',
		//'output' => 'search',
                'q'      => $keyword,
                'oq'     => $keyword,
		'xssi'    => 't',
		'authuser'=> '0',
            ));

        if (false == ($request = $this->_request()))
        {
            $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
            return false;
        }

        //set the status code
        $this->_setHttpStatusCode($request->getStatus());

        //log the status if there is a 4xx or 5xx error
        if ($request->isError())
        {
            $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
            return false;
        }

        //if no matches
        if (preg_match('/Your search -.*- did not match any documents/', $request->getBody()))
        {
            $this->_setContentStatus(self::CONTENT_STATUS_NO_RESULTS);
            return false;
        }

        //scrape links or grab snippets
        switch($this->getOption('scrape_source'))
        {
            case content::SCRAPE_SOURCE_CRAWL:

                if (preg_match_all('/class="\\br|r\\b".*href=".*q=(.*?)&amp;?.*"/is', $request->getBody(), $result, PREG_PATTERN_ORDER))
                {
                    if (is_array($result[1]) && count($result[1]) > 0)
                    {
                        shuffle($result[1]);
                        $urls = array_slice($result[1], 0, $this->getOption('crawl_site_count'));
                        $this->appendMultiUrl($urls);

                        if (false !== ($content = $this->_fetchMulti()))
                        {
                            $this->_setContentStatus(self::CONTENT_STATUS_PASSED);
                            return $content;
                        }
                        else
                        {
                            $this->_setContentStatus(self::CONTENT_STATUS_FAILED)
                                 ->_setHttpStatusCode(500);

                            return false;
                        }
                    }
                }

                break;

            case content::SCRAPE_SOURCE_SNIPPETS:
            default:

                if (preg_match_all('/<span class="?st"?>(.*?)<\\/div>/i', $request->getBody(), $result, PREG_PATTERN_ORDER))
                {
                    if (is_array($result[0]))
                    {
                        $content = '';
                        foreach ($result[0] as $text)
                        {
                            //remove google notes on snippets
                            $text = preg_replace('/<span class="?f"?>.*?<\/span>/i', '', $text);
                            $text = str_ireplace('<b>...</b>', '', $text);
                            $content .= ' ' . $text . (!preg_match('/[.!?]$/', $text) ? '. ' : '');
                        }

                        $this->_setContentStatus(self::CONTENT_STATUS_PASSED);

                        return $content;
                    }
                }

                break;
        }

        $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
        return false;
    }
}
