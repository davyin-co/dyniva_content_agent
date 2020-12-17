/**
 * @file
 * JavaScript behaviors for CodeMirror integration.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Initialize CodeMirror editor.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.contentAgentCodeMirror = {
    attach: function (context) {
      if (!window.CodeMirror) {
        return;
      }

      $('[data-action]').each(function() {
        if($(this).data('action') == 'codemirror-yaml') {
          window.CodeMirror.fromTextArea(this, {
            lineNumbers: true,
            mode: "yaml"
          });
        }
      });
    }
  };

})(jQuery, Drupal);
