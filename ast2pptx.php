<?php
require 'Common/src/Common/Autoloader.php';
require 'PHPPresentation/src/PhpPresentation/Autoloader.php';
\PhpOffice\Common\Autoloader::register();
\PhpOffice\PhpPresentation\Autoloader::register();
require 'ast2pptx/presentation.php';

define('PRESENTATION_WIDTH', 1280);
define('PRESENTATION_HEIGHT', 720);
ini_set('memory_limit','16G');


if (count($argv) < 4) {
  fwrite($stderr, "usage: " . $argv[0] . " <ast.json> <out.pptx> <resources> [<resources> ...]\n");
  exit(1);
}

$astfile = $argv[1];
$outfile = $argv[2];
$resourceDirs = array_slice($argv, 3);

$ast = json_decode(file_get_contents($astfile), true);
if (!$ast)
  exit(2);

$presentation = new AST2PPTX\Presentation($resourceDirs);
$presentation->setGeometry(PRESENTATION_WIDTH, PRESENTATION_HEIGHT);
$presentation->buildFromAST($ast);
$presentation->save($outfile);
