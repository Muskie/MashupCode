<?php
    /**
     * Class to create a collection of dvds
     * @author Muskie McKay
     * @link http://www.muschamp.ca
     * @version 0.8.1
     * This is a simple subclass of mCollection.php to show that it could be used for mashups not related to music.
     * 
     * This class inherrits from movieCollection.php which inherrits from mCollection.php
     *
     */  
    
    /*
    Permission is hereby granted, free of charge, to any person obtaining a
    copy of this software and associated documentation files (the "Software"),
    to deal in the Software without restriction, including without limitation
    the rights to use, copy, modify, merge, publish, distribute, sublicense,
    and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:
    
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
    THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
    DEALINGS IN THE SOFTWARE.
    */
    
    require_once('./movieCollection.php');

	class dvdCollection extends movieCollection
	{	          
		const NO_ROTTEN_TOMATOE_POSTER_URL = "http://images.rottentomatoescdn.com/images/redesign/poster_default.gif";
	
	   /**
		* Initialize a DVD Collection
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
		*  I haven't overided the constructer, but by overiding this method, I can add support for different APIs at different levels.
		*/
		protected function initializeAPIs()
		{
			parent::initializeAPIs();
			
		}
			
			
		/**
		 * This is a method that shouldn't be called much as it is a brute force dump of the entire album collection
		 * fetching the images from Amazon and linking to information about the movie fetched from Rotten Tomatoes, this could be a lot of 
		 * calls to APIs, but it is what I originally thought about doing, so I implemented it as further proof of concept
		 * 
		 * Warning: Calling this method on a collection of more than single digits is really slow, not sure how I'll speed it up...
		 * 
		 */
		 public function galleryForEntireCollection()
		 {
			// This can take a long time to run the very first time on large collections, I don't recommend using this method in that case
			if($this->hasMembers())
			{
				// Highly likely we find something in some database in this case
				print("<ul id='coverGallery'>");
			}
		 
			foreach($this->theCollection as $member)
			{
				$dvdCoverURL = NULL;
				$dvdTitle = $member[1];
				$director = $member[0];
				$amazonXML = $this->getXMLFromAmazon($dvdTitle, $director);
				$movieInfo = $this->searchRottenTomatoesFor($dvdTitle, $director);
		

				if (( ! empty($amazonXML)) && ($amazonXML->Items->TotalResults > 0))
				{
					// we have at least one result returned, just go with first and presumeably most accurate result
					$dvdCoverURL = $amazonXML->Items->Item->MediumImage->URL;						

				}
				else if ( strcmp($movieInfo->posters->profile, dvdCollection::NO_ROTTEN_TOMATOE_POSTER_URL) != 0) 
				{ 
					$dvdCoverURL = $movieInfo->posters->profile;  // This uses an image from Rotten Tomatoes instead.
				}

				else
				{
					// no results (images) found in Amazon.com or Rotten Tomatoes 
					// With no cover there is nothing to display, so iterate again
				}
				
				if(( ! empty($dvdCoverURL)) && ($movieInfo != NULL))
				{
					// We have a DVD cover and movie info from Rotten Tomatoes
					// This has been our goal all along

					$imageTag = "<img src=" . $dvdCoverURL . " alt=" . $dvdTitle . " />";
					print("<li>");
					print("<a href="  . $movieInfo->links->alternate . " >" . $imageTag . "</a>");
					print("</li>");
				}
				else if ( ! empty($dvdCoverURL))
				{
					// No TV DVDs are in Rotten Tomatoes... that is what this case mainly deals with
					$dvdASIN = $amazonXML->Items->Item->ASIN;
					
					$imageTag = "<img src=" . $dvdCoverURL . " alt=" . $dvdTitle . " />";
					print("<li>");
					print("<a href="  . $this->amazonProductURLFor($dvdASIN) . " >" . $imageTag . "</a>");
					print("</li>");
				}
			}
			
			if($this->hasMembers())
			{
				// If we have some albums then we're going to try our hardest to display the cover and that means we need a closing HTML tag
				print("</ul>");
			}
		 }
		
		
		
         private function getXMLFromAmazon($dvdTitle, $director)
         {
         	if(strcmp($director, "various") != 0)
         	{
         		$strippedDVDTitle = $dvdTitle . "-" . $director;
				$strippedDVDTitle = preg_replace("/[^a-zA-Z0-9]/", "", $strippedDVDTitle);
			}
			else
			{
				$strippedDVDTitle = preg_replace("/[^a-zA-Z0-9]/", "", $dvdTitle);
			}
	
			if(strlen($strippedDVDTitle) > 0)
			{
				$myCache = new Caching("./MashupCache/Amazon/", $strippedDVDTitle, 'xml');
				
				if (($myCache->needToRenewData()) && (strcmp($director, "various") != 0))
				{		
					try
					{
						$result = $this->amazonAPI->getDVDCoverByTitleAndDirector($dvdTitle, $director);
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();
					}
					$myCache->saveXMLToFile($result);  // Save new data before we return it to the caller of the method 
				}
				else if($myCache->needToRenewData())
				{
					try
					{
						$result = $this->amazonAPI->getDVDCoverByTitle($dvdTitle);
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
				throw new Exception('Incorrect data type passed to getXMLFromAmazon()');
			}

			
			return $result;
         }
         
         
        /**
         * Searches for a random collection member that is both in Amazon and Rotten Tomatoes.  If it can't find any data that satisfies both 
         * conditions it throws an error.
         *
         * @return the currentMember as array 
         */
         public function randomDVDWithDetails()
         {
         	$isInRottenTomatoes = false;
         	$isInAmazon = false;
         	$isNotByVarious = false;
         	
         	// My mashup relies on this method to choose random DVDs/films, the problem is I have the DVD title not the film title, I needed a method 
         	// that strips stuff from the data in the second array cell.
         	
         	// It is possible for this to run until timeout of 30 seconds when say Rotten Tomatoes suddenly changes their API, so I rewrote the method 
         	// to only check each member of the collection once then throw an error.
         	
         	$newIndex = rand(0, ($this->collectionSize() - 1));
         	$this->currentMemberIndex = $newIndex;
         	$maxIterations = $this->collectionSize();
         	$count = 0;

			while(( $count < $maxIterations) && ( ! $isInRottenTomatoes && ! $isInAmazon && ! $isNotByVarious))
			{
				$aDVD = $this->currentMemberAsArray();
				
				$dvdTitle = $aDVD[1];
				$director = $aDVD[0];
				$betterFilmTitle = $this->possibleFilmTitleFor($dvdTitle);
				$amazonXML = $this->getXMLFromAmazon($dvdTitle, $director);
				$movieInfo = $this->searchRottenTomatoesFor($betterFilmTitle, $director); 
		
				if ((( ! empty($amazonXML)) 
					&& ( $amazonXML->Items->TotalResults > 0)) 
					&& ($movieInfo != null)
					&& (strcasecmp($director, 'various') != 0))
				{
					$isInAmazon = true;
					$isInRottenTomatoes = true;
					$isNotByVarious = true;
				}
				else
				{
					// DVD does not satisfy the condition so increase currentMemberIndex and count
					$newIndex = $this->currentMemberIndex + 1;
					$count++;
					
					// Ensure we wrap around 
					if ( $newIndex == $this->collectionSize())
					{
						$newIndex = 0;
					}
					// Get next candidate
					$this->currentMemberIndex = $newIndex;
					$aDVD = $this->currentMemberAsArray();
				}
			}
			
			if ( $count >= $maxIterations)
			{
				throw new Exception("Unable to find a film that is in both Amazon.com and Rotten Tomatoes database in collection.");
			}
         
         	return $aDVD;
         }
         
         
       /**
        * Takes the passed in DVD title and removes a lot of the obvious strings describing the DVD edition, not foolproof obviously but 
        * should help me find more films in Rotten Tomatoes perhaps.
        *
        * @param string
        * @return string
        */
        private function possibleFilmTitleFor($dvdTitle)
        {
        	$possibleFilmTitle = $dvdTitle; 
        	// Looking just at my test data, anything in () is related to edition and I should also remove certain words and phrases
        	$possibleFilmTitle = preg_replace( '(\\(.*\\))', '', $possibleFilmTitle );
        	
        	// Criterion Collection
        	// Special Edition
        	// Extended Edition
        	// Vista Series
        	// International Version
        	
        	$possibleFilmTitle = preg_replace( '/Criterion Collection|Special Edition|Extended Edition|Vista Series|International Version/i', '', $possibleFilmTitle );
        	
        	// ' such as Director's cut are problematic...
        	// I think I need to do more work on Director's...
        	$possibleFilmTitle= str_ireplace("Director's", '', $possibleFilmTitle);
        	
        	$possibleFilmTitle= str_ireplace("Collector's Edition", '', $possibleFilmTitle);
        	
        	$possibleFilmTitle = preg_replace('/[0-9]DVD|[0-9] DVD/i', '', $possibleFilmTitle);
        	
        	// DVD
        	// Uncut
        	// set
        	// disc
        	// ultimate
        	// special
        	// edition
        	
        	$possibleFilmTitle = preg_replace('/DVD|Uncut|disc|ultimate|special|cut|set|edition/i', '', $possibleFilmTitle);
        	
        	// Lastly strip junk characters (,),&,-
        	$possibleFilmTitle = preg_replace('/\\(|\\)|&|-/', '', $possibleFilmTitle);
        	
        	return trim($possibleFilmTitle);  // Lastly remove trailing and proceeding whitespace 
        }
         
         
         
        /**
         * Returns the complete XML from Rotten Tomatoes for the current dvd.
         *
         * @return SimpleXML Object 
         */
         public function infoFromRottenTomatoesForCurrentDVD()
         {
         	$currentDVD = $this->currentMemberAsArray();
         	$dvdTitle = $currentDVD[1];
         	$dvdDirector = $currentDVD[0];
         	$betterFilmTitle = $this->possibleFilmTitleFor($dvdTitle);
         	
         	return $this->searchRottenTomatoesFor($betterFilmTitle, $dvdDirector);
         }
         
         
        /**
         * Returns the complete XML from Amazon for the current dvd.  This is the Image Response Group.
         *
         * @return SimpleXML Object 
         */
         public function infoFromAmazonForCurrentDVD()
         {
         	// This works fine as I optimized my CSV file and I have the DVD description not the film title
         	$currentDVD = $this->currentMemberAsArray();
         	$dvdTitle = $currentDVD[1];
         	$dvdDirector = $currentDVD[0];
         	
         	return $this->getXMLFromAmazon($dvdTitle, $dvdDirector);
         }
         
         
        /**
         * Returns the complete XML from Wikipedia for the current dvd's director. 
         *
         * @return SimpleXML Object 
         */
         public function infoFromWikipediaForCurrentDVDsDirector()
         {
         	$currentDVD = $this->currentMemberAsArray();
         	$dvdDirector = $currentDVD[0];
         	
         	return $this->searchWikipediaFor($dvdDirector);
         }
         
         
        /**
         * Returns the complete XML from Wikipedia for the current DVD 
         *
         * @return SimpleXML Object 
         */
         public function infoFromWikipediaForCurrentDVD()
         {
         	$currentDVD = $this->currentMemberAsArray();
         	$dvdTitle = $currentDVD[1];
         	$betterFilmTitle = $this->possibleFilmTitleFor($dvdTitle);
         	
         	return $this->searchWikipediaForFilm($betterFilmTitle);
         }
         
         
        /**
         * Returns the complete XML from IMDB for the current DVD or as most as can be had without an official API
         *
         * @return SimpleXML Object 
         */
         public function infoFromIMDBForCurrentDVD()
         {

         	$currentDVD = $this->currentMemberAsArray();
         	$dvdTitle = $currentDVD[1];
         	$betterFilmTitle = $this->possibleFilmTitleFor($dvdTitle);
         	
         	return $this->searchIMDBForFilm($betterFilmTitle);
         }
         
         
        /**
         * Returns the complete HTML for an embeddable video clip ideally the official theatrical trailer for the current DVD
         *
         * @return SimpleXML Object 
         */
         public function trailerForCurrentDVD()
         {
         	$currentDVD = $this->currentMemberAsArray();
         	$dvdTitle = $currentDVD[1];
         	$dvdDirector = $currentDVD[0];
         	$betterFilmTitle = $this->possibleFilmTitleFor($dvdTitle);
         	
         	return $this->trailerFor($betterFilmTitle, $dvdDirector);
         }
         
         
        /**
         * Returns the current DVD's ASIN which is a unique identifier used for Amazon.com in their webstore.  
         *
         * @return String
         */
         private function currentDVDASIN()
         {
         	$dvdASIN = null;
         	$dvdXML = $this->infoFromAmazonForCurrentDVD();
         	
         	if($dvdXML->Items->TotalResults > 0) 
         	{
         		$dvdASIN = $dvdXML->Items->Item->ASIN;
         	}
         	
         	return $dvdASIN;
         }
         
         
         
        /**
         * This method just returns the URL to the product page using the ASIN and will append on your Amazon Associate ID so you can
         * potentially earn a commision.  If the item isn't in Amazon, will return the hash symbol which just reloads the page when clicked...
         *
         * @return string;
         */
         public function currentDVDAmazonProductURL()
         {
         	$dvdASIN = $this->currentDVDASIN();
         	if($dvdASIN != null)
         	{
         		$amazonProductURL = $this->amazonProductURLFor($dvdASIN);
         	}
         	else
         	{
         		$amazonProductURL = "#"; // return hash instead of null or empty string so it just reloads the page
         	}
         	
         	return $amazonProductURL;
         }
         
         
         
        /**
         * This method returns the URL to the product page on BestBuy.com for the currentDVD.  If the item can not be found it returns the hash symbol
         *
         * Best Buy API is currently inferior to Amazon's.  It doesn't have as many features and their catalog of albums is smaller, perhaps DVDs will 
         * be more there style.  Nope!  This method isn't functioning, I'm waiting for the BestBuy API to mature more...
         *
         * @return string 
         */
        public function currentDVDBestBuyProductURL()
        {
        	$productURL = '#';
        	
        	throw new Exception("currentDVDBestBuyProductURL() isn't functional, Best Buy's search API is not up to snuff.");
        	
        	// Starting from just the text file isn't going to work for BestBuy.  If I had the exact film title that might help.
        	// So get info from either Rotten Tomatoes or IMDB.
			$movieInfo = $this->infoFromRottenTomatoesForCurrentDVD();
			
			// Now that Rotten Tomatoes is returning good data for some querries how to get useful information from BestBuy?
			
			
			// With movie info we can then try finding it in BestBuy.
			
			/*
			print("<pre>");
			print_r($movieInfo);
			print("</pre>");
			*/
			
			// Apostrophe's in titles also cause issues...  urlencode isn't doing anything with them.
			// Even with the proper movie title returned from Rotten Tomatoes still can't find the DVD in Best Buy, would have to do a series of 
			// guess the title querries...  NOT INTERESTED.
        	
        
        	$dvdTitleQuery = 'name="' . urlencode($movieInfo->title) .'"';  //wildcard unnecessary?
        	$searchResults = $this->bestBuyRemix->products(array('type=Movie', $dvdTitleQuery))
							->show(array('name', 'crew.name', 'url', 'sku', 'image'))
							->format('json')
							->query();
							
			// Need to decode results...
			$bestBuyData = json_decode($searchResults);
			
			print("<pre>");
			print_r($bestBuyData);
			print("</pre>");
        
        /*
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
			
		 */
			
			
			return $productURL;
        }
         
         
         
        /**
         * Returns a string consisting of a link and an image (icon) to the DVD on Amazon.com, I decided to return valid HTML as I thought this
         * would save some time later on and some services it is much more elaborate to get the correct info and link to work.  The link returned has
         * an Amazon Associate tag as detailed here: 
         * http://www.kavoir.com/2009/05/build-simple-amazon-affiliate-text-links-with-just-asin-10-digit-isbn-and-your-amazon-associate-tracking-id.html
         *
         * @return string;
         */
         public function currentDVDAmazonAssociateBadge()
         {
            $htmlTag = null;
         	
         	$amazonProductURL = $this->currentDVDAmazonProductURL();
         	if(strcmp($amazonProductURL, "#") != 0)
         	{
         		$openLinkTag = '<a href="' . $amazonProductURL . '" >';
         		$closeLinkTag = '</a>';
         		$iconTag = '<img src="' . myInfo::AMAZON_ICON_URL . '" class="iconImage" />';
         		
         		$htmlTag = $openLinkTag . $iconTag . $closeLinkTag;
         	}
         	
         	return $htmlTag;
         }
	}
?>