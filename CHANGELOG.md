# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.13.1] - 2020-08-31

### Fixed
- Config class getPaths() method had wrong return type specified, rendering module config screen unusable.

## [0.13.0] - 2020-08-28

### Added
- New hook makes View properties directly accessible in TemplateFiles (e.g. when rendering field templates)

### Changed
- Wireframe::getController() is now a public method that can return current Controller instance, the Controller instance for provided page, or a Controller instance for provided Page and template name.
- Visibility of following methods was changed from public to protected: Wireframe::___checkRedirects(), Wireframe::___redirect(), Wireframe::___initView(), Wireframe::___initController(), \Wireframe\Config::getCreateDirectoriesField().
- Controller class implementation was streamlined: new objects are wired using the native wire() function, and thus Controller constructors no longer require the ProcessWire instance as an argument.
- Wireframe no longer caches partials unnecessarily, plus new Partial objects are automatically wired.
- Various minor optimizations, some code cleanup, and a few improvements to comments.

## [0.12.0] - 2020-08-24

### Added
- Support for named arguments when using `Wireframe::component($component_name, $args)`.
- JSON API. See comments in the WireframeAPI module file for more details.
- New Page methods Page::getController() and Page::setController().
- Module config screen provides support for creating directories corresponding to configured Wireframe URLs, assuming that they were provided as relative paths.

### Changed
- Wireframe::setView() accepts optional view name as an argument.
- View::setController() accepts Controller name (string) in addition to Controller class instance or null.
- When Controller is instantiated, it no longer overrides the Controller property of the related View instance.

### Fixed
- Wireframe::partial() now works as expected for partial names with file ext included (partial_name.php etc.)
- Wireframe::partial() prevents 2 or more dots in partial name, just in case (directory traversal is not intended).

## [0.11.0] - 2020-05-13

### Changed
- Partials class now detects if a missing partial is being requested and throws an exception.

## [0.10.2] - 2020-03-13

### Fixed
- When rendering a partial with arguments, prepare the arguments array properly before use.

## [0.10.1] - 2020-03-13

### Fixed
- Autoload Factory class in cases where it's methods are called before Wireframe has been initiated.

## [0.10.0] - 2020-03-03

### Added
- New class Partials. Container for Partial objects. Provides a gateway for rendering partials with arguments (`$partials->name(['arg' => 'val'])`).
- New class Partial. Singular Partial object. Provides the ability to render a partial and adds support for multiple (alternate) file extensions for each partial.
- New class Factory. This class encapsulates various static factory methods and is accessed through the Wireframe class.
- New static method Wireframe::partial($partial_name, $args). This method provides a shortcut for rendering partial files.
- New method Wireframe::setViewTemplate(). This allows overriding current view template via the Wireframe object, primarily intended to be used in the Wireframe bootstrap file.

### Changed
- Shared renderer related features were moved to new trait RendererTrait. This is used internally by View, Component, and Partial classes.

## [0.9.1] - 2020-02-03

### Fixed
- An issue where rendering a Page that didn't have an existing template file or altFilename via Wireframe::page() could result in ProcessWire error message.

## [0.9.0] - 2020-02-02

### Added
- New EventListenerTrait. Currently used by Components only. Adds support for listening to and emitting events.
- Support for Renderer modules for adding templating engine support for view files, component view files, etc.
- New Page methods Page::viewTemplate(), Page::getViewTemplate(), and Page::setViewTemplate().
- New method Component::getData() for manually defining the data passed to the component view.

### Changed
- Controller::init() and Controller::ready() are now hookable methods.
- Component::setView() and Component::getView() are now final methods, preventing accidental overrides.
- Layout file is no longer necessary; if it's missing, the page can be rendered using just a view file.

## [0.8.0] - 2020-01-06

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
- New Page methods Page::getLayout(), Page::setLayout(), Page::getView(), and Page::setView().
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
