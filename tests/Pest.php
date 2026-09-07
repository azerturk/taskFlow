<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(
        'Feature',
        '../Modules/Projects/tests/Feature',
        '../Modules/Tasks/tests/Feature',
        '../Modules/Activity/tests/Feature',
        '../Modules/Dashboard/tests/Feature',
        '../Modules/Media/tests/Feature',
    );
