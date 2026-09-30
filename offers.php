<?php
// Registry of tracked offers for rp.php. To add a new offer, add one entry here —
// no new PHP file needed. 'key' is what shows up in the ?offer= query param.
//
// 'base_url' is the affiliate/offer destination. 'params' is a template of the
// query params appended to that URL; values containing '{click_id}' are replaced
// with the click_id generated for that visit (fbclid, or a pp_trkr_ fallback).

return [
    'rushpermit' => [
        'base_url' => 'https://rushpermit.com/secure/app-carry10/',
        'params' => [
            'affid' => '275',
            'oid' => '185',
            'fn' => '',
            'ln' => '',
            'em' => '',
            'ph' => '',
            'creative_id' => '17',
            'click_id' => '{click_id}',
            'fbclid' => '{fbclid}',
            'sub1' => '{sub1}',
            'sub2' => '{sub2}',
            'sub3' => '{sub3}',
            'sub4' => 'utm_source_pillowpotion',
            'sub5' => '{click_id}',
        ],
    ],

    'kinzeno' => [
        'base_url' => 'https://get-kinzeno.com/kinzeno/product',
        'params' => [
            'affid' => '275',
            'click_id' => '{click_id}',
            'fbclid' => '{fbclid}',
            'sub1' => '{sub1}',
            'sub2' => '{sub2}',
            'sub3' => '{sub3}',
            'sub4' => 'utm_source_pillowpotion_kz',
            'sub5' => '{click_id}',
        ],
    ],

    'sweetrestoreus' => [
        'base_url' => 'https://nmttrack.com/',
        'params' => [
            'a' => '303202',
            'c' => '435757',
            'co' => '369322',
            'mt' => '16',
            'fbclid' => '{click_id}',
            's2' => '{click_id}',
        ],
    ],

    'sweetrestoreca' => [
        'base_url' => 'https://nmttrack.com/',
        'params' => [
            'a' => '303202',
            'c' => '435766',
            'co' => '369322',
            'mt' => '16',
            'fbclid' => '{click_id}',
            's2' => '{click_id}',
        ],
    ],

    'kinzenov2adeel' => [
        'base_url' => 'https://click-ecom.com/',
        'params' => [
            'a' => '303202',
            'c' => '436909',
            'co' => '369322',
            'mt' => '16',
            'fbclid' => '{click_id}',
            's2' => '{click_id}',
        ],
    ],
];
