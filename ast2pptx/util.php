<?php
namespace AST2PPTX;


function absoluteify($x, $size) {
  if (is_float($x) || strpos($x, '.') !== FALSE) {
    return floatval($x) * $size;
  }
  return intval($x);
}

function calculateGeometry($path, $props, $screenWidth, $screenHeight)
{
  if (isset($props['size'])) {
    $size = explode(', ', trim($props['size'], '()'));
  } else {
    $size = getimagesize($path);
  }
  $w = $size[0] * (isset($props['zoom']) ? abs($props['zoom']) : isset($props['xzoom']) ? abs($props['xzoom']) : 1);
  $h = $size[1] * (isset($props['zoom']) ? abs($props['zoom']) : isset($props['yzoom']) ? abs($props['yzoom']) : 1);
  $xp = absoluteify(isset($props['xpos']) ? $props['xpos'] : 0.5, $screenWidth);
  $yp = absoluteify(isset($props['ypos']) ? $props['ypos'] : 1.0, $screenHeight);
  $xo = absoluteify(isset($props['xoffset']) ? $props['xoffset'] : 0, 1.0);
  $yo = absoluteify(isset($props['yoffset']) ? $props['yoffset'] : 0, 1.0);
  $xan = absoluteify(isset($props['xanchor']) ? $props['xanchor'] : 0.5, $w);
  $yan = absoluteify(isset($props['yanchor']) ? $props['yanchor'] : 1.0, $h);

  $x = $xp - $xan + $xo;
  $y = $yp - $yan + $yo;
  return array($x, $y, $w, $h);
}

function calculateFlips($props)
{
  $f = isset($props['zoom']) && floatval($props['zoom']) < 0;
  $xf = $f || (isset($props['xzoom']) && floatval($props['xzoom']) < 0);
  $yf = $f || (isset($props['yzoom']) && floatval($props['yzoom']) < 0);
  return array($xf, $yf);
}
