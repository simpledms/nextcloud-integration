<?php

declare(strict_types=1);

namespace OCA\SimpleDMS\Controller;

use OCA\SimpleDMS\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class ConfigController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function get(): DataResponse {
        return new DataResponse([
            'simpledmsBaseUrl' => $this->config->getAppValue(Application::APP_ID, 'simpledms_base_url', ''),
        ]);
    }

    /**
     * @AdminRequired
     */
    public function set(string $simpledmsBaseUrl = ''): DataResponse {
        $normalized = $this->normalizeBaseUrl($simpledmsBaseUrl);
        if ($normalized === null) {
            return new DataResponse([
                'message' => 'Invalid SimpleDMS URL. Use HTTPS (or HTTP for localhost).',
            ], 400);
        }

        $this->config->setAppValue(Application::APP_ID, 'simpledms_base_url', $normalized);

        return new DataResponse([
            'simpledmsBaseUrl' => $normalized,
        ]);
    }

    private function normalizeBaseUrl(string $value): ?string {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $validated = filter_var($trimmed, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return null;
        }

        $parts = parse_url($validated);
        if ($parts === false || !isset($parts['scheme'])) {
            return null;
        }

        $scheme = strtolower((string)$parts['scheme']);
        if ($scheme === 'https') {
            return rtrim($validated, '/');
        }

        if ($scheme !== 'http') {
            return null;
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $host === '[::1]') {
            return rtrim($validated, '/');
        }

        return null;
    }
}
