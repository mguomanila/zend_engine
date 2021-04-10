<?php
/**
 * Custom content generator
 *
 * This file cannot be deleted!
 *
 * @author SSES Mike
 * @label  Custom Content
 */
class Content_Adapter_Customcontent extends Content_Adapter_Abstract
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
        $customContent = $this->getOption('custom_content_text');

        $this->_setHttpStatusCode(200);

        if (!$customContent)
        {
            $this->_setContentStatus(self::CONTENT_STATUS_NO_RESULTS);
            return false;
        }

        if (preg_match_all('/({\*)(.*?)(\*})/', $customContent, $result))
        {
            if (is_array($result[0]))
            {
                foreach ($result[0] as $index => $group_string)
                {
                    //replace the first or next pattern match with a replaceable token
                    //$customContent = preg_replace('/(\{\*)(.*?)(\*\})/', '{#'.$index.'#}', $customContent, 1);
                    $customContent = preg_replace('/\{(((?>[^\{\}]+)|(?R))*)\}/x', '{#'.$index.'#}', $customContent, 1);

                    $words = explode('|', $result[2][$index]);

                    //clean and trim all words
                    $finalPhrase = array();
                    foreach ($words as $word)
                    {
                        if (preg_match('/\S/', $word))
                        {
                            $word = preg_replace('/{%keyword%}/i', $keyword, $word);
                            $finalPhrase[] = trim($word);
                        }
                    }

                    $finalPhrase = $finalPhrase[rand(0, count($finalPhrase) - 1)];

                    //now inject it back to where the token was
                    $customContent = str_ireplace('{#' . $index . '#}', $finalPhrase, $customContent);
                }

                $this->_setContentStatus(self::CONTENT_STATUS_PASSED);
            }
        }

        return $customContent;
    }
}
