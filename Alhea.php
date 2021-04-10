<?php
/**
 * Content scraper for Alhea.com
 *
 * @author SSES Mike
 * @label  Alhea
 */
class Content_Adapter_Alhea extends Content_Adapter_DeepLinkingAbstract {
	/**
	 * Fetch content
	 *
	 * @see Content_Adapter_Abstract::fetchContent()
	 *
	 * @param string $keyword        	
	 * @return false|string
	 */
	public function fetchContent($keyword) {
		$this->getClient ()->setHeaders ( 'Referer', 'http://www.alhea.com/' )
			->setHeaders('User-Agent','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.111 Safari/537.36')
			->setUri ( 'http://www.alhea.com/search/web' )->setParameterGet ( array (
				'q'    => urlencode($keyword),
				'vi'   => 'sb',
				'sbp'  => 'top',
				'qq'   => urldecode($keyword),
				//'pid'  => '677187239518764749'
		));
		
		if (false == ($request = $this->_request ())) {
			$this->_setContentStatus ( self::CONTENT_STATUS_FAILED );
			return false;
		}
		
		// set the status code
		$this->_setHttpStatusCode ( $request->getStatus () );
		
		// log the status if there is a 4xx or 5xx error
		if ($request->isError ()) {
			$this->_setContentStatus ( self::CONTENT_STATUS_FAILED );
			return false;
		}
		
		//print $request->getBody(); exit;
		
		// if no matches
		if (preg_match ( '/<h1>No results found for/', $request->getBody() )) {
			$this->_setContentStatus ( self::CONTENT_STATUS_NO_RESULTS );
			return false;
		}
		
		// scrape links or grab snippets
		switch ($this->getOption ( 'scrape_source' )) {
			case content::SCRAPE_SOURCE_CRAWL :
				if (preg_match_all ( '/<li\\s*id=".*?">.*?<h3.*?><a.*?href="(.*?)".*?>/is', $request->getBody (), $result, PREG_PATTERN_ORDER )) {
					if (is_array ( $result [1] ) && count ( $result [1] ) > 0) {
						shuffle ( $result [1] );
						$urls = array_slice ( $result [1], 0, $this->getOption ( 'crawl_site_count' ) );
						$this->appendMultiUrl ( $urls );
						
						if (false !== ($content = $this->_fetchMulti ())) {
							$this->_setContentStatus ( self::CONTENT_STATUS_PASSED );
							return $content;
						} else {
							$this->_setContentStatus ( self::CONTENT_STATUS_FAILED )->_setHttpStatusCode ( 500 );
							
							return false;
						}
					}
				}
				
				break;
			
			case content::SCRAPE_SOURCE_SNIPPETS :
			default :
				#if (preg_match_all ( '/<div\\s*class="searchResult\\s*webResult">.*?<div\\s*class="resultDescription">(.*?)<\/div>.*?<\/div>/is', $request->getBody (), $result, PREG_PATTERN_ORDER )) 
				if (preg_match_all ( '/<div[^>]*?>(.*?)<\/div>/is', $request->getBody (), $result, PREG_PATTERN_ORDER ))
				{
					if (is_array ( $result [1] )) {
						$content = '';
						foreach ( $result [1] as $text ) {
							$text = preg_replace ( '/\\s*\\.\\.\\.$/', '', $text );
							$text = preg_replace ( '/\\s*…$/', '', $text );
							$content .= ' ' . $text . (! preg_match ( '/[.!?]$/', $text ) ? '. ' : '');
						}
						
						$this->_setContentStatus ( self::CONTENT_STATUS_PASSED );
						
						return $content;
					}
				}
				
				break;
		}
		
		$this->_setContentStatus ( self::CONTENT_STATUS_FAILED );
		return false;
	}
}
