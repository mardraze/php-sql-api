<?php 
/**
 * Sample configuration, you can use it as a base for manual configuration. 
 */

/**
 * This is needed for cookie based authentication to encrypt the cookie.
 * Needs to be a 32-bytes long string of random bytes. See FAQ 2.10.
 */
$cfg['blowfish_secret'] = ''; /* YOU MUST FILL IN THIS FOR COOKIE AUTH! */

/**
 * Servers configuration
 */
$i = 0;

/**
 * First server
 */
$i++;
/* Authentication type */
$cfg['Servers'][$i]['auth_type'] = 'session'; 

/* DSN examples
 * mysql:host=localhost
 * pgsql:host=localhost
 * sqlite:/opt/databases/mydb.sq3
 */
$cfg['Servers'][$i]['dsn'] = 'pgsql:host=localhost'; 

/**

Second server

$i++;
$cfg['Servers'][$i]['auth_type'] = 'session'; 
$cfg['Servers'][$i]['dsn'] = 'mysql:host=localhost'; 

Third server

$i++;
$cfg['Servers'][$i]['auth_type'] = 'session'; 
$cfg['Servers'][$i]['dsn'] = 'sqlite:/opt/databases/mydb.sq3'; 

*/
/**
 * Directories for saving/loading files from server
 */
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
