<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream classes
use Pablotron\ZipStream\ZipStream;
use Pablotron\ZipStream\FileWriter;
use Pablotron\ZipStream\Methods;

# output zip file
$zip_path = __DIR__ . '/example-12.zip';

# output options
$zip_args = [
  'output' => new FileWriter(),
];

# create archive in this directory with a comment
ZipStream::send($zip_path, function(ZipStream &$zip) {
  # add "hello.txt" to output using the STORE method
  $zip->add_file('hello.txt', 'hello world!', [
    # use STORE method (e.g., no compression)
    'method' => Methods::STORE,
  ]);

  # add "hola.txt" to output using the DEFLATE method
  $zip->add_file('hola.txt', 'hola!!', [
    # use DEFLATE method (e.g., compressed)
    'method' => Methods::DEFLATE,
  ]);
}, $zip_args);
