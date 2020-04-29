<?php

$file = rex_file::get(rex_path::addon('nv_rex_structuremanager', 'README.md'));
$body = rex_markdown::factory()->parse($file);
$fragment = new rex_fragment();
$fragment->setVar('body', $body, false);
$content = $fragment->parse('core/page/section.php');
echo $content;