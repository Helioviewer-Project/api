<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/Movies.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';

$params = array(
    'action'      => 'getYouTubeAuth',
    'id'          => 'abcdefg',
    'title'       => 'title test',
    'description' => 'description test',
    'tags'        => 'tags test',
    'share'       => 'false'
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