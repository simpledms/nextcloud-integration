<?php

declare(strict_types=1);

namespace OCA\SimpleDMS\Settings;

use OCA\SimpleDMS\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class Admin implements ISettings {
    public function __construct(
        private IConfig $config,
        private IL10N $l10n,
    ) {
    }

    public function getForm(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'admin-settings', [
            'simpledmsBaseUrl' => $this->config->getAppValue(Application::APP_ID, 'simpledms_base_url', ''),
            'title' => $this->l10n->t('SimpleDMS Integration'),
            'description' => $this->l10n->t('Configure the SimpleDMS base URL used by the file context menu action.'),
        ]);
    }

    public function getSection(): string {
        return 'additional';
    }

    public function getPriority(): int {
        return 50;
    }
}
