<?php

// This class simply has constants of the keys you need to use various APIs, I put them all in one place then I load them in 
// class constructors to private variables in classes
class myInfo
{
	const MY_AMAZON_PUBLIC_KEY ="";
	const MY_AMAZON_PRIVATE_KEY ="";
	const MY_LAST_FM_PUBLIC_KEY ="";
	const MY_LAST_FM_PRIVATE_KEY ="";
	const MY_LAST_FM_USER_NAME = "";  // Useful for music mashups if you plan to make any
	const MISSING_COVER_URL = "";
	const MY_FACEBOOK_PUBLIC_KEY ="";
	const MY_FACEBOOK_PRIVATE_KEY ="";
	const CACHE_RENEW_TIME ="2419200";  // This is currently four weeks in seconds
	const FACEBOOK_ICON_URL = "";
	const LAST_FM_ICON_URL = "";
	const AMAZON_ICON_URL = "";
	const APPLE_ICON_URL = "";
	const MY_AMAZON_ASSOCIATE_ID = "";
	const MY_TWITTER_ACCOUNT = "";
	const DEFAULT_TWEET = " ";  // You have way less than 140 characters
	const MY_HOME_PAGE = "";
	const MY_FLICKR_PUBLIC_KEY = "";
	const MY_FLICKR_PRIVATE_KEY = "";
	const MY_ROTTEN_TOMATOES_KEY = "";
	const MY_KLOUT_KEY = "";
	const CACHING_DIRECTORY = "";
	const CACHE_FILE_EXTENSION = "cache";  // No period!
	const MY_BESTBUY_PUBLIC_KEY = "";
	const MY_TOPSY_KEY = "";
	const MY_GOOGLE_API_KEY = "";
	const MY_TWITTER_PUBLIC_KEY = ""; // Twitter calls this the cosumer key 
	const MY_TWITTER_PRIVATE_KEY = "";
	const MY_TWITTER_ACCESS_TOKEN = "";
	const MY_TWITTER_ACCESS_TOKEN_SECRET = "";
}
?>