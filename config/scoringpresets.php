<?php
// List of scoring presets for points system

$scoring_presets = [
    'f1_2024' => [
        'name' => 'F1 2024 (Main + Sprint + Bonus)',
        'json' => [
            'main' => [1=>25,2=>18,3=>15,4=>12,5=>10,6=>8,7=>6,8=>4,9=>2,10=>1],
            'sprint' => [1=>8,2=>7,3=>6,4=>5,5=>4,6=>3,7=>2,8=>1],
            'quali' => [],
            'bonus' => ['fastest_lap'=>1, 'pole'=>1]
        ]
    ],
    'motogp' => [
        'name' => 'MotoGP (Main + Sprint)',
        'json' => [
            'main' => [1=>25,2=>20,3=>16,4=>13,5=>11,6=>10,7=>9,8=>8,9=>7,10=>6,11=>5,12=>4,13=>3,14=>2,15=>1],
            'sprint' => [1=>12,2=>9,3=>7,4=>6,5=>5,6=>4,7=>3,8=>2,9=>1],
            'quali' => [],
            'bonus' => []
        ]
    ],
    'indycar' => [
        'name' => 'IndyCar',
        'json' => [
            'main' => [1=>50,2=>40,3=>35,4=>32,5=>30,6=>28,7=>26,8=>24,9=>22,10=>20,11=>19,12=>18,13=>17,14=>16,15=>15,16=>14,17=>13,18=>12,19=>11,20=>10,21=>9,22=>8,23=>7,24=>6,25=>5,26=>5],
            'sprint' => [],
            'quali' => [],
            'bonus' => ['pole'=>1,'most_laps_led'=>2,'fastest_lap'=>1]
        ]
    ],
    'custom' => [
        'name' => 'Custom (edit below)',
        'json' => [
            'main' => [],
            'sprint' => [],
            'quali' => [],
            'bonus' => []
        ]
    ]
];