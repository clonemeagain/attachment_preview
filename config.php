<?php
require_once INCLUDE_DIR . 'class.plugin.php';

class AttachmentPreviewPluginConfig extends PluginConfig {

  // Provide compatibility function for versions of osTicket prior to
  // translation support (v1.9.4)
  function translate() {
    if (! method_exists('Plugin', 'translate')) {
      return [
        function ($x) {
          return $x;
        },
        function ($x, $y, $n) {
          return $n != 1 ? $y : $x;
        }
      ];
    }
    return Plugin::translate('attachment_preview');
  }

  /**
   * Build an Admin settings page.
   *
   * {@inheritdoc}
   *
   * @see PluginConfig::getOptions()
   */
  function getOptions() {
    list ($__, $_N) = self::translate();
    
    return [
      'attachment-enabled' => new BooleanField(
        [
          'label' => $__('Permission'),
          'default' => TRUE,
          'hint' => 'Check to enable attachments inline, uncheck only allows the API to function.'
        ]),
        'attachment-size' => new TextboxField(
        [
          'label' => $__('Max Size'),
          'default' => 1024,
          'hint' => 'Enter maximum Kilobytes of an attachment to inline. Larger attachments are ignored.'
        ]),      
      'attach-pdf' => new BooleanField(
        [
          'label' => $__('Inline PDF files as <object>s'),
          'default' => TRUE
        ]),
      'attach-image' => new BooleanField(
        [
          'label' => $__('Inline image files as <img>s'),
          'default' => TRUE
        ]),
      'attach-text' => new BooleanField(
        [
          'label' => $__('Inline textfiles (txt,csv) as <pre>'),
          'default' => TRUE
        ]),
      'attach-html' => new BooleanField(
        [
          'label' => $__('Inline HTML files into a <div>'),
          'hint' => $__(
            'Dangerous: While we filter/sanitize the HTML, make sure it is something you really need before turning on.'),
          'default' => FALSE
        ]),
      'attach-audio' => new BooleanField(
        [
          'label' => $__('Inline audio attachments as Players'),
          'default' => FALSE
        ]),
      'attach-video' => new BooleanField(
        [
          'label' => $__('Inline video attachments as Players'),
          'hint' => $__("Embeds video attachments "),
          'default' => FALSE
        ]),
      'attach-youtube' => new BooleanField(
        [
          'label' => $__('Inline Youtube links to Players'),
          'default' => FALSE
        ]),
      'show-ms-upgrade-help' => new BooleanField(
        [
          'label' => $__('Show IE upgrade link'),
          'hint' => $__(
            'Enable help link to abetterbrowser.org for PDFs when on Internet Explorer'),
          'default' => TRUE
        ]),
      'show-initially' => new ChoiceField(
        [
          'label' => $__('Number of attachments to show initially'),
          'default' => 0,
          'hint' => $__(
            'If you find too many attachments displaying at once is slowing you down, change this to only show some of them at first.'),
          'choices' => array_merge([
            '0' => $__('All')
          ], array_combine(range(1, 100), range(1, 100)))
        ])
    ];
  }
}