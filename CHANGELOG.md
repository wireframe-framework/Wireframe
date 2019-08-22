# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- New Page methods Page::getLayout, Page::setLayout(), Page::getView(), and Page::setView().

## [0.5.1] - 2019-08-22

### Fixed
- Fix an issue where the redirect feature wasn't working properly when using a Page Reference field.

## [0.5.0] - 2019-08-15

### Added
- New internal features for caching and optimizing render times.

### Fixed
- Perform certain initialization tasks only once (attach hooks, configure autoloader, etc.)
- Fix an issue where another page couldn't be rendered with provided view/layout combination.

## [0.4.0] - 2019-07-07

### Added
- Wireframe\Lib namespace was added to ProcessWire's class autoloader.

## [0.3.0] - 2019-07-03

### Added
- New Wireframe Config class was added and Wireframe was made configurable.
- Support for automatically creating Wireframe directories via module config screen in case ProcessWire has necessary write access.

### Changed
- Some refactoring, including changes to method names and return values, for better readability and more consistent API.
- In Wireframe::init() paths and ext are now set by separate methods, not directly in the init() method itself.

## [0.2.1] - 2019-06-30

### Changed
- Bumped required version of wireframe-framework/processwire-composer-installer to 1.0.

## [0.2.0] - 2019-06-23

### Changed
- Bulk of the documentation removed from the README file and moved to wireframe-framework.com.
- The "view" directory was removed and its contents were moved directly under the templates directory.
- The templates directory was added to the include path, and view directory removed.

## [0.1.0] - 2019-06-23

### Changed
- Renamed the module, along with its namespaces, from wireframe to Wireframe.
- Renamed "view scripts" to "views", while also using the term "view files" where appropriate.

### Fixed
- Corrected site profile URLs in the install instructions in the README file.
- Improvements and corrections to PHPDoc DocBlocks.

## [0.0.16] - 2019-06-22

### Changed
- Updated the required version of wireframe-framework/processwire-composer-installer.

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
