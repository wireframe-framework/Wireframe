Wireframe ProcessWire module and output framework
-------------------------------------------------

Wireframe is, in the lack of a better term, an output framework for the ProcessWire CMS/CMF. It
loosely follows the MVC (Model-View-Controller) architecture, introducing concepts such as View
Controllers to ProcessWire site development.

Technically Wireframe is a combination of a ProcessWire module - called Wireframe - and a set of
related classes. Certain features are bundled into a separate module called Wireframe API; this
optional companion module can be used to quickly set up a JSON based API.

This README file provides basic instructions for setting Wireframe up. More detailed instructions
can be found from https://wireframe-framework.com, and if you'd like to see an example site using
Wireframe, be sure to check out https://github.com/wireframe-framework/site-wireframe-boilerplate/.

## Getting started

1. Download and install the Wireframe ProcessWire module.

There are a couple of ways to get the module:

- Clone or download from GitHub: https://github.com/wireframe-framework/Wireframe
- Install using Composer: https://packagist.org/packages/wireframe-framework/wireframe

2. Set up the Wireframe directories within the /site/templates/ directory, or install a new site
using the site-wireframe-boilerplate site profile.

If your site already has identically named files or folders, you can rename the included files to
something else, as long as you also adjust the paths in config settings ($config->wireframe array)
accordingly. See https://wireframe-framework.com/docs/configuration-settings/ for more information.

3. Copy wireframe.php from the module's directory to /site/templates/.

This is the file that bootstraps Wireframe. If you want to pass variables to Wireframe during init
or render phases, you can directly modify this file.

4. Add wireframe.php as the filename of the template(s) you want to use Wirerame for

This can be done via the Alternate Template Filename setting found from the template edit screen,
and will redirect requests for pages using those templates through the Wireframe bootstrap file.

Note: you don't actually have to route all your templates through Wireframe. In case you want to
use other output strategies for other templates, that will work just fine.

## Resources

- An introduction to Wireframe and output strategies in general: https://wireframe-framework.com/about/
- A more in-depth getting started guide for Wireframe: https://wireframe-framework.com/getting-started/
- Wireframe documentation: https://wireframe-framework.com/docs/
- Support forum: https://processwire.com/talk/topic/21893-wireframe/

## License

This project is licensed under the Mozilla Public License Version 2.0.
