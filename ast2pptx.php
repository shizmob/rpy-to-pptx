<?php
require 'Common/src/Common/Autoloader.php';
require 'PHPPresentation/src/PhpPresentation/Autoloader.php';
\PhpOffice\Common\Autoloader::register();
\PhpOffice\PhpPresentation\Autoloader::register();

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\Shape\Drawing;
use PhpOffice\PhpPresentation\Slide\Transition;
use PhpOffice\PhpPresentation\Style\Color;
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
        if ($pos === false) {
          break;
        }
        $testPath[$pos] = '_';
      }
      if (isset($imageCache[$path])) {
        break;
      }
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

function calculateGeometry($path, $props)
{
  if (isset($props['size'])) {
    $size = explode(', ', trim($props['size'], '()'));
  } else {
    $size = getimagesize($path);
  }
  $w = $size[0] * (isset($props['zoom']) ? $props['zoom'] : isset($props['xzoom']) ? $props['xzoom'] : 1);
  $h = $size[1] * (isset($props['zoom']) ? $props['zoom'] : isset($props['yzoom']) ? $props['yzoom'] : 1);
  $xp = absoluteify(isset($props['xpos']) ? $props['xpos'] : 0.5, PRESENTATION_WIDTH);
  $yp = absoluteify(isset($props['ypos']) ? $props['ypos'] : 1.0, PRESENTATION_HEIGHT);
  $xo = absoluteify(isset($props['xoffset']) ? $props['xoffset'] : 0, 1.0);
  $yo = absoluteify(isset($props['yoffset']) ? $props['yoffset'] : 0, 1.0);
  $xan = absoluteify(isset($props['xanchor']) ? $props['xanchor'] : 0.5, $w);
  $yan = absoluteify(isset($props['yanchor']) ? $props['yanchor'] : 1.0, $h);

  $x = $xp - $xan + $xo;
  $y = $yp - $yan + $yo;
  return array($x, $y, $w, $h);
}


function emitImage($slide, $resourceDirs, $n) {
  global $stderr;
  $path = findImagePath($resourceDirs, $n['image']);
  if (!$path) {
    fprintf($stderr, "can't find %s\n", implode(' ', $n['image']));
    return;
  }
  list($x, $y, $w, $h) = calculateGeometry($path, $n['at']);
  $image = $slide->createDrawingShape();
  $image->setName($s)->setPath($path)->setHeight($h)->setWidth($w)->setOffsetX($x)->setOffsetY($y);
}

function emitTextbox($slide, $resourceDirs, $who, $what, $showing) {
  global $stderr;

  $tag = explode('_', $who)[0];
  if ($who) {
    if (isset($showing['misc']) && array_search('phone', $showing['misc']['image']) !== false) {
      $bar = 'phone';
    } else if ($tag == 'oliver') {
      $bar = 'oliver';
    } else if (isset($showing[$tag])) {
      $node = $showing[$tag];
      $path = findImagePath($resourceDirs, $node['image']);
      if (!$path) {
        fprintf($stderr, "can't find %s\n", implode(' ', $node['image']));
      }
      list($x, $y, $w, $h) = calculateGeometry($path, $node['at']);
      $xr = floatval($x) / PRESENTATION_WIDTH;
      if ($xr >= 0.8) {
        $bar = 'farright';
      } else if ($xr >= 0.7) {
        $bar = 'midright';
      } else if ($xr > 0.5) {
        $bar = 'right';
      } else if ($xr <= 0.2) {
        $bar = 'farleft';
      } else if ($xr <= 0.3) {
        $bar = 'midleft';
      } else {
        $bar = 'left';
      }
    } else {
      $bar = 'default';
    }
    $textbar = ['ui', 'textbar', $bar];
    $whobar = ['ui', 'textbar', 'names', $who];
  } else {
    $textbar = ['ui', 'textbar', 'default'];
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
  $tbar->setName('textbar')->setPath($barpath)->setOffsetY(456);
  if ($whobar && $whobarpath) {
    $wbar = $slide->createDrawingShape();
    $wbar->setName('whobar')->setPath($whobarpath)->setOffsetX(153)->setOffsetY(493);
  }

  $textShape = $slide->createRichTextShape()->setWidth(880)->setOffsetX(200)->setOffsetY(570);
  $text = $textShape->createTextRun($what);
  $text->getFont()->setName('Set Fire To The Rain')->setSize(16)->setColor(new Color('FF352114'));
}

function emitSlide($presentation, $resourceDirs, $who, $what, $transition, $showing, $textbox) {
  global $stderr;
  $slide = $presentation->createSlide();
  foreach ($showing as $s => $n) {
    if (isset($showing['cgs']) && $s != 'cgs')
      continue;
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
    $tag = $node['image'][1];
  }

  if ($tag == 'textbox') {
    $showTextbox = true;
  } else {
    if (isset($showing[$tag])) {
      if (($node['image'][0] == 'sprites' && count($node['image']) > 2) || ($node['image'][0] != 'sprites' && count($node['image']) > 1)) {
        $showing[$tag]['image'] = $node['image'];
      }
      $at = $showing[$tag]['at'];
    } else {
      $showing[$tag] = ['image' => $node['image']];
      $at = [];
    }
    if (isset($node['at'])) {
      $showing[$tag]['at'] = array_merge($at, $node['at']);
    } else {
      $showing[$tag]['at'] = $at;
    }
  }
  break;
case 'hide':
  $tag = $node['image'][0];
  if ($tag == 'textbox') {
    $showTextbox = false;
  } else {
    unset($showing[$tag]);
  }
  break;
case 'scene':
  $tag = $node['image'][0];
  $showing = [];
  $showing[$tag] = ['image' => $node['image'], 'at' => isset($node['at']) ? $node['at'] : []];
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
