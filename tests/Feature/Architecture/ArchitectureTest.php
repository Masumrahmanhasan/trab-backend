<?php

arch()->preset()->laravel();
arch()->preset()->security();

arch('globals')
    ->expect('App')
    ->toUseStrictTypes()
    ->not->toUse(['dd', 'dump', 'die']);
