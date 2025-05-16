<?php

//error_reporting(E_ALL);
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');

return [
  'id'          => 'clonemeagain:attachment_preview', # notrans
  'version'     => '1.2',
  'name'        => 'Attachment Inline Plugin',
  'author'      => 'clonemeagain@gmail.com',
  'description' => 'Modifies the page to include as many attachments as would make sense, directly into the Thread.',
  'url'         => 'https://github.com/clonemeagain/attachment_preview',
  'plugin'      => 'class.AttachmentPreviewPlugin.php:AttachmentPreviewPlugin',
];
