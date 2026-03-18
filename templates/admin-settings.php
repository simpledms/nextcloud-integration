<?php

declare(strict_types=1);

script('simpledms_integration', 'simpledms-admin-settings');

?>

<div id="simpledms-nextcloud-admin-settings" class="section">
  <h2><?php p($_['title']); ?></h2>
  <p class="settings-hint"><?php p($_['description']); ?></p>

  <p>
    <label for="simpledms-base-url"><?php p($l->t('SimpleDMS base URL')); ?></label>
    <input
      type="url"
      id="simpledms-base-url"
      name="simpledms-base-url"
      class="settings-input"
      placeholder="https://simpledms.example.com"
      value="<?php p($_['simpledmsBaseUrl']); ?>"
      style="width: 100%; max-width: 520px;"
    >
  </p>

  <p>
    <button type="button" id="simpledms-save" class="button button-primary">
      <?php p($l->t('Save')); ?>
    </button>
    <span id="simpledms-save-status" aria-live="polite"></span>
  </p>
</div>
