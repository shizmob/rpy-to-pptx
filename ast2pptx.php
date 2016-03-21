<?php
require 'Common/src/Common/Autoloader.php';
require 'PHPPresentation/src/PhpPresentation/Autoloader.php';
\PhpOffice\Common\Autoloader::register();
\PhpOffice\PhpPresentation\Autoloader::register();

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\Shape\Drawing;
use PhpOffice\PhpPresentation\Slide\Transition;
use PhpOffice\PhpPresentation\IOFactory;

define('PRESENTATION_WIDTH', 1280);
define('PRESENTATION_HEIGHT', 720);
ini_set('memory_limit','16G');


$imageCache = [];

function findImagePath($resourceDirs, $pieces) {
  global $imageCache;
  $path = implode('/', $pieces);
  if (!isset($imageCache[$path])) {
    foreach ($resourceDirs as $resources) {
      $testPath = $path;
      while (true) {
        $res = glob($resources . '/' . $testPath . '.*');
        if ($res) {
          $imageCache[$path] = $res[0];
          break;
        }
        $pos = strrpos($testPath, '/');
        if ($pos === false)
          break;
        $testPath[$pos] = '_';
      }
      if (isset($imageCache[$path]))
        break;
    }
  }
  return isset($imageCache[$path]) ? $imageCache[$path] : null;
}

function absoluteify($x, $size) {
  if (is_float($x) || strpos($x, '.') !== FALSE) {
    return floatval($x) * $size;
  }
  return intval($x);
}

function emitImage($slide, $resourceDirs, $n) {
  global $stderr;
  $path = findImagePath($resourceDirs, $n['image']);
  if (!$path) {
    fprintf($stderr, "can't find %s\n", implode(' ', $n['image']));
    return;
  }
  $props = $n['at'];
  if (isset($props['size']))
    $size = explode(', ', trim($props['size'], '()'));
  else
    $size = getimagesize($path);
  $w = $size[0] * ($props['zoom'] or $props['xzoom'] or 1);
  $h = $size[1] * ($props['zoom'] or $props['yzoom'] or 1);
  $xp = absoluteify($props['xpos'] or 0.5, PRESENTATION_WIDTH);
  $yp = absoluteify($props['ypos'] or 0.5, PRESENTATION_HEIGHT);
  $xo = absoluteify($props['xoffset'] or 0, 1.0);
  $yo = absoluteify($props['yoffset'] or 0, 1.0);
  $xan = absoluteify($props['xanchor'] or 0, $w);
  $yan = absoluteify($props['yanchor'] or 0, $h);

  $x = $xp - $xan + $xo;
  $y = $yp - $yan + $yo;
  $image = $slide->createDrawingShape();
  $image->setName($s)->setPath($path)->setHeight($h)->setWidth($w)->setOffsetX($x)->setOffsetY($y);
}

function emitTextbox($slide, $resourceDirs, $who, $what, $showing) {
  global $stderr;

  if ($who) {
    $textbar = ['ui', 'textbar', 'default'];
    $whobar = ['ui', 'textbar', 'names', $who];
  } else {
    $textbar = ['ui', 'textbar', 'oliver'];
    $whobar = null;
  }

  $barpath = findImagePath($resourceDirs, $textbar);
  if (!$barpath) {
    fprintf($stderr, "can't find textbar: %s\n", implode(' ', $textbar));
    return;
  }
  if ($whobar) {
    $whobarpath = findImagePath($resourceDirs, $whobar);
    if (!$whobarpath) {
      fprintf($stderr, "can't find whobar: %s\n", implode(' ', $whobar));
    }
  }

  $tbar = $slide->createDrawingShape();
  $tbar->setName('textbar')->setPath($barpath)->setOffsetY(491);
  if ($whobar && $whobarpath) {
    $wbar = $slide->createDrawingShape();
    $wbar->setName('whobar')->setPath($whobarpath)->setOffsetX(153)->setOffsetY(528);
  }

  $textShape = $slide->createRichTextShape()->setWidth(880)->setOffsetX(200)->setOffsetY(605);
  $text = $textShape->createTextRun($what);
  $text->getFont()->setName('Set Fire To The Rain')->setSize(18);
}

function emitSlide($presentation, $resourceDirs, $who, $what, $transition, $showing, $textbox) {
  global $stderr;
  $slide = $presentation->createSlide();
  foreach ($showing as $s => $n) {
    emitImage($slide, $resourceDirs, $n);
  }
  if ($textbox) {
    emitTextbox($slide, $resourceDirs, $who, $what, $showing);
  }

  if ($transition) {
    $trans = new Transition();
    $trans->setManualTrigger(true)->setTransitionType(Transition::TRANSITION_DISSOLVE);
    $slide->setTransition($trans);
  }

  fprintf($stderr, "\r%u slides emitted...", $presentation->getSlideCount());
}


$stderr = fopen('php://stderr', 'w+');

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

$showing = [];
$showTextbox = true;
$transition = null;
$presentation = new PhpPresentation();
$presentation->getLayout()->setCX(PRESENTATION_WIDTH, DocumentLayout::UNIT_PIXEL)->setCY(PRESENTATION_HEIGHT, DocumentLayout::UNIT_PIXEL);

foreach ($ast as $node) switch($node['type']) {
case 'show':
  $tag = $node['image'][0];
  if ($tag == 'sprites') {
    if (isset($showing['cgs']))
      continue;
    $tag = $node['image'][1];
  }

  if ($tag == 'textbox')
    $showTextbox = true;
  else {
    if (isset($showing[$tag])) {
      if (($node['image'][0] == 'sprites' && count($node['image']) > 2) || ($node['image'][0] != 'sprites' && count($node['image']) > 1))
        $showing[$tag]['image'] = $node['image'];
      $at = $showing[$tag]['at'];
    } else {
      $showing[$tag] = ['image' => $node['image']];
      $at = [];
    }
    if (isset($node['at']))
      $showing[$tag]['at'] = array_merge($at, $node['at']);
    else
      $showing[$tag]['at'] = $at;
  }
  break;
case 'hide':
  $tag = $node['image'][0];
  if ($tag == 'textbox')
    $showTextbox = false;
  else
    unset($showing[$tag]);
  break;
case 'scene':
  $tag = $node['image'][0];
  $showing = [];
  $showing[$tag] = ['image' => $node['image'], 'at' => []];
  emitSlide($presentation, $resourceDirs, null, null, $transition, $showing, false);
  $transition = null;
  break;
case 'transition':
  $transition = $node['transition'];
  break;
case 'say':
  $showTextbox = true;
  emitSlide($presentation, $resourceDirs, $node['who'], $node['what'], $transition, $showing, $showTextbox);
  $transition = null;
  break;
case 'doublesay':
  $showTextbox = true;
  foreach ($node['who'] as $who) {
    emitSlide($presentation, $resourceDirs, $who, $node['what'], $transition, $showing, $showTextbox);
    $transition = null;
  }
  break;
}

fprintf($stderr, "\nWriting $outfile...");
$presentation->removeSlideByIndex(0);
$writer = IOFactory::createWriter($presentation, 'PowerPoint2007');
$writer->save($outfile);
