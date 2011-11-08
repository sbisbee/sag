<?php
/**
 * Uses the PHP cURL bindings for HTTP communication with CouchDB. This gives
 * you more advanced features, like SSL supports, with the cost of an
 * additional dependency that your shared hosting environment might now have.
 *
 * @version 0.7.0
 * @package HTTP
 */
class SagCURLHTTPAdapter extends SagHTTPAdapter
{
	//*************************************************************************
	//* Private Members
	//*************************************************************************

	/**
	 * @var resource
	 */
	protected $_curlHandle = null;
	/**
	 * @var array The response headers post-parse
	 */
	protected $_responseHeaders = null;
	/**
	 * @var array The base curl options
	 */
	protected $_baseCurlOptions = array(
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HEADER => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	);

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * @param $host
	 * @param $port
	 */
	public function __construct( $host, $port )
	{
		if ( !extension_loaded( 'curl' ) )
		{
			throw new \SagException( 'Sag cannot use cURL on this system: the PHP cURL extension is not installed.' );
		}

		parent::__construct( $host, $port );

		//	Reset the curl handle
		$this->reset();
	}

	/**
	 * Resets CURL, re-initializes and gets a new handle
	 */
	public function reset()
	{
		if ( null !== $this->_curlHandle )
		{
			@curl_close( $this->_curlHandle );
			$this->_curlHandle = null;
		}

		$this->_curlHandle = curl_init();
	}

	/**
	 * @param string $method
	 * @param string $url
	 * @param mixed|null $payload
	 * @param array $headers
	 * @return mixed
	 * @throws \SagException
	 */
	public function procPacket( $method, $url, $payload = null, $headers = array() )
	{
		$_haveStatus = false;
		$_response = null;
		$_chunks = array();

		//	Set our CURL options
		curl_setopt_array(
			$this->_curlHandle,
			$this->_buildCurlOptions( trim( strtoupper( $method ) ), $url, $payload, $headers )
		);

		//	Make the call
		if ( false === ( $_curlResponse = curl_exec( $this->_curlHandle ) ) )
		{
			if ( curl_errno( $this->_curlHandle ) )
			{
				throw new SagException(
					'cURL error #' . curl_errno( $this->_curlHandle ) . ': ' . curl_error( $this->_curlHandle ),
					curl_errno( $this->_curlHandle )
				);
			}

			throw new SagException( 'cURL failed without providing an good reason.' );
		}

		//	Split headers and body
		$_responseParts = explode( "\r\n\r\n", $_curlResponse );

		foreach ( $_responseParts as $_chunk )
		{
			$_parts = explode( "\r\n", $_chunk );

			if ( is_array( $_parts ) && !empty( $_parts ) && false !== stripos( $_parts[0], 'HTTP/1.1 100 Continue' ) )
			{
				//	This is a continue header, skip it completely!
				continue;
			}

			$_chunks[] = $_chunk;
		}

		//	Now, deal with the response
		if ( empty( $_chunks ) )
		{
			throw new \SagException( 'Invalid response received from server: ' . $_curlResponse, 500 );
		}

		//	Prepare the response object
		$_response = new stdClass();
		$_response->body = isset( $_chunks[1] ) ? $_chunks[1] : null;

		//	Parse out the headers
		foreach ( explode( "\r\n", $_chunks[0] ) as $_header )
		{
			if ( false === $_haveStatus )
			{
				$_response->request = new \stdClass();
				$_response->request->method = $method;
				$_response->request->url = $url;

				$_response->headers = new \stdClass();
				$_response->headers->_HTTP = new \stdClass();
				$_response->headers->_HTTP->raw = $_header;

				preg_match( '(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $_header, $_match );

				$_response->headers->_HTTP->version = $_match['version'];
				$_response->headers->_HTTP->status = $_match['status'];
				$_response->status = $_match['status'];

				$_haveStatus = true;
				continue;
			}

			//	Parse the header line
			$_parts = explode( ':', $_header, 2 );
			$_response->headers->{$_parts[0]} = trim( $_parts[1] );

			if ( 'set-cookie' == strtolower( trim( $_parts[0] ) ) )
			{
				$_response->cookies = $this->parseCookieString( $_parts[1] );
			}

			unset( $_header );
		}

		unset( $_chunks );

		//	Clean up body if this was a HEAD
		if ( 'HEAD' == $method && isset( $_response->body ) && empty( $_response->body ) )
		{
			$_response->body = null;
		}

		return $this->makeResult( $_response, $method );
	}

	//*************************************************************************
	//* Private Methods
	//*************************************************************************

	/**
	 * @param string $method
	 * @param string $url
	 * @param array|null $payload
	 * @param array|null $headers
	 * @return array
	 */
	protected function _buildCurlOptions( $method, $url, $payload = null, array $headers = null )
	{
		if ( null === $this->_curlHandle )
		{
			$this->reset();
		}

		//	Reset our handle
		curl_setopt( $this->_curlHandle, CURLOPT_HTTPGET, true );

		//	Start building out the options array
		$_curlOptions = $this->_baseCurlOptions;
		$_curlOptions[CURLOPT_URL] = $this->proto . '://' . $this->host . $url;
		$_curlOptions[CURLOPT_PORT] = $this->port;

		//	Set the method if non-standard
		switch ( $method )
		{
			case 'POST':
				$_curlOptions[CURLOPT_POST] = true;
				break;

			case 'PUT':
				$_curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
				break;

			case 'GET':
				//	Nothing to do here
				break;

			case 'DELETE':
				$_curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
				break;

			case 'HEAD':
				if ( false === $this->_suppressHeadBody )
				{
					$_curlOptions[CURLOPT_CUSTOMREQUEST] = 'HEAD';
				}
				else
				{
					$_curlOptions[CURLOPT_NOBODY] = true;
				}
				break;

			default:
				throw new \SagException( 'Invalid or unsupported method "' . $method . '" requested.' );
		}
		
		//	Add in any headers
		if ( !empty( $headers ) )
		{
			$_headers = array();

			foreach ( $headers as $_key => $_header )
			{
				if ( is_numeric( $_key ) )
				{
					$_headers[] = $_header;
				}
				else
				{
					$_headers[] = $_key . ':' . $_header;
				}
			}

			$_curlOptions[CURLOPT_HTTPHEADER] = $_headers;
		}

		//	Set the data if any
		if ( null !== $payload )
		{
			$_curlOptions[CURLOPT_POSTFIELDS] = $payload;
		}

		//	Connect timeout
		if ( null !== $this->socketOpenTimeout )
		{
			$_curlOptions[CURLOPT_CONNECTTIMEOUT] = $this->socketOpenTimeout;
		}

		//	Exec timeout (seconds)
		if ( null !== $this->socketRWTimeoutSeconds )
		{
			$_curlOptions[CURLOPT_TIMEOUT] = $this->socketRWTimeoutSeconds;
		}

		//	Exec timeout (ms)
		if ( null !== $this->socketRWTimeoutMicroseconds )
		{
			$_curlOptions[CURLOPT_TIMEOUT_MS] = $this->socketRWTimeoutMicroseconds;
		}

		//	SSL support: don't verify unless we have a cert set
		if ( 'https' == $this->proto )
		{
			if ( null === $this->sslCertPath )
			{
				$_curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
			}
			else
			{
				$_curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
				$_curlOptions[CURLOPT_SSL_VERIFYHOST] = true;
				$_curlOptions[CURLOPT_CAINFO] = $this->sslCertPath;
			}
		}

		return $_curlOptions;
	}

	//*************************************************************************
	//* Properties
	//*************************************************************************

	/**
	 * @return array
	 */
	public function getResponseHeaders()
	{
		return $this->_responseHeaders;
	}

	/**
	 * @return \resource
	 */
	public function getCurlHandle()
	{
		return $this->_curlHandle;
	}

	/**
	 * @return array
	 */
	public function getBaseCurlOptions()
	{
		return $this->_baseCurlOptions;
	}

}
