<?php

return [
    /*
    *   Time will be consider into minutes
    */
    'expire_time' => 1440,


    /*
    *   Cache Store Name  
    */
    'store' => env('CACHE_DRIVER', 'file'),
];
