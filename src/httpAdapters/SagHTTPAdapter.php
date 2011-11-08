<?php
/**
 * Provides a common interface for Sag to connect to CouchDB over HTTP,
 * allowing for different drivers to be used thereby controlling your project's
 * dependencies.
 *
 * @version 0.7.1
 * @package HTTP
 */
abstract class SagHTTPAdapter
{
	//*************************************************************************
	//* Private Members
	//*************************************************************************

	/**
	 * @var bool
	 */
	public $decodeResp = true;
	/**
	 * @var string
	 */
	protected $host = '127.0.0.1';
	/**
	 * @var int
	 */
	protected $port = 5984;
	/**
	 * @var string http or https
	 */
	protected $proto = 'http';
	/**
	 * @var string
	 */
	protected $sslCertPath = null;
	/**
	 * @var int The seconds until socket connection timeout
	 */
	protected $socketOpenTimeout;
	/**
	 * @var int The seconds for socket I/O timeout
	 */
	protected $socketRWTimeoutSeconds;
	/**
	 * @var int The microseconds for socket I/O timeout
	 */
	protected $socketRWTimeoutMicroseconds;
	/**
	 * @var bool If true, no body will be returned for a HEAD request
	 */
	protected $_suppressHeadBody;

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * @param string $host
	 * @param string $port
	 */
	public function __construct( $host = '127.0.0.1', $port = '5984' )
	{
		$this->host = $host;
		$this->port = $port;
	}

	/**
	 * @param \stdClass $response
	 * @param string $method
	 * @return \stdClass
	 * @throws SagCouchException|SagException
	 */
	protected function makeResult( $response, $method )
	{
		if ( 'HEAD' == $method )
		{
			/**
			 * HEAD requests can return an HTTP response code >= 400, meaning that there
			 * was a CouchDB error, but we don't get a $response->body->error because
			 * HEAD responses don't have bodies.
			 *
			 * And we do this before the json_decode() because even running
			 * json_decode() on undefined can take longer than calling it on a JSON
			 * string. So no need to run any of the $_jsonResponse code.
			 */
			if ( $response->headers->_HTTP->status >= 400 )
			{
				throw new SagCouchException( 'HTTP/CouchDB error without message body', $response->headers->_HTTP->status );
			}

			//	And that's it!
			return $response;
		}

		//	Make sure we have entire response
		if ( isset( $response->headers->{'Content-Length'} ) && strlen( $response->body ) != $response->headers->{'Content-Length'} )
		{
			throw new SagException( 'Content length does not match size of received body.' );
		}

		/**
		 * $_jsonResponse will be set to false if invalid JSON is sent back to us.
		 * This will most likely happen if we're GET'ing an attachment that isn't JSON
		 * (ex., a picture or plain text). Don't be fooled by storing a PHP string in an
		 * attachment as text/plain and then expecting it to be parsed properly by json_decode().
		 */
		if ( false !== ( $_jsonResponse = json_decode( $response->body ) ) )
		{
			/**
			 * Check for an error from CouchDB regardless of whether they want JSON returned.
			 */
			if ( !empty( $_jsonResponse->error ) )
			{
				throw new SagCouchException( $_jsonResponse->error . ' (' . $_jsonResponse->reason . ')"', $response->headers->_HTTP->status );
			}

			$response->body = ( $this->decodeResp ? $_jsonResponse : $response->body );
		}

		return $response;
	}

	/**
	 * @param string $cookieHeader
	 * @return \stdClass
	 */
	protected function parseCookieString( $cookieHeader )
	{
		$_cookies = new \stdClass();

		foreach ( explode( '; ', $cookieHeader ) as $_cookie )
		{
			$_crumbs = explode( '=', $_cookie );
			$_cookies->{trim( $_crumbs[0] )} = trim( $_crumbs[1] );
		}

		return $_cookies;
	}

	/**
	 * Processes the packet, returning the server's response.
	 *
	 * @param string $method
	 * @param string $url
	 * @param mixed|null $data
	 * @param array $headers
	 */
	abstract public function procPacket( $method, $url, $data = null, $headers = array() );

	//*************************************************************************
	//* Properties
	//*************************************************************************

	/**
	 * Whether to use HTTPS or not.
	 *
	 * @param boolean $use
	 */
	public function useSSL( $use = false )
	{
		$this->proto = 'http' . ( ( $use ) ? 's' : '' );
	}

	/**
	 * Sets the location of the CA file.
	 *
	 * @param $path
	 */
	public function setSSLCert( $path = null )
	{
		$this->sslCertPath = $path;
	}

	/**
	 * Returns whether Sag is using SSL.
	 *
	 * @return bool
	 */
	public function usingSSL()
	{
		return ( 'https' == $this->proto );
	}

	/**
	 * Sets how long Sag should wait to establish a connection to CouchDB.
	 *
	 * @param int $seconds
	 */
	public function setOpenTimeout( $seconds )
	{
		if ( !is_int( $seconds ) || $seconds < 1 )
		{
			throw new SagException( 'setOpenTimeout() expects a positive integer.' );
		}

		$this->socketOpenTimeout = $seconds;
	}

	/**
	 * Set how long we should wait for an HTTP request to be executed.
	 *
	 * @param int $seconds The number of seconds.
	 * @param int $microseconds The number of microseconds.
	 */
	public function setRWTimeout( $seconds, $microseconds = 0 )
	{
		if ( !is_int( $microseconds ) || $microseconds < 0 )
		{
			throw new SagException( 'setRWTimeout() expects $microseconds to be an integer >= 0.' );
		}

		//TODO make this better, including checking $microseconds
		//$seconds can be 0 if $microseconds > 0
		if ( !is_int( $seconds ) || ( ( !$microseconds && $seconds < 1 ) || ( $microseconds && $seconds < 0 ) )
		)
		{
			throw new SagException( 'setRWTimeout() expects $seconds to be a positive integer.' );
		}

		$this->socketRWTimeoutSeconds = $seconds;
		$this->socketRWTimeoutMicroseconds = $microseconds;
	}

	/**
	 * Returns an associative array of the currently set timeout values.
	 *
	 * @return array An associative array with the keys 'open', 'rwSeconds', and
	 * 'rwMicroseconds'.
	 *
	 * @see setTimeoutsFromArray()
	 */
	public function getTimeouts()
	{
		return array(
			'open' => $this->socketOpenTimeout,
			'rwSeconds' => $this->socketRWTimeoutSeconds,
			'rwMicroseconds' => $this->socketRWTimeoutMicroseconds
		);
	}

	/**
	 * A utility function that sets the different timeout values based on an
	 * associative array.
	 *
	 * @param array $arr An associative array with the keys 'open', 'rwSeconds',
	 * and 'rwMicroseconds'.
	 *
	 * @see getTimeouts()
	 */
	public function setTimeoutsFromArray( $arr )
	{
		/**
		 * Validation is lax in here because this should only ever be used with
		 * getTimeouts() return values. If people are using it by hand then there
		 * might be something wrong with the API.
		 */
		if ( !is_array( $arr ) )
		{
			throw new SagException( 'Expected an array and got something else.' );
		}

		if ( is_int( $arr['open'] ) )
		{
			$this->setOpenTimeout( $arr['open'] );
		}

		if ( is_int( $arr['rwSeconds'] ) )
		{
			if ( is_int( $arr['rwMicroseconds'] ) )
			{
				$this->setRWTimeout( $arr['rwSeconds'], $arr['rwMicroseconds'] );
			}
			else
			{
				$this->setRWTimeout( $arr['rwSeconds'] );
			}
		}
	}

	/**
	 * @param boolean $suppressHeadBody
	 * @return \SagHTTPAdapter
	 */
	public function setSuppressHeadBody( $suppressHeadBody )
	{
		$this->_suppressHeadBody = $suppressHeadBody;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getSuppressHeadBody()
	{
		return $this->_suppressHeadBody;
	}

}