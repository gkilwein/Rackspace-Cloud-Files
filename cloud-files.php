<?php

// Unofficial Rackspace Cloud Files PHP class.  Supports uploading files from files and strings, deleting files, getting the CDN URLs for files,
// and setting CORS headers on files. Doesn't support all the features like searching for file names, container names, etc. 
// Requires guzzlehttp/guzzle. Written based on this documentation: https://docs.rackspace.com/docs/cloud-files/v1/storage-api-reference/

if( class_exists( 'CloudFilesClass' ) )
    return;

require 'vendor/autoload.php'; // for guzzlehttp/guzzle


// Usage for CloudFilesClass (v2, the unofficial version built using CURL from https://docs.rackspace.com/docs/cloud-files/v1/storage-api-reference/ )
// Instantiate new object with the container name, Rackspace username and API key, and region (e.g. 'DFW', 'ORD', etc). Optional region 'US' or 'UK' supported.
// e.g. $profile_photos_container = new CloudFilesClass( 'Profile Photos', 'username', 'apikey', 'DFW' );
class CloudFilesClass
{
    private $US_IDENTITY_ENDPOINT = 'https://identity.api.rackspacecloud.com/v2.0/';
    private $UK_IDENTITY_ENDPOINT = 'https://lon.identity.api.rackspacecloud.com/v2.0/';

    private $http_client = null;
    private $no_exception_http_client = null;

    private $auth_token = null;
    private $cloud_files_endpoint_url = null;
    private $cloud_files_cdn_endpoint_url = null;
    private $container_cdn_url_https = null;
    private $container_cdn_url_http = null;

    private $objectStoreService = null;
    private $container = null;
    private $container_name = null;
    private $cors_headers = [
        // CORS headers
        'X-Container-Meta-Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => '*',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Expose-Headers' => 'Origin, X-Requested-With, Content-Type, Accept, Authorization',
        'Access-Control-Request-Headers' => 'Origin, X-Requested-With, Content-Type, Accept, Authorization'
    ];

    function __construct( $container_name, $rackspace_username, $rackspace_api_key, $rackspace_region, $identity_endpoint = 'US' )
    {
        if( $identity_endpoint == 'UK' )
            $authUrl = $this->UK_IDENTITY_ENDPOINT;
        else
            $authUrl = $this->US_IDENTITY_ENDPOINT;

        try {
            $initial_http_client = new GuzzleHttp\Client([
                'headers' => [
                    'Content-type' => 'application/json'
                ],
            ]);
            $response = $initial_http_client->request('POST', $authUrl . 'tokens', [
                'json' => [
                    'auth' => [
                        'RAX-KSKEY:apiKeyCredentials' => [
                            'username' => $rackspace_username,
                            'apiKey' => $rackspace_api_key
                        ]
                    ],
                ],
            ]);
            if( $response->getStatusCode() == 200 ) {
                $auth_response_object = json_decode($response->getBody());

                // get auth token from the TOKEN section of the response
                $auth_token = $auth_response_object->access->token->id;

                $cloud_files_endpoint_url = '';
                $cloud_files_cdn_endpoint_url = '';

                $service_catalog = $auth_response_object->access->serviceCatalog;
                foreach( $service_catalog as $catalog ) {
                    if( $catalog->name == 'cloudFilesCDN' ) {
                        foreach( $catalog->endpoints as $endpoint ) {
                            if ($endpoint->region == $rackspace_region)
                                $cloud_files_cdn_endpoint_url = $endpoint->publicURL;
                        }
                    }
                    else if( $catalog->name == 'cloudFiles' ) {
                        foreach( $catalog->endpoints as $endpoint ) {
                            if ($endpoint->region == $rackspace_region)
                                $cloud_files_endpoint_url = $endpoint->publicURL;
                        }
                    }
                }

                // Set the auth token (good for 24 hours)
                $this->auth_token = $auth_token;

                // Set the Cloud Files endpoint URL
                $this->cloud_files_endpoint_url = $cloud_files_endpoint_url;

                // Set the Cloud Files CDN endpoint URL (used for getting the container CDN URL)
                $this->cloud_files_cdn_endpoint_url = $cloud_files_cdn_endpoint_url;

                // Set the container name
                $this->container_name = $container_name;

                // create a new HTTP client with auto authentication
                $this->http_client = new GuzzleHttp\Client([
                    'headers' => [
                        'Content-type' => 'application/json',
                        'X-Auth-Token' => $this->auth_token
                    ]
                ]);

                // create a "no exceptions" new HTTP client with auto authentication
                $this->no_exception_http_client = new GuzzleHttp\Client([
                    'headers' => [
                        'Content-type' => 'application/json',
                        'X-Auth-Token' => $this->auth_token
                    ],
                    // don't raise an exception on a 400 or 500 error
                    'http_errors' => false
                ]);
            }
            else
                echo 'Unhandled response ' . $response->getStatusCode();

        } catch( GuzzleHttp\Exception\RequestException $exception ) {
            ErrorLog(  'Exception with Rackspace Cloud Files: ' . $exception->getResponse()->getBody()->getContents(), __FILE__, __LINE__, null, $exception );
        } catch( Throwable $throwable ) {
            ErrorLog(  'Exception: ' . $throwable->getMessage(), __FILE__, __LINE__, null, $exception );
        }
    }

    // Purpose: populate the container's CDN URLs
    // Inputs: nothing - just needs an initialized class with the container name
    // Returns: nothing - just sets the $this->container_cdn_url_https and $this->container_cdn_url_http variables
    private function getContainerCdnUrls()
    {
        if( $this->container_name === null )
            return;

        $response = $this->no_exception_http_client->head( $this->cloud_files_cdn_endpoint_url . '/' . $this->container_name );

        // if the container is not CDN-enabled, these URLs will be null
        $this->container_cdn_url_https = $response->getHeader('X-Cdn-Ssl-Uri')[0];
        $this->container_cdn_url_http = $response->getHeader('X-Cdn-Uri')[0];
    }

    // Purpose: get the HTTPS CDN URL for a filename in the current container
    // Input: $filename
    // Returns: string with filename of the secure (HTTPS) version of the file or null if container is not CDN-enabled
    public function getHttpsUrlForObject( $filename )
    {
        if( $this->container_cdn_url_https === null )
            $this->getContainerCdnUrls();

        if( $this->container_cdn_url_https === null )
            return null;

        return $this->container_cdn_url_https . '/' . $this->encodeURIComponent( $filename );
    }

    // Purpose: get the HTTP CDN URL for a filename in the current container
    // Input: $filename
    // Returns: string with filename of the insecure (HTTP) version of the file or null if container is not CDN-enabled
    public function getHttpUrlForObject( $filename )
    {
        if( $this->container_cdn_url_http === null )
            $this->getContainerCdnUrls();

        if( $this->container_cdn_url_http === null )
            return null;

        return $this->container_cdn_url_http . '/' . $this->encodeURIComponent( $filename );
    }

    // Purpose: delete an object by filename
    // Inputs: $filename: name of the filename to delete
    // Returns: response
    public function deleteObject( $filename )
    {
        $retval = null;

        try {
            return $this->http_client->delete( $this->cloud_files_endpoint_url . '/' . $this->container_name . '/' . $filename );
        }
        catch( Throwable $ex ) {
            usleep( 100 );

            // try again in case it was a random error
            try {
                $retval = $this->http_client->delete( $this->cloud_files_endpoint_url . '/' . $this->container_name . '/' . $filename );
            }
            catch( Throwable $ex ) {
                error_log( 'Error deleting Cloud Files object. Code: ' . $ex->getCode() . ' message: ' . $ex->getMessage() . 
                          ' file name: ' . $filename . ' Container name: ' . $this->container_name );
            }
        }
        return $retval;
    }

    // retrieves contents of an object from the cloud files container based on filename
    public function getObjectToString( $filename )
    {
        $response_string = '';

        try {
            $retval = $this->http_client->get( $this->cloud_files_endpoint_url . '/' . $this->container_name . '/' . $filename );
            $response_string = $retval->getBody()->getContents();
        }
        catch( Throwable $ex ) {
            usleep( 100 );

            // try again in case it was a random error
            try {
                $retval = $this->http_client->get( $this->cloud_files_endpoint_url . '/' . $this->container_name . '/' . $filename );
                $response_string = $retval->getBody()->getContents();
            }
            catch( Throwable $ex ) {
                error_log( 'Error getting Cloud Files object. Code: ' . $ex->getCode() . ' message: ' . $ex->getMessage() . 
                          ' file name: ' . $filename . ' Container name: ' . $this->container_name );
            }
        }
        return $response_string;
    }

    // uploads an object from a local file to a cloud files container, by filename, then deletes the local file
    // returns newly created object
    public function uploadObjectFromLocalFile( $remote_filename, $local_filename )
    {
        // sanitize name... this converts characters such as ø to +APg-
        $remote_filename = mb_convert_encoding( $remote_filename, 'UTF-7' );

        // open the file to upload
        $handle = fopen($local_filename, 'r');
        $content_type = mime_content_type( $handle );

        try {
            $upload_object = [
                'name' => $remote_filename,
                'body' => $handle,
                'headers' => $this->cors_headers
            ];
            if( $content_type !== false )
                $upload_object['headers']['Content-Type'] = $content_type;

            $response = $this->http_client->put( $this->cloud_files_endpoint_url . '/' . $this->container_name . '/' . $remote_filename, $upload_object );

            // erase temporary local file
            unlink( $local_filename );

            return GetModelSuccess( $response );
        }
        catch( Throwable $ex ) {
            error_log( 'Error uploading file to Cloud Files from a file. Code: ' . $ex->getCode() . ' message: ' . $ex->getMessage() . 
                      ' Container name: ' . $this->container_name );
            return false;
        }
        return true;
    }

    // uploads an object from a string $string_to_upload to a cloud files container, by filename
    // returns newly created object
    public function uploadObjectFromString( $remote_filename, $string_to_upload, $include_cors_headers = false )
    {
        // sanitize name... this converts characters such as ø to +APg-
        $remote_filename = mb_convert_encoding( $remote_filename, 'UTF-7' );

        try {
            $upload_object = [
                'name' => $remote_filename,
                'body' => $string_to_upload
            ];
            if( $include_cors_headers === true )
                $upload_object['headers'] = $this->cors_headers;

            $response = $this->http_client->put( $this->cloud_files_endpoint_url . '/' . $this->container_name . '/' . $remote_filename, $upload_object );
            return GetModelSuccess( $response );
        }
        catch( Throwable $ex ) {
            error_log( 'Error uploading file to Cloud Files from a string. Code: ' . $ex->getCode() . ' message: ' . $ex->getMessage() . 
                      ' Container name: ' . $this->container_name );
            return false;
        }
        return true;
    }
  
    // encoding function for URI components
    private function encodeURIComponent($str) {
        $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
        return strtr(rawurlencode($str), $revert);
    }

}
