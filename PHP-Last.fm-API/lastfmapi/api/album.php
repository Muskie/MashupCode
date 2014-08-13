<?php
/**
 * File that stores api calls for album api calls
 * @package apicalls
 */
/**
 * Allows access to the api requests relating to albums
 * @package apicalls
 */
class lastfmApiAlbum extends lastfmApi {
	/**
	 * Stores the config values set in the call
	 * @access public
	 * @var array
	 */
	public $config;
	/**
	 * Stores the auth variables used in all api calls
	 * @access private
	 * @var array
	 */
	private $auth;
	/**
	 * States if the user has full authentication to use api requests that modify data
	 * @access private
	 * @var boolean
	 */
	private $fullAuth;
	
	/**
	 * @param array $auth Passes the authentication variables
	 * @param array $fullAuth A boolean value stating if the user has full authentication or not
	 * @param array $config An array of config variables related to caching and other features
	 */
	function __construct($auth, $fullAuth, $config) {
		$this->auth = $auth;
		$this->fullAuth = $fullAuth;
		$this->config = $config;
	}
	
	/**
	 * Tag an album using a list of user supplied tags. (Requires full auth)
	 * @param array $methodVars An array with the following required values: <i>album</i>, <i>artist</i>, <i>tags</i>
	 * @return boolean
	 */
	public function addTags($methodVars) {
		// Only allow full authed calls
		if ( $this->fullAuth == TRUE ) {
			// Check for required variables
			if ( !empty($methodVars['album']) && !empty($methodVars['artist']) && !empty($methodVars['tags']) ) {
				// If the tags variables is an array build a CS list
				if ( is_array($methodVars['tags']) ) {
					$tags = '';
					foreach ( $methodVars['tags'] as $tag ) {
						$tags .= $tag.',';
					}
					$tags = substr($tags, 0, -1);
				}
				else {
					$tags = $methodVars['tags'];
				}
				$methodVars['tags'] = $tags;
				
				// Set the call variables
				$vars = array(
					'method' => 'album.addtags',
					'api_key' => $this->auth->apiKey,
					'sk' => $this->auth->sessionKey
				);
				$vars = array_merge($vars, $methodVars);
				
				// Generate a call signiture
				$sig = $this->apiSig($this->auth->secret, $vars);
				$vars['api_sig'] = $sig;
				
				// Do the call and check for errors
				if ( $call = $this->apiPostCall($vars) ) {
					// If none return true
					return TRUE;
				}
				else {
					// If there is return false
					return FALSE;
				}
			}
			else {
				// Give a 91 error if incorrect variables are used
				$this->handleError(91, 'You must include album, artist and tags varialbes in the call for this method');
				return FALSE;
			}
		}
		else {
			// Give a 92 error if not fully authed
			$this->handleError(92, 'Method requires full auth. Call auth.getSession using lastfmApiAuth class');
			return FALSE;
		}
	}
	
	/**
	 * Get the metadata for an album on Last.fm using the album name or a musicbrainz id
	 * @param array $methodVars An array with the following required values: <i>album</i> and optional values: <i>artist</i>, <i>mbid</i>
	 * @return array
	 */
	public function getInfo($methodVars) 
	{
		// Set the call variables
		$vars = array(
			'method' => 'album.getinfo',
			'api_key' => $this->auth->apiKey
		);
		$vars = array_merge($vars, $methodVars);
		
		$info = array();
		if ($call = $this->apiGetCall($vars)) 
		{
			$info['name'] = (string) $call->album->name;
			$info['artist'] = (string) $call->album->artist;
			$info['lastfmid'] = (string) $call->album->id;
			$info['mbid'] = (string) $call->album->mbid;
			$info['url'] = (string) $call->album->url;
			$info['releasedate'] = strtotime(trim((string) $call->album->releasedate));
			$info['image']['small'] = (string) $call->album->image;
			$info['image']['medium'] = (string) $call->album->image[1];
			$info['image']['large'] = (string) $call->album->image[2];
			$info['listeners'] = (string) $call->album->listeners;
			$info['playcount'] = (string) $call->album->playcount;
			
			if ( ! empty($call->album->toptags))
			{
				$i = 0;
				foreach ( $call->album->toptags->tag as $tags ) 
				{
					$info['toptags'][$i]['name'] = (string) $tags->name;
					$info['toptags'][$i]['url'] = (string) $tags->url;
					$i++;
				}
			}
			
			// New data apparently
			$info['wiki'] = array(
				'summary'=>(string) $call->album->wiki->summary,
				'content'=>(string) $call->album->wiki->content
			);
	
			if ( ! empty($call->album->tracks))
			{
				for( $n = 0 ; $n < count($call->album->tracks->track); $n++)
				{
					// This is identical to how tags is done...
					$info['tracks'][$n]['name'] = (string) $call->album->tracks->track[$n]->name;
					$info['tracks'][$n]['url'] = (string) $call->album->tracks->track[$n]->url;
					$info['tracks'][$n]['rank'] =(string) $call->album->tracks->track[$n]['rank'][0];
            		$info['tracks'][$n]['duration'] = (string) $call->album->tracks->track[$n]->duration;
				}
			}
			
			return $info;
		}
		else 
		{
			return FALSE;  // I also don't like multiple returns.  If I work on Matt's code much more I'm going to do some major refactoring.
		}
	}
	
	/**
	 * Get the tags applied by an individual user to an album on Last.fm
	 * @param array $methodVars An array with the following required values: <i>album</i>, <i>artist</i>
	 * @return array
	 */
	public function getTags($methodVars) {
		// Only allow full authed calls
		if ( $this->fullAuth == TRUE ) {
			// Check for required variables
			if ( !empty($methodVars['album']) && !empty($methodVars['artist']) ) {
				// Set the variables
				$vars = array(
					'method' => 'album.gettags',
					'api_key' => $this->auth->apiKey,
					'sk' => $this->auth->sessionKey
				);
				$vars = array_merge($vars, $methodVars);
				
				// Generate a call signiture
				$sig = $this->apiSig($this->auth->secret, $vars);
				$vars['api_sig'] = $sig;
				
				$tags = array();
				// Make the call
				if ( $call = $this->apiGetCall($vars) ) {
					if ( count($call->tags->tag) > 0 ) {
						$i = 0;
						foreach ( $call->tags->tag as $tag ) {
							$tags[$i]['name'] = (string) $tag->name;
							$tags[$i]['url'] = (string) $tag->url;
							$i++;
						}
						
						return $tags;
					}
					else {
						$this->handleError(90, 'User has no tags for this artist');
						return FALSE;
					}
				}
				else {
					return FALSE;
				}
			}
			else {
				// Give a 91 error if incorrect variables are used
				$this->handleError(91, 'You must include album and artist varialbes in the call for this method');
				return FALSE;
			}
		}
		else {
			// Give a 92 error if not fully authed
			$this->handleError(92, 'Method requires full auth. Call auth.getSession using lastfmApiAuth class');
			return FALSE;
		}
	}
	
	
	/**
	 * This returns links where you can buy a copy of the album online.  You have the option of using the artist name AND the album name, or the 
	 * MusicBrainz ID.  We will return an array as that is the pattern established.  Last.fm's API is capeable of returning XML.
	 *
	 * Even though it says it is optional you appear to need to submit a country!
	 * 
	 * @return array
	 */
	public function getBuyLinks($methodVars)
	{
		if( (( ! empty($methodVars['artist'])) && ( ! empty($methodVars['album']))) || ( ! empty($methodVars['mbid'])) )
		{
			// Not sure if Album requires country, the documentation says it is optional...
			if ( ! empty($methodVars['country']))
			{
				$ourCountry = $methodVars['country']; // do I even need to do this?
			}
			else
			{
				$ourCountry = "United Kingdom";  // This default should be set somewhere!
			}
			$vars = array(
						'method' => 'album.getbuylinks',
						'country' => $ourCountry,
						'api_key' => $this->auth->apiKey
						);
			$vars = array_merge($vars, $methodVars);
			if ( $response = $this->apiGetCall($vars) ) 
			{
				// Now we have a SimpleXML object, which I'm fine with, perhaps in the future we set a flag to return this rather than an array
				if ( count($response->affiliations) > 0 ) 
				{
					// There are two types of affliations physicals and downloads, we might as well return them all
					if( ! empty($methodVars['mbid']))
					{
						$buyLinks['mbid'] = $methodVars['mbid'];
					}
					else
					{
						$buyLinks['artist'] = $methodVars['artist'];
						$buyLinks['album'] = $methodVars['album'];
					}
					// I'm a method should have one return statement kinda guy, but...
					$i = 0;
					foreach ( $response->affiliations->physicals->affiliation as $physical ) 
					{
						$buyLinks['physicals'][$i]['supplierName'] = (string) $physical->supplierName;
						$buyLinks['physicals'][$i]['buyLink'] = (string) $physical->buyLink;
						$buyLinks['physicals'][$i]['supplierIcon'] = (string) $physical->supplierIcon;
						$buyLinks['physicals'][$i]['isSearch'] = (boolean) $physical->isSearch;
						$i++;
					}
					$n = 0;
					foreach ( $response->affiliations->downloads->affiliation as $download ) 
					{
						$buyLinks['downloads'][$n]['supplierName'] = (string) $download->supplierName;
						$buyLinks['downloads'][$n]['buyLink'] = (string) $download->buyLink;
						$buyLinks['downloads'][$n]['supplierIcon'] = (string) $download->supplierIcon;
						$buyLinks['downloads'][$n]['isSearch'] = (boolean) $download->isSearch;
						$n++;
					}
					return $buyLinks;
				}
				else 
				{
					$this->handleError(90, 'This album has no buy links');
					return FALSE;
				}
			}
			else 
			{
				return FALSE;
			}
		}
		else
		{
			// Give a 91 error if incorrect variables are used
			$this->handleError(91, 'You must include either artist and album, or MusicBrainz ID.');
			return FALSE;
		}
	}
	
	/**
	 * Remove a user's tag from an album. (Requires full auth)
	 * @param array $methodVars An array with the following required values: <i>album</i>, <i>artist</i>, <i>tag</i>
	 * @return boolean
	 */
	public function removeTag($methodVars) {
		// Only allow full authed calls
		if ( $this->fullAuth == TRUE ) {
			// Check for required variables
			if ( !empty($methodVars['album']) && !empty($methodVars['artist']) && !empty($methodVars['tag']) ) {
				// Set the variables
				$vars = array(
					'method' => 'album.removetag',
					'api_key' => $this->auth->apiKey,
					'sk' => $this->auth->sessionKey
				);
				$vars = array_merge($vars, $methodVars);
				
				// Generate a call signature
				$sig = $this->apiSig($this->auth->secret, $vars);
				$vars['api_sig'] = $sig;
				
				// Do the call
				if ( $call = $this->apiPostCall($vars) ) {
					return TRUE;
				}
				else {
					return FALSE;
				}
			}
			else {
				// Give a 91 error if incorrect variables are used
				$this->handleError(91, 'You must include album, artist and tag varialbes in the call for this method');
				return FALSE;
			}
		}
		else {
			// Give a 92 error if not fully authed
			$this->handleError(92, 'Method requires full auth. Call auth.getSession using lastfmApiAuth class');
			return FALSE;
		}
	}
	
	/**
	 * Search for an album by name. Returns album matches sorted by relevance
	 * @param array $methodVars An array with the following required values: <i>album</i>
	 * @return array
	 */
	public function search($methodVars) {
		// Check for required variables
		if ( !empty($methodVars['album']) ) {
			$vars = array(
				'method' => 'album.search',
				'api_key' => $this->auth->apiKey
			);
			$vars = array_merge($vars, $methodVars);
			
			$searchresults = array();
			if ( $call = $this->apiGetCall($vars) ) {
				$opensearch = $call->results->children('http://a9.com/-/spec/opensearch/1.1/');
				if ( $opensearch->totalResults > 0 ) {
					$searchresults['totalResults'] = (string) $opensearch->totalResults;
					$searchresults['startIndex'] = (string) $opensearch->startIndex;
					$searchresults['itemsPerPage'] = (string) $opensearch->itemsPerPage;
					$i = 0;
					foreach ( $call->results->albummatches->album as $album ) {
						$searchresults['results'][$i]['name'] = (string) $album->name;
						$searchresults['results'][$i]['artist'] = (string) $album->artist;
						$searchresults['results'][$i]['id'] = (string) $album->id;
						$searchresults['results'][$i]['url'] = (string) $album->url;
						$searchresults['results'][$i]['streamable'] = (string) $album->streamable;
						$searchresults['results'][$i]['image']['small'] = (string) $album->image[0];
						$searchresults['results'][$i]['image']['medium'] = (string) $album->image[1];
						$searchresults['results'][$i]['image']['large'] = (string) $album->image[2];
						$i++;
					}
					
					return $searchresults;
				}
				else {
					// No tagsare found
					$this->handleError(90, 'No results');
					return FALSE;
				}
			}
			else {
				return FALSE;
			}
		}
		else {
			// Give a 91 error if incorrect variables are used
			$this->handleError(91, 'You must include album varialbe in the call for this method');
			return FALSE;
		}
	}
}

?>