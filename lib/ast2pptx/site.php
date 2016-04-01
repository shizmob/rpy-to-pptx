<?php
namespace AST2PPTX;

function loadSite($site)
{
  require_once 'site_'.basename($site).'.php';
}
