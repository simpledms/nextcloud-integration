# Nextcloud -> SimpleDMS Integration (Draft Spec)

## 1) Objective

Build a Nextcloud app that adds a file context-menu action in Files:

- Action label: `Upload to SimpleDMS`
- Behavior: Open SimpleDMS import with signed Nextcloud URL using `GET /open-file/from-url?url=...`
- Result: User continues in SimpleDMS open-file flow to complete import

## 2) Scope

### In Scope

- Nextcloud app installable on supported Nextcloud versions
- File context-menu integration in Files UI
- Single-file import via signed one-time URL
- Basic admin configuration (SimpleDMS base URL)
- Error handling and user feedback in Nextcloud UI

### Out of Scope (Phase 1)

- Deep bidirectional sync
- Import status polling back into Nextcloud
- Metadata mapping beyond filename and downloadable file URL
- Complex retry queues/background jobs

## 3) User Stories

1. As a Nextcloud user, I can right-click a file and choose `Upload to SimpleDMS`.
2. As a user, I am redirected (or a new tab opens) to SimpleDMS import flow where I select a target space.
3. As a user, I receive clear feedback if import cannot be triggered (token creation failed, URL unreachable, authentication issue).
4. As an admin, I can configure the SimpleDMS base URL once for all users.

## 4) High-Level Architecture

### Nextcloud Frontend

- Register a Files action via Nextcloud Files extension APIs.
- Action appears in context menu for files (not folders).
- On trigger:
  - Resolve selected file path from Nextcloud Files context.
  - Call Nextcloud API to mint a signed one-time download URL.
  - Open `https://<simpledms>/open-file/from-url?url=<signed_download_url>` in a new tab.

### Nextcloud Backend (minimal, Phase 1)

- Provide app bootstrap and admin settings endpoint/storage.
- Expose base URL config to frontend.
- Provide API for signed one-time URL creation.
- Provide public token download endpoint that serves file once and expires.

### SimpleDMS

- Receives `GET /open-file/from-url?url=...` request.
- Fetches file from provided URL.
- Continues the open-file flow and finalizes import.

## 5) UX Specification

### Context Menu Entry

- Location: Nextcloud Files right-click/context menu
- Label: `Upload to SimpleDMS`
- Icon: SimpleDMS glyph (fallback generic upload/share icon)
- Visibility rules:
  - Show for regular files
  - Hide/disable for folders
  - Hide/disable if SimpleDMS URL not configured

### Interaction

- Click action -> loading toast: `Preparing upload...`
- Submit to SimpleDMS:
  - Preferred: open in new tab to avoid disrupting Files navigation
- On failure, show actionable toast with short reason and retry hint.

## 6) Data Contract (Draft)

SimpleDMS entrypoint:

- `GET /open-file/from-url?url=<absolute_encoded_nextcloud_token_url>`

Nextcloud app APIs:

- `POST /apps/simpledms_integration/api/create-signed-url`
  - Request: `path` (user-relative file path)
  - Response: `downloadUrl`, `expiresAt`
- `GET /apps/simpledms_integration/download/{token}`
  - Public one-time download URL
  - Returns file bytes if token is valid and unexpired

## 7) Security & Privacy

- No long-term storage of file content in Nextcloud app.
- Signed token payload stores only minimal metadata (user, path, name, mime, expiry).
- Tokens are one-time use and short-lived.
- SimpleDMS URL must be validated (HTTPS required except local dev).
- Do not log file bytes or sensitive metadata.
- Respect existing Nextcloud permissions: only files user can access are exposed by UI action.

## 8) Technical Constraints / Risks

1. **Network reachability**: SimpleDMS backend must reach the Nextcloud token download URL.
2. **Auth model mismatch**: if user is not logged into SimpleDMS, `from-url` flow should handle login redirect and continue.
3. **Token security**: token TTL and one-time semantics must be enforced.
4. **Large files**: SimpleDMS fetch performance and timeout handling for large files.

## 9) Proposed Phase Plan

### Phase 1 (MVP)

- Installable Nextcloud app with one file action.
- Admin setting for SimpleDMS base URL.
- Single-file signed URL handoff to `from-url` endpoint.
- New-tab launch and baseline error toasts.

### Phase 2

- Optional multi-select support by sending one request per selected file.
- Improved diagnostics (reachability/auth-specific messaging).
- Optional per-user preferences (open same tab/new tab, default behavior).

### Phase 3

- Optional signed batch manifests for multi-file imports.

## 10) Acceptance Criteria (MVP)

1. App can be enabled in Nextcloud and action appears for files.
2. Clicking action opens SimpleDMS import flow with selected file available.
3. If SimpleDMS is unreachable or rejects request, user sees non-technical but actionable error.
4. Admin can configure SimpleDMS URL from settings.
5. No regression in normal Nextcloud file actions.

## 11) Test Plan (MVP)

- Unit:
  - Config validation for SimpleDMS URL
  - Token generation/validation and expiry
- Integration/manual:
  - Right-click file -> upload action visible and callable
  - Successful import path with authenticated SimpleDMS session
  - Unauthenticated session path (login then continue)
  - Failure paths: invalid URL, expired token, unreachable Nextcloud URL, file too large
  - Browser matrix: latest Chromium + Firefox

## 12) Packaging / Deliverables

- Nextcloud app source with standard app structure
- README with setup and troubleshooting
- Admin docs: required SimpleDMS reachability/session prerequisites
- Changelog entry for initial release

---

## Open Questions

1. Supported Nextcloud versions: 32 and 33.
2. Can SimpleDMS backend reach the Nextcloud public URL used in signed download links?
3. Should the action open SimpleDMS in a new tab (recommended) or same tab?
4. Do you want only global admin config, or also per-user SimpleDMS URL override?
