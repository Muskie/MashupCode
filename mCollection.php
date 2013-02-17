<?php
    /**
     * Class to facilitate the creation of web mashups using various APIs.
     * @author Muskie McKay <andrew@muschamp.ca>
     * @link http://www.muschamp.ca
     * @version 1.5
     * @copyright Muskie McKay
     * @license MIT
     *
     * This started as a simple class to represent a collection of music,
     * a physical collection or virtual that then can be easily manipulated just like a crate of LPs.
     * However due to unending unemployment I decided to make an even more versatile class to represent a collection of 
     * anything you might want to make a mashup of: music, movies, quotations etc.  
     * 
     * There are subclasses musicCollection.php, albumCollection.php, dvdCollection.php, movieCollection.php , and 
     * quotationCollection.php that should do the heavy lifting as I try to get PHP to be even more OOP. Instructions and examples 
     * are found:
     * http://www.muschamp.ca/Muskie/webMashups.html
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
    
    require_once('AmazonAPI/amazon_api_class.php');  // Amazon API by Sameer Borate, extended by yours truely
    require_once('phpflickr/phpFlickr.php');
	require_once('myInfo.php'); // This is where all your API keys and user names and what not go as constants, should be .inc?
	require_once('muskLib.php'); // A few little helper functions I wrote or acquired online
	require_once('caching.php');  // This is my DIY caching of XML and other data returned from APIs 
	require_once('facebookLib.php');  // This only slightly makes my life easier, the author had big plans so I included support...
	require_once('BestBuy/Service/Remix.php'); //After learning of it, I added the BestBuy Remix product API,
	// I prefer Amazon due to already having it debugged and the superior documentation, BestBuy integration is not very useful currently

	class mCollection
	{
	
		/**
         * This will be a multi-dimensional array originally consisting of arrays containing an artist in the first cell
         * and an album title in the second cell.  That is the simplest way to represent the collection of albums.
         * @access protected
         * @var array
         */
         protected $theCollection = array();  
         
         
    	/**
         * This is a simple index of which member from theCollection array is being displayed or otherwise manipulated.
         * This is often useful information to have, but it will also let me use the array as a circular linked list 
         * Which will open up some interesting options graphically.
         * @access protected
         * @var int
         */
         protected $currentMemberIndex = 0;
         
         
        /**
         * This is an instance of tha Amazon API we need it to fetch data
         * @access protected
         * @var AmazazonAPI Object
         */
         protected $amazonAPI; 
         
         
        /**
		 * This is an instance of facebookLib an extension to the official facebook open graph class
		 * @access protected
		 * @var Facebook object
		 */
		 protected $facebook;
		 
		 
		/**
		 * This is an instance of the official Facebook access token, you need this to make some requests
		 * @access private
		 * @var Facebook object
		 */
		 private $facebookAccessToken; 
		 
		const TWEETS_PER_PAGE = 5;
	
		/**
		 * This is an instance of tha phpFlickr API we need it to fetch data
		 * @access protected
		 * @var phpFlickr Object
		 */	
		protected $flickrAPI;
		
		
		/**
		 * This is an instance of the BestBuy Remix API wrapper class.  It is similar to the Amazon API but less popular and less well documented.
		 * @access protected
		 * @var Best Buy Remix API Object
		 */
		protected $bestBuyRemix;
		 
		 
		 
		// PHP doesn't support multiple constructors so like everything they have a work around or two...
    	public function __construct($input) 
    	{
			if (is_array($input)) 
			{
				// Initialize the collection array with input.
				// This seems the most obvious way to initialize the class so I need to create the array I want outside the class.
				$this->theCollection = $input;
			}
			else if ($input instanceof SimpleXMLElement) {
				// Initialize the collection array by parsing the XML.
				// Not implemented yet but a decent idea to support eventually
			}
			else if(is_string($input))
			{
				// Initialize collection array by reading from CSV file 
				$this->theCollection  = createArrayFromCSVFile($input);		
			}
			else 
			{
				throw new Exception('Wrong input type, passed to mCollection constructor.');
			}
			
			if(! $this->hasMembers())
			{
				//The collection should not be empty after the constructor is called...
				throw new Exception('The Collection should not be empty after calling the constructor.  Please check when and how you create an mCollection object');
			}
			else
			{
				// Finish setting up the mCollection object
				$this->initializeAPIs();
			}
    	}
    	
    	
    	// This initializes the APIs this object/class uses to do stuff
    	protected function initializeAPIs()
    	{
    		$this->amazonAPI = new AmazonProductAPI();
    		$this->bestBuyRemix = new BestBuy_Service_Remix(myInfo::MY_BESTBUY_PUBLIC_KEY);  //New API which may become more useful
    		$this->facebook = new facebookLib(array(
  											'appId'  => myInfo::MY_FACEBOOK_PUBLIC_KEY,
  											'secret' => myInfo::MY_FACEBOOK_SECRET_KEY,
 		 									'cookie' => true
											));
			facebookLib::$CURL_OPTS[CURLOPT_CAINFO] = './ca-bundle.crt';
			facebookLib::$CURL_OPTS[CURLOPT_FRESH_CONNECT] = 1;
			facebookLib::$CURL_OPTS[CURLOPT_PORT] = 443;
			
			$this->facebookAccessToken = $this->facebook->getAccessToken();

			$this->flickrAPI = new phpFlickr(myInfo::MY_FLICKR_PUBLIC_KEY, myInfo::MY_FLICKR_PRIVATE_KEY);
			$this->flickrAPI->enableCache("fs", "./" . myInfo::CACHING_DIRECTORY . "/Flickr");  // This class came with it's own caching system, now I'm using at least three.  Last.fm PHP API has one too but it requires a database.
    	}
    	
    	
    	// This should probably be private, but it is mainly for debugging my constructor, constructors shouldn't be this problematic...
    	protected function dumpArrayContents()
    	{
    		if( ! empty($this->theCollection))
    		{
				print_r('These are the contents of theCollection array currently:');
				print('<br />');
				print('<pre>');
				print_r($this->theCollection);
				print('</pre>');
			}
			else
			{
				// Will this ever happen now that I got my constructor and  private variable accessing correct, shouldn't!
				print_r('There is nothing in the albums array right now.' . '<br />');
			}
    	}
    	
    	
         
        /**
         * Returns true if we have some members in the collection
         * 
         * @return boolean
         */
         public function hasMembers()
         {
         	return ( ! empty($this->theCollection));
         }
         
         
         
        protected function isCurrentMemberTheLast()
        {
        	$isCurrentMemberTheLast = false;
        
        	if ( $this->currentMemberIndex == ($this->collectionSize() - 1))
        	{
        		$isCurrentMemberTheLast = true;
        	}
        	
        	return $isCurrentMemberTheLast;
        }
		   
		   
		   
		protected function isCurrentMemberTheFirst()
        {
        	$isCurrentMemberTheFirst = false;
        	
        	if ( $this->currentMemberIndex == 0)
        	{
        		$isCurrentMemberTheFirst = true;
        	}
        	
        	return $isCurrentMemberTheFirst;
        }
		   
		   
		   
		/**
         * Returns the number of members in our collection
         * 
         * @return int
         */
         public function collectionSize()
         {
         	return count($this->theCollection);
         }
         
         
         
        /**
         * Returns the current member in array form based on currentMemberIndex
         *
         * @return array
         */
         public function currentMemberAsArray()
         {
         	return $this->theCollection[$this->currentMemberIndex];
         }
         
         
         
        /**
         * Sets $currentMemberIndex to be a random number.
         * I return the information as an array but with minimal information contained currently.  
         * Once it is the the current member you can fetch lots of info.
         *
         * @return array
         */
         public function randomMember()
         {
         	$oldIndex = $this->currentMemberIndex;
         	
         	if ($this->collectionSize() < 3)
         	{
         		throw new Exception("This collection is less than three, why are you wasting time calling randomMember on a collection this small?");
         	}
         	else
         	{
         		$newIndex = rand(0, ($this->collectionSize() - 1));
         		while($newIndex == $oldIndex)
         		{
         			// I want it random, but I don't want it to ever return the same member
         			$newIndex = rand(0, ($this->collectionSize() - 1));
         		}
         		$this->currentMemberIndex = $newIndex;
         	}
         
         	return $this->currentMemberAsArray();
         }
         
         
         
        /**
         * This method was created primarily for debugging.  This method sets the currentMemberIndex to be the last item in the collection and 
         * then returns the now currentMemberAsArray.
         *
         * @return array 
         */
         public function lastMember()
         {
         	$newIndex = $this->collectionSize() - 1;
         	
         	$this->currentMemberIndex = $newIndex;
         	
         	return $this->currentMemberAsArray();
         }
         
         
         
        /**
         * Finally decided to add accessors for the array index, this method lets you set the currentMemberIndex 
         *
         */
        public function setCurrentMemberIndex($newIndex)
        {
        	if( ($newIndex >= 0) && ($newIndex <= ($this->collectionSize() - 1)))
        	{
        		$this->currentMemberIndex = $newIndex;
        	}
        	else
        	{
        		throw new Exception("New Index is not valid");
        	}
        }
        
        
        
        /**
         * Finally decided to add accessor to array index, this method lets you get the value of the currentMemberIndex 
         *
         */
        public function getCurrentMemberIndex()
        {
        	return $this->currentMemberIndex;
        }
         
         
         
        /**
         * This method uses Facebook's Open Graph format to search for a page or more likely pages 
         * corresponding to the string passed in, in Facebook's social graph.  
         * The most likely URL is chosen and then we creat the HTML tag(s) necessary to display a fully functional like button
         *
         * @return string
         */
         public function facebookLikeButtonFor($somethingILike)
         {
         	// Needs to produce HTML that looks like this:
         	/*
         	<iframe src="http://www.facebook.com/plugins/like.php?href=http%3A%2F%2Fexample.com%2Fpage%2Fto%2Flike&amp;layout=button_count&amp;show_faces=true&amp;width=200&amp;action=like&amp;colorscheme=light&amp;height=21" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:200px; height:21px;" allowTransparency="true"></iframe>
         	*/
         	
         	$htmlTag = null;   
         	$this->facebook->setDecodeJson(true);
         	$possiblePages = $this->facebook->search('page', $somethingILike);  
      
      		if( ! empty($possiblePages->data))
      		{
				$firstID = $possiblePages->data[0]->id;
				
				$graphURL = Facebook::$DOMAIN_MAP['graph'] . $firstID;
				
				$resultingString = fetchThisURL($graphURL);
				if(is_string($resultingString))
				{
					$facebookPage = json_decode($resultingString);
				  	$strippedURL = urlencode($facebookPage->link);
					$openLinkTag = '<iframe src="http://www.facebook.com/plugins/like.php?href=';
         			$closeLinkTag = '%2F&amp;layout=button_count&amp;show_faces=true&amp;width=200&amp;action=like&amp;colorscheme=light&amp;height=32" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:200px; height:32px;" allowTransparency="true"></iframe>';
         		
         			$htmlTag = $openLinkTag . $strippedURL . $closeLinkTag;
				}
				else
				{
					// We're not getting a string in JSON format 
					print('<pre>');
					print_r($resultingString);  // nor is this...
					print('</pre>');
				}

      		}
      		
      		return $htmlTag;      
         }

         
         
	   /**
		* Searches Twitter for mentions of the string passed in.  Uses the default Twitter search which is a blend of new and popular
		*
		* @param search string
		*
		* @return decoded JSON of results
		*/
		public function searchTwitterFor($searchString)
		{
		// Twitter can do a variety of responses, probably will use JSON which is the default, so will return a decoded JSON object
		// 15 is the default number of tweets per page, do I want less?  Probably?
			$searchResults;
			
			$searchStringStart = 'http://search.twitter.com/search.json?q=';
			$encodedQuery = urlencode( $searchString );
			$additionalArgument = '&rpp=' . mCollection::TWEETS_PER_PAGE;
			$englishOnly = '&lang=en';
			$searchString = $searchStringStart . $encodedQuery . $additionalArgument . $englishOnly;
			
			$results = fetchThisURL($searchString);
			$searchResults = json_decode($results);
		
			return $searchResults;
		}
         
        
        
        /**
         * This method creates a fully functional "Tweet This" button.  You don't need to register an app at Twitter to just do this.
         * It uses Twitter's Javascript but it passes in text and variables concerning the current member and pulls information from
         * myInfo.php specifically MY_TWITTER_ACCOUNT and MY_HOME_PAGE
         *
         * @return string
         */
         public function tweetThisButton($tweet = myInfo::DEFAULT_TWEET)
         {
         	// This method needs to produce HTML that looks like this:
         	/*
         	<a href="http://twitter.com/share" class="twitter-share-button" data-url="http://www.test.com" data-text="test title" data-count="none" data-via="MuskieMcKay">Tweet</a><script type="text/javascript" src="http://platform.twitter.com/widgets.js"></script>
         	*/
         	
         	// Maybe it should look like this, I switch to this second format for quotations...
         	/*
         	<a href="https://twitter.com/share" class="twitter-share-button" data-via="MuskieMcKay">Tweet</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
         	*/
         	
         	$tweetThisButtonCode = null;
         	$twitterDataURL = myInfo::MY_HOME_PAGE;
         	
         	// Just in case I started stripping HTML from passed in Tweets...
         	
         	$twitterDataBy = myInfo::MY_TWITTER_ACCOUNT;
         	$openingTag = '<a href="http://twitter.com/share" class="twitter-share-button" data-url="' . $twitterDataURL . '" data-text="' . strip_tags($tweet) . '" data-via="' . $twitterDataBy . '">';
         	$closingTag = '</a><script type="text/javascript" src="http://platform.twitter.com/widgets.js"></script>';
         	$tweetThisButtonCode = $openingTag . "Tweet" . $closingTag;
         	
         	return $tweetThisButtonCode;
         }
         
         
         
        /**
         * This method produces a working Pin It button. It requires three arguments none of which should be empty or null. It also requires similar to 
         * the latest greatest Facebook button that a second bit of Javascript be placed just inside the body tag, failure to do that will result in a 
         * non-funtioning Pin It button. I like how in the past I could have one method per button, but if you want to play with Pinterest you have 
         * to play by their rules, so include this:
         *
         * <script type="text/javascript" src="//assets.pinterest.com/js/pinit.js"></script>
         *
         * More information can be found here:
         * https://support.pinterest.com/entries/21101982-adding-the-pin-it-button-to-your-website
         *
         * @param string
         * @param string
         * @param string
         * @return string
         */
        public function pinItButton($pageURL, $imageURL, $text)
        {
        	$html = '<!-- Pin It Button Not Possible -->';
        	// Should probably assert that no data passed in is empty, checking valid URLs would be even better...
			/*
			Pinterest requires two bits of code, something like this:
			<a href="http://pinterest.com/pin/create/button/?url=http%3A%2F%2Fwww.muschamp.ca%2F&media=http%3A%2F%2Fwww.muschamp.ca%2Fimage.jpg&description=whatever" class="pin-it-button" count-layout="horizontal"><img border="0" src="//assets.pinterest.com/images/PinExt.png" title="Pin It" /></a>
			Where you want the button ie here, and 
			<script type="text/javascript" src="//assets.pinterest.com/js/pinit.js"></script> 
			elsewhere near where <head> meets <body>
			*/
			// Not sure if the first two tests are necessary, they were necessary in my news aggregator, so I'm leaving them in for now...
			if(( ! strpos($imageURL, '+')) && ( ! strpos($imageURL, '%'))
				&& (preg_match('|^http?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $imageURL)) && (preg_match('|^http?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $pageURL)))
			{
				// If I don't display a valid image, no sense in showing a Pin It button 
				$html = '<a href="http://pinterest.com/pin/create/button/?url=' . $pageURL . '&media=' . $imageURL . '&description=' . strip_tags($text) . '" class="pin-it-button" count-layout="horizontal" target="_blank"><img border="0" src="//assets.pinterest.com/images/PinExt.png" title="Pin It" /></a>';
			}
			
			return $html;
        }
         
         
         
        /**
         * This method returns a valid product URL with the Associate referral for the passed in ASIN 
         *
         * @param string product ASIN 
         * @return string URL 
         */
         public function amazonProductURLFor($productASIN)
         {
         	$amazonProductURL = 'https://www.amazon.com/dp/' . $productASIN . '?tag=' . myInfo::MY_AMAZON_ASSOCIATE_ID;
         	
         	return $amazonProductURL;
         }
         
         
         
        /**
         * This method returns the Klout score for the past in Twitter account.
         *
         * @param string 
         * @return numberic score
         */
         public function kloutScoreFor($twitterAccount)
         {
         	if(strlen($twitterAccount) > 0)
         	{
				// This is based on the documentation here:
				// http://developer.klout.com/docs/read/api/API
				$q = urlencode($twitterAccount);
				$query = 'http://api.klout.com/1/klout.xml?key=' . myInfo::MY_KLOUT_KEY . '&users=' . $q;
				
				$returnedXML = simplexml_load_file($query);  // Simple but with less error checking perhaps
			}
         	
         	return $returnedXML->user->kscore;
         }

         
         
        /**
         * This method searches Topsy to find the biggest expert on the social web for the passed in subject.
         *
         * @param string subject
         * @return string the experts Twitter account
         */
         public function topsyExpertOn($subject)
         {
         	$expert = NULL;
         	
         	// First we need to encode the passed in string 
         	$q = urlencode($subject);
         	
         	// Need to create a URL as per instructions http://code.google.com/p/otterapi/wiki/Resources#/experts
         	// Now need to attach an API key, which is only free for 30 days, otherwise you get NULL back for the expert.
         	$query = 'http://otter.topsy.com/experts.json?q=' . $q . '&apikey=' . myInfo::MY_TOPSY_KEY;
						
			$lookUpResult = fetchThisURL($query);
			 
			// decode the json data to make it easier to parse the php
			$searchResults = json_decode($lookUpResult);
			
			$expert = $searchResults->response->list[0]->nick;
         	
         	return $expert;
         }
         
         
         
        /**
         * This method returns a valid link to the Twitter account passed in.
         *
         * @param string Twitter Account
         * @return string link to the experts Twitter account
         */
         public function linkToTwitterAccountFor($id)
         {
			// Check for '@'
			$validID = str_replace('@', '', $id);
			return '<a href="http://twitter.com/#!/' . $validID . '">' . $id . '</a>';
         }
         
         
         
       /**
		* This method searches YouTube and potentially eventually other video services for a video clip featurning the passed in subject.  Then
		* it returns the HTML for an embeddable player to play that video.
		*
		* @param string
		*
		* @return string
		*/
	   public function embeddableVideoClipFor($searchString)
	   {
			// Previous experience revealed that video search is not perfect, in that for given keywords the top result isn't always accurate.
			$embeddableVideoClipHTML = NULL;
			
			// Further details on searching YouTube http://www.ibm.com/developerworks/xml/library/x-youtubeapi/
			// This was working well for over two years but had to be revised to use version 2 of the API
			// May switch to Zend or version 3.0 of Google/YouTube API but this is working again... I even asked Stack Overflow 
			// http://stackoverflow.com/questions/14915298/searching-youtube-and-displaying-first-video-in-php-advice-needed
			
			$vq = $searchString;
			$vq = preg_replace('/[[:space:]]+/', ' ', trim($vq));
        	$vq = urlencode($vq);
        	$feedURL = 'http://gdata.youtube.com/feeds/api/videos?q=' . $vq . '&safeSearch=none&orderby=viewCount&v=2'; // Added version 2 argument	
		  
			// read feed into SimpleXML object
			try
			{
				$youTubeXML = simplexml_load_file($feedURL);
			}
			catch(Exception $e)
			{	
				// This rarely throws an error, but when it does, I just want to pretend I can't find a video clip
				$youTubeXML = NULL;
			}	
			
			if(($youTubeXML != NULL) && ( ! empty($youTubeXML->entry->link[0]['href'])))
			{
				$videoLink = $youTubeXML->entry->link[0]['href'];  // This is not enough, I need to trim the beginning and end off this to just get the video code
				$trimedURL = str_replace('http://www.youtube.com/watch?v=', '' , $videoLink);
				$videoCode = str_replace('&feature=youtube_gdata', '', $trimedURL);
				$embeddableVideoClipHTML = '<iframe id="ytplayer" type="text/html" width="640" height="360" src="https://www.youtube.com/embed/' . $videoCode . '"frameborder="0" allowfullscreen>';
			}
			
			return $embeddableVideoClipHTML;
	   }
	   
	   
	   
	  /**
	   * This method searches the Wikipedia for the passed in string.  Not sure what format I'll return probably SimpleXML object.
	   *
	   * @param string the search query
	   * @return the number one result for the search in Wikipedia
	   */
	   public function searchWikipediaFor($searchString)
	   {
	   		// My Wikipedia search code is based on this example: http://adamzwakk.com/?p=383
	   		$searchResults = fetchThisURL('http://en.wikipedia.org/w/api.php?action=opensearch&search=' . urlencode($searchString) . '&format=xml&limit=1');
	   		
 			$wikiXML = simplexml_load_string($searchResults);
 			
 			return $wikiXML;
	   }
	   
	}
?>