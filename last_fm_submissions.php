<?php

/**
 * LastFM Submission class
 *
 * This source file can be used to scrobble track to last.fm (http://last.fm)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-lastfm-submission-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * License
 * Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including neglience or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author		Tijs Verkoyen <php-lastfm-submission@verkoyen.eu>
 * @version		1.0.1
 *
 * @copyright	Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 * @license		BSD License
 */
class LastFmSubmissions
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the twitter-api
	const API_URL = 'http://post.audioscrobbler.com';

	// port for the twitter-api
	const API_PORT = 80;

	// current version
	const VERSION = '1.0.0';


	/**
	 * The API-key
	 *
	 * @var string
	 */
	private $apiKey;


	/**
	 * The client id
	 *
	 * @var	string
	 */
	private $clientId;


	/**
	 * The client version
	 *
	 * @var	string
	 */
	private $clientVersion;


	/**
	 * cURL instance
	 *
	 * @var	resource
	 */
	private $curl;


	/**
	 * The secret
	 *
	 * @var	string
	 */
	private $secret;


	/**
	 * The session key
	 *
	 * @var	string
	 */
	private $sessionKey;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 10;


	/**
	 * The urls
	 *
	 * @var	array
	 */
	private $urls = array();


	/**
	 * The user
	 *
	 * @var	string
	 */
	private $user;


	/**
	 * The user Agent
	 *
	 * @var	string
	 */
	private $useragent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $apiKey			A Last.fm API key.
	 * @param	string $secret			A Last.fm secret
	 * @param	string $user			Is the name of the user on who's behalf the request is being performed.
	 * @param	string $sessionKey		The Web Services session key generated via the authentication protocol.
	 * @param	string $clientId		Is an identifier for the client (See http://www.last.fm/api/submissions#1.1).
	 * @param	string $clientVersion	Is the version of the client being used.
	 */
	public function __construct($apiKey, $secret, $user, $sessionKey, $clientId, $clientVersion)
	{
		// set some properties
		$this->setAPIKey($apiKey);
		$this->setSecret($secret);
		$this->setUser($user);
		$this->setSessionKey($sessionKey);
		$this->setClientId($clientId);
		$this->setClientVersion($clientVersion);

		// do a handshake
		$this->doHandshake();
	}


	/**
	 * Default destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		// close the cURL instance if needed
		if($this->curl !== null) curl_close($this->curl);
	}


	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $method
	 * @param	array[optiona] $parameters
	 */
	private function doCall($url, array $parameters, $httpMethod = 'POST')
	{
		// process parameters
		if($httpMethod == 'GET') $url .= '?'. http_build_query($parameters);

		else
		{
			// init var
			$paramString = '';

			// loop parameters
			foreach($parameters as $key => $value) $paramString .= $key .'='. urlencode($value) .'&';

			// cleanup
			$paramString = trim($paramString, '&');

			// set options
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $paramString;
		}

		// set options
		$options[CURLOPT_URL] = $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
		$options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;

		// init
		if($this->curl == null) $this->curl = curl_init();

		// set options
		curl_setopt_array($this->curl, $options);

		// execute
		$response = curl_exec($this->curl);
		$headers = curl_getinfo($this->curl);

		// fetch errors
		$errorNumber = curl_errno($this->curl);
		$errorMessage = curl_error($this->curl);

		// invalid headers
		if(!in_array($headers['http_code'], array(0, 200)))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';

				// stop the script
				exit;
			}

			// throw error
			throw new LastFmSubmissionException('Invalid headers ('. $headers['http_code'] .')', (int) $headers['http_code']);
		}

		// error?
		if($errorNumber != '') throw new LastFmSubmissionException($errorMessage, $errorNumber);

		// process response
		$lines = explode("\n", trim($response, "\n"));

		// validate response
		if($lines[0] !== 'OK')
		{
			// cleanup
			$lines[0] = trim($lines[0]);

			// throw error
			throw new LastFmSubmissionException($lines[0]);
		}

		// return the lines
		return $lines;
	}


	/**
	 * The initial negotiation with the submissions server to establish authentication and connection details for the session.
	 * A handshake must occur each time a client is started, and additionally if failures are encountered later on in the submission process.
	 *
	 * @return	void
	 */
	private function doHandshake()
	{
		// build parameters
		$parameters['hs'] = 'true';
		$parameters['p'] = '1.2.1';
		$parameters['c'] = $this->getClientId();
		$parameters['v'] = $this->getClientVersion();
		$parameters['u'] = $this->getUser();
		$parameters['t'] = time();
		$parameters['a'] = md5($this->getSecret() . $parameters['t']);
		$parameters['api_key'] = $this->getApiKey();
		$parameters['sk'] = $this->getSessionKey();

		// make the call
		$response = $this->doCall(self::API_URL, $parameters, 'GET');

		// store data
		$this->setSessionKey($response[1]);
		$this->urls['now_playing'] = $response[2];
		$this->urls['submission_url'] = $response[3];
	}


	/**
	 * Get the API key
	 *
	 * @return	string
	 */
	private function getAPIKey()
	{
		return $this->apiKey;
	}


	/**
	 * Get the secret
	 *
	 * @return	string
	 */
	private function getSecret()
	{
		return $this->secret;
	}


	/**
	 * Get the client id
	 *
	 * @return	string
	 */
	private function getClientId()
	{
		return (string) $this->clientId;
	}


	/**
	 * Get the client version
	 *
	 * @return	string
	 */
	private function getClientVersion()
	{
		return (string) $this->clientVersion;
	}


	/**
	 * Get the session key
	 *
	 * @return	string
	 */
	private function getSessionKey()
	{
		return (string) $this->sessionKey;
	}


	/**
	 * Get the timeout
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the user
	 *
	 * @return	string
	 */
	private function getUser()
	{
		return (string) $this->user;
	}


	/**
	 * Get the useragent
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP LastFm Scrobbling/'. self::VERSION .' '. $this->useragent;
	}


	/**
	 * Set the API key
	 *
	 * @return	void
	 * @param	string $apiKey
	 */
	private function setAPIKey($apiKey)
	{
		$this->apiKey = (string) $apiKey;
	}


	/**
	 * Set the client id
	 *
	 * @return	void
	 * @param	string $id
	 */
	private function setClientId($id)
	{
		$this->clientId = (string) $id;
	}


	/**
	 * Set the client version
	 *
	 * @return	void
	 * @param	string $version
	 */
	private function setClientVersion($version)
	{
		$this->clientVersion = (string) $version;
	}


	/**
	 * Set the secret
	 *
	 * @return	void
	 * @param	string $secret
	 */
	private function setSecret($secret)
	{
		$this->secret = (string) $secret;
	}


	/**
	 * Set the session key
	 *
	 * @return	void
	 * @param	string $key
	 */
	private function setSessionKey($key)
	{
		$this->sessionKey = (string) $key;
	}


	/**
	 * Set the timeout
	 *
	 * @return	void
	 * @param	int $seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user
	 *
	 * @return	void
	 * @param 	string $user
	 */
	private function setUser($user)
	{
		$this->user = (string) $user;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours
	 *
	 * @return	void
	 * @param	string $userAgent
	 */
	public function setUserAgent($useragent)
	{
		$this->useragent = (string) $useragent;
	}


// submission methods
	/**
	 * Optional lightweight notification of now-playing data at the start of the track for realtime information purposes.
	 * The Now-Playing notification is a lightweight mechanism for notifying Last.fm that a track has started playing. This is used for realtime display of a user's currently playing track, and does not affect a user's musical profile.
	 * The Now-Playing notification is optional, but recommended and should be sent once when a user starts listening to a song.
	 *
	 * @return	void
	 * @param	string $artist					The artist name.
	 * @param	string $track					The track name.
	 * @param	string[optional] $album			The album title.
	 * @param	int[optional] $length			The length of the track in seconds.
	 * @param	string[optional] $trackNumber	The position of the track on the album.
	 * @param	string[optional] $mbid			The MusicBrainz Track ID.
	 */
	public function submitNowPlaying($artist, $track, $album = null, $length = null, $trackNumber = null, $mbid = null)
	{
		// build parameters
		$parameters['s'] = $this->getSessionKey();
		$parameters['a'] = (string) $artist;
		$parameters['t'] = (string) $track;
		$parameters['b'] = ($album !== null) ? (string) $album : '';
		$parameters['i'] = ($length !== null) ? (int) $length : '';
		$parameters['n'] = ($trackNumber !== null) ? (string) $trackNumber : '';
		$parameters['m'] = ($mbid !== null) ? (string) $mbid : '';

		// make the call
		$this->doCall($this->urls['now_playing'], $parameters);
	}


	/**
	 * Submission of full track data at the end of the track for statistical purposes.
	 * The client should monitor the user's interaction with the music playing service to whatever extent the service allows. In order to qualify for submission all of the following criteria must be met:
	 * 	1. The track must be submitted once it has finished playing. Whether it has finished playing naturally or has been manually stopped by the user is irrelevant.
	 * 	2. The track must have been played for a duration of at least 240 seconds or half the track's total length, whichever comes first. Skipping or pausing the track is irrelevant as long as the appropriate amount has been played.
	 * 	3. The total playback time for the track must be more than 30 seconds. Do not submit tracks shorter than this.
	 * 	4. Unless the client has been specially configured, it should not attempt to interpret filename information to obtain metadata instead of using tags (ID3, etc).
	 *
	 * @return	void
	 * @param	string $artist					The artist name.
	 * @param	string $track					The track title.
	 * @param	int $time						The time the track started playing, in UNIX timestamp format. This must be in the UTC time zone, and is required.
	 * @param	string[optional] $source		The source of the track, possible values are: P = Chosen by the user (the most common value, unless you have a reason for choosing otherwise, use this), R = Non-personalised broadcast (e.g. Shoutcast, BBC Radio 1), E = Personalised recommendation except Last.fm (e.g. Pandora, Launchcast), L = Last.fm (any mode). In this case, the 5-digit Last.fm recommendation key must be appended to this source ID to prove the validity of the submission (for example, "o[0]=L1b48a").
	 * @param	string[optional] $rating		A single character denoting the rating of the track, possible values are: L = Love (on any mode if the user has manually loved the track). This implies a listen, B = Ban (only if source=L). This implies a skip, and the client should skip to the next track when a ban happens, S = Skip (only if source=L).
	 * @param	int[optional] $length			The length of the track in seconds. Required when the source is P, optional otherwise.
	 * @param	string[optional] $album			The album title.
	 * @param	string[optional] $trackNumber	The position of the track on the album.
	 * @param	string[optional] $mbid			The MusicBrainz Track ID
	 */
	public function submit($artist, $track, $time, $length = null, $source = 'P', $rating = null, $album = null, $trackNumber = null, $mbid = null)
	{
		// init vars
		$possibleSources = array('P', 'R', 'E', 'L');
		$possibleRatings = array('L', 'B', 'S');

		if(!in_array(substr($source, 0, 1), $possibleSources)) throw new LastFmSubmissionException('Invalid source ('. $source .'), possible values are: '. implode(', ', $possibleSources));
		if($rating !== null && !in_array($rating, $possibleRatings)) throw new LastFmSubmissionException('Invalid rating ('. $source .'), possible values are: '. implode(', ', $possibleRatings));

		// validate
		if($source == 'P' && $length == null) throw new LastFmSubmissionException('Length is required when source is P.');
		if($rating == 'B' && $source !== 'L') throw new LastFmSubmissionException('B can only be used when source is L');
		if($rating == 'S' && $source !== 'L') throw new LastFmSubmissionException('S can only be used when source is L');

		// build parameters
		$parameters['s'] = $this->getSessionKey();
		$parameters['a[0]'] = (string) $artist;
		$parameters['t[0]'] = (string) $track;
		$parameters['i[0]'] = (int) $time;
		$parameters['o[0]'] = (string) $source;
		$parameters['r[0]'] = ($rating !== null) ? (string) $rating : '';
		$parameters['l[0]'] = ($length !== null) ? (int) $length : '';
		$parameters['b[0]'] = ($album !== null) ? (string) $album : '';
		$parameters['n[0]'] = ($trackNumber !== null) ? (string) $trackNumber : '';
		$parameters['m[0]'] = ($mbid !== null) ? (string) $mbid : '';

		// make the call
		$this->doCall($this->urls['submission_url'], $parameters);
	}
}


/**
 * Last.fm Submission Exception class
 *
 * @author	Tijs Verkoyen <php-lastfm-submission@verkoyen.eu>
 */
class LastFmSubmissionException extends Exception
{
}

?>