<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'import#createSignedUrl', 'url' => '/api/create-signed-url', 'verb' => 'POST'],
        ['name' => 'import#download', 'url' => '/download/{token}', 'verb' => 'GET'],
        ['name' => 'config#get', 'url' => '/api/config', 'verb' => 'GET'],
        ['name' => 'config#set', 'url' => '/api/config', 'verb' => 'POST'],
    ],
];
