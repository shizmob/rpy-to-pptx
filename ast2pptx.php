<?php
require 'lib/Common/src/Common/Autoloader.php';
require 'lib/PHPPresentation/src/PhpPresentation/Autoloader.php';
\PhpOffice\Common\Autoloader::register();
\PhpOffice\PhpPresentation\Autoloader::register();
require 'lib/ast2pptx/presentation.php';

define('PRESENTATION_WIDTH', 1280);
define('PRESENTATION_HEIGHT', 720);
ini_set('memory_limit','16G');


if (count($argv) < 5) {
  print "usage: " . $argv[0] . "<ast.json> <out.pptx> <site> <resources> [<resources> ...]\n";
  exit(1);
}

$astfile = $argv[1];
$outfile = $argv[2];
$site = $argv[3];
$resourceDirs = array_slice($argv, 4);

$ast = json_decode(file_get_contents($astfile), true);
if (!$ast) {
  exit(2);
}

if ($site) {
  AST2PPTX\loadSite($site);
}
$presentation = new AST2PPTX\Presentation($resourceDirs);
$presentation->setGeometry(PRESENTATION_WIDTH, PRESENTATION_HEIGHT);
$presentation->buildFromAST($ast);
$presentation->save($outfile);
