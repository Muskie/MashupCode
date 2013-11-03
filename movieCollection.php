<?php

	/**
	 * This class is for creating movie mashups, it is a subclass of mCollection.php and has a subclass called dvdCollection.php which is 
	 * designed for manipulating DVDs.
	 *
	 * @author Muskie McKay <andrew@muschamp.ca>
     * @link http://www.muschamp.ca
     * @version 0.7.2
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
	
	require_once('./mCollection.php');

	class movieCollection extends mCollection
	{
		
	   /**
		* Initialize a Movie Collection
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
		* Initialize a Movie Collection
		*
		*  I haven't overided  the constructer, but by overiding this method, I can add support for other APIs
		*/
		protected function initializeAPIs()
		{
			parent::initializeAPIs();
			
		
		}
		
		
		
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
						// Rotten Tomatoes changed their API but didn't inform developers...
						$query = 'http://api.rottentomatoes.com/api/public/v1.0/movies.json?apikey=' . myInfo::MY_ROTTEN_TOMATOES_KEY . '&q=' . $q;
						
						$lookUpResult = fetchThisURL($query);
						
						// print("<pre>");
						// print_r($lookUpResult);
						// print("</pre>");
						 
						// decode the json data to make it easier to parse the php
						$searchResults = json_decode($lookUpResult);
						
						if ( ! empty($searchResults))
						{
						  // Now need to iterate to the first movie, cache it and return it...
						  
						  foreach($searchResults->movies as $movie)  // We're not finding data on movies like I was
						  {
						  	// I'm a bit worried as IMDBAPI is in trouble and this method may become much more important that it funcitons perfectly
						  	$newAPIURL = $movie->links->self;
						  	$targetURL = $newAPIURL . '?apikey=' . myInfo::MY_ROTTEN_TOMATOES_KEY;
						  	$nextLookUp = fetchThisURL($targetURL);
						  	$possibleMatch = json_decode($nextLookUp);

						  	if( strcasecmp($possibleMatch->abridged_directors[0]->name, $director) == 0)
						  	{
						  		// This is the best match 
						  		$movieInfo = $possibleMatch;
						  	}
						  }
						  
						  if($movieInfo == NULL)
						  {
						  	$movieInfo = $searchResults->movies[0];  // Trust Rotten Tomatoes regarding best match, this is a fallback option
						  	// I thought this might always happen, but it will always happen if you don't know the director!
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
		* This method searches YouTube and potentially other video services for a trailer for the passed in film title 
		*
		* @param string
		* @return string
		*/
		protected function trailerForFilm($filmTitle)
		{
			$htmlTag = '';
			
			// hierarchacal search, but first match from YouTube is what this returns
			// YouTube is returning some totally garbage results, made a version which also takes in director AND revised this further
			$searchString = $filmTitle . ' Theatrical Trailer'; 
			$htmlTag = $this->embeddableVideoClipFor($searchString);
			if(empty($htmlTag))
			{
				$searchString = $filmTitle . ' movie trailer';
				$htmlTag = $this->embeddableVideoClipFor($searchString);
				if(empty($htmlTag))
				{
					$searchString = $filmTitle . ' trailer';
					$htmlTag = $this->embeddableVideoClipFor($searchString);
					if(empty($htmlTag))
					{
						$searchString = $filmTitle;
						$htmlTag = $this->embeddableVideoClipFor($searchString);
					}
				}
			}
			
			return $htmlTag;
		}
		
		
		
		/**
		* This method searches YouTube and potentially other video services for a trailer for the passed in film title and director's name
		*
		* @param string movie title 
		* @return string valid HTML for an embedded video clip 
		*/
		protected function trailerFor($filmTitle, $director)
		{
			$htmlTag = '';
			
			// No matter how much work I put into the above method, YouTube returns some strange top results so I'm going to try a search which
			// includes the director's name which I should have for most movies.
			if( ! empty($director))
			{
				$searchString = 'theatrical trailer for "' . $filmTitle . '" directed by ' . $director;
				$htmlTag = $this->embeddableVideoClipFor($searchString);
				if(empty($htmlTag))
				{
					$searchString = '"'. $filmTitle . '" a film by ' . $director;
					$htmlTag = $this->embeddableVideoClipFor($searchString);
					if(empty($htmlTag))
					{
						$htmlTag = $this->trailerForFilm($filmTitle);  // Shouldn't happen much
					}
				}
			}
			
			return $htmlTag;
		}
		
		
		
	  /**
	   * This method searches the Wikipedia for the passed in film title.  I return the entire SimpleXML Object
	   *
	   * @param string
	   * @return SimpleXML Object
	   */
	   protected function searchWikipediaForFilm($filmTitle)
	   {
			$searchString = $filmTitle . ' (film)';
			
			return $this->searchWikipediaFor($searchString);
			
			// To get a brief descrption of the film, use this path ->Section->Item->Description
	   }
	   
	   
	   
	  /**
	   * This method searches the IMDB (using the unofficial API) for the passed in film title.  I return the entire SimpleXML Object
	   * WARNING this web service is in hot water with Amazon owner of IMDB so I am moving away from it towards Rotten Tomatoes 
	   *
	   * @param string
	   * @return SimpleXML Object
	   */
	   protected function searchIMDBForFilm($filmTitle)
	   {
	   		$movieInfo = NULL;
	   
			// A lot of people have wanted to search or scrape the IMDB with an API or PHP.  Amazon owns the website so it is possible some of this 
			// data is in the main Amazon Product Advertising API.  I've already noticed how Wikipedia and Rotten Tomatoes occaisionally have the same 
			// exact text.
			
			// The default is to return data in JSON format, but XML is also supported.  Here is what a query URL should look like:
			// http://www.imdbapi.com/?t=True Grit&y=1969 
			// spaces need to be encoded.
			
			// This works well as I always pass in titles of films that I've already found in Rotten Tomatoes, this isn't an official precondition and 
			// I should probably cache this just like so much other data.
			
			// first check that we don't have a local cached version, no reason to get lazy
			$properFileName = preg_replace("/[^a-zA-Z0-9]/", "", $filmTitle);
			
			if(strlen($properFileName) > 0)
			{
				$myCache = new Caching("./MashupCache/IMDB/", $properFileName);
				
				if ($myCache->needToRenewData())
				{
					try
					{
						$encodedTitle = urlencode ( $filmTitle );
						$queryURL = 'http://www.imdbapi.com/?t=' . $encodedTitle . '&plot=full';  // I prefer the long version of the plot
						$queryResult = fetchThisURL($queryURL);
						$movieInfo = json_decode($queryResult);
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();  
					}
					
					$serializedObject = serialize($movieInfo);
					$myCache->saveSerializedDataToFile($serializedObject);
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
		 * Returns the current collection director, ie the first item in currentMemberAsArray
		 *
		 * @return string
		 */
		 public function currentDirector()
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
		 * Wikipedia doesn't give you very big photos, so we'll try our luck with Flickr.  Flickr has some very high resolution photos, they also have
		 * a huge API and not always the best search algorithm.
		 *
		 * @return array
		 */
		public function getCurrentDirectorPhotosFromFlickr()
		{
			// The results of this method have become disappointing for most directors, no longer using in my main movie mashup.
			
			// Returns an associated array.  I fetch extra image URLs: t = tiny, s = small, m = medium, o = oversized or something...
			
			// I need to build an associative array of arguments as this method/API call has so damn many:
			// http://www.flickr.com/services/api/flickr.photos.search.html
			// I probably don't need to pass in my API-key as I already did when I created the instance of phpFlickr.
			$args = array(
							'text' => $this->currentDirector(),
							'sort' => 'relevance',
							'content_type' => 1,
							'per_page' => 5,
							'extras' => 'url_t, url_s, url_m, url_o'
						);
			
			$flickrResults = $this->flickrAPI->photos_search($args);

			return $flickrResults;
		}
	}
?>