<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary API configuration
    |--------------------------------------------------------------------------
    |
    | Before using Cloudinary you need to register and get some detail
    | to fill in below, please visit cloudinary.com.
    |
    */

    //'cloudName'  => 'hngojet', 
   // 'baseUrl'    => env('CLOUDINARY_BASE_URL', 'http://res.cloudinary.com/'.env('CLOUDINARY_CLOUD_NAME')),
   // 'secureUrl'  => env('CLOUDINARY_SECURE_URL', 'https://res.cloudinary.com/'.env('CLOUDINARY_CLOUD_NAME')),
   // 'apiBaseUrl' => env('CLOUDINARY_API_BASE_URL', 'https://api.cloudinary.com/v1_1/'.env('CLOUDINARY_CLOUD_NAME')),
  //  'apiKey'     => env('CLOUDINARY_API_KEY'),
   // 'apiSecret'  => env('CLOUDINARY_API_SECRET'),

	'cloudName'  => 'hngojet', 
    'baseUrl'    => 'http://res.cloudinary.com/hngojet',
    'secureUrl'  => 'https://res.cloudinary.com/hngojet',
    'apiBaseUrl' => 'https://api.cloudinary.com/v1_1/hngojet',
    'apiKey'     => '478836986573586',
    'apiSecret'  => 'g9A--r-PHEPsiz8hBxla9-SZAd8',

    'scaling'    => [
        'format' => 'png',
        'width'  => 150,
        'height' => 150,
        'crop'   => 'fit',
        'effect' => null
    ],
//Cloud name:	hngojet
//API Key:	478836986573586
// API Secret:	g9A--r-PHEPsiz8hBxla9-SZAd8


];
