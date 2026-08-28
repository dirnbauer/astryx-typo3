# Changelog

All notable changes to `webconsulting/astryx-typo3` are documented here.

## [1.0.2] - 2026-08-28

### Fixed

- Keep structural page fields, including `is_siteroot`, synchronized on German
  page overlays so EXT:solr can resolve multilingual rootlines.
- Make translation seeding idempotent after concurrent runs by retaining the
  oldest managed overlay and soft-deleting duplicate overlay records.

## [1.0.1] - 2026-08-06

### Fixed

- Added the official `Configuration/ViteEntrypoints.json` declaration required
  by `vite-plugin-typo3`, so project builds discover both Astryx entries.

## [1.0.0] - 2026-08-06

### Added

- Initial independent Astryx for TYPO3 release with 250 server-rendered
  Content Blocks, 25 themes and the pinned upstream Astryx v0.3.0 data.
