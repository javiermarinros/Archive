Example of use:

```<?php

require 'Base.php';
require 'Tar.php';
require 'Zip.php';

/* Pruebas de los compresores de archivos */
$compressor = new Archive_Tar();
$compressor->level = 100;
$compressor->add_data('path/to/fïlé.txt', 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöùúûüýÿ');
$compressor->add_data('root.txt', 'This file should be in the top folder');
$compressor->add_file(__FILE__, 'test.php');
$compressor->add_data('path/to/other.nfo', ':)');
$compressor->add_folder('empty_folder');
$compressor->add_data('path/to/other.txt', ':)');
$compressor->add_data('long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/other.txt', ':)');

$compressor->create(dirname(__FILE__) . '/created.tar.gz');

$compressor = new Archive_Zip();
$compressor->level = 100;
$compressor->comment = 'This is a comment';
//$compressor->store_only=TRUE;
$compressor->add_data('path/to/fïlé.txt', 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöùúûüýÿ');
$compressor->add_data('root.txt', 'This file should be in the top folder');
$compressor->add_file(__FILE__, 'test.php');
$compressor->add_folder('empty_folder');
$compressor->add_data('path/to/other.nfo', ':)');
$compressor->add_data('path/to/other.txt', ':)');
$compressor->add_data('long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/long/path/to/file/other.txt', ':)');

$compressor->create(dirname(__FILE__) . '/created.zip');

?>```` 














