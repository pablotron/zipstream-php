<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream classes
use Pablotron\ZipStream\ZipStream;
use Pablotron\ZipStream\FileWriter;

$zip_path = __DIR__ . '/example-10.zip';

# create archive in this directory with a comment
ZipStream::send($zip_path, function(ZipStream &$zip) {
  # add a file named "hello.txt" to output archive, containing
  # the string "hello world!"
  $zip->add_file('hello.txt', 'hello world!');
}, [
  'comment' => 'example archive comment',
  'output'  => new FileWriter(),
]);
