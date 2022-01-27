# Rackspace-Cloud-Files-PHP
Unofficial simple Rackspace Cloud Files client created out of necessity since the official
Rackspace PHP client ( https://github.com/rackspace/php-opencloud ) does not support PHP 8.
Written based on this documentation: https://docs.rackspace.com/docs/cloud-files/v1/storage-api-reference/

Feel free to submit pull requests if you'd like to add features.

This class supports uploading files from files and strings, deleting files, getting the CDN URLs for files,
and setting CORS headers on files. 

Doesn't support all the features like searching for file names, container names, large objects,
creating/managing containers, etc.

Requires `guzzlehttp/guzzle`. 

Free to use in any project, commercial or otherwise.

# Usage

Make sure `guzzlehttp/guzzle` is installed in your app. Try `composer require guzzlehttp/guzzle`

Once that's installed, include the cloud-files.php file into your app, then try these examples.

## Setup

Instantiate one class per Cloud Files container

```php
require_once 'cloud-files.php';

$rackspace_username = 'your-username-here';
$rackspace_api_key = 'your-rackspace-api-key-here';
$container_name = 'Your container name here';
$cloud_files_region = 'DFW'; // put your Cloud Files region here, e.g. DFW, ORD etc
$identity_endpoint = 'US'; // or UK, or other regions they may offer

$MyContainer = new CloudFilesClass( $container_name, $rackspace_username, $rackspace_api_key, $rackspace_region, $identity_endpoint );
```

## Add a file from a file on the file system

```php
$MyContainer->uploadObjectFromLocalFile( 'filename_to_save_it_as_on_cloud_files.txt', 'local_filename.txt' );
```

## Add a file from a string

```php

// If you do not want cross-origin headers added, do this:
$MyContainer->uploadObjectFromString( 'filename_to_save_it_as_on_cloud_files.txt', 'this is the string that will be in the file' );

// If you *do* want cross-origin headers added, do this:
$MyContainer->uploadObjectFromString( 'filename_to_save_it_as_on_cloud_files.txt', 'this is the string that will be in the file', true );
```

## Get the CDN URL for a filename. The container must already be CDN-enabled.

```php
// HTTPS (recommended)
$https_url = $MyContainer->getHttpsUrlForObject( 'filename_to_get_cdn_url_for_on_cloud_files.txt' );

// HTTP (not recommended)
$http_url = $MyContainer->getHttpUrlForObject( 'filename_to_get_cdn_url_for_on_cloud_files.txt' );
```

## Download the file contents from Cloud Files into a string

```php
$file_contents = $MyContainer->getObjectToString( 'filename_on_cloud_files.txt' );
```

## Delete a file on Cloud Files

```php
$MyContainer->deleteObject( 'filename_to_delete_on_cloud_files.txt' );
```
