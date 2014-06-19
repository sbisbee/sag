Sag
===

Version %VERSION%

http://www.saggingcouch.com

Sag is a PHP library for working with CouchDB. It is designed to not force any
particular programming method on its users - you just pass PHP objects, and get
stdClass objects and Exceptions back. This makes it trivial to incorporate Sag
into your application, build different functionality on top of it, and expand
Sag to incorporate new CouchDB functionality.

Compatability
-------------

Each Sag release is tested with an automated testing suite against all the
combinations of:

  - PHP 5.4.x

  - CouchDB 1.6.x

  - Cloudant

Lower versions of CouchDB and PHP will likely work with Sag, but they are not
officially supported, so your mileage may vary.

If you are running pre-1.5.1 CouchDB (important security fix) or pre-5.3 PHP,
then you probably want to look into updating your environment.

Error Handling
--------------

Sag's paradigm of simplicity is carried into its error handling by allowing you
to send data to CouchDB that will result in errors (ex., malformed JSON). This
is because CouchDB knows when there is an error better than Sag. This also
makes Sag more future proof, instead of worrying about each of CouchDB's API
changes. Therefore, Sag will only look for PHP interface problems and issues
that are native to PHP, such as passing an int instead of a stdClass.

All errors are floated back to your application with Exceptions. Sag does not
catch any errors itself, allowing your application to care about them or not.

There are two types of exceptions: 

SagException            For errors that happen within Sag, such as an invalid
                        type being passed to a function or being unable to open
                        a socket to the server.

SagCouchException       For errors generated by CouchDB (ex., if you pass it
                        invalid JSON). The CouchDB error message will be put
                        into the Exception's message (`$e->getMessage()`) and the
                        HTTP status code will be the exception's code
                        (`$e->getCode()`).

You can catch these two types of exceptions explicitly, allowing you to split
your error handling depending on where the error occurred, or implicitly by
simply catching the Exception class.

Networking
----------

Sag allows you to specify the HTTP library you want to use when communicating
with CouchDB. The supported libraries are:

  - cURL (`Sag::$CURL_HTTP_ADAPTER`) - has functionality that native sockets do
    not support, such as SSL. Used by default.

  - Native sockets (`Sag::$NATIVE_HTTP_ADAPTER`). Prevent dependencies, such as
    cURL, that shared environments may not support.

You can choose which library you want Sag to use by calling the
`setHTTPAdapter()` function and passing it the appropriate variable.

If you want to monitor your application's activity on the server side (ex., if
you are proxying requests to CouchDB through a web server), then examine the
HTTP User-Agent header.

Results
-------

When you have told Sag to decode CouchDB's responses (the default setting),
they are stored in an object, breaking out the HTTP header lines and data. For
example, running `print_r($sag->get('/1'));` (where '/1' is the location of a
document) would give you something like this:

```
(
    [headers] => stdClass Object
        (
            [_HTTP] => stdClass Object
                (
                    [raw] => HTTP/1.1 200 OK
                    [version] => 1.1
                    [status] => 200
                )

            [server] => CouchDB/1.5.0 (Erlang OTP/R15B01)
            [etag] => "1-967a00dff5e02add41819138abb3284d"
            [date] => Sat, 30 Nov 2013 20:39:43 GMT
            [content-type] => application/json
            [content-length] => 87
            [cache-control] => must-revalidate
        )

    [body] => stdClass Object
        (
            [_id] => 7c23517e0faa1af2786d27e2ae095552
            [_rev] => 1-967a00dff5e02add41819138abb3284d
        )

    [status] => 200
)
```

HTTP protocol information is stored in $result->headers, its headers broken out
as entries in the headers array - the "_HTTP" array element holds the basic
HTTP information in raw form (`$result->headers->_HTTP->raw)`, and then broken
out into HTTP version number (`$result->headers->_HTTP->version`) and status code
(`$result->headers->_HTTP->status`). The status code is also stored at the top of
the response object (`$result->status`).

The `$result->body` property holds the raw data from CouchDB, which you can have
Sag automatically decode into PHP objects with `json_decode()`.

The `$result->body` property holds the response body (usually JSON), which Sag
will automatically decode with `json_decode()` when the content-type is
'application/json'.

If you've told Sag to not decode CouchDB's responses, then it'll only return
the resulting JSON from CouchDB as a string (what would have been in the body
property if you had set decode to true). None of the HTTP info is included.

If CouchDB specifies Set-Cookies, then they will be stored in `$result->cookies`
as a stdClass.

Functions
---------

Detailed documentation of the functions and API are available at 
http://www.saggingcouch.com/documentation.php.

License
-------

Sag is released under the Apache License, version 2.0. See the file named
LICENSE for more information.

Copyright information is in the NOTICE file.

More?
-----

See http://www.saggingcouch.com for more detailed information, bug reporting,
planned features, etc.
