<?php
/**
 * Content scraper for Yahoo
 *
 * @author SSES Mike
 * @label  Yahoo!
 */
class Content_Adapter_Yahoo extends Content_Adapter_DeepLinkingAbstract
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
        $this->getClient()
            ->setHeaders('Referer', '')
            ->setUri('http://search.yahoo.com')
            ->setCookieJar();

        /*
         * FIRST REQUEST: Get search landing page
         */
        if (false == ($request = $this->_request()))
        {
            $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
            return false;
        }

        //set the status code
        $this->_setHttpStatusCode($request->getStatus());
		


        //log the status if there is a 4xx or 5xx error
        //or we couldn't find the search form
        if ($request->isError() || !preg_match('/action="(.*?)"/', $request->getBody(), $regs))
        {
            $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
            return false;
        }

        //get the search URL
        $uri = $regs[1];

        //correct absolute URL bug
        if (!preg_match('/^http/i', $uri))
        {
            $uri = 'http://search.yahoo.com' . $uri;
        }

        /*
         * SECOND REQUEST: Search
         */
        $this->getClient()
            ->setUri($uri)
            ->setConfig(array(
                'maxredirects' => 10
            ))
            ->setParameterGet(array(
                'p'      => $keyword,
                'fr'     => 'sfp',
                'fr2'    => '',
                'iscqry' => '',
                'n'      => 100
            ));

        if (false == ($request = $this->_request()))
        {
            $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
            return false;
        }
		

        //set the status code
        $this->_setHttpStatusCode($request->getStatus());

        //log the status if there is a 4xx or 5xx error
        //or we couldn't find the search form
        if ($request->isError())
        {
            $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
            return false;
        }
		


        //if no matches
        if (preg_match('/We did not find results for:.*? Try the suggestions below or type a new query above/', $request->getBody()))
        {
            $this->_setContentStatus(self::CONTENT_STATUS_NO_RESULTS);
            return false;
        }

        //scrape links or grab snippets
        switch($this->getOption('scrape_source'))
        {
            case content::SCRAPE_SOURCE_CRAWL:

                if (preg_match_all('/<div class="(sm-hd|res)">.*?<a.*?href="(.*?)">/s', $request->getBody(), $result, PREG_PATTERN_ORDER))
                {
                    if (is_array($result[2]) && count($result[2]) > 0)
                    {
                        shuffle($result[2]);
                        $urls = array_slice($result[2], 0, $this->getOption('crawl_site_count'));

                        //get the actual link from each url
                        foreach ($urls as $k => $v)
                        {
                            if (!preg_match('/^http/', $v) && preg_match('/(https?)(.*?)"/', $v, $regs))
                            {
                                $urls[$k] = $regs[1] . urldecode($regs[2]);
                            }
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

                if (preg_match_all('/<div class="?abstr"?>(.*?)<\/div>/s', $request->getBody(), $result, PREG_PATTERN_ORDER))
                {
                    if (is_array($result[1]))
                    {
                        $content = '';
                        foreach ($result[1] as $text)
                        {
                            $text = preg_replace('/\\s*\\.\\.\\.$/', '', $text);
                            $content .= ' ' . $text . (!preg_match('/[.!?]$/', $text) ? '. ' : '');
                        }

                        $this->_setContentStatus(self::CONTENT_STATUS_PASSED);

                        return $content;
                    }
                }

                break;
        }

//debug start
/*
$file = '/tmp/file';
$contents = serialize($content);
file_put_contents($file, $contents);
$contents = unserialize(file_get_contents($file));
 */
 
//debug end

        $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
        return false;
    }
}
