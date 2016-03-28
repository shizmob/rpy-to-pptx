<?php
namespace AST2PPTX\Site;
use PhpOffice\PhpPresentation\Style\Color;

require_once 'util.php';


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

function emitUI($presentation, $slide, $who, $what) {
  $resourceDirs = $presentation->siteData;
  $showing = $presentation->getShowing();
  list($sw, $sh) = $presentation->getGeometry();

  $tag = explode('_', $who)[0];
  if ($who) {
    if (isset($showing['phone'])) {
      $bar = 'phone';
    } else if ($tag == 'oliver') {
      $bar = 'oliver';
    } else if (isset($showing[$tag])) {
      $node = $showing[$tag];
      list($x, $y, $w, $h) = \AST2PPTX\calculateGeometry($node['image'], $node['at'], $sw, $sh);
      $x = floatval($x) + 0.5 * floatval($w);
      $xr = $x / PRESENTATION_WIDTH;
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
    printf("can't find textbar: %s\n", implode(' ', $textbar));
    return;
  }
  if ($whobar) {
    $whobarpath = findImagePath($resourceDirs, $whobar);
    if (!$whobarpath) {
      printf("can't find whobar: %s\n", implode(' ', $whobar));
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
