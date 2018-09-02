<?php

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream class
use Pablotron\ZipStream\ZipStream;

# create the output stream
$zip = new ZipStream('example.zip');

# add a file named "hello.txt" to output archive, containing
# the string "hello world!"
$zip->add_file('hello.txt', 'hello world!');

# finalize the output stream
$zip->close();
