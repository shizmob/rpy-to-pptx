<?php
namespace AST2PPTX;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\Shape\Drawing;
use PhpOffice\PhpPresentation\Slide\Transition;
use PhpOffice\PhpPresentation\IOFactory;

require_once 'util.php';
require_once 'site.php';


class Presentation
{
  public function __construct($siteData)
  {
    $this->siteData = $siteData;
    $this->presentation = new PhpPresentation();
    $this->width = 640;
    $this->height = 480;

    $this->showing = [];
    $this->showOrder = [];
    $this->showUI = true;
    $this->transition = null;

    $this->callStack = [];
    $this->stderr = fopen('php://stderr', 'w+');

    $this->setGeometry($this->width, $this->height);
  }

  public function setGeometry($width, $height)
  {
    $this->width = $width;
    $this->height = $height;
    $this->presentation
      ->getLayout()
      ->setCX(PRESENTATION_WIDTH, DocumentLayout::UNIT_PIXEL)
      ->setCY(PRESENTATION_HEIGHT, DocumentLayout::UNIT_PIXEL);
  }

  public function buildFromAST($ast)
  {
    $label = $ast['entry_point'];
    $this->callStack[] = $label;
    $this->buildFromLabel($ast, $label);
  }

  protected function buildFromLabel($ast, $label)
  {
    $script = $ast['script'][$label];

    foreach ($script as $node) switch($node['type']) {
    case 'call':
      $this->callStack[] = $node['label'];
      $this->buildFromLabel($ast, $node['label']);
      break;
    case 'jump':
      $this->callStack[count($this->callStack) - 1] = $node['label'];
      return $this->buildFromLabel($ast, $node['label']);
    case 'show':
      $tag = $node['tag'];

      if (isset($this->showing[$tag])) {
        if ($node['image']) {
          $this->showing[$tag]['image'] = $node['image'];
        }
        $pos = array_search($tag, $this->showOrder);
        $at = $this->showing[$tag]['at'];
      } else {
        if (!$node['image']) {
          fprintf($this->stderr, "New image without resource: " . $tag . "\n");
          continue;
        }
        $this->showing[$tag] = ['image' => $node['image']];
        $pos = max(array_keys($this->showOrder)) + 1;
        $at = [];
      }

      if (isset($node['at'])) {
        $this->showing[$tag]['at'] = array_merge($at, $node['at']);
      } else {
        $this->showing[$tag]['at'] = $at;
      }

      $behindPos = $pos;
      foreach ($node['behind'] as $btag) {
        $bpos = array_search($btag, $this->showOrder);
        if ($bpos !== false && $bpos < $behindPos) {
          $behindPos = $bpos;
        }
      }
      if ($behindPos === $pos) {
        $this->showOrder[$pos] = $tag;
      } else {
        unset($this->showOrder[$pos]);
        array_splice($this->showOrder, $behindPos, 0, array($tag));
        $this->showOrder = array_values($this->showOrder);
      }
      break;
    case 'hide':
      $tag = $node['tag'];
      unset($this->showing[$tag]);
      $key = array_search($tag, $this->showOrder);
      if ($key !== false) {
        unset($this->showOrder[$key]);
        $this->showOrder = array_values($this->showOrder);
      }
      break;
    case 'show_ui':
      $this->showUI = true;
      break;
    case 'hide_ui':
      $this->showUI = false;
      break;
    case 'scene':
      $tag = $node['tag'];
      if (!$node['image']) {
        fprintf($this->stderr, "New image without resource: " . $tag . "\n");
        continue;
      }

      $this->showing = [];
      $this->showing[$tag] = ['image' => $node['image'], 'at' => isset($node['at']) ? $node['at'] : []];
      $this->showOrder = [$tag];

      $showUI = $this->showUI;
      $this->showUI = false;
      $this->emitSlide(null, null);
      $this->showUI = $showUI;
      break;
    case 'transition':
      $this->transition = $node['transition'];
      break;
    case 'say':
      $this->showUI = true;
      $this->emitSlide($node['who'], $node['what']);
      break;
    case 'doublesay':
      $this->showUI = true;
      foreach ($node['who'] as $who) {
        $this->emitSlide($who, $node['what']);
      }
      break;
    }

    array_pop($this->callStack);
  }

  public function save($outfile)
  {
    fprintf($this->stderr, "\nWriting $outfile...");
    $this->presentation->removeSlideByIndex(0);
    $writer = IOFactory::createWriter($this->presentation, 'PowerPoint2007');
    $writer->save($outfile);
  }

  protected function emitSlide($who, $what)
  {
    $slide = $this->presentation->createSlide();
    foreach ($this->showOrder as $tag) {
      $entry = $this->showing[$tag];
      if (isset($this->showing['cg']) && $tag != 'cg')
        continue;
      $this->emitImage($slide, $entry);
    }
    if ($this->showUI) {
      Site\emitUI($this->siteData, $slide, $who, $what, $this->showing);
    }

    if ($this->transition) {
      $trans = new Transition();
      $trans
        ->setManualTrigger(true)
        ->setTransitionType(Transition::TRANSITION_DISSOLVE);
      $slide->setTransition($trans);
      $this->transition = null;
    }

    fprintf($this->stderr, "\r%u slides emitted...", $this->presentation->getSlideCount());
  }

  protected function emitImage($slide, $node) {
    list($x, $y, $w, $h) = calculateGeometry($node['image'], $node['at'], $this->width, $this->height);
    $image = $slide->createDrawingShape();
    $image
      ->setName($s)
      ->setPath($node['image'])
      ->setHeight($h)
      ->setWidth($w)
      ->setOffsetX($x)
      ->setOffsetY($y);
  }

}
