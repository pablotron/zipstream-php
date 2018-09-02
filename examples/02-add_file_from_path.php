<?php

require_once __DIR__ . '/../src/ZipStream.php';

# import zipstream class
use Pablotron\ZipStream\ZipStream;

# set source path of local file
$src_path = __DIR__ . '/02-add_file_from_path.php';

# create the output archive named "example.zip"
$zip = new ZipStream('example.zip');

# read from source path and add it to the archive as "example.php"
$zip->add_file_from_path('example.php', $src_path);

# finalize the output stream
$zip->close();
