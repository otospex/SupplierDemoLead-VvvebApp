<?php
 return array (
  '* * *' => 
  array (
    'name' => 'Default site',
    'host' => '*.*.*',
    'path' => '',
    'theme' => 'souverainete-digitale',
    // Per-language homepage template, keyed by language_id (see app/controller/index.php).
    // Index 0 is the fallback; 1 = English, 2 = French. If the French language_id
    // differs in a given database, add/adjust the matching key here.
    'template' =>
    array (
      0 => 'index.html',
      1 => 'index.html',
      2 => 'index.fr.html',
    ),
    'state' => 'live',
    'site_id' => 1,
  ),
);