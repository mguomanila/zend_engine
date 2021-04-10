<?php
/**
 * Content scraper for google.co.uk
 *
 * @author SSES Mike
 * @label  Google.co.uk
 */
class Content_Adapter_Google_Co_Uk extends Content_Adapter_DeepLinkingAbstract
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
        ->setHeaders('Referer', 'https://www.google.co.uk/')
        ->setUri('https://www.google.co.uk/search')
        ->setParameterGet(array(
                'site'     => 'collection',
                'output'  => 'search',
                'as_oq'      => $keyword,
                'q'       => $keyword,
                'client' => 'collection'));

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

        //print_r($request->getBody()); exit;

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

                //if (preg_match_all('/<h3 class="?r"?.*?<a\\shref="\/url\\?q=(.*?)&amp;sa=U/s', $request->getBody(), $result, PREG_PATTERN_ORDER))
                if (preg_match_all('/<h3\\s*class="r">\\s*<a.*?href="\/url\?q=(.*?)&amp;sa=.*?".*?>.*?<\/a>\\s*<\/h3>/is', $request->getBody(), $result, PREG_PATTERN_ORDER))
                {
                    if (is_array($result[1]) && count($result[1]) > 0)
                    {
                        shuffle($result[1]);
                        $urls = array_slice($result[1], 0, $this->getOption('crawl_site_count'));

                        //decode urls
                        foreach ($urls as $k => $v)
                        {
                            $urls[$k] = rawurldecode($v);
                        }

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

                if (preg_match_all('/<span class="?st"?>(.*?)<\\/div>/is', $request->getBody(), $result, PREG_PATTERN_ORDER))
                {
                    if (is_array($result[1]))
                    {
                        $content = '';
                        foreach ($result[1] as $text)
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
