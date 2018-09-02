<?php

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream class
use Pablotron\ZipStream\ZipStream;

# set source path
$src_path = __DIR__ . '/03-add_stream.php';

# create the output archive named "example.zip"
$zip = new ZipStream('example.zip');

# open stream
$src = fopen($src_path, 'rb');

# read from source stream and add it to the archive as "example.php"
$zip->add_stream('example.php', $src);

# close input stream
fclose($src);

# finalize the output stream
$zip->close();
