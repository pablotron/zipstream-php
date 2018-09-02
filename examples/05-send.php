<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream class
use Pablotron\ZipStream\ZipStream;

# create archive named "example.zip"
ZipStream::send('example.zip', function(ZipStream &$zip) {
  # add a file named "hello.txt" to output archive, containing
  # the string "hello world!"
  $zip->add_file('hello.txt', 'hello world!');
});
