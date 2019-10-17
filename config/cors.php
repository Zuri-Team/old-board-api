<?php

return [
    'supportsCredentials' => true,
    'allowedOrigins' => ['*'],
     'allowedHeaders' => ['*'],
    //'allowedHeaders' => ["Access-Control-Allow-Headers", "Access-Control-Allow-Headers", "Origin", "Accept", "X-Requested-With", "Content-Type", "Access-Control-Request-Method", "Access-Control-Request-Headers", "Authorization"],
    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
    'exposedHeaders' => ['DAV', 'content-length', 'Allow'],
    // 'exposedHeaders' => [],
    'maxAge' => 86400,
    'hosts' => [],
    
    //"Access-Control-Allow-Headers", "Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers"
];
