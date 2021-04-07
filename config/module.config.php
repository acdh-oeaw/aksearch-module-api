<?php
namespace AkSearchApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'AkSearchApi\Controller\UserApiController' => 'AkSearchApi\Controller\UserApiControllerFactory'
        ],
        'aliases' => [
            'UserApi' => 'AkSearchApi\Controller\UserApiController',
            'userapi' => 'UserApi'
        ]
    ],
    'router' => [
        'routes' => [
            'userApiLegacy' => [
                'type' => 'Laminas\Router\Http\Segment',
                'verb' => 'post',
                'options' => [
                    'route'    => '/Api/User/[:action]',
                    'defaults' => [
                        'controller' => 'UserApi',
                        'action' => 'Auth'
                    ]
                ]
            ],
            'userApiV1' => [
                'type' => 'Laminas\Router\Http\Segment',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/user/[:action]',
                    'defaults' => [
                        'controller' => 'UserApi'
                    ]
                ]
            ]
        ]
    ]
];

return $config;
