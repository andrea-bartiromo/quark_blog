<?php

use App\Services\SocialDistribution\FakeSocialProvider;
use App\Services\SocialDistribution\FacebookSocialProvider;

return [
    'enabled' => (bool) env('SOCIAL_DISTRIBUTION_ENABLED', false),
    'max_attempts' => 3,
    'channels' => [
        'facebook' => ['enabled' => (bool) env('SOCIAL_FACEBOOK_ENABLED', false), 'provider' => FacebookSocialProvider::class],
        'instagram' => ['enabled' => (bool) env('SOCIAL_INSTAGRAM_ENABLED', false), 'provider' => FakeSocialProvider::class],
    ],
    'facebook' => [
        'graph_url' => env('SOCIAL_FACEBOOK_GRAPH_URL', 'https://graph.facebook.com'),
        'allowed_hosts' => ['graph.facebook.com'],
        'graph_version' => env('SOCIAL_FACEBOOK_GRAPH_VERSION', 'v23.0'),
        'page_id' => env('SOCIAL_FACEBOOK_PAGE_ID'),
        'access_token' => env('SOCIAL_FACEBOOK_ACCESS_TOKEN'),
        'timeout_seconds' => 10,
    ],
];
