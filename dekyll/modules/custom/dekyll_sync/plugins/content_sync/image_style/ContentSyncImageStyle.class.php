<?php

/**
 * Image style content sync.
 */
class ContentSyncImageStyle extends ContentSyncImage {

  /**
   * Export images in different sizes.
   *
   * We copy the images to the image folder under the node's path.
   */
  public function export(EntityDrupalWrapper $wrapper, &$yaml = array(), &$text = '', $files_info) {
    $plugin_name = $this->plugin['name'];

    $file_path = $this->filePath;

    $path_parts = pathinfo($file_path);
    $image_path = $path_parts['dirname'] . '/images';

    $full_path = dekyll_repository_get_repo_path($this->branchId). '/' . $file_path;

    $path_parts = pathinfo($full_path);
    $image_full_path = $path_parts['dirname'] . '/images';

    $file_names = array();

    if (!file_exists($image_full_path)) {
      drupal_mkdir($image_full_path, NULL, TRUE);
    }

    foreach ($this->syncMap[$plugin_name] as $field_name) {
      if (!$files = $wrapper->{$field_name}->value()) {
        // No files.
        continue;
      }

      $instance = field_info_instance($wrapper->type(), $field_name, $wrapper->getBundle());

      // We can't use is_array() as even the single result is an array.
      $files = empty($files['fid']) ? $files : array($files);

      foreach ($files as $delta => $file) {
        foreach ($instance['settings']['content_sync']['settings']['style_names'] as $style_name) {

          // @todo: Check if the file doesn't already exist.

          // Create the image in the right style.
          $uri = image_style_url($style_name, $file['uri']);

          $real_path = drupal_realpath($uri);

          $contents = file_exists($real_path) ? $real_path : file_get_contents($uri);
          file_put_contents($image_full_path . '/' . $style_name . '-' . $file['filename'], $contents);

          // Add the new file names.
          $file_name = $image_path . '/' . $style_name . '-' . $file['filename'];
          $file_names[] = $file_name;

          // We expect that {{ BASE_PATH }} will prefix the file name
          // the Jekyll file.
          $yaml[$field_name][$delta][$style_name] = '/' . $file_name;
        }
      }
    }

    return $file_names;
  }

  /**
   * Settings form.
   */
  public function settingsForm($field, $instance) {
    $form = parent::settingsForm($field, $instance);

    $settings = !empty($instance['settings']['content_sync']['settings']) ? $instance['settings']['content_sync']['settings'] : array();
    $settings += array(
      'style_names' => array(),
    );

    $options = array();
    foreach (image_styles() as $name => $value) {
      $options[$name] = $value['name'];

    }

    $form['style_names'] = array(
      '#type' => 'select',
      '#title' => t('Style names'),
      '#options' => $options,
      '#default_value' => $settings['style_names'],
      '#required' => TRUE,
      '#multiple' => TRUE,
    );

    return $form;
  }

  /**
   * Access callback.
   */
  public function access($op, $field = NULL, $instance = NULL) {
    if ($op == 'settings') {
      return $field['type'] == 'image';
    }

    if ($op == 'import') {
      return;
    }

    return parent::access($op, $field, $instance);
  }
}
