<?php

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream class
use Pablotron\ZipStream\ZipStream;

# create the output stream
# this will send the archive as an HTTP response by default
$zip = new ZipStream('example.zip');

# add "hello.txt" to output archive, containing
# the string "hello world!"
$zip->add_file('hello.txt', 'hello world!');

# finalize the output stream
$zip->close();
