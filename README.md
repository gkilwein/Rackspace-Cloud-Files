# Rackspace-Cloud-Files
Unofficial simple Rackspace Cloud Files client created out of necessity since the official
Rackspace PHP client ( https://github.com/rackspace/php-opencloud ) does not support PHP 8.
Written based on this documentation: https://docs.rackspace.com/docs/cloud-files/v1/storage-api-reference/

Feel free to submit pull requests if you'd like to add features.

This class supports uploading files from files and strings, deleting files, getting the CDN URLs for files,
and setting CORS headers on files. 

Doesn't support all the features like searching for file names, container names, etc. 

Requires guzzlehttp/guzzle. 

Free to use in any project, commercial or otherwise.

# Usage

