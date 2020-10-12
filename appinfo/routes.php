<?php
return [
    'routes' => [
	   [
            'name' => 'mautoolz#compressFile',
            'url' => 'api/compressFile.php',
            'verb' => 'POST'
        ],
       [
            'name' => 'mautoolz#convertToPDF',
            'url' => 'api/convertToPDF.php',
            'verb' => 'POST'
        ]
    ]
];
