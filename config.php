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
      'attachment' => new SectionBreakField(
        [
          'label' => $__('Attachment Inliner')
        ]),
      'attachment-video' => new BooleanField(
        [
          'label' => $__('Convert Youtube/video to Player'),
          'hint' => $__(
            "Watch video attachments and YouTube in the thread! (Default HTML5 player supports mp4,webm,ogv,3gp")
        ]),
      'attachment-allowed' => new ChoiceField(
        [
          'label' => $__('Choose the types of attachments to inline.'),
          'default' => 'pdf-image',
          'hint' => $__(
            "While HTML and Text documents are filtered before being inserted in the DOM, there is always a chance this is riskier than necessary, Has no effect if txt & html extensions are not allowed to be attached in the first place."),
          'choices' => [
            'none' => $__('Safest: OFF'),
            'pdf' => $__('PDF Only'),
            'image' => $__('Images Only'),
            'pdf-image' => $__('PDF\'s and Images'),
            'all' => $__('I accept the risk: ALL')
          ]
        ]),
      'attachment-enabled' => new BooleanField(
        [
          'label' => $__('Permission'),
          'default' => TRUE,
          'hint' => 'Check to enable attachments inline, disable to onlyi allow API to function.'
        ]),
      'show-initially' => new ChoiceField(
        [
          'label' => $__('Number of attachments to show initially'),
          'default' => 0,
          'hint' => $__(
            'If you find too many attachments displaying at once is slowing you down, change this to only show some of them at first.'),
          'choices' => [
            0 => $__('All'),
            1 => '1',
            10 => '10',
            20 => '20',
            50 => '50',
            100 => '100'
          ]
        ])
    ];
  }
}