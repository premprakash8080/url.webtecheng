<?php
/**
 * =======================================================================================
 *                           GemFramework (c) GemPixel                                     
 * ---------------------------------------------------------------------------------------
 *  This software is packaged with an exclusive framework as such distribution
 *  or modification of this framework is not allowed before prior consent from
 *  GemPixel. If you find that this framework is packaged in a software not distributed 
 *  by GemPixel or authorized parties, you must not use this software and contact gempixel
 *  at https://gempixel.com/contact to inform them of this misuse otherwise you risk
 *  of being prosecuted in courts.
 * =======================================================================================
 *
 * @package GemPixel\Premium_URL_Shortener
 * @author GemPixel (https://gempixel.com)
 * @copyright 2023 GemPixel
 * @license https://gempixel.com/license
 * @link https://gempixel.com  
 */
  
  // Database Configuration
  define('DBhost', 'shareddb-u.hosting.stackcp.net');      // Your mySQL Host (usually Localhost)
  define('DBname', 'urlshortener-313333ed62');         // The database name where the data will be stored
  define('DBuser', 'urlshortener-313333ed62');         // Your mySQL username
  define('DBpassword', '1yarwswl0p');        //  Your mySQL Password 
  define('DBprefix', '');         // Prefix for your tables if you are using same db for multiple scripts

  define('DBport', 3306);

  // This is your base path. If you have installed this script in a folder, add the folder's name here. e.g. /folderName/
  define('BASEPATH', 'AUTO');

  // Use CDN to host libraries for faster loading
  define('USECDN', true);    

  // CDN URL to your assets
  define('CDNASSETS', null);
  define('CDNUPLOADS', null);

  // If FORCEURL is set to false, the software will accept any domain name that resolves to the server otherwise it will force settings url
  define('FORCEURL', true);

  // Your Server's Timezone - List of available timezones (Pick the closest): https://php.net/manual/en/timezones.php  
  define('TIMEZONE', 'GMT+0'); 

  // Cache Data - If you notice anomalies, disable this. You should enable this when you get high hits
  define('CACHE', true);  

  // Do not enable this if your site is live or has many visitors
  define('DEBUG', 0);

  /************************************************************************************
   ====================================================================================
   * Do not change anything below - it might crash your site
   * ----------------------------------------------------------------------------------
   *  - Setup a security phrase - This is used to encode some important user 
   *    information such as password. The longer the key the more secure they are.
   *  - If you change this, many things such as user login and even admin login will 
   *    not work anymore.
   ====================================================================================
   ***********************************************************************************/

  define('AuthToken', 'PUSce6c18636f856a65b7441c802701144cdf73ec125872ab1c343dc094cdf73089');
  define('EncryptionToken', 'def00000e8b27a6761ce0701c31eff1adb08585c049f0be2fea4f72f6c3dc016d491e21d914e9e1e006d85d8c2646c38122bd80e1d9448117e10ee22b1d90a2e9cd1859a');
  define('PublicToken', '63ddfa6880da7257f661de87928cba13');