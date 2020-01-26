# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- New trait EventListenerTrait, which currently adds support for Components to listening and emitting events.
- Support for Renderer modules for adding templating engine support for view files, component view files, etc.

### Changed
- Controller::init() and Controller::ready() are now hookable methods.
- Component::setView() and Component::getView() are now final methods, preventing accidental overrides.
- Layout file is no longer necessary; if it's missing, the page can be rendered using just a view file.

## [0.8.0] - 2019-01-06

### Added
- Support for Components, along with a new static factory method Wireframe::component($component_name, $args).
- Support for rendering pages that have not been "routed" to Wireframe using the altFilename template setting.
- New static getter/factory/utility method Wireframe::page($source, $args).
- New static utility method Wireframe::isInitialized().

### Changed
- Wireframe::$initialized is now a static property. This was a necessary change so that Wireframe::isInitialized() could be implemented effectively.

## [0.7.0] - 2019-11-04

### Added
- Runtime caching support for Controller method return values. Values are cached *unless* the name of the method is found from Controller::$uncacheable_methods.
- Persistent caching support for Controller method return values. Values are cached only when found from the Controller::$cacheable_methods array.

### Changed
- In the View class all internal requests for Controller properties are routed through View::getFromController().

## [0.6.0] - 2019-09-13

### Added
- New Page methods Page::getLayout, Page::setLayout(), Page::getView(), and Page::setView().
- New Controller::render() method, executed right before a page is actually rendered.
- New ViewData class for storing (internal) data required by the View class.
- New getter/setter methods for ViewData properties for the View class.
- New method Wireframe::getConfig() for getting current config settings.
- New method ViewPlaceholders::has() for checking if a placeholder has already been populated.

### Changed
- Various View-related features moved from Wireframe module and ViewPlaceholders class to the View class.
- Removed access to local get* and set* methods via the PHP's magic setter method __set() and getter method __get() in the View class.
- Redirect feature no longer fails if provided with a WireArray data type; in these cases the first item is used as the redirect target.
- Improvements to PHPDoc comments.

### Fixed
- An issue with Config class where the "all directories exist" message was sometimes displayed unintentionally.
- An issue where View Placeholder values might've been overwritten because existence of earlier value was checked inproperly.
- An issue where empty / null view file would be automatically replaced with value "default".

## [0.5.2] - 2019-08-28

### Fixed
- ViewPlaceholders is provided with a reference to the View object in order to keep template name in sync.

### Changed
- ViewPlaceholders no longer tracks template name separately, and the constructor method no longer accepts template name as an optional param.
- Comments, property visibilities, and some parameter names in the ViewPlaceholders class updated to match the rest of the codebase.

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
