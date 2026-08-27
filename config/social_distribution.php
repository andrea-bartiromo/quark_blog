<?php

use App\Services\SocialDistribution\FakeSocialProvider;

return [
    'enabled' => (bool) env('SOCIAL_DISTRIBUTION_ENABLED', false),
    'max_attempts' => 3,
    'channels' => [
        'facebook' => ['enabled' => (bool) env('SOCIAL_FACEBOOK_ENABLED', false), 'provider' => FakeSocialProvider::class],
        'instagram' => ['enabled' => (bool) env('SOCIAL_INSTAGRAM_ENABLED', false), 'provider' => FakeSocialProvider::class],
    ],
];
