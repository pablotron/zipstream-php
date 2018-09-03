<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream classes
use Pablotron\ZipStream\ZipStream;
use Pablotron\ZipStream\FileWriter;

# output zip file
$zip_path = __DIR__ . '/example-11.zip';

$zip_args = [
  'output' => new FileWriter(),
];

# create archive in this directory with a comment
ZipStream::send($zip_path, function(ZipStream &$zip) {
  # add a file "hello.txt" to output with a comment
  $zip->add_file('hello.txt', 'hello world!', [
    'comment' => 'hello this is a comment!',
  ]);
}, $zip_args);
