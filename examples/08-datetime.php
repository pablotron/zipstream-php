<?php
declare(strict_types = 1);

require_once __DIR__ . '/../src/ZipStream.php';

# import classes
use Pablotron\ZipStream\DateTime;

# create DateTime with current timestamp
$dt = new DateTime(time());

# print DOS date and time
echo "UNIX time: {$dt->time}\n";
echo "DOS time: {$dt->dos_time}\n";
echo "DOS date: {$dt->dos_date}\n";
