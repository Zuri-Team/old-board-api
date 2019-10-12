<?php

return [
    'supportsCredentials' => false,
    'allowedOrigins' => ['*'],
    // 'allowedHeaders' => ['*'],
    'allowedHeaders' => ['Accept', 'Content-Type', 'Origin', 'User-Agent', 'X-Requested-With', 'Authorization'],
    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
    'exposedHeaders' => ['DAV', 'content-length', 'Allow'],
    // 'exposedHeaders' => [],
    'maxAge' => 86400,
    'hosts' => [],
];