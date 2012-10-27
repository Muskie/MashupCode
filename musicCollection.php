<?php

	/**
	 * This class is for creating music mashups, it is a subclass of mCollection.php and has a subclass called albumCollection.php which is 
	 * designed for manipulating albums, you know those things they used to release music on, groups of 10-12 songs on some physical format.  
	 * Some of us still like albums.
	 *
	 * @author Muskie McKay <andrew@muschamp.ca>
     * @link http://www.muschamp.ca
     * @version 1.3.1
	 * @copyright Muskie McKay
	 * @license MIT
	 */
	
	/**
		The MIT License
	
		Copyright (c) 2010 Andrew "Muskie" McKay
	
		Permission is hereby granted, free of charge, to any person obtaining a copy
		of this software and associated documentation files (the "Software"), to deal
		in the Software without restriction, including without limitation the rights
		to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
		copies of the Software, and to permit persons to whom the Software is
		furnished to do so, subject to the following conditions:
	
		The above copyright notice and this permission notice shall be included in
		all copies or substantial portions of the Software.
	
		THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
		IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
		FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
		AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
		LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
		OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
		THE SOFTWARE.
	 */
	
	require_once('mCollection.php');
	require_once('lastfmapi/lastfmapi.php');  // Last.fm API by Matt Oakes
	require_once('phpbrainz/phpBrainz.class.php');  // Adds support for MusicBrainz.org API which is occaisionally useful

	class musicCollection extends mCollection
	{
		/**
		 * These are the keys and username needed for the LastFM API
		 * @access public static
		 * @var array
		 */
		 static $lastFMVariables = array(
										'apiKey' => myInfo::MY_LAST_FM_PUBLIC_KEY,
										'secret' => myInfo::MY_LAST_FM_PRIVATE_KEY,
										'username' => myInfo::MY_LAST_FM_USER_NAME
										);
										
		/**
		 * This is an authorized response from the LastFM API letting us make further requests to the API
		 * @access protected
		 * @var lastfmApiAuth Object
		 */
		 protected $lastFMAuthority;
		 
		 
		 //Not sure why I need this, but you do need to say your application is enabled for the LastFM API to work properly 
		 static $lastFMConfig = array(
									'enabled' => true,
									'path' => './lastfmapi/',
									'cache_length' => 1800
									);
			
			
		/**
		 * This is an instance of tha LastFM API we need it to fetch data eventually
		 * @access protected
		 * @var lastfmApi Object
		 */	
		protected $lastFMAPI;
	
	
		/**
		 * This is an instance a phpBrainz object it is necessary to use phpBrainz and thus MusicBrainz.org easily
		 * @access private
		 * @var Facebook object
		 */
		 protected $theBrainz;
	
		
		
	   /**
		* Initialize a Music Collection
		*
		* I'm not sure if this is required or if I will do anything, the original constructor was always designed to handle various ways
		* of getting the data into the class, from a CSV file to passing it in as an array to eventually database access...
		*
		* @param input can vary and what type determines how the class is initialized/created see parent method.
		*/
		public function __construct($input)
		{
			parent::__construct($input);
		}
	
	
		
	   /**
		* Initialize a Music Collection
		*
		* I haven't overided the constructer, but by overiding this method, I can add support for other APIs
		*/
		protected function initializeAPIs()
		{
			parent::initializeAPIs();
			
			$this->lastFMAPI = new lastfmApi();
			$this->lastFMAuthority = new lastfmApiAuth('setsession', musicCollection::$lastFMVariables);
			$this->theBrainz = new phpBrainz();
		
		}
		
			
		 // This method is the one place so far I make artist data requests from Last.fm 
		 // The advantage of doing it in only one place, besides less code is I knew exactly where to put in the caching...
		 // It is public as I use it in my favouriteSongs mashup and that is a bit of a hack
		 public function getArtistInfoFromLastFM($artistName)
		 {
			$strippedArtistName = $artistName;
			$strippedArtistName = preg_replace("/[^a-zA-Z0-9]/", "", $strippedArtistName);
			
			if(strlen($strippedArtistName) > 0)
			{
				$myCache = new Caching("./MashupCache/LastFM/", $strippedArtistName);
				$artistClass = $this->lastFMAPI->getPackage($this->lastFMAuthority, 'artist', musicCollection::$lastFMConfig);
				$methodVars = array(
									'artist' => $artistName
									);
				
				if ($myCache->needToRenewData())
				{
					try
					{
						// result is an array not a simpleXML Object 
						$result = $artistClass->getInfo($methodVars);
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();
					}				
					$reformatedResult = serialize($result);
					
					$myCache->saveSerializedDataToFile($reformatedResult);
				}
				else
				{
					// It doesn't need to be renewed so use local copy of array
					$result =  $myCache->getUnserializedData();
				}
			}
			
			return $result;
		 }
			 
			 
			 
		 // Although Matt's code is a bit unrefined and frequently Last.fm doesn't have all the information I need, it's album.getInfo
		 // is a good second choice to find track information or even image information.  I still prefer to check Amazon.com first as
		 // They have a lot of higher res images, hopefully previewable tracks, and of course the potential of making money from referrals.
		 protected function getAlbumInfoFromLastFM($artistName, $albumTitle)
		 {
			$fileName = $artistName . '-' . $albumTitle;
			$fileName = preg_replace("/[^a-zA-Z0-9]/", "", $fileName);  // I think this removes the slash, I just put in, but maybe not RegEx is not my specialty
			
			if(strlen($fileName) > 0)
			{
				$myCache = new Caching("./MashupCache/LastFM/", $fileName);
				$albumClass = $this->lastFMAPI->getPackage($this->lastFMAuthority, 'album', musicCollection::$lastFMConfig);
			
				// Setup the variables
				$methodVars = array(
								'artist' => $artistName,
								'album' => $albumTitle
								);
				if ($myCache->needToRenewData())
				{
					try
					{
						$result = $albumClass->getInfo($methodVars);  // This method is problematic, Matt/My code needs more work...
					}
					catch (Exception $e)
					{
						// This getInfo method throws a lot more errors than artist does...
						echo $e->getMessage();
					}
					$reformatedResult = serialize($result);
					
					$myCache->saveSerializedDataToFile($reformatedResult);
				}
				else
				{
					// It doesn't need to be renewed so use local copy of array
					$result =  $myCache->getUnserializedData();  // This is where things go south...
				}
			}  
			
			return $result;  
		 }
			 
			 
			 
		 // This method is an improvement on the out of the box Last.fm track.search as I then look through the results for a track that
		 // matches the current album's artist.  This is cached as part of the LovedTracks features I've added.
		 private function getTrackBuyingInfoFromLastFM($artistName, $songTitle)
		 {
			$fileName = $artistName . '-' . $songTitle;
			$fileName = preg_replace("/[^a-zA-Z0-9]/", "", $fileName);  
			if(strlen($fileName) > 0)
			{
				$myCache = new Caching("./MashupCache/LovedTracks/", $fileName);
				$trackClass = $this->lastFMAPI->getPackage($this->lastFMAuthority, 'track', musicCollection::$lastFMConfig);
		
				// Setup the variables
				$methodVars = array(
					'artist' => $artistName,
					'track' => $songTitle
				);
		
				if ($myCache->needToRenewData())
				{
					try
					{
						$result = $trackClass->getBuylinks($methodVars); 
					}
					catch (Exception $e)
					{
						echo $e->getMessage();
					}
					$reformatedResult = serialize($result);
					
					$myCache->saveSerializedDataToFile($reformatedResult);
				}
				else
				{
					// It doesn't need to be renewed so use local copy of array
					$result =  $myCache->getUnserializedData();  
				}
			}  
			
			return $result;  
		 }
			 
			 
	    /**
		 * Although I don't make many calls to iTunes as I use Amazon for the primary image source and Last.fm as the primary source for
		 * artist bios.  Apple returns a lot of information when you make a search call, so I might as well cache it.  Caching will be
		 * done the same way as for Last.fm using serialize and unserialize.  Apple iTunes Store returns JSON formatted strings which can be
		 * turned into objects easily enough
		 *
		 * @param string
		 * @return JSON object decoded
		 */
		 protected function getArtistResultsFromITunes($artistName)
		 {
			$iTunesInfo = null;
		 
			$strippedArtistName = $artistName;
			$strippedArtistName = preg_replace("/[^a-zA-Z0-9]/", "", $strippedArtistName);
			
			if(is_string($artistName) && strlen($strippedArtistName) > 0)
			{
				$myCache = new Caching("./MashupCache/iTunes/", $strippedArtistName);
				
				if ($myCache->needToRenewData())
				{
					try
					{
						$formattedArtistString = str_replace(' ', '+', $artistName);
						$iTunesSearchString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedArtistString . '&entity=musicArtist';
						$searchResult = fetchThisURL($iTunesSearchString);
						$iTunesInfo = json_decode($searchResult);
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();
					}				
					$serializedObject = serialize($iTunesInfo);
					
					$myCache->saveSerializedDataToFile($serializedObject);
				}
				else
				{
					// It doesn't need to be renewed so use local copy of array
					$iTunesInfo =  $myCache->getUnserializedData();
				}
			}
			else
			{
				throw new Exception('Incorrect data type passed to getArtistResultsFromITunes()');
			}
			
			return $iTunesInfo;
		 }
			 
			 
		// This is the worker function for a method that I wrote to get the Facebook Badge, but then retired it in preference to the 'like' button
		 // I never got full Facebook integration working to my satisfaction, even with facebookLib, but I can search the Graph API using curl.
		 // This is returning a JSON Object that I decoded from Facebook's Open Graph
		 protected function getFacebookPageForArtist($artistName)
		 {
			$facebookPage = null;
			
			if ($artistName != null)
			{
			
				$this->facebook->setDecodeJson(true);
				$possibleArtists = $this->facebook->search('page', $artistName);  
				
				if($possibleArtists->data != null )  // This is giving me grief for various artists...
				{
					$firstID = $possibleArtists->data[0]->id;
					
					// This is a bit of fakery, an end around if you will
					$graphURL = Facebook::$DOMAIN_MAP['graph'] . $firstID;
		
					$resultingString = fetchThisURL($graphURL);
					
					if(is_string($resultingString))
					{
						$facebookPage = json_decode($resultingString);
					}
					else
					{
						// We're not getting a string in JSON format WTF!
						// Probably should throw exception here, haven't seen this error, but it must have happened once...
						throw new Exception("Not getting a string in JSON format, got " . $resultingString);
					}
				} 
			}
			
			return $facebookPage;
		 }
				  
		
		
		// To improve performance and to make the code more general, I created this method to fetch and cache Loved Tracks for a Last.fm user
		 static function getLovedTracksFor($lastFMUserName)
		 {
			$strippedUserName = $lastFMUserName;
			$strippedUserName = preg_replace("/[^a-zA-Z0-9]/", "", $strippedUserName);
			
			if(strlen($strippedUserName) > 0)
			{
				$myCache = new Caching("./MashupCache/LovedTracks/", $strippedUserName);
				
				$tempLastFMAPI = new lastfmApi();
				$tempLastFMAuthority = new lastfmApiAuth('setsession', musicCollection::$lastFMVariables);
				$lastFMUser = $tempLastFMAPI->getPackage($tempLastFMAuthority, 'user', musicCollection::$lastFMConfig);
			
				// Setup the variables
				$methodVars = array(
					'user' => $lastFMUserName
				);
				
				if ($myCache->needToRenewData())
				{
					try
					{
						// result is an array not a simpleXML Object 
						$result = $lastFMUser->getLovedTracks($methodVars);
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();
					}				
					$reformatedResult = serialize($result);
					
					$myCache->saveSerializedDataToFile($reformatedResult);
				}
				else
				{
					// It doesn't need to be renewed so use local copy of array
					$result =  $myCache->getUnserializedData();
				}
			}
			
			
			return $result;
		 }
	
	
	   
	   
	   /**
		* This returns a photo of the current album's artist in large and medium format.  It returns the data in an array and gets the information from
		* Last.fm  Last.fm's large image isn't so large, they have bigger ones but they don't appear to want API users to have them.
		*
		* @return array
		*/
		public function getCurrentArtistPhotoFromLastFM()
		{
			$photoInfo = array();

			$artistInfo = $this->getArtistInfoFromLastFM($this->currentArtist());
			
			if ( ! empty($artistInfo))
			{
				$photoInfo['mediumURL'] = $artistInfo['image']['medium'];
				$photoInfo['largeURL'] = $artistInfo['image']['large'];
			}
			
			return $photoInfo;
		}
		
		
		
		/**
		 * Last.fm doesn't give you very big photos, so we'll try our luck with Flickr.  Flickr has some very high resolution photos, they also have
		 * a huge API, luckily it looks like phpFlickr is well supported and documented.  Plus after all these other APIs I've started to recognize
		 * certain design patterns and I'm up on the REST, JSON, lingo.
		 *
		 * @return array
		 */
		public function getCurrentArtistPhotosFromFlickr()
		{
			// Returns an associated array.  I fetch extra image URLs: t = tiny, s = small, m = medium, o = oversized or something...
			
			// I need to build an associative array of arguments as this method/API call has so damn many:
			// http://www.flickr.com/services/api/flickr.photos.search.html
			// I probably don't need to pass in my API-key as I already did when I created the instance of phpFlickr.
			$args = array(
							'text' => $this->currentArtist(),
							'sort' => 'relevance',
							'content_type' => 1,
							'per_page' => 5,
							'extras' => 'url_t, url_s, url_m, url_o'
						);
			
			$flickrResults = $this->flickrAPI->photos_search($args);

			return $flickrResults;
		}
		
		
		
		 // Apple iTunes Music Store is one place I can get preview of tracks reliably. 
		 // After getting this method to work like I wanted, including caching the information returned by the iTunes Music Store, I decided I might try
		 // and use this information to supplement my cover image fetching...  I still won't make any money off of iTunes but the more album covers
		 // I find the better for my original album cover gallery.
		 // Apple iTunes store returns JSON formatted strings which can be turned into objects easily enough
		 protected function getAlbumAndTracksFromITunes($iTunesID, $albumName)
		 {
			$iTunesInfo = null;
		
			$properFileName = $iTunesID . $albumName;
			$properFileName = preg_replace("/[^a-zA-Z0-9]/", "", $properFileName);
			
			if(strlen($properFileName) > 0)
			{
				$myCache = new Caching("./MashupCache/iTunes/", $properFileName);
				
				if ($myCache->needToRenewData())
				{
					try
					{
						// First get all the albums by an artist using a lookup request
						//http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsLookup?id=909253&entity=album
						$iTunesLookUpString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsLookup?id=' . $iTunesID . '&entity=album';
						$lookUpResult = fetchThisURL($iTunesLookUpString);
						// Now we have the look up results but we need to find the $iTunesID for the correct album then get the tracks that go with it 
						// We need collectionName to equal $albumName
						$lookUp = json_decode($lookUpResult);
						if ( ! empty($lookUp))
						{
							$resultCount = $lookUp->resultCount;  // This isn't always correct, maybe have to check more carefully what was returned...
						
							$i = 1;  // First result is the artist not an album, I already had the artist info.
							$collectionID = null;
							
							while(($i < $resultCount) && ($collectionID == null))
							{
								// Look for album in iTunes...
								$collectionName = (string) $lookUp->results[$i]->collectionName;
								if( strcasecmp($collectionName, $albumName) == 0)  
								{
									// We have our match
									$collectionID = $lookUp->results[$i]->collectionId;
								}
								
								// remember to increment!
								$i++;
							}
							
							if( $collectionID != null)
							{
								// If collectionID isn't null... lookup album again in iTunes with tracks and that is what we really really want!
								// cache and return...
								//http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsLookup?upc=075678317729&entity=song
								$newLookupString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsLookup?id=' . $collectionID . '&entity=song';
								$newLookUpResult = fetchThisURL($newLookupString);
								$iTunesInfo = json_decode($newLookUpResult); 
								
								$serializedObject = serialize($iTunesInfo);
								$myCache->saveSerializedDataToFile($serializedObject);
							}
						}		
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();  
					}				
				}
				else
				{
					// It doesn't need to be renewed so use local copy
					$iTunesInfo =  $myCache->getUnserializedData();
				}
			}
			
			return $iTunesInfo;
		 }
		
		
		
	   /**
		* This returns an array of similar artists, the array consists of artist name and a URL, just like tags method.
		*
		* @return array
		*/
		public function similarArtistsToCurrentArtist()
		{
			$similarArtists = array();
			
			$artistInfo = $this->getArtistInfoFromLastFM($this->currentArtist());
			
			if ( ! empty($artistInfo['similar']))
			{
				for( $i = 0; $i < count($artistInfo['similar']) ; $i++)
				{
					// There are always five similar artists...
					$similarArtists[$i]['name'] = $artistInfo['similar'][$i]['name'];
					$similarArtists[$i]['url'] = $artistInfo['similar'][$i]['url'];
				}
			}
			
			return $similarArtists;
		}
		
		
		
		/**
		 * This method searchs the iTunes store for an audio sample we can link directly to.  If I can't find that I search Amazon for 
		 * a page I can link to that will have a preview on it.  This method is bigger than I would like, but I'm torn as to where to 
		 * add modularity...
		 *
		 * @param string song title 
		 * @param string album title 
		 * @param string artist name 
		 * @return string 
		 */
		 protected function findAudioSample($songTitle, $albumTitle, $artistName)
		 {
		 	$sampleURL = '';
		 	
		 	// Check input
		 	if (( ! empty($songTitle)) && ( ! empty($albumTitle)) && ( ! empty($artistName)))
		 	{

				$sampleURL = $this->findTrackInITunesFor($songTitle, $albumTitle, $artistName);
				
				// We need to search Amazon next...
				if ($sampleURL == null)
				{
				  // This method should by made modular 
				  // need to use getMP3ForSongByArtist in this case
				  	$amazonSearchResults = $this->amazonAPI->getMP3ForSongByArtist($songTitle, $artistName);
					
					// The above returns a lot more tracks or at least ASINs than I want.
			
					if( count($amazonSearchResults->Items->Item) > 0)
					{
						// We have multiple tracks or at least ASINs most likely, go with first one.
						$furtherAmazonXML = $this->amazonAPI->getItemByAsin($amazonSearchResults->Items->Item[0]->ASIN);

						$sampleURL = $furtherAmazonXML->Items->Item->DetailPageURL;
					}
				}

		 	}
		 	else if (( ! empty($albumTitle)) && ( ! empty($artistName)))
		 	{		 		
				$iTunesArtistInfo = $this->getArtistResultsFromITunes($artistName);
				
				if ( count($iTunesArtistInfo->results) > 0)  // insufficient $iTunesArtistInfo != null
				{
					$iTunesAlbumInfo = $this->getAlbumAndTracksFromITunes($iTunesArtistInfo->results[0]->artistId, $albumTitle);
					
					if($iTunesAlbumInfo != null)
					{
						$sampleURL = $iTunesAlbumInfo->results[1]->previewUrl; // Track 1 is good enough, I can get them ranked by popularity perhaps...
					}
				}
				
				if ($sampleURL == null)
				{				
					$amazonSearchResults = $this->amazonAPI->getMP3sForAlbumByArtist($albumTitle, $artistName);
					
					// The above returns a lot more tracks or at least ASINs than I want.
			
					if( count($amazonSearchResults->Items->Item) > 0)
					{
						// We have multiple tracks or at least ASINs most likely, go with first one.
						$furtherAmazonXML = $this->amazonAPI->getItemByAsin($amazonSearchResults->Items->Item[0]->ASIN);
						// May need conditional around this but so far it works with my sample data set
						$sampleURL = $furtherAmazonXML->Items->Item->DetailPageURL;
					}
				}
		 		
		 	}
		 	else if ( ! empty($artistName))
		 	{
		 		$sampleURL = $this->findAudioSampleForArtist($artistName);
		 	}
		 	else if ( ! empty($songTitle))
		 	{
		 		$sampleURL = $this->findAudioSampleFor($songTitle);
		 	}
		 	else
		 	{
		 		// No useful data passed in or just an album title
		 		$sampleURL = $this->findAudioSampleFor($albumTitle);
		 	}
		 	
		 	
		 	return $sampleURL;
		 }
		 
		 
		 
		/**
		 * This method searches just iTunes for now, for the most popular track by the artist.  It then returns the direct link to an audio preview 
		 * eventually I may search other sources, but iTunes is the only one which I can get direct links to an audio sample.
		 *
		 * @param string artist name
		 * @return string URL or empty string
		 */
		 public function findAudioSampleForArtist($name)
		 {
		 	$previewURL = '';
		 	
		 	// Tempted for now to just search iTunes.
		 	// We probably have the artist search cached so get that and look for most popular track 
		 	$iTunesArtistInfo = $this->getArtistResultsFromITunes($name);
		 	
		 	// We have a long way to go, assume first artist is the artist we want.
		 	if( ! empty($iTunesArtistInfo->results))
		 	{
		 		$iTunesArtistID = $iTunesArtistInfo->results[0]->artistId;
		 		// Now we want to perform a look up of songs, well just the top song really.
		 		// Something like this:
		 		// http://itunes.apple.com/lookup?id=909253&entity=song&limit=1
		 		$prefix = 'http://itunes.apple.com/lookup?id=';
		 		$limitingText = '&entity=song&limit=1';
		 		$lookUpURL = $prefix . $iTunesArtistID . $limitingText;
		 		$lookUpResult = fetchThisURL($lookUpURL);
				$lookUp = json_decode($lookUpResult);
				if ( ! empty($lookUp))
				{		 			
		 			// Result number one is the artist info again, which I already had.
		 			// Result number two is the most popular track by an artist so return that previewUrl 
		 			if ( ! empty($lookUp->results))
		 			{
		 				$previewURL = $lookUp->results[1]->previewUrl;
		 			}
				}
		 	}
		 	
		 	return $previewURL;
		 }
		 
		 
		 
		/**
		 * This method searches just iTunes for now, for the most popular track for the title, It is probably going to assume it is a song title but it 
		 * could be an album title or any string.  The method then returns the direct link to an audio preview 
		 * eventually I may search other sources, but iTunes is the only one which I can get direct links to an audio sample.
		 *
		 * @param string artist name
		 * @return string URL or empty string
		 */
		 public function findAudioSampleFor($title)
		 {
		 	$previewURL = '';
		 	
		    // This one I have less confidence it will work perfectly.  Sure iTunes or Amazon would return something, but it is a useful result?
		    // Want a querry something like:
		    // http://itunes.apple.com/search?term=jack+johnson&entity=song&limit=1
		    $prefix = 'http://itunes.apple.com/search?term=';
		    $limitingText = '&entity=song&limit=1';
		    // I need to to make sure the title is encoded correctly 
		    $formattedTitle = str_replace(' ', '+', $title);
		    $lookUpURL = $prefix . $formattedTitle . $limitingText;
		    $lookUpResult = fetchThisURL($lookUpURL);
			$lookUp = json_decode($lookUpResult);
			if ( ! empty($lookUp))
			{
		   		// if we have results there is only one and that one what we return the previewUrl for 
		   		if ( count($lookUp->results) > 0)
		   		{
		   			$previewURL = $lookUp->results[0]->previewUrl;
		   		}
		   	}
		   
		    return $previewURL;
		 }
		
	   
	   
	   /**
		* This method searches the iTunes store primarily for an audio sample of the current album's favourite track which is stored
		* in the albums array.
		*
		* This method expects addtional information in the array...  Not sure what happens when you don't provide that information, need to test more.
		*
		* @return string
		*/
		public function audioSampleForCurrentAlbumsFavouriteSong()
		{
			$currentAlbumInfo = $this->currentMemberAsArray();
			$artistName = $currentAlbumInfo[0];
			$albumTitle = $currentAlbumInfo[1];
			$favouriteTrackTitle = $currentAlbumInfo['songTitle'];
			
			return $this->findAudioSample($favouriteTrackTitle, $albumTitle, $artistName);
		}
		
		
		
	   /**
	    * Finds an audio sample for artist and album passed in.  I can only find a direct link in iTunes but I can find an indirect link to
	    * an audio preview in Amazon or elsewhere.
	    *
	    * @param string $collectionTitle title of the collection
	    * @param string $artistName the name of the Artist
	    *
	    * @return string
	    */
	   public function audioSampleFor($collectionTitle, $artistName)
	   {
			return $this->findAudioSample(null, $collectionTitle, $artistName);
	   }
		
		
		
	   /**
		* This method searches YouTube and potentially eventually other video services for a video clip featurning the passed in artist
		* I now return the HTML necessary for an embeddable player
		*
		* @return array
		*/
	   public function videoClipForCurrentArtist()
	   {
			return $this->embeddableVideoClipFor($this->currentArtist());
	   }
	   
	   
	   
	   /**
		* This method searches YouTube and potentially eventually other video services for a video clip featurning the currentArtist and 
		* the passed in keywords corresponding to a song title.
		*
		* @param string
		*
		* @return array
		*/
	   public function videoClipForCurrentArtistEntitled($songTitle)
	   {
			$fullquery = '"' . $songTitle . '" by ' . $this->currentArtist();
			
			return $this->embeddableVideoClipFor($fullquery);
	   }
	   
	   
	   
		/**
		 * Returns an array of the tags associated with the current member of the collection.
		 *
		 * @return array of tags
		 */
		 public function lastFMAlbumTags()
		 {
			$tags = array();
			
			$currentMember = $this->currentMemberAsArray();
			
			$albumInfo = $this->getAlbumInfoFromLastFM($currentMember[0], $currentMember[1]);
			
			if ( ! empty($albumInfo))
			{
				if( ! empty($albumInfo['toptags']))
				{
					$tags = $albumInfo['toptags'];
				}
			}    	
			
			return $tags;
		 }
	   
	   
	   
		/**
		 * Returns the current collection artist, ie the first item in currentMemberAsArray
		 *
		 * @return string
		 */
		 public function currentArtist()
		 {
			$anAlbum = $this->currentMemberAsArray();
			$artistName = $anAlbum[0];
			if ($artistName == null)
			{
				$artistName = "unknown";  // This is better than returning null or nothing...
			}
			
			return $artistName;
		 }
		
		
	   
	   /**
		* This method I created as part of my favourite or loved tracks mashup.  The code is for creating an instance of this
		* class populated by the albums that correspond to a user's loved tracks in last.fm.  By putting the code here as a static method
		* future users of this class can populate their music collection with this info too.
		*
		* In the array I also put the track name and the url on Last.fm, I used non-numeric indices for this extra data.
		*
		* @return musicCollection
		*/
		static public function collectionFromLovedTracks()
		{
			$lovedTracksInfo = array();
		
			$myLovedTracks = musicCollection::getLovedTracksFor(myInfo::MY_LAST_FM_USER_NAME);
			
			// New Caching Code
			$strippedUserName = preg_replace("/[^a-zA-Z0-9]/", "", myInfo::MY_LAST_FM_USER_NAME);
			
			if(strlen($strippedUserName) > 0)
			{
				$strippedUserName = $strippedUserName . 'LovedTracks';
				$myCache = new Caching("./MashupCache/LovedTracks/", $strippedUserName);
				
				if ($myCache->needToRenewData())
				{	
					$lovedTracksCount = count($myLovedTracks);
					$artistName = '';
					$albumTitle = '';
					$songTitle = '';
					$songURL = '';
					$i = 0;
			
					while ( $i < $lovedTracksCount)
					{
						// Need album titles
						$formattedSongTitle = str_replace(' ', '+', $myLovedTracks[$i]['name']);
						// http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=song+title&entity=musicTrack
						$iTunesSearch = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedSongTitle . '&media=music&entity=musicTrack&attribute=musicTrackTerm';
						$iTunesSearchResults = fetchThisURL($iTunesSearch);
						$iTunesResults = json_decode($iTunesSearchResults);
						
						$artistName = $myLovedTracks[$i]['artist']['name'];
						$songTitle = $myLovedTracks[$i]['name'];
						$songURL = 'http://' . $myLovedTracks[$i]['url'];  // This should solve some linking issues...
						
						if($iTunesResults->resultCount > 0)
						{
							$j = 0;
							$foundAlbum = false;
			
							while( ($j < $iTunesResults->resultCount) && ( ! $foundAlbum) )
							{
								if ( strcmp($iTunesResults->results[$j]->artistName, $myLovedTracks[$i]['artist']['name']) == 0)
								{
									
									$albumTitle = $iTunesResults->results[$j]->collectionName;
									$foundAlbum = true;
								}
							
								$j++;
							}
							// if we don't find one, such as in the case of Tom Waits "Looking for the heart of Saturday Night" , we are end up with the wrong data...
							if ( ! $foundAlbum)
							{
								// This is necessary 
								$albumTitle = '';
							}
						}
						else
						{
							$albumTitle = '';
						}
						$lovedTracksInfo[$i] = array(
													0 => $artistName,
													1 => $albumTitle,
													'songTitle' => $songTitle,
													'songURL' => $songURL
													);
						
						$i++;
					}			
					$reformatedTracks = serialize($lovedTracksInfo);
					
					$myCache->saveSerializedDataToFile($reformatedTracks);
				}
				else
				{
					// It doesn't need to be renewed so use local copy of array
					$lovedTracksInfo =  $myCache->getUnserializedData();
				}
			}
			 
			return new musicCollection($lovedTracksInfo);
		}
		
		
		
		/**
		 * This is the best way to find playable audio samples and track listings.
		 *
		 * @param string artist's name
		 * @param string album title
		 * @return array 
		 */
		 public function findTracksInITunesFor($artistName, $albumTitle)
		 {
			$iTunesTracks = null;
			
			try
			{
				$iTunesArtistInfo = $this->getArtistResultsFromITunes($artistName);
				
				if (($iTunesArtistInfo != null) && ( count($iTunesArtistInfo->results) > 0))  
				{
					$iTunesAlbumInfo = $this->getAlbumAndTracksFromITunes($iTunesArtistInfo->results[0]->artistId, $albumTitle);
					
					if($iTunesAlbumInfo != null)
					{						
						for( $i = 1 ; $i < count($iTunesAlbumInfo->results); $i++)
						{
							// I should look at this again... Damn Tom Waits album!
							// The first result is the album/artist info with the tracks taking up the rest of the space in the results array
							$correctIndex = $i - 1;
							$iTunesTracks[$correctIndex]['title'] = $iTunesAlbumInfo->results[$i]->trackName;
							$iTunesTracks[$correctIndex]['URL'] = $iTunesAlbumInfo->results[$i]->previewUrl; 
							// Tom Waits Orphans has more tracks than it has previews...  It screws up my code...
							$iTunesTracks[$correctIndex]['purchaseURL'] = $iTunesAlbumInfo->results[$i]->trackViewUrl;
						}
					}
				}
			}
			catch (Exception $e)
			{
				// Finding tracks is tough, if you can't find them, just catch the error and try another API...
				throw new Exception("Not going to get Tracks for: " . $albumTitle . " from iTunes.");
			}
		
			
			return $iTunesTracks;
		 }
		 
		 
		 // Trying to make my code more modular, but still retain versatility.  Not sure which method should be long or how many methods 
		 // I should be writing or rewriting...  That's the problem working all alone.
		 private function findTrackInITunesFor($favouriteTrackTitle, $albumTitle, $artistName)
		 {
		 		$sampleURL = '';
		 
		 		// Try sample of song, from album by artist
		 		$albumTracks = $this->findTracksInITunesFor($artistName, $albumTitle);
				// Now we need to find the correct track.
				
				for( $i = 0 ; $i < count($albumTracks); $i++)
				{
					if(strcmp($albumTracks[$i]['title'], $favouriteTrackTitle) == 0)
					{
						$sampleURL = $albumTracks[$i]['URL'];  // This seems to find the wrong track for See See Rider by Lightnin' Hopkins
					}
				}
				
				return $sampleURL;
		 }
			 
			 
			 
	   /**
		* I didn't make these classes to make money, but the potential is there.  You can make some money referring people to Amazon.com 
		* It is also possible to make money referring people to the iTunes Music Store but not if you are a Canadian.  Last.fm is my
		* third choice, it provides buy links too, but Last.fm keeps the money.
		*
		* $songTitle is optional, it makes the method a little more versatile, how to do this in PHP though?
		*
		* @param string
		*
		* @return string
		*/
		public function trackBuyLinkFor($songTitle)
		{
			$buyLink = '';
			
			$memberInfo = $this->currentMemberAsArray();
			
			$artistName = $memberInfo[0];
			$albumTitle = $memberInfo[1];
			
			// Amazon is the only service that I can potentially make money from so it gets searched first 
			
			$amazonSearchResults = $this->amazonAPI->getMP3ForSongByArtist($songTitle, $artistName);
			
			// probably have to do another search knowing Amazon...
			// The above returned a lot more tracks or at least ASINs than I wanted.
	
			if( count($amazonSearchResults->Items->Item) > 0)
			{
				// We have multiple tracks or at least ASINs, go with first one.
				$moreAmazonXML = $this->amazonAPI->getItemByAsin($amazonSearchResults->Items->Item[0]->ASIN);
				// May need conditional around this but so far it works with my sample data set
				$buyLink = $moreAmazonXML->Items->Item->DetailPageURL;
			}
			
			if ( ! empty($buyLink))
			{
				// Try finding link in iTunes
				try
				{
					$iTunesArtistInfo = $this->getArtistResultsFromITunes($artistName);
					
					if (($iTunesArtistInfo != null) && (count($iTunesArtistInfo->results) > 0)) 
					{
						$iTunesAlbumInfo = $this->getAlbumAndTracksFromITunes($iTunesArtistInfo->results[0]->artistId, $albumTitle);
						
						if($iTunesAlbumInfo != null)
						{
							if( $iTunesAlbumInfo->resultCount > 0)
							{
								$buyLink = $iTunesAlbumInfo->results[0]->collectionViewUrl;        				
							}
						}
					}
				}
				catch (Exception $e)
				{
					// Buy links aren't as difficult to find as track previews but if an exception is thrown, just catch and try another web service.
					
				}
			}
			
			// Last choice is Last.fm for a buy link as they keep the money from the referral 
			else if((strcmp($buyLink, '#') == 0) && ( ! empty($songTitle)))
			{
				$lastFMTrackInfo = $this->getTrackBuyingInfoFromLastFM($artistName, $songTitle);
				
				// First look for a download and if there isn't one, get a link to a physical copy 
				$downloadCount = count($lastFMTrackInfo['downloads']);
				$physicalCount = count($lastFMTrackInfo['physicals']);
				if ($downloadCount > 0)
				{
					$buyLink = $lastFMTrackInfo['downloads'][0]['buyLink'];
				}
				else if ($physicalCount > 0)
				{
					$buyLink = $lastFMTrackInfo['physicals'][0]['buyLink'];
				}
				else
				{
					$buyLink = '';  // No buy link found despite lots of effort
				}
			}
			
			return $buyLink;
		}
	
	}
?>