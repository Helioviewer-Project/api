<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/Movies.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Helper/ErrorHandler.php';

$params = array(
    'action'   => 'getMovieStatus',
    'id'       => 'FJ0d5',
    'format'   => 'mp4'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$movies = new Module_Movies($params);

echo "\n\nOutputting evidence that input was accepted:\n";
echo '$movies => ';
var_dump($movies);

echo "\n\nExecuting API call:\n";
echo '$movies->execute()'."\n";

echo "\nActual Test Output:\n";
$movies->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>