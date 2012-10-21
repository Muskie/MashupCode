<?php
	/*
	 * Caching	This caching code I got from Gaya Kessler then I (Muskie McKay) modified it some...
	 * Author:	Gaya Kessler
	 * URL:		http://www.gayadesign.com/
	 */

	require_once('./myInfo.php'); // This is where all your API keys and user names and what are stored as constants

	class Caching {

		var $filePath = "";
		var $fileName = "";
		var $pathAndFileName = "";

		function __construct($filePath, $objectName, $fileExtension = myInfo::CACHE_FILE_EXTENSION) 
		{
			//check if the file path and api URI are specified, if not: break out of constructor.
			if (strlen($filePath) > 0 && strlen($objectName) > 0) 
			{
				//set the local file path and api path
				$this->filePath = $filePath;
				$this->fileName = $objectName;
				$this->pathAndFileName = $filePath . $objectName . '.' . $fileExtension;  //Sometimes it is an XML file sometimes it isn't
			} 
			else 
			{
				throw new Exception("Incorrect data passed to Caching constructor");
			}
		}
		
		
		function needToRenewData() 
		{
			$result = true;  // Better to default to this
			
			//set the caching time (in seconds)
			$cachetime = myInfo::CACHE_RENEW_TIME;

			//get the file time if the file exists 
			if(file_exists($this->pathAndFileName))
			{
				try
				{
					$filetimemod = filemtime($this->pathAndFileName) + $cachetime;
				}
				catch(Exception $e)
				{
					// The very first time you try to call this on a file that doesn't exist it doesn't like it...
					throw new Exception("Need to look at where and when you call needToRenewData()");
				}
				//if the renewal date is smaller than now
				if ($filetimemod > time()) 
				{
					$result = false;
				} 
			}

			return $result;
		}


		function getLocalXML() 
		{
			if (file_exists($this->pathAndFileName)) 
			{
				try
				{
    				$localCopy = simplexml_load_file($this->pathAndFileName);  // This is timing out sometimes, need to find out on what data...
    			}
    			catch(Exception $e)
    			{
    				//This is most likely my time out error, want to catch it and for now throw a new error with the name of the damn file causing issues
    				throw new Exception("Trouble loading this " . $this->pathAndFileName . " as XML");
    			}
			} 
			else 
			{
    			throw new Exception("The local file " . $this->pathAndFileName . " failed to open."); //It is timing out but not throwing this exception
			}
			
			return $localCopy;  //simplexml_load_file() returns false on errors but I catch them, or I should be...
		}
		
		
		function getUnserializedData()
		{
			if (file_exists($this->pathAndFileName)) 
			{
				// We have to open the file then unserialize it
				$rawData = file_get_contents($this->pathAndFileName);
    			$localCopy = unserialize($rawData);  // You can't unserialize a SimpleXML object, you must convert all of them to arrays...
			} 
			else 
			{
    			throw new Exception("The local file " . $this->pathAndFileName . " failed to open.");
			}
			
			return $localCopy;  // This will return an array to my Last.fm caching stuff
		}


		function saveXMLToFile($xml) 
		{
			//save the xml in the cache
			file_put_contents($this->pathAndFileName, $xml->asXML());
		}
		
		
		function saveSerializedDataToFile($string) 
		{
			//save serialized data in the cache
			file_put_contents($this->pathAndFileName, $string);
		}
		
	}
?>