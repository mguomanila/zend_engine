<?php
/**
 * Content scraper for RSS feeds
 *
 * This file cannot be deleted!
 *
 * Note: The RSS Feed adapter does not report failed or
 * no content statuses
 *
 * @author SSES Mike
 * @label  RSS Feeds
 */
class Content_Adapter_Rss extends Content_Adapter_Abstract
{
    /**
     * Content string
     *
     * @var string
     */
    private $_content = '';

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
        $feeds = $this->getOption('feeds');

        if (is_array($feeds) && count($feeds) > 0)
        {
            foreach ($feeds as $feedUrl)
            {
                $feedUrl = str_ireplace('{%keyword%}', $keyword, $feedUrl);

                $this->getClient()
                    ->resetParameters(true)
                    ->setHeaders('Referer', '')
                    ->setConfig(array(
                        'maxredirects' => 3
                    ))
                    ->setUri($feedUrl);

                if (false == ($request = $this->_request()))
                {
                    $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
                    return false;
                }

                //set the status code
                $this->_setHttpStatusCode($request->getStatus());

                if ($request->isError())
                {
                    $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
                    return false;
                }

                if (preg_match_all('/<description>(.*?)<\/description>/si', $request->getBody(), $reg, PREG_PATTERN_ORDER))
                {
                    if (is_array($reg[1]))
                    {
                        //burn 0
                        unset($reg[1][0]);

                        //iterate
                        foreach ($reg[1] as $text)
                        {
                            $text = html_entity_decode($text);
                            $text = str_replace('<b>...</b>', '', $text);
                            $this->_content .= ' ' . $text . (!preg_match('/[.!?]$/', $text) ? '. ' : '');
                        }

                        $this->_setContentStatus(self::CONTENT_STATUS_PASSED);

                        continue;
                    }
                }

                $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
                return false;
            }

            return $this->_content;
        }
    }
}
