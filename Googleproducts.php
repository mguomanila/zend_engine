<?php
/**
 * Content scraper for google.com/shopping
 *
 * @author SSES Mike
 * @label  Google Product Search
 */
class Content_Adapter_Googleproducts extends Content_Adapter_Abstract
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
            ->setHeaders('Referer', 'http://www.google.com/advanced_product_search')
            ->setUri('http://www.google.com/search')
            ->setParameterGet(array(
                'as_epq'  => '',
                'as_eq'   => '',
                'as_oq'   => '',
                'as_q'    => $keyword,
                'gbv'     => 1,
                'safe'    => 'off',
                'scoring' => 'r',
                'tbm'     => 'shop',
                'tbs'     => ''
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

        //find all snippets
        if (preg_match_all('/<div class="?pslimain"?>(.*?)<\/div>/i', $request->getBody(), $result, PREG_PATTERN_ORDER))
        {
            if (is_array($result[0]))
            {
                $content = '';
                foreach ($result[0] as $text)
                {
                    //remove h3
                    $text = preg_replace('/<h3.*?<\/h3>/i', '', $text);
                    $text = str_replace('...', '', $text);
                    $content .= ' ' . $text . (!preg_match('/[.!?]$/', $text) ? '. ' : '');
                }

                $this->_setContentStatus(self::CONTENT_STATUS_PASSED);

                return $content;
            }
        }

        $this->_setContentStatus(self::CONTENT_STATUS_FAILED);

        return false;
    }
}
