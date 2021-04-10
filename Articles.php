<?php
/**
 * Article directory scraper
 *
 * This file cannot be deleted!
 *
 * @author SSES Mike
 */
class Content_Adapter_Articles extends Content_Adapter_Abstract
{
    const EXTRACTION_TYPE_SENTENCES        = 'rand_sentences';
    const EXTRACTION_TYPE_PARAGRAPHS       = 'rand_paragraphs';

    /**
     * Determines the regex to use for
     * either sentences or paragraphs
     *
     * @var array
     */
    protected $_extractionRegex = array(
        self::EXTRACTION_TYPE_SENTENCES  => '/.*?(\\.|\\!|\\?)/si',
        self::EXTRACTION_TYPE_PARAGRAPHS => '/(.*)/'
    );

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
        $artDirectory = realpath(dirname(__FILE__) . '/../../../_articles/' . $this->getOption('article_directory'));

        if (!is_dir($artDirectory))
        {
            $this->_setContentStatus(self::CONTENT_STATUS_FAILED);
            $this->_setHttpStatusCode(404); //Directory not found
            return false;
        }

        //scan the directory and find all text files
        $dir = scandir($artDirectory);

        foreach (scandir($artDirectory) as $file)
        {
            $fileWithDir = $artDirectory . '/' . $file;

            if (in_array($file, array('.', '..')) || is_dir($fileWithDir))
            {
                continue;
            }

            $article = file_get_contents($fileWithDir);

            $this->_setHttpStatusCode(200);

            //if the file is empty, report no results
            if (!$article)
            {
                $this->_setContentStatus(self::CONTENT_STATUS_NO_RESULTS);
                return false;
            }

            //start content string
            $content = '';

            //now extract according to settings
            switch ($this->getOption('article_extraction'))
            {
                case self::EXTRACTION_TYPE_SENTENCES:
                case self::EXTRACTION_TYPE_PARAGRAPHS:

                    preg_match_all($this->_extractionRegex[$this->getOption('article_extraction')], $article, $result, PREG_PATTERN_ORDER);

                    if (!is_array($result[0]))
                    {
                        $this->_setContentStatus(self::CONTENT_STATUS_FAILED)
                             ->_setHttpStatusCode(500);

                        return false;
                    }

                    $parts = $result[0];
                    shuffle($parts);

                    //choose the slice point based on the extraction percentage
                    $slicePoint = round(count($parts) * $this->getOption('extraction_percentage'));

                    //slice
                    $parts = array_slice($parts, 0, $slicePoint);

                    //set the return content
                    $content = implode(' ', $parts);

                    break;

                case null:
                case '':
                default:

                    $content = $article;

                    break;
            }

            $this->_setContentStatus(self::CONTENT_STATUS_PASSED);

            return $content;
        }
    }
}
