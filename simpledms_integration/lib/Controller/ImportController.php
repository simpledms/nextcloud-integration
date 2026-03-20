<?php

declare(strict_types=1);

namespace OCA\SimpleDMS\Controller;

use OCA\SimpleDMS\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class ImportController extends Controller {
    private const TOKEN_PREFIX = 'download_token_';
    private const TOKEN_TTL_SECONDS = 600;

    public function __construct(
        string $appName,
        IRequest $request,
        private IUserSession $userSession,
        private IRootFolder $rootFolder,
        private IConfig $config,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function createSignedUrl(string $path = ''): DataResponse {
        try {
            $this->purgeExpiredTokens();

            $user = $this->userSession->getUser();
            if ($user === null) {
                return new DataResponse([
                    'message' => 'You need to be logged in.',
                ], Http::STATUS_UNAUTHORIZED);
            }

            $uid = $user->getUID();

            $normalizedPath = $this->normalizePath($path);
            if ($normalizedPath === '') {
                return new DataResponse([
                    'message' => 'Missing file path.',
                ], Http::STATUS_BAD_REQUEST);
            }

            $resolved = $this->resolveUserFileNode($uid, $normalizedPath);
            if ($resolved === null) {
                return new DataResponse([
                    'message' => 'Selected file could not be resolved.',
                ], Http::STATUS_NOT_FOUND);
            }

            /** @var File $node */
            $node = $resolved['file'];
            /** @var string $resolvedPath */
            $resolvedPath = $resolved['path'];

            if (!($node instanceof File)) {
                return new DataResponse([
                    'message' => 'Only files can be uploaded to SimpleDMS.',
                ], Http::STATUS_BAD_REQUEST);
            }

            $token = bin2hex(random_bytes(24));
            $expiresAt = time() + self::TOKEN_TTL_SECONDS;
            $payload = [
                'uid' => $uid,
                'path' => $resolvedPath,
                'name' => $node->getName(),
                'mime' => $node->getMimeType(),
                'expiresAt' => $expiresAt,
            ];

            $encodedPayload = json_encode($payload);
            if (!is_string($encodedPayload)) {
                return new DataResponse([
                    'message' => 'Failed to create token payload.',
                ], Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            $this->config->setAppValue(Application::APP_ID, $this->tokenKey($token), $encodedPayload);

            $downloadUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.import.download', [
                'token' => $token,
            ]);

            return new DataResponse([
                'downloadUrl' => $downloadUrl,
                'expiresAt' => $expiresAt,
            ]);
        } catch (\Throwable $exception) {
            return new DataResponse([
                'message' => 'Failed to create signed URL: ' . $exception->getMessage(),
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     */
    public function download(string $token): Response {
        if (!$this->isValidToken($token)) {
            return new DataResponse([
                'message' => 'Invalid download token.',
            ], Http::STATUS_BAD_REQUEST);
        }

        $key = $this->tokenKey($token);
        $encodedPayload = $this->config->getAppValue(Application::APP_ID, $key, '');
        $this->config->deleteAppValue(Application::APP_ID, $key);
        if ($encodedPayload === '') {
            return new DataResponse([
                'message' => 'Download token not found or already used.',
            ], Http::STATUS_NOT_FOUND);
        }

        $payload = json_decode($encodedPayload, true);
        if (!is_array($payload)) {
            return new DataResponse([
                'message' => 'Invalid download token payload.',
            ], Http::STATUS_BAD_REQUEST);
        }

        $expiresAt = (int)($payload['expiresAt'] ?? 0);
        if ($expiresAt > 0 && time() > $expiresAt) {
            return new DataResponse([
                'message' => 'Download token expired.',
            ], Http::STATUS_GONE);
        }

        $uid = (string)($payload['uid'] ?? '');
        $path = $this->normalizePath((string)($payload['path'] ?? ''));
        if ($uid === '' || $path === '') {
            return new DataResponse([
                'message' => 'Download token is missing file references.',
            ], Http::STATUS_BAD_REQUEST);
        }

        try {
            $node = $this->rootFolder->getUserFolder($uid)->get($path);
        } catch (\Throwable) {
            return new DataResponse([
                'message' => 'Referenced file no longer exists.',
            ], Http::STATUS_NOT_FOUND);
        }

        if (!($node instanceof File)) {
            return new DataResponse([
                'message' => 'Referenced node is not a file.',
            ], Http::STATUS_BAD_REQUEST);
        }

        $stream = $node->fopen('rb');
        if ($stream === false) {
            return new DataResponse([
                'message' => 'Could not open referenced file stream.',
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $name = (string)($payload['name'] ?? $node->getName());
        if ($name === '') {
            $name = $node->getName();
        }

        $mime = (string)($payload['mime'] ?? $node->getMimeType());
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        $response = new StreamResponse($stream, Http::STATUS_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $this->escapeHeaderValue($name) . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);

        return $response;
    }

    private function tokenKey(string $token): string {
        return self::TOKEN_PREFIX . $token;
    }

    private function isValidToken(string $token): bool {
        return (bool)preg_match('/^[a-f0-9]{32,128}$/', $token);
    }

    private function normalizePath(string $path): string {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        $withoutQuery = explode('?', $trimmed, 2)[0];
        $withoutHash = explode('#', $withoutQuery, 2)[0];
        $cleaned = ltrim($withoutHash, '/');

        if ($cleaned === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $cleaned), static fn (string $segment): bool => $segment !== ''));
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return '';
            }
        }

        return implode('/', $segments);
    }

    /**
     * @return array{file: File, path: string}|null
     */
    private function resolveUserFileNode(string $uid, string $path): ?array {
        $folder = $this->rootFolder->getUserFolder($uid);
        $candidates = $this->buildPathCandidates($path, $uid);

        foreach ($candidates as $candidate) {
            try {
                $node = $folder->get($candidate);
            } catch (\Throwable) {
                continue;
            }

            if ($node instanceof File) {
                return [
                    'file' => $node,
                    'path' => $candidate,
                ];
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function buildPathCandidates(string $path, string $uid): array {
        $candidates = [$path];

        foreach ([
            'files/' . $uid . '/',
            $uid . '/files/',
            $uid . '/',
            'files/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $candidate = substr($path, strlen($prefix));
                if ($candidate !== '') {
                    $candidates[] = $candidate;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    private function purgeExpiredTokens(): void {
        $now = time();

        foreach ($this->config->getAppKeys(Application::APP_ID) as $key) {
            if (!str_starts_with($key, self::TOKEN_PREFIX)) {
                continue;
            }

            $raw = $this->config->getAppValue(Application::APP_ID, $key, '');
            if ($raw === '') {
                $this->config->deleteAppValue(Application::APP_ID, $key);
                continue;
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $this->config->deleteAppValue(Application::APP_ID, $key);
                continue;
            }

            $expiresAt = (int)($payload['expiresAt'] ?? 0);
            if ($expiresAt <= 0 || $expiresAt < $now) {
                $this->config->deleteAppValue(Application::APP_ID, $key);
            }
        }
    }

    private function escapeHeaderValue(string $value): string {
        return str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value);
    }
}
