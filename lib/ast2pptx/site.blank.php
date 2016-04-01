<?php
namespace AST2PPTX\Site;


function emitUI($presentation, $slide, $who, $what) {
  $whoShape = $slide->createRichTextShape()->setWidth(200)->setOffsetX(180)->setOffsetY(580);
  $whoShape->createTextRun($who);
  $textShape = $slide->createRichTextShape()->setWidth(880)->setOffsetX(200)->setOffsetY(600);
  $textShape->createTextRun($what);
}
