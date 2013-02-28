<?php

	/**
	 * This class is for creating mashups out of collections of quotations, it is a subclass of musicCollection as many of my quotations are song lyrics.
	 * I pass in a CSV file to the mashup I made. Originally I used two columns in the CSV file but in order to improve the results especially for song
	 * lyrics and film quotations I've added two more columns.
	 *
	 * 1st Column : Source 
	 * 2nd Column : Quotation 
	 * 3rd Column : one of person, song, movie, or musician
	 * 4th Column : for songs the song title, for movies the director's name
	 *
	 * @author Muskie McKay <andrew@muschamp.ca>
     * @link http://www.muschamp.ca
     * @version 1.2
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
	
	require_once('musicCollection.php');

	class quotationCollection extends musicCollection
	{
	
		const MAX_TWEET_SIZE = 114; // This isn't 140 as you need to allow for the link, some white space, and probably a RT or hashtag such as #qotd
		
	   /**
		* Constructor for Quotation Collection
		*
		* @param input can vary and what type determines how the class is initialized/created see parent method.
		*/
		public function __construct($input)
		{
			parent::__construct($input);
		}
	
	
		
	   /**
		* Initialize a Quotation Collection
		*
		*  I haven't overiden the constructer, but by overiding this method, I can more easily add new APIs, which I currently don't for this subclass
		*/
		protected function initializeAPIs()
		{
			parent::initializeAPIs();
		}
		
		
		
		/**
		 * This is a method that shouldn't be called much as it is a brute force dump of the entire quotation collection
		 * linking to the Wikipedia for the source, when possible.
		 * 
		 */
		 public function outputEntireCollection()
		 {
			if($this->hasMembers())
			{
				// Highly likely we find something in some database in this case
				print("<ul>");
			}
		 
			foreach($this->theCollection as $quotationInfo)
			{
				$results = $this->searchWikipediaFor($quotationInfo[0]);  // Will eventually possibly do something more clever
				if ($results->Section->Item != NULL)
				{
					print('<li>');
					print('<a href="' . $results->Section->Item->Url . '">' . $quotationInfo[0] . '</a>:');
					print('<br />');
					print('<blockquote>');
					print($quotationInfo[1]);
					print('</blockquote>');
					print('</li>');
				}
			}
			
			if($this->hasMembers())
			{
				print("</ul>");
			}
		 }
         
         
         
        /**
         * Searches for a random collection member, I use the time stamp in order to limit repeats when someone browses the entire collection.
         * They'll still miss some quotations and it will wrap around but I think it is fine.
         *
         * @return array 
         */
         public function randomQuotation()
         {	
         	$seedNumber = time();
         	$randomNumber = $seedNumber % $this->collectionSize();
         	$this->currentMemberIndex = $randomNumber;
         	$aMember = $this->currentMemberAsArray();
         
         	return $aMember;
         }
         
         
         
       	/**
       	 * This method like many before it, searches Amazon's Product API for information about the quotation described in the array.
       	 * I use a third field/column to give hints on what too look for in Amazon and other APIs.  I also cache the results and in some
       	 * cases we may have the information we are looking for already cached locally.  A lot of information can be returned, we usually just use the
       	 * first item ie $result->Items->Item[0]
       	 *
       	 * @param array
       	 * @return Simple XML object
       	 */
         private function getInfoFromAmazonFor($quotation)
         {   
         	if(count($quotation) >= 3) // 3rd value is hint about source of quotation
         	{
				$validFilename = preg_replace("/[^a-zA-Z0-9]/", "", $quotation[0]);
	
				if(is_array($quotation) && strlen($validFilename) > 0)
				{
					$myCache = new Caching("./MashupCache/Amazon/", $validFilename, 'xml');
					
					if ($myCache->needToRenewData())
					{		
						try
						{
							// Going to have a three (or eventually more) pronged approach that will require new methods in the Amazon API class.
							if($this->isFromFilm($quotation))
							{
								// We have a quotation from a movie, look in Film section of Amazon
								$result = $this->amazonAPI->getDVDCoverByTitle($quotation[0]);
							}
							else if(($this->isFromSong($quotation)) || ($this->isFromMusician($quotation)))
							{
								// We have a quotation from a song or musician, looks in music section of Amazon 
								$result = $this->amazonAPI->getInfoForSongwriter($quotation[0]);
							}
							else
							{
								// We have a quotation, look for book by or about author 
								$result = $this->amazonAPI->getBookForKeyword($quotation[0]);
							}
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
					throw new Exception('Incorrect data type passed to getInfoFromAmazonFor()');
				}
			}
			else
			{
				$result = null;  // No info found in Amazon 
			}
	
			
			return $result;
         }
         
          
         
        /**
         * Returns the results from a querry to the Amazon Product API for the current quotation 
         *
         * @return SimpleXML Object
         */
         public function getInfoFromAmazonForCurrentQuotation()
         {
         	return $this->getInfoFromAmazonFor($this->currentMemberAsArray());
         }
         
         
         
        /** 
         * Searches the Wikipedia for the passed in quotation in array format.  I use the third column to improve search accuracy.
         *
         * @param array
         * @return Simple XML object 
         */
        protected function searchWikipediaForQuotation($quotation)
        {
        	// Should this method be called searchWikipediaFor($quotation)? no one reviews my code anymore and PHP has such poor naming convention adherence
			
			if($this->isFromFilm($quotation))
			{
				// We have a quotation from a movie
				$searchString = $quotation[0] . ' (film)';
				$result = $this->searchWikipediaFor($searchString);
			}
			else if(($this->isFromSong($quotation)) || ($this->isFromMusician($quotation)))
			{
				// We have a quotation from a song thus by a songwriter or by a musician.
				$searchString = $quotation[0] . ' (musician)'; // This helps in some cases but not in the case of bands....
				$result = $this->searchWikipediaFor($searchString);
			}
			else
			{ 
				// This works for most everyone else but for popular names like Joe Smith will not. 
				// Famous people like Albert Einstein and Nietzsch seem to not be successfully found even given full name
				$result = $this->searchWikipediaFor($quotation[0]);
			}
			
			return $result;
        }
        
        
        
    	/** 
         * Searches the Wikipedia for the current quotation
         *
         * @return Simple XML object 
         */
        public function wikipediaInfoForCurrentQuotation()
        {
        	return $this->searchWikipediaForQuotation($this->currentMemberAsArray());
        }
        
        
        
        // subclass can't use this so override 
        public function audioSampleForCurrentAlbumsFavouriteSong()
        {
        	throw new Exception('quotationCollection.php does not support the method audioSampleForCurrentAlbumsFavouriteSong');
        }
        
        
        
        // this subclass can't use this method either
        public function lastFMAlbumTags()
        {
            throw new Exception('quotationCollection.php does not support the method lastFMAlbumTags');
        }
        
        
        
        // This next method was stolen from movieCollection.php as I also quote from films frequently
        // Switched from IMDBAPI which is unofficial to Rotten Tomatoes, which is more hoops, but hopefully less likely to disappear in a lawsuit
        
       /**
		* Searches Rotten Tomatoes dot com for a film matching the title passed in.  Returns the decoded JSON object.
		* Director's name is used to ensure the correct film is found as many films have had the same title over the years.
		*
		* @param string
		* @param string
		* @return decoded JSON object 
		*/
		protected function searchRottenTomatoesFor($title, $director)
		{
			// This method is based upon sample code from http://developer.rottentomatoes.com/docs/read/json/v10/examples
			// It is also based on methods I've written in musicCollection.php and albumCollection.php 
			
			$movieInfo = NULL;
			
			// first check that we don't have a local chached version, no reason to get lazy
			$properFileName = preg_replace("/[^a-zA-Z0-9]/", "", $title);
			
			if(strlen($properFileName) > 0)
			{
				$myCache = new Caching("./MashupCache/RottenTomatoes/", $properFileName);
				
				if ($myCache->needToRenewData())
				{
					try
					{
						$q = urlencode($title); // make sure to url encode query parameters
						 
						// construct the query with our apikey and the query we want to make
						$query = 'http://api.rottentomatoes.com/api/public/v1.0/movies.json?apikey=' . myInfo::MY_ROTTEN_TOMATOES_KEY . '&q=' . $q;
						
						$lookUpResult = fetchThisURL($query);
						
						// print("<pre>");
						// print_r($lookUpResult);
						// print("</pre>");
						 
						// decode the json data to make it easier to parse
						$searchResults = json_decode($lookUpResult);
						
						if ( ! empty($searchResults))
						{
						  // Now need to iterate to the first movie, cache it and return it...
						  
						  foreach($searchResults->movies as $movie)
						  {
						  	$newAPIURL = $movie->links->self;
						  	$targetURL = $newAPIURL . '?apikey=' . myInfo::MY_ROTTEN_TOMATOES_KEY;
						  	$nextLookUp = fetchThisURL($targetURL);
						  	$possibleMatch = json_decode($nextLookUp);

							// line below was causing issues, may just simplify to returning first film with title...
							// Do some films in Rotten Tomatoe not have the directors? Add additional conditionals 
							if(( ! empty($possibleMatch->abridged_directors[0])) && ( ! empty($director)))
							{
								if( strcasecmp($possibleMatch->abridged_directors[0]->name, $director) == 0)
								{
									// This is the best match 
									$movieInfo = $possibleMatch;
								}
							}
						  }
						  
						  if($movieInfo == NULL)
						  {
						  	$movieInfo = $searchResults->movies[0]; 
						  	// I thought this might always happen, but if you don't know the director it will default to this!
						  }
						  
						  $serializedObject = serialize($movieInfo);
						  $myCache->saveSerializedDataToFile($serializedObject);
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
					$movieInfo =  $myCache->getUnserializedData();
				}
			}
			
			return $movieInfo;
		}	
	   
	   
	   
	    /**
		 * Returns the source of the current quotation
		 *
		 * @return string
		 */
		 public function currentQuotationSource()
		 {
		 	return $this->currentArtist();
		 }
		 
		 
		 
		/**
		 * Returns the current quotation ie the second cell in the array
		 *
		 * @return string
		 */
		 public function currentQuotation()
		 {
		 	$arrayVersion = $this->currentMemberAsArray();
		 	
		 	return $arrayVersion[1];
		 }
		 
		 
		 
		/**
		 * Returns the current quotation ie the second cell in the array, with all HTML tags removed.
		 *
		 * @return string
		 */
		 public function currentQuotationWithoutHTML()
		 {
		 	$arrayVersion = $this->currentMemberAsArray();
		 	
		 	return strip_tags($arrayVersion[1]);  // Tempted to leave line breaks in...
		 }
		 
		 
		 
		/**
		 * Returns a hint regarding the type and source of the current quotation, ie the third column/cell
		 *
		 * @return string
		 */
		 public function currentQuotationType()
		 {
		 	$arrayVersion = $this->currentMemberAsArray();
		 	
		 	return $arrayVersion[2];
		 }
		 
		 
		 
		/**
		 * Returns the song title of the song being quoted
		 *
		 * @return string
		 */
		 public function currentQuotationSongTitle()
		 {
		 	$songTitle = null;
		 	
		 	//If the quotation isn't from a song, they shouldn't be calling this, but let them eat Null.
		 	
		 	if($this->isCurrentQuotationFromASong())
		 	{
		 		$arrayVersion = $this->currentMemberAsArray();
		 		$songTitle = $arrayVersion[3];
		 	}
		 	
		 	return $songTitle;
		 }
		 
		 
		 
		/**
		 * Returns the director of the film that is being quoted
		 *
		 * @return string
		 */
		 public function currentQuotationFilmDirector()
		 {
		 	$filmDirector = null;
		 	
		 	//If the quotation isn't from a film, they shouldn't be calling this, but let them eat Null.
		 	
		 	if($this->isCurrentQuotationFromAFilm())
		 	{
		 		$arrayVersion = $this->currentMemberAsArray();
		 		// This next line is also causing trouble for a film....
		 		$filmDirector = $arrayVersion[3];
		 	}
		 	
		 	return $filmDirector;
		 }
	   
	   
	   
	    /**
	     * Returns the length of the current quotation after all HTML tags have been removed 
	     *
	     * @return int 
	     */
	    public function currentQuotationActualLength()
	    {
	    	return strlen($this->currentQuotationWithoutHTML());
	    }
	   
	   
	   
		/**
		 * Returns true if the current quotations length with HTML tags removed is below MAX_TWEET_SIZE
		 *
		 * @return bool 
		 */
		public function isCurrentQuotationTweetable()
		{
			// The tweet this button will add a link like:
			// http://t.co/uO4Piib
			// That is 18 characters long, plus white space, call it 20, so quotations need a length of less than 120 characters are tweetable.
			
			// I've decided to try tweeting longer quotations, tweeting the first say 100 characters adding a " to the front and a ... to the back
			
			$isTweetable = false;
			
			if ($this->currentQuotationActualLength() < self::MAX_TWEET_SIZE)
			{
				$isTweetable = true;
			}
			
			return $isTweetable; 
		}
		 
	   
	   
	  /**
	   * This method looks at a variety of sources in descending priority to find a decent sized image of the author/source of the quotation 
	   *
	   * @return string representing URL to image 
	   */
	   public function authorImageForCurrentQuotation()
	   {
	   		$imageURL = NULL;
	   		// Wikipedia doesn't have very useful images returned in their API.  
	   		// Amazon is alright but not perfect for people, works fine for CD and DVD covers though...
	   		// Switched to smaller images in some cases, could easily switch back.
	   		
	   		if($this->isCurrentQuotationFromAFilm())
			{
				$movieInfo = $this->searchRottenTomatoesFor($this->currentQuotationSource(), $this->currentQuotationFilmDirector());
				$imageURL = $movieInfo->posters->detailed; // Could use a smaller image... see http://developer.rottentomatoes.com/docs
			}
			else if(($this->isCurrentQuotationFromASong()) || ($this->isCurrentQuotationFromAMusician())) 
			{
				// We have a quotation from a song thus by a songwriter or musician
				$lastFMImageArray = $this->getCurrentArtistPhotoFromLastFM();
				
				if( ! empty($lastFMImageArray))
				{
					// Not sure why this didn't work for Bob Marley, Last.fm may have made changes and no one really maintains the PHP API code 
					try
					{
						$imageURL = $lastFMImageArray['largeURL'];  
					}
					catch(Exception $e)
					{
						// No large image for Bob Marley and possibly others...
						// Just catch exception and let it try Flickr where there is an image of Bob Marley and most everything...
					}
				}
			}
			else
			{
				// We have a quotation by a person or ficticious character
				// Amazon isn't perfect but testing revealed it was superior to Wikipedia 
				$amazonXML = $this->getInfoFromAmazonForCurrentQuotation();
				if ((( ! empty($amazonXML)) 
					&& ($amazonXML->Items->TotalResults > 0)))
				{
					$imageURL = $amazonXML->Items->Item->MediumImage->URL;
				}
			}
			
			if(empty($imageURL))
			{
				// No image so far try flickr!
				// This probably ensures something is always returned for every quotation, even if it is irrelevant
				$flickrResults = $this->getCurrentArtistPhotosFromFlickr();
				if ( ! empty($flickrResults['photo']))
				{
					$imageURL = $flickrResults['photo'][0]['url_s'];
					// Got Undefined Offset error once...
					// May have to surround this with a try catch construct
				} 
			}
			
			return $imageURL;
	   }
	   
	   
	   
	   // The next three methods were borrowed and adapted from dvdCollection.php because lots of quotations are from films 
	   
	    /**
         * Returns the current quotation's ASIN which is a unique identifier used for Amazon.com in their webstore.  
         *
         * @return string
         */
         private function currentQuotationASIN()
         {
         	$productASIN = null;
         	
         	$productXML = $this->getInfoFromAmazonForCurrentQuotation();
         	
         	if($productXML->Items->TotalResults > 0) 
         	{
         		$productASIN = $productXML->Items->Item->ASIN;
         	}
         	
         	return $productASIN;
         }
         
         
         
        /**
         * This method just returns the URL to the product page using the ASIN and will append on your Amazon Associate ID so you can
         * potentially earn a commision.  If the item isn't in Amazon, well return the hash symbol which just reloads the page when clicked...
         *
         * @return string;
         */
         public function currentQuotationAmazonProductURL()
         {
         	$asin = $this->currentQuotationASIN();
         	
         	if($asin != null)
         	{
         		// I'm thinking of doing something a lot clever, as Amazon has stores for major artists like Bob Dylan and Gordon Lightfoot, of course,
         		// I wouldn't get any referral income, but then I don't get any right now as things are...
         		$amazonProductURL = $this->amazonProductURLFor($asin);
         	}
         	else
         	{
         		// I wonder if returning hashtag was a bad design decision, but everything has worked for years with occaisional needs to recode due to
         		// API changes...
         		$amazonProductURL = "#"; // return hash instead of null or empty string so it just reloads the page
         	}
         	
         	return $amazonProductURL;
         }
         
         
         
        /**
         * Returns a string consisting of a link and an image (icon) to the product on Amazon.com, I decided to return valid HTML as I thought this
         * would save some time later on and for some services it is much more work to get the correct info and link to work.  The link returned has
         * an Amazon Associate tag as detailed here: 
         * http://www.kavoir.com/2009/05/build-simple-amazon-affiliate-text-links-with-just-asin-10-digit-isbn-and-your-amazon-associate-tracking-id.html
         *
         * @return string;
         */
         public function currentQuotationAmazonAssociateBadge()
         {
            $htmlTag = NULL;
                        
            if($this->isCurrentQuotationFromASong())
            {
            	// This is where I could get clever...
            	// What I need is an API to this:
            	// https://artistcentral.amazon.com/?ref=aspfaq
            	$amazonProductURL = $this->currentQuotationAmazonProductURL();
            }
            else
            {
            	$amazonProductURL = $this->currentQuotationAmazonProductURL();
            }
         	
         	
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
         * This method searches various APIs to find a short bunch of text describing the source of the quotation. Despite best efforts
         * it isn't always possible to find a biography.
         *
         * @return string 
         */
         public function currentQuotationSourceBio()
         {
         	$bio = NULL;
         	
         	// I don't understand why it doesn't find a good bio for Albert Einstein who is most definitely in the Wikipedia 
	   		
	   		if($this->isCurrentQuotationFromAFilm())
			{
				// We have a quotation from a movie, switching to Rotten Tomatoes for movie related info
				$movieInfo = $this->searchRottenTomatoesFor($this->currentQuotationSource(), $this->currentQuotationFilmDirector());
				/*
				print("<pre>");
				print_r($movieInfo);
				print("</pre>");
				*/
				// Some films have no synopsis in Rotten Tomatoes, can't use critics_consensus instead as it does not always exist for every result either!
				$bio = $movieInfo->synopsis;
			}
			else if(($this->isCurrentQuotationFromASong()) || ($this->isCurrentQuotationFromAMusician())) 
			{
				// Going with Last.fm here which might just use the description from Wikipedia anyway...
				$lastFMInfo = $this->getArtistInfoFromLastFM($this->currentQuotationSource());
				$bio = $lastFMInfo["bio"]["summary"];  // Possibly emtpy for obscure artists
			}
			else
			{
				// Wikia descriptions are so brief, tempted to try Amazon instead...
				$wikiXML = $this->wikipediaInfoForCurrentQuotation();
				$bio = $wikiXML->Section->Item->Description;
			}
			
			if(empty($bio))
			{  
				// This happens sometimes, Rotten Tomatoes seems to have blank sysnopsis, but Wikipedia can also let you down, so no bio is possible
				$wikiXML = $this->wikipediaInfoForCurrentQuotation();
				$bio = $wikiXML->Section->Item->Description;
			}
			
			return $bio;
         }
         
         
         
        /**
         * This method returns a Facebook Like button for the most likely page for the source of the current quotation.
         *
         * @return string of valid HTML
         */
         public function facebookLikeButtonForCurrentQuotationSource()
         {
         	// I've slowly started using the fancier like buttons which requires additional data to be put inside the <head> portion of a webpage
         	// Thus I no longer use this method in my current favourite quotation mashup
         	return $this->facebookLikeButtonFor($this->currentQuotationSource());
         }
         
         
         
        /**
         * This method searches YouTube to try and find an appropriate video clip four the source of the quotation 
         *
         * @return string 
         */
         public function youtubeClipForCurrentQuotationSource()
         {
         	$clipHTML = NULL;
	   		
	   		if($this->isCurrentQuotationFromAFilm())
			{
				// I did a lot of expirementation trying to find the trailer for films using my YouTube search code.  It is less than 100% successful. 
				$searchString = 'theatrical trailer for "' . $this->currentQuotationSource() . '" directed by ' . $this->currentQuotationFilmDirector();  // need to include director...
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			else if($this->isCurrentQuotationFromASong())
			{
				$searchString = '"' . $this->currentQuotationSource() . ' ' . $this->currentQuotationSongTitle() . '"';
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			else if($this->isCurrentQuotationFromAMusician())
			{
				// Most musicians have clips of them performing as before recording technology was invented it was the composer that 
				// was famous, more so than the performer...
				$searchString = '"' . $this->currentQuotationSource() . ' plays"'; // live did not find anything useful for Pat MacDonald
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			else
			{
				// We have a quotation by a person or ficticious person
				// This is proving very disappointing especially for the long dead, best not to call this method for non-films and non-songs
				$wikiXML = $this->wikipediaInfoForCurrentQuotation();
				$searchString = $this->currentQuotationSource() . ' ' . $wikiXML->Section->Item->Description;
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			
			if ($clipHTML == NULL)
			{
				// This could easily happen, in which case I could display no YouTube clip...
				// However try one last time
				// The Princess Bride must be well policed in YouTube as I think that film triggers this condition among others...
				$searchString = '"' . $this->currentQuotationSource() . '" clip';
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			
			return $clipHTML;
         }
         
         
         
        /**
         * This method creates a fully functional "Tweet This" button.  You don't need to register an app with Twitter to do this.
         * It uses Twitter's latest Javascript but takes in two arguments, both strings, apparently you shouldn't URL encode the URL.
         *
         * More information on Twitter buttons can be found here:
         * https://dev.twitter.com/docs/tweet-button
         *
         * @param string
         * @param string
         * @return string
         */
         public function tweetThisButton($quotation, $twitterDataURL = myInfo::MY_HOME_PAGE)
         {
         	$tweetThisButtonCode = null;
         	
         	// We should also strip the quotation passed in of junk and HTML tags...
         	$newLinesVersion = str_replace('<br />', "\n", $quotation);
         	$quotationWithNewLine = $newLinesVersion . "\n";
         	
         	if($this->isCurrentQuotationTweetable())
         	{
         		$openingTag = '<a href="http://twitter.com/share" class="twitter-share-button" data-url="' . $twitterDataURL . '" data-text="' . strip_tags($quotationWithNewLine) . '">';
         		$closingTag = '</a><script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';
         	}
         	else
         	{
         		// Need to chomp then Tweet.
         		$excerpt = substr(strip_tags($quotationWithNewLine), 0, 100) . '...';
         		$openingTag = '<a href="http://twitter.com/share" class="twitter-share-button" data-url="' . $twitterDataURL . '" data-text="' . $excerpt . '">';
         		$closingTag = '</a><script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';	
         	}
         	
         	// Try not encoding $twitterDataURL, seems to work this way and not the other...

         	$tweetThisButtonCode = $openingTag . "Tweet" . $closingTag;
         	
         	return $tweetThisButtonCode;
         }
         
         
         
        /**
         * This method checks to see if the passed in quotation (array) is from a film by checking the third column 
         *
         * @param array
         * @return boolean 
         */
         public function isFromFilm($quotation)
         {
         	$result = false;
         	
         	// Should check that $quotation[2] is not null first....
         	if(isset($quotation[2])) 
         	{
				if(strcmp(trim($quotation[2]), 'movie') == 0)
				{
					$result = true;
				}
			}
         	
         	
         	return $result;
         }
         
         
         
        /**
         * This is a convience method to see if the current quotation is from a film 
         *
         * @return boolean
         */
        public function isCurrentQuotationFromAFilm()
        {
        	return $this->isFromFilm($this->currentMemberAsArray());
        }
        
        
        
        /**
         * This method checks to see if the passed in quotation (array) is from a song by checking the third column 
         *
         * @param array
         * @return boolean 
         */
         public function isFromSong($quotation)
         {
         	$result = false;
         	
         	// Should check that $quotation[2] is not null first....
         	if(isset($quotation[2])) 
         	{
         		if(strcmp(trim($quotation[2]), 'song') == 0)
				{
					$result = true;
				}
			}
         	
         	
         	return $result;
         }
         
         
         
        /**
         * This is a convience method to see if the current quotation is from a song
         *
         * @return bool
         */
        public function isCurrentQuotationFromASong()
        {
        	return $this->isFromSong($this->currentMemberAsArray());
        }
        
        
        
    	/**
         * This method checks to see if the passed in quotation (array) is from a musician but not a song by checking the third column 
         *
         * @param array
         * @return boolean 
         */
         public function isFromMusician($quotation)
         {
         	$result = false;
         	
         	// Should check that $quotation[2] is not null first....
         	if(isset($quotation[2])) 
         	{
				if(strcmp(trim($quotation[2]), 'musician') == 0)
				{
					$result = true;
				}
			}
         	
         	
         	return $result;
         }
         
         
         
        /**
         * This is a convience method to see if the current quotation is from a musician not a song
         *
         * @return bool
         */
        public function isCurrentQuotationFromAMusician()
        {
        	return $this->isFromMusician($this->currentMemberAsArray());
        }
        
        
        
		/**
		 * This method only makes sense to call when the source of the quotation is a song or a singer, there must be a way to enforce this in PHP,
		 * but in the mean time I'll base my method on one I've already written in albumCollection.php 
		 *
		 * @param string
		 * @return string of valid HTML
		 */
		 public function lastFMBadgeForQuotationSource($quotationSource)
		 {
				$htmlTag = null;  // was '#'
				
				try
				{
					$artistInfo = $this->getArtistInfoFromLastFM($quotationSource);
				}
				catch(Exception $e)
				{	
					// Passing in two artists, such as co-songwriters is causing issues, catch and set results to null...
					$artistInfo = null;
				}
				
				if($artistInfo != null)
				{
					$openLinkTag = '<a href="' . $artistInfo["url"] . '" >';
					$closeLinkTag = '</a>';
					$iconTag = '<img src="' . myInfo::LAST_FM_ICON_URL . '" class="iconImage" />';
					
					$htmlTag = $openLinkTag . $iconTag . $closeLinkTag;
				}
				
				return $htmlTag;
		 }
		 
		 
		 
		 // This method is overriden and deprechiated as I wrote a more versatile one 
		 protected function getArtistResultsFromITunes($artistName)
		 {
			throw new Exception('Please use getResultsFromITunesForSourceOfQuotation() instead');
		 }
		 
		 
		 
     	/**
     	 * This method searches the iTunes store and returns the artist page, or best guess at the product page.
     	 *
     	 * @param array
     	 * @return Simple XML object
     	 */
     	 protected function getResultsFromITunesForSourceOfQuotation($quotation)
     	 {
     	 	// This method replaces getArtistResultsFromITunes() but follows the basic technique caching the results.
     	 	
     	 	$iTunesInfo = null;
		 
			$strippedSource = $quotation[0];
			$strippedSource = preg_replace("/[^a-zA-Z0-9]/", "", $strippedSource);
			
			if(is_string($quotation[0]) && strlen($strippedSource) > 0)
			{
				$myCache = new Caching("./MashupCache/iTunes/", $strippedSource);
				
				if ($myCache->needToRenewData())
				{
					try
					{	
						// Now we will have a three or more pronged approach, just like many methods in this class 
						
						if($this->isFromFilm($quotation))
						{
							// Here we want media to be movie 
							$formattedSource = str_replace(' ', '+', $quotation[0]);
							$iTunesSearchString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedSource . '&entity=movie&media=movie';

						}
						else if (($this->isFromSong($quotation)) || ($this->isFromMusician($quotation))) 
						{
							// This can be the same give or take as the parent class, searching for an artist page.
							$formattedArtistString = str_replace(' ', '+', $quotation[0]);
							$iTunesSearchString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedArtistString . '&entity=musicArtist';
						}
						else
						{
							// This is going to be less likely to return results from the iTunes store, but it has so much stuff now so who knows
							// Going to go with media of type ebook
							$formattedSource = str_replace(' ', '+', $quotation[0]);
							$iTunesSearchString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedSource . '&entity=ebook&media=ebook';
						}
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
				throw new Exception('Incorrect data type passed to getResultsFromITunesForSourceOfQuotation()');
			}
			
			return $iTunesInfo;
     	 	
     	 }
     
     
     
    	/**
         * Returns a string consisting of a link and an image (icon) for the Apple iTunes store, the link goes to the 
         * artist info page or another appropriate page.  I decided to return valid HTML as I thought this
         * would save some time later on and for some services it is much more involved to get the correct info and link to work.
         * Apple's iTunes Associate program isn't available in Canada but if it were, this is where you'd want to put in your associate ID
         *
         * This method has proved problematic as I quote from songs, films, and long dead people. Musicians needed a special case as originally
         * the iTunes Music Store only had music....
         *
         * I now cache the JSON results returned from Apple as serialized objects in the method getResultsFromITunesForSourceOfQuotation()
         *
         * @return string;
         */
         public function iTunesBadgeForCurrentQuotation()
         {
         	$finalHTML = null;
         	
         	try
         	{
         		$iTunesInfo= $this->getResultsFromITunesForSourceOfQuotation($this->currentMemberAsArray());
         	}
         	catch(Exception $e)
         	{
         		throw new Exception("Something went wrong while attempting to access iTunes data on: " . $this->currentQuotationSource());
         	}
    
			if ( ($iTunesInfo != NULL) && ($iTunesInfo->resultCount > 0))
			{	

				if ($this->isCurrentQuotationFromAFilm())
				{
					$iTunesArtistLink = $iTunesInfo->results[0]->trackViewUrl;  
				}
				else if(($this->isCurrentQuotationFromASong()) || ($this->isCurrentQuotationFromAMusician()))
				{
					// Musicians who write books such as Steve Earle can be problematic. Frank Zappa too apparently now.
					if (isset($iTunesInfo->results[0]->artistLinkUrl))
					{
						$iTunesArtistLink = $iTunesInfo->results[0]->artistLinkUrl;
					}
					else
					{
						// May want to refactor but first get it working for Frank Zappa
						$iTunesArtistLink = $iTunesInfo->results[0]->artistViewUrl;
					}
				}
				else
				{
					// Regularly got warning:
					// Undefined property: stdClass::$artistViewUrl in /home/muskie/domains/muschamp.ca/public_html/Muskie/quotationCollection.php
					// Always happened with Miles Davis http://www.muschamp.ca/Muskie/favouriteQuotationsMashup.php?q=181
					// Fixed above by adding a check for quotations by musicians but not from songs.
					$iTunesArtistLink = $iTunesInfo->results[0]->artistViewUrl; 
				}
			
				$openLinkTag = '<a href="' . $iTunesArtistLink . '" >';
				$closeLinkTag = '</a>';
				$iconTag = '<img src="' . myInfo::APPLE_ICON_URL . '" class="iconImage" />';
				$finalHTML = $openLinkTag . $iconTag . $closeLinkTag;
			}
			
			return $finalHTML;
         }
	}
?>