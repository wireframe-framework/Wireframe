# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.15] - 2019-06-17

### Added
- Added $config->urls->dist, by default pointing to /site/assets/dist/.

### Changed
- Renamed default resources (assets) directory from "static" to "resources".
- Changed recommended image resources directory name from "img" to "images".

## [0.0.14] - 2019-06-16

### Changed
- Switched Composer installer from hari/pw-module to wireframe-framework/processwire-composer-installer.

## [0.0.13] - 2019-06-02

### Added
- Added .htaccess to protect markdown files from direct access.

### Changed
- Improvements to code comments and some minor refactoring.

### Fixed
- Fixed a minor issue in Controller base class where the _wire property wasn't being set properly.

## [0.0.12] - 2019-06-01

### Added
- Added composer.json.

## [0.0.11] - 2019-05-27

### Fixed
- Fixed invalid JSON syntax in module info file.

## [0.0.10] - 2019-05-23

### Added
- Added CHANGELOG.md.
