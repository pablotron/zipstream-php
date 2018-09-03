<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream classes
use Pablotron\ZipStream\ZipStream;
use Pablotron\ZipStream\FileWriter;

# output zip path
$zip_path = __DIR__ . '/example-13.zip';

# output options
$zip_args = [
  'output' => new FileWriter(),
];

# create archive in this directory with a comment
ZipStream::send($zip_path, function(ZipStream &$zip) {
  # get current time
  $time = time();

  # add "hello.txt" to output with a time of 2 hours ago
  $zip->add_file('hello.txt', 'hello world!', [
    'time' => $time - (2 * 3600),
  ]);

  # add "hola.txt" to output with a time of 2 hours in the future
  $zip->add_file('hola.txt', 'hola!!', [
    'time' => $time + (2 * 3600),
  ]);
}, $zip_args);
