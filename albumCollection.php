<?php
    /**
     * Class to create a collection of records/albums/cds
     * @author Muskie McKay
     * @link http://www.muschamp.ca
     * @version 1.3.1
     * This started as a simple class to represent a collection of music,
     * a physical collection or virtual that then can be easily manipulated just like a crate of LPs.
     * This class mainly returns information in the form of strings (sometimes JSON encoded), arrays, and XML objects
     * Rarely does a method return HTML or output it to the screen, this allows the user of the 
     * class to control the look of the collection on screen.  This class has support for the following APIs:
     * Amazon Product API
     * Last.FM 
     * Facebook Open Graph
     * Twitter
     * MusicBrainz
     * iTunes Store Search API
     * Flickr
     * 
     * This class inherrits from musicCollection.php which inherrits from mCollection.php
     *
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
		
    */
    
    require_once('./musicCollection.php');

	class albumCollection extends musicCollection
	{	          
	
	   /**
		* Initialize an Album Collection
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
		* Initialize a the APIs
		*
		* I haven't overided  the constructer, but by overiding this method, I can add support for different APIs at different levels.
		*/
		protected function initializeAPIs()
		{
			parent::initializeAPIs();
			
		}
			
			
		/**
		 * This is a method that shouldn't be called much as it is a brute force dump of the entire album collection
		 * fetching the images from Amazon and linking to the artist profile on Last.fm, this could be a lot of 
		 * calls to APIs, but it is what I originally thought about doing, so I implemented it as further proof of concept.
		 * 
		 * Warning: Calling this method on a collection of more than single digits is really slow, not sure how I'll speed it up...
		 * 
		 */
		 public function galleryForEntireCollection()
		 {
			// This can take a long time to run the very first time on large cd collections, I don't recommend using this method 
			if($this->hasAlbums())
			{
				// Highly likely we find something in some database in this case
				print("<ul id='coverGallery'>");
			}
		 
			foreach($this->theCollection as $albumInfo)
			{
				$amazonXML = $this->getAlbumXMLFromAmazon($albumInfo);
		
				// Now we at least have a valid XML response from Amazon regardless of whether we found anything useful
				if(( ! empty($amazonXML)) && ($amazonXML->Items->TotalResults > 0))
				{
					// We have at least one result returned, just go with first and presumeably most accurate result
					$albumCoverURL = $amazonXML->Items->Item->MediumImage->URL;
					$artistName = $albumInfo[0];
					
					// We have an album now we see if we can find the artist in Last.fm
					// If the artist name is various we don't want to waste time trying to get it from Last.fm
					if (strcasecmp($artistName , 'various') != 0)
					{
						$artist = $this->getArtistInfoFromLastFM($artistName);
						
						if(is_array($artist) && ! empty($albumCoverURL))
						{
							// We have an album cover from Amazon and an artist from Last.fm
							// This has been our goal all along
							$imageTag = "<img src=" . $albumCoverURL . " alt=" . $albumCoverURL . " />";
							print("<li>");
							print("<a href=" . $artist['url'] . " >" . $imageTag . "</a>");
							print("</li>");
						}
					}
				}
				else
				{
					// no results (images) found in Amazon.com for $albumInfo
					// With no album cover there is nothing to display, so iterate again
				}
			}
			
			if($this->hasAlbums())
			{
				// If we have some albums then we're going to try our hardest to display the cover and that means we need a closing HTML tag
				print("</ul>");
			}
		 }
		
		
         // Added caching to this function, simple but hopefully effective means to speed up my albumCollection class/mashups
         private function getAlbumXMLFromAmazon($albumInfoArray)
         {    	
         	if(count($albumInfoArray) >= 2)
         	{
				// Some data from cds.csv is passing in empty arrays to this method
				$strippedAlbumName = $albumInfoArray[0] . "-" . $albumInfoArray[1]; //This should be a unique and human readable file name
				$strippedAlbumName = preg_replace("/[^a-zA-Z0-9]/", "", $strippedAlbumName);
	
				if(is_array($albumInfoArray) && strlen($strippedAlbumName) > 0)
				{
					$myCache = new Caching("./MashupCache/Amazon/", $strippedAlbumName, 'xml');
					
					if ($myCache->needToRenewData())
					{		
						try
						{
							$result = $this->amazonAPI->getAlbumCoverByArtistAndTitle($albumInfoArray[0], $albumInfoArray[1]);
						}
						catch(Exception $e)
						{	
							echo $e->getMessage();
						}
						$myCache->saveXMLToFile($result);  // Save new data before we return it to the caller of the method 
					}
					else
					{
						// It doesn't need to be renewed so use local copy
						$result = $myCache->getLocalXML();
					}
				}
				else
				{
					throw new Exception('Incorrect data type passed to getAlbumXMLFromAmazon()');
				}
			}
			else
			{
				// What to do about this?  Currently it is throwing a warning...
				$result = null;
			}
			
			return $result;
         }
         
         
         
         // Although I don't make many calls to MusicBrainz as I use Amazon for the primary image source and Last.fm as the primary source for
         // artist bios.  MusicBrainz has a lot of information and requires no authorization to use it. Caching will be
         // done the same way as for Last.fm using serialize and unserialze.  MusicBrainz using phpBrainz uses it's own custom Objects. 
         private function getReleaseFromMusicBrainz($albumASIN)
         {
			//ASIN's are unique and don't have spaces or garbage characters, hooray!
         	
         	$myCache = new Caching("./MashupCache/MusicBrainz/", $albumASIN);
				
			if ($myCache->needToRenewData())
			{
				try
				{
					$args = array( 
   						 		"asin"=>$albumASIN
								);

					$releaseFilter = new phpBrainz_ReleaseFilter($args);
					$releaseResults = $this->theBrainz->findRelease($releaseFilter);  // It says this returns an array!
					if( ! empty($releaseResults))
					{
						// Not all phpBrainz_Release Objects are created equal
						// This is maybe why I'm not getting tracks...
						$trackIncludes = array(
											"artist",
											"discs",
											"tracks"
											);
						// I need the musicbrainz ID for the release I just found...
						$mbid = $releaseResults[0]->getId();
						$musicBrainzRelease = $this->theBrainz->getRelease($mbid, $trackIncludes); // This gets better results from MusicBrainz.org
					}
					else
					{
						$musicBrainzRelease = $releaseResults;  // This is a new idea and may just be a waste of time without having found tracks...
					}
				}
				catch(Exception $e)
				{	
					echo $e->getMessage();
				}				
				$serializedObject = serialize($musicBrainzRelease);
				
				$myCache->saveSerializedDataToFile($serializedObject);
			}
			else
			{
				// It doesn't need to be renewed so use local copy of array
				$musicBrainzRelease =  $myCache->getUnserializedData();
			}
			
			return $musicBrainzRelease;
         }
                
         
         
        /**
         * Returns the current album as an array of information from Last.fm corresponding to the artist's getArtistInfo 
         * If the artist isn't in the Last.fm system it throws an error...
         *
         * @return array
         */
        public function currentAlbumArtistInfoFromLastFM()
        {
        	$artistInfo;
        
        	$artistName = $this->currentAlbumArtist();
        	
        	if ( ! empty($artistName))
        	{
        		$artistInfo = $this->getArtistInfoFromLastFM($artistName);
        	}
        	else
        	{
        		$artistInfo = null;
        	}
        	
        	return $artistInfo;
        }
        
        
        
        /**
         * Returns a string consisting of a link and an image (icon) for the Last.fm service, the link goes to the 
         * artist info page for the current album's artist on Last.fm.  I decided to return valid HTML as I thought this
         * would save some time later on and some services it is much more elaborate to get the correct info and link to work.
         *
         * @return string;
         */
         public function currentAlbumLastFMArtistBadge()
         {
         	$htmlTag = null;
         	$artistInfo = $this->currentAlbumArtistInfoFromLastFM();
         	
         	if(strcmp($artistInfo["name"], "various") != 0)
         	{
         		$openLinkTag = '<a href="' . $artistInfo["url"] . '" >';
         		$closeLinkTag = '</a>';
         		$iconTag = '<img src="' . myInfo::LAST_FM_ICON_URL . '" class="iconImage" />';
         		
         		$htmlTag = $openLinkTag . $iconTag . $closeLinkTag;
         	}
         	
         	return $htmlTag;
         }
         
         
         
        /**
         * Returns a string consisting of a link and an image (icon) to the album on Amazon.com, I decided to return valid HTML as I thought this
         * would save some time later on and some services it is much more elaborate to get the correct info and link to work.  The link returned has
         * an Amazon Associate tag as detailed here: 
         * http://www.kavoir.com/2009/05/build-simple-amazon-affiliate-text-links-with-just-asin-10-digit-isbn-and-your-amazon-associate-tracking-id.html
         *
         * @return string;
         */
         public function currentAlbumAmazonAssociateBadge()
         {
            $htmlTag = null;
         	
         	$amazonProductURL = $this->currentAlbumAmazonProductURL();
         	if(strcmp($amazonProductURL, "#") != 0)
         	{
         		$openLinkTag = '<a href="' . $amazonProductURL . '" >';
         		$closeLinkTag = '</a>';
         		$iconTag = '<img src="' . myInfo::AMAZON_ICON_URL . '" class="iconImage" />';
         		
         		$htmlTag = $openLinkTag . $iconTag . $closeLinkTag;
         	}
         	
         	return $htmlTag;
         }
         
         
         
        /**
         * This method just returns the URL to the product page using the ASIN and will append on your Amazon Associate ID so you can
         * potentially earn a commision.  If the item isn't in Amazon, well return the hash symbol which just reloads the page when clicked...
         *
         * @return string;
         */
         public function currentAlbumAmazonProductURL()
         {
         	$albumASIN = $this->currentAlbumASIN();
         	if($albumASIN != null)
         	{
         		$amazonProductURL = $this->amazonProductURLFor($albumASIN);
         	}
         	else
         	{
         		// I'm less enamoured with this idea, perhaps return a link to a search or error page.
         		$amazonProductURL = "#"; // return hash instead of null or empty string so it just reloads the page
         	}
         	
         	return $amazonProductURL;
         }
         
         
         
        /**
         * This method returns the URL to the product page on BestBuy.com for the currentAlbum.  If the item can not be found it returns the hash symbol
         *
         * Best Buy API is currently inferior to Amazon.  It doesn't have as many features and their catalog of albums is smaller.
         *
         * @return string 
         */
        public function currentAlbumBestBuyProductURL()
        {
        	$productURL = '#';
        
        	$albumTitleQuery = 'albumTitle="' . urlencode($this->currentAlbumTitle()) .'"';  //wildcard unnecessary... 
        	$searchResults = $this->bestBuyRemix->products(array('type=Music', $albumTitleQuery))
							->show(array('name','albumTitle', 'artistName', 'url', 'sku', 'image'))
							->format('json')
							->query();
							
			// Need to decode results...
			$bestBuyData = json_decode($searchResults);
        
        	if($bestBuyData != null)
        	{
				if($bestBuyData->products != null)
				{			
					for( $i = 0 ; $i < count($bestBuyData->products); $i++)
					{
						if(strcmp($bestBuyData->products[$i]->artistName, $this->currentAlbumArtist()) == 0)
						{
							$productURL = $bestBuyData->products[$i]->url;  
						}
					}
				}
			}
			
			
			return $productURL;
        }
         
         
         
        /**
         * Returns a string consisting of a link and an image (icon) for the Apple iTunes store, the link goes to the 
         * artist info page for the current album's artist.  I decided to return valid HTML as I thought this
         * would save some time later on and some services it is much more elaborate to get the correct info and link to work.
         * Apple's iTunes Associate program isn't available in Canada but if it were, this is where you'd want to put in your associate ID
         *
         * I now cache the JSON results return from Apple as serialized objects in the method getArtistResultsFromITunes()
         *
         * @return string;
         */
         public function currentAlbumITunesArtistBadge()
         {
         	$finalHTML = null;
         	
         	$artistName = $this->currentAlbumArtist();
         	
         	try
         	{
         		$iTunesInfo= $this->getArtistResultsFromITunes($artistName);
         	}
         	catch(Exception $e)
         	{
         		throw new Exception("Something went wrong while attempting to access iTunes data on: " . $artistName);
         	}
         	
         	/*
         	print("<pre>");
         	print_r($iTunesInfo);
         	print("</pre>");
         	*/
    
			if ( ($iTunesInfo != NULL) && ($iTunesInfo->resultCount > 0))
			{
				$iTunesArtistLink = $iTunesInfo->results[0]->artistLinkUrl; // This through an exception for Steve Earle Jeruselem
				$openLinkTag = '<a href="' . $iTunesArtistLink . '" >';
				$closeLinkTag = '</a>';
				$iconTag = '<img src="' . myInfo::APPLE_ICON_URL . '" class="iconImage" />';
				$finalHTML = $openLinkTag . $iconTag . $closeLinkTag;
			}
			
			return $finalHTML;
         }
         
         
         
        /**
         * This method uses Facebook's Open Graph format to search for a page or more likely pages corresponding to an artist in 
         * Facebook's social graph.  
         * The most likely URL is chosen for an artist/musician and then we creat the HTML tags necessary to display a little Facebook badge
         *
         * @return string
         */
         public function currentAlbumFacebookArtistBadge()
         { 
         	// I'm not caching facebook requests after all the open graph err social graph is constantly changing...
         	$artistName = $this->currentAlbumArtist();    
         	$artistFacebookPage = $this->getFacebookPageForArtist($artistName); 
         	
         	$openLinkTag = '<a href="' . $artistFacebookPage->link . '" >';
			$closeLinkTag = '</a>';
			$iconTag = '<img src="' . myInfo::FACEBOOK_ICON_URL . '" class="iconImage" />';
		
			$htmlTag = $openLinkTag . $iconTag . $closeLinkTag;
         	
      		
      		return $htmlTag;
         }
         
         
         
        /**
         * This method goes a step further than the one above, both use Facebook's Open Graph format to search for a page or more likely pages 
         * corresponding to an artist in Facebook's social graph.  
         * The most likely URL is chosen for an artist/musician and then we create the HTML tag necessary to display a fully functional like button
         *
         * @return string
         */
         public function currentAlbumFacebookLikeButton()
         {
      		return $this->facebookLikeButtonFor($this->currentAlbumArtist());    
         }
         
         
         
        /**
         * This method creates a fully functional "Tweet This" button.  You don't need to register an app at Twitter to just do this.
         * It uses Twitter's Javascript but it passes in text and variables concerning the current album and pulls information from
         * myInfo.php specifically MY_TWITTER_ACCOUNT and MY_HOME_PAGE
         *
         * @return string
         */
         public function currentAlbumTweetThisButton($tweet = myInfo::DEFAULT_TWEET)
         {	
         	return $this->tweetThisButton($tweet);
         }
         
         
         
        /**
         * Returns the current album as an array based on currentMemberIndex
         * Made this private to obscure the underlying array from users of this class.
         *
         * @return array
         */
         private function currentAlbumAsArray()
         {
         	return $this->currentMemberAsArray();
         }
         
         
         
        /**
         * Returns the current album's artist, ie the first item in currentAlbumAsArray
         *
         * @return string
         */
         public function currentAlbumArtist()
         {
         	$anAlbum = $this->currentAlbumAsArray();
         	$artistName = $anAlbum[0];
         	if ($artistName == null)
         	{
         		$artistName = "unknown";  // This is better than returning null or nothing...
         	}
         	
         	return $artistName;
         }
         
         
         
        /**
         * Returns the current album's album title, ie the second item in currentAlbumAsArray
         *
         * @return string
         */
         public function currentAlbumTitle()
         {
         	$anAlbum = $this->currentAlbumAsArray();
         	return $anAlbum[1];
         }
         
         
         
        /**
         * Returns the current album's artist bio, which can be potentially from Last.fm or someplace else...
         *
         * @return string
         */
         public function currentAlbumArtistBio()
         {
         	$artistName = $this->currentAlbumArtist();
         	$artistBio = $artistName . "'s bio is proving tough to locate.";
         	$facebookPage = null;
         	
         	try
         	{
         		$lastFMResults = $this->currentAlbumArtistInfoFromLastFM();
         	}
         	catch (Exception $e)
         	{
         		// getInfo in Last.fm at least in Matt's code can go astray...
         		// Just catch exceptions and move on...
         	}
         	if ( ! empty($lastFMResults) ) 
         	{
         		// Now check that there is actually a short bio, sometimes an artist is in Last.fm but lots of data is missing.
         		if ( ! empty($lastFMResults["bio"]["summary"]))
         		{
         			// Success
         			$artistBio = $lastFMResults["bio"]["summary"];
         			$facebookPage = "Is unnecessary.";
         		}
         	}
         	
         	
         	if ( $facebookPage == null )
         	{
         		// We need to try a little harder and iTunes isn't the place to do it, try Facebook instead...
         		$facebookPage = $this->getFacebookPageForArtist($artistName);
         		if ( ! empty($facebookPage->bio))
         		{
         			$artistBio = $facebookPage->bio;  // This one should go first I think
         		}
         		elseif( ! empty($facebookPage->description))
         		{
         			$artistBio = $facebookPage->description;  // This was my very first attempt to get a bio from Facebook
         		}
         		elseif ( ! empty($facebookPage->personal_info))
         		{
         			$artistBio = $facebookPage->personal_info;  // This will work for Booker T and the MGs
         		}
         		elseif ( ! empty($facebookPage->link))
         		{
         			$artistBio = 'Perhaps try <a href="' . $facebookPage->link . '"> this link </a> for more information on ' . $artistName . '. Never know it could be useful after all.';
         		}
         		else
         		{         			
         			// This happens for Koerner & Murphy but I swear Amazon had data on them...
         			try
         			{
         				$xmlFromAmazon = $this->amazonAPI->searchProducts($artistName, AmazonProductAPI::CATEGORY_MUSIC, AmazonProductAPI::TYPE_ARTIST, AmazonProductAPI::RESPONSE_GROUP_LARGE);
         				if ( count($xmlFromAmazon->Items) > 0)
         				{
         					if( ! empty($xmlFromAmazon->Items->Item[0]->CustomerReviews))
         					{
         						$artistBio = $xmlFromAmazon->Items->Item[0]->CustomerReviews->Review[0]->Content;  // This is far from ideal...
         					}
         				}
         			}
         			catch (Exception $e)
         			{
         				// This time I want to see the error at least while debugging  
         				// throw new Exception($e->getMessage);
         			}
         			
         			// If I still can't find a bio, try Wikipedia 
         			
         			$wikiXML = $this->searchWikipediaFor($artistName);
					$artistBio = $wikiXML->Section->Item->Description;
         		}     		
         	}
         	
         	// This is a really clever regular expression, I must have gotten from somewhere as I'm not the most regular expression savvy...
         	$betterBio = preg_replace("/[^(\x20-\x7F)]*/", "", $artistBio);  // Too much garbage is in some of these results, I want <, >, and quotation marks though
         	
         	return $betterBio;
         }
         
         
         
        /**
         * Creates a random number and assigns that to the currentAlbumIndex then returns the new currentAlbumAsArray()
         * After calling this just use the various methods that return data for the current album or get another random album
         * This method is now private, as usually the user wants a random album that satisfies some condition.
         *
         * @return array
         */
         private function randomAlbumAsArray()
         {         
         	return $this->randomMember();
         }
        
        
         
        /**
         * Sets current album to be a random album, for which the artist, ie cell 0 in the array isn't various.
         * I return the information as an array but with minimal information currently.  Once it is THE currentAlbum you can get piles of data
         *
         * @return array
         */
         public function randomAlbumNotByVarious()
         {
         	$validAlbum = array(); 
         	
         	$randomAlbum = $this->randomAlbumAsArray();
         	if( ! $this->isCurrentAlbumByVarious() )
         	{
         		//Hooray
         		$validAlbum = (array) $randomAlbum;
         	}
         	else
         	{
         		// Continue Random Recursive search
         		$validAlbum = (array) $this->randomAlbumNotByVarious();
         	}
         	
         	return $validAlbum;
         }
 
 
         
        /**
         * Since what I really care about is albums in my collection that I can get valid album covers for 
         * I needed a method to get only albums with both a medium and large images as they look
         * the best in galleries.  Amazon is my primary source for album covers.
         * With caching working now this shouldn't be that inefficient but on a large collection with a lot of obscure old 
         * albums that are out of print, well this method could take a while, I don't want it to time out so I only try so hard...
         *
         * @return array 
         */
         public function randomAlbumWithMediumAndLargeImagesAsArray()
         {
             // This method will obviously use randomAlbumAsArray() and various current album methods until it finds one that satisfies all the 
             // conditions, this could concievably take forever, now that I look at the code in the morning...  but the odds of that on a decent
             // sized collection are very small...
             $randomAlbum = $this->randomAlbumAsArray();
             $mediumImageURL = $this->currentAlbumsMediumImageURL();
             $largeImageURL = $this->currentAlbumsLargeImageURL();
             
             while((strcmp($mediumImageURL, myInfo::MISSING_COVER_URL) == 0) || (strcmp($largeImageURL, myInfo::MISSING_COVER_URL) == 0))
             {
				$this->goToNextAlbum();
             	$mediumImageURL = $this->currentAlbumsMediumImageURL();
            	$largeImageURL = $this->currentAlbumsLargeImageURL();
             } 
             
             return $this->currentAlbumAsArray();  // We've found one that satisfied the conditions and it is now the currentAlbum
         }
         

         
        /**
         * The initial two APIs I integrated into albumCollection.php were last.fm and Amazon Product API
         * so I wanted a means of getting an album with all three cover images, plus some basic information on the artist 
         * from Last.fm, I then returned this as an array with non-numerical indices for ease of use.
         *
         * @return array
         */
         public function randomAlbumWithThreeImagesAndArtistInfo()
         {
         	$hasArtistInfo = false;
         	$hasLargeImage = false;
         	$hasMediumImage = false;
         	$hasSmallImage = false;
         	$counter = 0;  // I don't want any infinite loops, so I give it five tries...
         	$randomAlbum = $this->randomAlbumWithMediumAndLargeImagesAsArray();
         	
         	while (($counter < 5) && (!$hasArtistInfo || !$hasLargeImage || !$hasMediumImage || !$hasSmallImage))
         	{
         		$counter++;
         		$hasLargeImage = true;
         		$hasMediumImage = true;
         		$possibleSmallImage = $this->currentAlbumsSmallImageURL();
         		if((strcmp($possibleSmallImage, myInfo::MISSING_COVER_URL) != 0) && (! $this->isCurrentAlbumByVarious()))
         		{
         			// We have a useful small image
         			$hasSmallImage = true;
         			// Finally see if we have useful artist info 
         			try
         			{
         				$artistInfo = $this->currentAlbumArtistInfoFromLastFM();
         				// Not only do we want $artistInfo we want it to have at least some data
         				if( ! empty($artistInfo))
         				{
							$hasArtistInfo = true;
						}
         			}
         			catch(Exception $e)
         			{
         			  // Although we have an exception we're going to deal with it here...
         			  // In this case we do nothing and let the while loop iterate again...
         			  // Lets try the album next door
         			  $this->goToNextAlbum();
         			  $hasSmallImage = false;  // Wasn't doing this before.  Still not so sure on just stepping over, no guarantee it has Large Image etc.
         			}
         		}
         		else
         		{
         			// We need a different random album
         			$randomAlbum = $this->randomAlbumWithMediumAndLargeImagesAsArray();
         		}
         	}
         	// Now we should have valid information so we need to make a pretty associative array and return it 
         	$basicInfo = $this->currentAlbumAsArray();
         	$usefulInfo = array(
								'artist' => $basicInfo[0],
								'album' => $basicInfo[1],
								'largeImageURL' => $this->currentAlbumsLargeImageURL(),
								'mediumImageURL' => $this->currentAlbumsMediumImageURL(),
								'smallImageURL' => $this->currentAlbumsMediumImageURL(),
								'artistInfoURL' => $artistInfo['url'],
								'shortArtistBio' => $artistInfo["bio"]["summary"]
								);
								
			// If we don't succeed in finding a valid album, well it'll probably throw an exception before now, that's what debugging is for...
			return $usefulInfo;
         }
         
         
         
        /**
         * Worried about efficiency and thinking how people will actually use the class, it seems that the hardest information to find online
         * is supposedly high resolution album images, which is why you have to use the Amazon API which is why you jump through all the Amazon.com 
         * hoops.  To this end this method returns a random album which has a large image URL, plus that album's artist info from
         * Last.fm, it returns an array with the same formating and all the info as the method above and sets this random album to be the current album
         *
         * @return array;
         */
         public function randomAlbumWithLargeImageAndArtistInfo()
         {
         	$randomAlbumInfo = $this->randomAlbumAsArray();
         	$largeImageURL = $this->currentAlbumsLargeImageURL();
         	
         	while(strcmp($largeImageURL, myInfo::MISSING_COVER_URL) == 0)
         	{
         		$randomAlbumInfo = $this->randomAlbumAsArray();
         		$largeImageURL = $this->currentAlbumsLargeImageURL();
         	}
			// When we escape the while loop we have an album with a valid large image set as the current album 
			// It probably has artist info, but we need to check
			if(strcasecmp("various", $randomAlbumInfo[0]) != 0)
			{
				$artistInfo = $this->currentAlbumArtistInfoFromLastFM();  // Currently not encased in try catch structure, should probably be.
			}
			else
			{
				$artistInfo = null;
			}
			
			if(empty($artistInfo))
			{
			  // We need to try again
			  $results = $this->randomAlbumWithLargeImageAndArtistInfo();
			}
			else
			{
				$results = array(
								'artist' => $randomAlbumInfo[0],
								'album' => $randomAlbumInfo[1],
								'largeImageURL' => $largeImageURL,
								'mediumImageURL' => $this->currentAlbumsMediumImageURL(),
								'smallImageURL' => $this->currentAlbumsSmallImageURL(),
								'artistInfoURL' => $artistInfo['url'],
								'shortArtistBio' => $artistInfo["bio"]["summary"]
								);
			}
			
			return $results;	
         }
         
         
         
        /**
         * Even with the limitations on the two random access methods above, they still are probably more computationally intensive than sequential 
         * access.  I recommend getting a single random album then calling one of the nextAlbum style methods. 
         * This method returns data in the same format as it's random counterpart above. 
         *
         * @return array
         */
         public function nextAlbumWithThreeImagesAndArtistInfo()
         {
			$counter = 0;
			while($counter < $this->collectionSize()) //This still could go around more than once technically
			{
				$largeImageURL = $this->getNextValidLargeAlbumCoverImageURL(); // This method will do a fair amount of work for us.
				$counter++;  // This is not an accurate count here, but it keeps it from going too wild
				if((strcmp($this->currentAlbumsMediumImageURL(), myInfo::MISSING_COVER_URL) != 0) 
					&& (strcmp($this->currentAlbumsSmallImageURL(), myInfo::MISSING_COVER_URL) != 0)
					&& (! $this->isCurrentAlbumByVarious()))
				{
					try
					{
						$artistInfo = $this->currentAlbumArtistInfoFromLastFM();
						// Not only do we want $artistInfo we want it to have at least some data
						if( ! empty($artistInfo))
						{
							// This is not a sufficient test perhaps
							$counter = ($this->collectionSize() + 1);  // This will break the while loop
						}
					}
					catch(Exception $e)
					{
					  // Although we have an exception we're going to deal with it here...
					  // In this case we do nothing and let the while loop iterate again...
					  // Lets try the album next door
					  $this->goToNextAlbum();
					  $counter++;
					}
				}
				else
				{
					$this->goToNextAlbum();
					$counter++;
				}
			}
			// Now the current album has the data we want 
			$basicInfo = $this->currentAlbumAsArray();
         	$usefulInfo = array(
								'artist' => $basicInfo[0],
								'album' => $basicInfo[1],
								'largeImageURL' => $this->currentAlbumsLargeImageURL(),
								'mediumImageURL' => $this->currentAlbumsMediumImageURL(),
								'smallImageURL' => $this->currentAlbumsMediumImageURL(),
								'artistInfoURL' => $artistInfo['url'],
								'shortArtistBio' => $artistInfo["bio"]["summary"]
								);
								
			// If we don't succeed in finding a valid album, well it'll probably throw an exception before now, that's what debugging is for...
			return $usefulInfo;
         }
         
         
         
        /**
         * Returns the current album in amazon XML form based on currentAlbumIndex
         * I currently use the Images ResponseGroup in the method in AmazonAPI that does the fetching
         * Don't overlook this fact.  Methods like this will be minimal as they force the user of this class
         * to learn the AmazonXML format...
         *
         * @return XML Object from Amazon.com
         */
         public function currentAlbumAsAmazonXML()
         {
         	return $this->getAlbumXMLFromAmazon($this->currentAlbumAsArray());
         }
         
         
         
        /**
         * Returns the current album's ASIN which is a unique identifier used for Amazon.com in their webstore.  It is also used
         * by MusicBrainz which is why I implemented a private method to fetch just the ASIN.
         *
         * @return String
         */
         private function currentAlbumASIN()
         {
         	$albumASIN = null;
         	$albumXML = $this->currentAlbumAsAmazonXML();
         	
         	if($albumXML->Items->TotalResults > 0) 
         	{
         		if($albumXML->Items->TotalResults == 1)
         		{
         			$albumASIN = $albumXML->Items->Item->ASIN;  //When two results are found, I think this is insufficient at least part of the time...
         		}
         		else
         		{
         			$albumASIN = $albumXML->Items->Item[0]->ASIN;
         		}
         	}
         	
         	return $albumASIN;
         }
         
         
         
        /**
         * This method searches MusicBrainz.org for information using the current album's ASIN.  The returned information is in the form of
         * a release which in MusicBrainz lingo can be an album, a single, or a compilation.  In most cases it will be an album but the other two
         * are valid.  Once we have the release from MusicBrainz we can use that info to fetch tracks or track listings.
         *
         * @return phpBrainz_Release Object
         */
         public function currentAlbumAsMusicBrainzRelease()
         {
         	$albumASIN = $this->currentAlbumASIN();
         	
         	// if $albumASIN is not found we just abort...
         	if ( ! empty($albumASIN))
         	{
         		$results = $this->getReleaseFromMusicBrainz($albumASIN);  // Might have to check if string is null instead...
         	}
         	else
         	{
         		$results = null;
         	}
         	
         	
         	return $results;
         }
         
         
         
         // Look for this data in the iTunes Store Search API
         // Now protected as it is the best way to find preview audio samples I've found
         protected function findCurrentAlbumTracksInITunes()
         {
         
            // Needs to call parent method now...
         
         	$artistName = $this->currentAlbumArtist();
			$albumTitle = $this->currentAlbumTitle();
			
			return $this->findTracksInITunesFor($artistName, $albumTitle);
         }
         
         
         
         // Look for this data in the Amazon Product API, returns inferior results to iTunes so use the above or search multiple sources for tracks.
         private function findCurrentAlbumTracksInAmazon()
         {

         	$amazonTracks = null;
         	$i = 0;
         	$albumTitle = $this->currentAlbumTitle();
         	$artistName = $this->currentAlbumArtist();
         	
         	$resultingXML = $this->amazonAPI->getMP3sForAlbumByArtist($albumTitle, $artistName);
         	// The first result being found is usually the album not a track...
         	// Need to try and account for that.
         	// If you want all the tracks you need to fetch the second page of results too... another reason to use iTunes first
         	// Fetching multiple pages and properly ordering the tracks is not implemented currently.
         	
         	foreach($resultingXML->Items->Item as $potentialTrack)
         	{
         		$trackXML = $this->amazonAPI->getItemAttributesByAsin($potentialTrack->ASIN);
         		// need to check that the track is a track and not an album
         		
         		if(strcmp($trackXML->Items->Item->ItemAttributes->ProductGroup, "Digital Music Track") == 0)
         		{
         			$amazonTracks[$i]['title'] = $trackXML->Items->Item->ItemAttributes->Title;
         			$amazonTracks[$i]['URL'] = $trackXML->Items->Item->DetailPageURL;  // Not a preview, still can't get preview out of Amazon API
         			$i++; // We could get fancy and page the original $resultingXML search results too...
         		}
         	}
			
			return $amazonTracks;
         }
         
         
         
         // Look for this data in Last.fm
         private function findCurrentAlbumTracksInLastFM()
         {
         	$lastFMTracks = null;
			
			$artistName = $this->currentAlbumArtist();
			$albumTitle = $this->currentAlbumTitle();
			try
			{
				$album = $this->getAlbumInfoFromLastFM($artistName, $albumTitle);  
			}
			catch (Exception $E)
			{
				// Just catch and move on, see if we can find info in another API
			}		
			
			if($album && ( ! empty($album['tracks']))) 
			{
				// We have found track info in last.fm not bad, but iTunes has playable previews, hopefully Last.fm will have them eventually
				/*
				print("<pre>");
				print_r($album);
				print("</pre>");
				*/
				$p = 0;
				$numberOfTracks = count($album['tracks']);
				while($p < $numberOfTracks)
				{
					$lastFMTracks[$p]['title'] = $album['tracks'][$p]['name'];
					$lastFMTracks[$p]['URL'] = $album['tracks'][$p]['url']; 
					$p++;
				}
			}
			
			return $lastFMTracks;
         }
         
         
         
         // Look for this data in MusicBrainz.org
         private function findCurrentAlbumTracksInMusicBrainz()
         {
         	$tracks = null;
         
			try
			{
				$musicBrainzAlbumInfo = $this->currentAlbumAsMusicBrainzRelease();
			}
			catch(Exception $e)
			{
				// This isn't the end of the world, we just have no track listings to return at all...
				$musicBrainzAlbumInfo = null;
			}
			if(  $musicBrainzAlbumInfo != null)
			{			
				$musicBrainzTracks = $musicBrainzAlbumInfo->getTracks();  // Should return an array of phpBrainz_Track Objects
				
				if (is_array($musicBrainzTracks))
				{
					// I think there is an error condition that doesn't return an array... in the phpBrainz code
					$numberOfTracks = count($musicBrainzTracks);
					$n = 0;
					while ( $n < $numberOfTracks)
					{
						$tracks[$n]['title'] = (string) $musicBrainzTracks[$n]->getTitle();  // It worked before...  It has to be an array I test for that!
						// There is no URL for tracks in MusicBrainz, yet another reason to do it last.
						$tracks[$n]['URL'] = $this->currentAlbumAmazonProductURL();  // Just a thought until I think of something better!
						// Lastly increase index
						$n++;
					}
				}
				else 
				{
					print("What the hell is being returned?");  // This should never happen!
					print("<pre>");
					print_r($musicBrainzTracks);
					print("</pre>");
				}
			}
			else
			{
				print('I tried to find track listings everywhere, even ' . '<a href="http://musicbrainz.org">MusicBrainz.org</a> maybe you can enter that data into their system.');  // Just to see how often this happens.
			}
			
			return $tracks;
         }
         
         
         
        /**
         * Returns the current album's tracks if I can find them.  First try to find playable previews in iTunes, then look for some sort of URL
         * for the track in Last.fm, lastly look in MusicBrainz...  Amazon.com support is now working, but without direct links to playable previews
         *
         * Searching for track links along with making the custom array means this method is very time consuming, potentially.
         *
         * @return array of tracks
         */
         public function currentAlbumsTracks()
         {         	
         	$tracks = array(); // Time for some serious modularity!  
      
         	// First check iTunes for track info
         	
         	$tracks = $this->findCurrentAlbumTracksInITunes();
         	
         	// I possibly should look in Last.fm before Amazon, I never make any money, but Last.fm returns all the tracks in the proper order...
         	// Amazon has previews although I can't get a direct link to them yet...
         	if ($tracks == null)
         	{
         		// Check Amazon
         		$tracks = $this->findCurrentAlbumTracksInAmazon();
         	}
         	
			if ($tracks == null)
			{
				// Check Last.fm
				$tracks = $this->findCurrentAlbumTracksInLastFM();
			}
         	
         	if ($tracks == null)
         	{
         		// Check MusicBrainz
         		$tracks = $this->findCurrentAlbumTracksInMusicBrainz();
         	}         	
         	
         	return $tracks; 
         }
         
         
         
        /**
         * Returns an array of the tags associated with the album in Last.fm.  I think Last.fm returns the top five tags.
         *
         * @return array of tracks
         */
         public function currentAlbumTags()
         {
         	$tags = array();
         	
         	$albumInfo = $this->getAlbumInfoFromLastFM($this->currentAlbumArtist(), $this->currentAlbumTitle());
         	
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
         * Returns some sort of audio sample if I can find it.  Amazon.com has previews but I never can access them so iTunes and Last.fm are the 
         * most likely places I'll get data.  Fetching all the tracks and previews for all the tracks like I try to do in currentAlbumTracks is 
         * very intensive and I get throttled by Amazon.com when I try too hard.  Thus I look for just a token audio sample here. 
         *
         * This method would benefit from Amazon.com's API providing support for this and being easier to work with, I do the best I can.
         *
         * @return string
         */
         public function currentAlbumAudioSample()
         {
         	$artistName = $this->currentAlbumArtist();
			$albumTitle = $this->currentAlbumTitle();
			
			// Now that I've written more code, I could try to find an audio sample with just an artist name if this yields nothing...
			// But why would I want to search using less information?
			
			return $this->audioSampleFor($albumTitle, $artistName);
         }
         
         
         
        /**
         * Returns the current album's cover image in medium size as a valid URL or a place holder image
         *
         * @return string
         */
         public function currentAlbumsMediumImageURL()
         {
         	$imageURL = $this->currentAlbumsImageURLOf("Medium");
         	
         	// If we can't find it in Amazon, we could try Last.fm
         	if(strcmp($imageURL, myInfo::MISSING_COVER_URL) == 0)
         	{
         		$albumInfo = $this->getAlbumInfoFromLastFM($this->currentAlbumArtist() , $this->currentAlbumTitle());
         		
         		if ( ! empty($albumInfo))
         		{
         			$possibleImageURL = $albumInfo['image']['medium'];
         			if ( ! empty($possibleImageURL))
         			{
         				$imageURL = $possibleImageURL;  // Last.fm has images, but they are not as good as Amazon or as correct. 
         			}
         		}
         	}
         
         	return $imageURL;
         }
         
         
         
        /**
         * Returns the current album's cover image in small size as a valid URL or a place holder image
         *
         * @return string
         */
         public function currentAlbumsSmallImageURL()
         {
         	// First check Amazon.com but after that check iTunes Music Store
         	$imageURL = $this->currentAlbumsImageURLOf("Small");
         	
         	if(strcmp($imageURL, myInfo::MISSING_COVER_URL) == 0)
         	{
         		// Try finding this album cover in iTunes using my latest greatest caching enabled method!
         		// This is much more work, so only do it as a last resort...
         		$albumInfo = $this->currentAlbumAsArray();
				$iTunesArtistInfo = $this->getArtistResultsFromITunes($albumInfo[0]);
				$iTunesAlbumInfo = $this->getAlbumAndTracksFromITunes($iTunesArtistInfo->results[0]->artistId, $albumInfo[1]); 
				if ($iTunesAlbumInfo != null)
				{
					$imageURL = $iTunesAlbumInfo->results[0]->artworkUrl100; // This is bigger than Amazon.com by 25 pixels but the browser can downsize...
				}
         	}
         	
			return $imageURL;
         }
         
         
         
        /**
         * Returns the current album's cover image in large size as a valid URL or a place holder image
         *
         * @return string
         */
         public function currentAlbumsLargeImageURL()
         {	
         	// If we can't find it in Amazon, we could try Last.fm
         	$imageURL = $this->currentAlbumsImageURLOf("Large");
         	
         	if(strcmp($imageURL, myInfo::MISSING_COVER_URL) == 0)
         	{
         		$albumInfo = $this->getAlbumInfoFromLastFM($this->currentAlbumArtist() , $this->currentAlbumTitle());
         		
         		if ( ! empty($albumInfo))
         		{
         			$possibleImageURL = $albumInfo['image']['large'];
         			if ( ! empty($possibleImageURL))
         			{
         				$imageURL = $possibleImageURL;
         			}
         		}
         	}
         
         	return $imageURL;
         }
         
         
         
         // This function is private so I don't have to worry about typos in fetching images of the wrong size
         // Amazon.com is the best source for large images, but images can also be sourced from other APIs, I just implemented Amazon first.
         private function currentAlbumsImageURLOf($size)
         {
         	$amazonXML = $this->currentAlbumAsAmazonXML();
         	
         	if (($amazonXML != null) && ($amazonXML->Items->TotalResults > 0))
         	{
				switch ($size) {
							case "Small":
								$imageURL = $amazonXML->Items->Item->SmallImage->URL;
								break;
							case "Medium":
								$imageURL = $amazonXML->Items->Item->MediumImage->URL;
								break;
							case "Large":
								$imageURL = $amazonXML->Items->Item->LargeImage->URL;
								break;
								}
				if(empty($imageURL))
				{
					$imageURL = myInfo::MISSING_COVER_URL;
				}
			}
         	else
         	{
         		// We couldn't find an image in Amazon so we need a generic image
         		$imageURL = myInfo::MISSING_COVER_URL;
         	}
         	
         	return $imageURL;
         }
         
         
         
       	/**
         * Returns true if we have some albums in our collection
         * 
         * @return boolean
         */
         public function hasAlbums()
         {
         	return $this->hasMembers();
         }
         
         
         
        /** 
         * This increases the currentAlbumIndex by one and if that is one too many goes back to first item in the albums array 
         *
         */
         public function goToNextAlbum()
         {
         	if( ! $this->isCurrentAlbumTheLast())
         	{
         		$this->currentMemberIndex++;
         	}
         	else
         	{
         		//We are at the last item in the array so go to the first
         		$this->currentMemberIndex = 0;
         	}
         }
         
        
        
        /**
         * This decreases the currentAlbumIndex by one and if that is one too few we go to the last item in the albums array
         *
         */
        public function goToPreviousAlbum()
        {
        	if($this->isCurrentAlbumTheLast())
        	{
        		$this->currentMemberIndex = ($this->collectionSize() - 1);
        	}
        	else
        	{
        		$this->currentMemberIndex--;
        	}
        }
        
        
        
        /**
         * Returns the currentAlbumIndex back to zero
         *
         */
        public function goToFirstAlbum()
        {
        	$this->currentMemberIndex = 0;
        }
        
        
        
        /**
         * This returns the first album for which we can find a valid large image url in Amazon.com
         * It also advances the currentAlbumIndex so you can then call getNextValidLargeAlbumCoverImageURL()
         *
         * @return string
         */
        public function getFirstValidLargeAlbumCoverImageURL()
        {
        	return $this->getFirstValidCoverImageURLOf("Large");
        }
        
        
        
        /**
         * This returns the first album for which we can find a valid medium image url in Amazon.com
         * It also advances the currentAlbumIndex so you can then call getNextValidMediumAlbumCoverImageURL()
         *
         * @return string
         */
        public function getFirstValidMediumAlbumCoverImageURL()
        {
        	return $this->getFirstValidCoverImageURLOf("Medium");
        }
        
        
        
        /**
         * This returns the first album for which we can find a valid small image url in Amazon.com
         * It also advances the currentAlbumIndex so you can then call getNextValidSmallAlbumCoverImageURL()
         *
         * @return string
         */
        public function getFirstValidSmallAlbumCoverImageURL()
        {
        	return $this->getFirstValidCoverImageURLOf("Small");
        }
        
        
        
        // Again I made a private function to do the work after I got it to work for one size
        // Other functions call this one passing in the correct valid image sizes...
        private function getFirstValidCoverImageURLOf($size)
        {
        	// First start at the beginning of the array regardless of where we are now.
        	$this->goToFirstAlbum();
        	$counter = 0;
        	$foundValidURL = false;
        	
        	while( ($counter < $this->collectionSize()) && ( ! $foundValidURL))
        	{
        		$potentialURL = $this->currentAlbumsImageURLOf($size);
        		if(strcmp($potentialURL, myInfo::MISSING_COVER_URL) == 0)
        		{
        			// We've found nothing useful
        			$this->goToNextAlbum();
        		}
        		else
        		{
        			$foundValidURL = true;
        		}
        		$counter = $counter + 1;  // Always increase the counter
        	}
        	
        	if(!$foundValidURL)
        	{
        		throw new Exception('There are no valid URLs for images of ' . $size . ' in this albumCollection');
        	}
        	
        	return $potentialURL;
        }
        
        
        
        /**
         * This returns the first album for which we can find a valid small image url
         * It also advances the currentAlbumIndex as many times as necessary or until the end of the array
         *
         * @return string
         */
        public function getNextValidSmallAlbumCoverImageURL()
        {     	
        	return $this->getNextValidAlbumCoverImageURLOf("Small");
        }
        
        
        
        /**
         * This returns the first album for which we can find a valid medium image url in Amazon.com
         * It also advances the currentAlbumIndex as many times as necessary or until the end of the array
         *
         * @return string
         */
        public function getNextValidMediumAlbumCoverImageURL()
        {     	
        	return $this->getNextValidAlbumCoverImageURLOf("Medium");
        }
        
        
        
        /**
         * This returns the first album for which we can find a valid large image url in Amazon.com
         * It also advances the currentAlbumIndex as many times as necessary or until the end of the array
         *
         * @return string
         */
        public function getNextValidLargeAlbumCoverImageURL()
        {     	
        	return $this->getNextValidAlbumCoverImageURLOf("Large");
        }
        
        
        
        private function getNextValidAlbumCoverImageURLOf($size)
        {
        	$this->goToNextAlbum();  // First advance to the next item 
        	
        	if ($this->isCurrentAlbumTheLast())
        	{
        		// This is the last album in the index
				$potentialURL = $this->currentAlbumsImageURLOf($size);
				if(strcmp($potentialURL, myInfo::MISSING_COVER_URL) == 0)
				{
					// We've found nothing useful
					$potentialURL = null;
				}
        	}
        	elseif($this->isCurrentAlbumTheFirst())
        	{
        		// We've looped around return null 
        		$potentialURL = null;
        	}
        	else
        	{
        		// Neither the first nor the last album in the array.
				$potentialURL = $this->currentAlbumsImageURLOf($size);
				if(strcmp($potentialURL, myInfo::MISSING_COVER_URL) == 0)
				{
					$this->goToNextAlbum();  //am I going forward two at a time?
					// Even if I am it is working like this to a degree...
					$potentialURL = $this->getNextValidAlbumCoverImageURLOf($size);
				}
			}
        	
        	return $potentialURL;
        }
        
        
        
        private function isCurrentAlbumTheLast()
        {
        	return $this->isCurrentMemberTheLast();
        }
        
        
        
        private function isCurrentAlbumTheFirst()
        {	
        	return $this->isCurrentMemberTheFirst();
        }
         
         
         
        // Compilation albums screw up album getInfo fetches to Last.fm due to there being an actual band called "various"
        private function isCurrentAlbumByVarious()
        {
         	$byVarious = false;
         	$currentAlbum = $this->currentAlbumAsArray();
         	if(strcasecmp($currentAlbum[0], "various") == 0)  // Changed to case insensitive not sure if that is a big deal after all
         	{
         		$byVarious = true;
         	}
         	
         	return $byVarious;
        }
	}
?>