<?php
 return array (
  '* * *' => 
  array (
    'name' => 'Default site',
    'host' => '*.*.*',
    'path' => '',
    'theme' => 'souverainete-digitale',
    // Per-language homepage template (see app/controller/index.php). Keyed by
    // language SLUG so it works regardless of language_id across databases.
    // Index 0 is the default fallback; numeric keys kept for back-compat.
    'template' =>
    array (
      0 => 'index.html',
      'en' => 'index.html',
      'fr' => 'index.fr.html',
      1 => 'index.html',
      2 => 'index.fr.html',
    ),
    'state' => 'live',
    'site_id' => 1,
  ),
);