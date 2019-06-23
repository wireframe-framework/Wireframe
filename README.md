Wireframe ProcessWire module and output framework
-------------------------------------------------

Wireframe is, in the lack of a better term, an output framework for the ProcessWire CMS/CMF. It
loosely follows the MVC (Model-View-Controller) architecture by providing a View component, and
(optionally) template specific Controllers.

Wireframe is implemented as a combination of a ProcessWire module called Wireframe and a set of
classes found form the lib directory. This README file contains some basic information for getting
a site using the Wireframe framework up and running, but a lot more information can be found from
the Wireframe documentation site at https://wireframe-framework.com.

You may also want to check out https://github.com/wireframe-framework/site-wireframe-boilerplate/
for a boilerplate site profile built on top of Wireframe.

## Getting started

1. Install the Wireframe ProcessWire module.

2. Set up the Wireframe directories within the /site/templates/ directory, or install a new site
using the site-wireframe-boilerplate site profile.

If your site already has identically named files or folders, you can rename the included files to
something else, as long as you also adjust the paths in config settings ($config->wireframe)
accordingly. See Wireframe.module.php for more details.

3. Copy wireframe.php from the Wireframe module directory to /site/templates/.

This is the file that bootstraps Wireframe. If you want to pass variables to Wireframe during init
or render phases, you can directly modify this file.

4. Set the value of the Alternate Template Filename setting of templates you want to route through
Wireframe to 'wireframe'.

This will redirect requests for pages using those templates through the Wireframe bootstrap file.

Since this solution is based on the Alternate Template Filename setting, you can use it for only a
subset of your templates. In case you want to use other output strategies for other templates,
that's perfectly fine.

## Other MVC-ish output strategies

In case you're interested in working with the MVC pattern – or simply looking for a solution that
offers separation of concerns for your template files – and this particular project doesn't fit
your needs, I'd recommend checking out following alternatives:

* [A Rails-inspired [something]VC boilerplate for new ProcessWire projects](https://github.com/fixate/pw-mvc-boilerplate)
* [Spex, an asset and template management module for ProcessWire](https://github.com/jdart/Spex)
* [Template Data Providers module](https://github.com/marcostoll/processwire-template-data-providers)

## License

This project is licensed under the Mozilla Public License Version 2.0.