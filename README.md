wireframe ProcessWire module and output framework
-------------------------------------------------

Wireframe is, in the lack of a better term, an output framework for the ProcessWire CMS/CMF. It
loosely follows the MVC (Model-View-Controller) architecture by providing a View component, and
(optionally) template specific Controllers.

Wireframe is implemented as a combination of a ProcessWire module called wireframe, and a set of
related classes found form the lib directory. This README file contains basic information about
the framework, and instructions on setting it up and getting started with site development.

Check out https://github.com/teppokoivula/wireframe-profile/ for a boilerplate site profile built
on top of wireframe.

WARNING: this project is in an alpha state. If may or may not work as expected, and the API (such
as hookable methods and method names in general) may still change.

## A bit of history

Wireframe is based on an earlier project called pw-mvc. Both projects share similar base concepts,
but wireframe is more modern – and more complete – solution in many ways. If you're using pw-mvc,
you may want to migrate to wireframe eventually.

One reason behind the name change is to avoid unnecessary confusion caused by the "MVC" part of
the name. The goal was never to produce a "real" MVC solution, but rather borrow some ideas and
implement them in a way that makes most sense in the context of ProcessWire. Hence wireframe: the
frame(work) for your site.

## Basic concepts

If you're not familiar with the MVC pattern, there's a ton of stuff written about it all around the
web. That being said, as long as you know that MVC is a pattern promoting separation of concerns
(data, business logic, and user interface), and that there are many variants of it floating around,
you'll do just fine.

Just like most of those variants, wireframe is also nowhere near a 100% accurate MVC implementation.
One particularly notable difference to many other projects, as of this writing, is that wireframe
doesn't have a separate Model component.

The reasoning behind this design decision is that in the context of a ProcessWire site, ProcessWire
itself *is* the real Model layer. As a result, wireframe Controllers tend to contain more business
logic than they do in many other MVC applications and frameworks.

Below you'll find a quick breakdown of the core concepts used within this project.

### Wireframe bootstrap file

The wireframe bootstrap file is a single file in your /site/templates/ directory, typically called
wireframe.php. This file is inserted into the Alternate Template File setting for templates you want
to utilize wireframe for, and it is responsible for bootstrapping the wireframe framework, and also
providing it with any general-purpose settings (such as a site name or language) you may need.

You'll find the default (boilerplate) wireframe bootstrap file from the wireframe module directory.

### View

View component is essentially the current rendering context. You can pass params from Controller to
View using the $view API variable and they'll become locally scoped variables for layotus and view
scripts (`<?= $this->some_var ?>`).

Note that you can also access the $view API var from within layouts and view scripts, which makes it
possible to pass data from the view script to the layout. Still, due to the rendering order, passing
data from a layout to view script is not possible.

### Controllers

Controllers are template-specific classes. Their main responsibilities include processing user input,
fetching data from the Model (ProcessWire), formatting it as needed, and passing it to the View.

Controllers should contain any business logic related to the template – or, at least, business logic
that doesn't belong to a separate module or class. One of the key concepts of MVC is separation of
concerns, and the first step towards that goal is not mixing business logic with markup generation.

Note that Controllers are optional: if a Page can be rendered without complex business rules (which
means that you just need basic control structures, loops, and echo/print statements) it is perfectly
fine to leave Controllers outt of the equation and request data directly from ProcessWire's API.

When a Page is rendered, wireframe will check if it can find and instantiate a template-specific
Controller class. If it can't, it'll just continue rendering the Page.

### Layouts

A layout is essentially a wrapper or container for page content. Most sites will likely only need
one or two layouts, but this of course depends a lot on the site in question. Page-specific content
can be rendered using View Scripts and then injected within the Layouts using View Placeholders.

Layouts are recommended but optional in wireframe. If you find that many of your pages include an
identical basic structure, such as a common header and footer, one option is to include files for
these on each view script – but that's also where layouts come in handy. By moving those shared
parts of the page markup into a layout file you avoid unnecessary repetition in view scripts.

### View Scripts

View Scripts are the files used to render actual page content. Each template may have one or more
view scripts: while you only need one to render page content, you can add additional view scripts
for rendering page content in different ways, or perhaps in different contexts.

For an example, a news-list template could have "default" view script for rendering a HTML markup
for a news list, "rss" view script for rendering the child pages (i.e. news items) as an RSS feed,
"json" view script for providing a JSON API, and so on.

Each view script can specify a content type of it's own, and you can use just about anything from
GET parameters to URL segments and even headers for selecting which view script to use.

Note: by default view scripts are PHP files, which means that you can technically perform even the
most complex programmatic tasks within them. Regardless, it is strongly recommended that you only
ever perfrom the most basic actions within view scripts – essentially output content, and at most
include simple loops and other control structures.

### View Placeholders

View Placeholders are the preferred way to inject the rendered page content, or any markup and/or
variables produced by Controllers or the Model layer for that matter, into a Layout.

View Placeholders are accessed via the $placeholders variable. First set a value in a Controller:

```
$this->view->placeholders->title = $page->title;
```

After which you can read the value in a Layout file:

```
<title><?= $placeholders->title ?></title>
```

You can also define placeholders using view scripts: create a view script matching the name of the
intended placeholder, such as /site/templates/views/scripts/home/title.php, and use the placeholder
variable in a layout – just like we did in the example above. When you do that, wireframe renders
the page using that view script and populates the variable with the rendered output.

### Partials

Partials are smmaller pieces of markup (specific parts or elements of the site) that you can include
within your view scripts or layout files. Partials can come in handy any time you want to split view
files into smaller, more manageable chunks, but they are especially useful when it comes to reusable
UI components.

Partials are embedded using PHP's native include, require, etc. commands:

```
<?php include 'partials/menu/top.php'; ?>
```

Wireframe also provides an alternative object oriented way to access partials:

```
<?php include $partials->menu->top; ?>
```

## Directory structure

The directory structure outlined here is based on the [recommended project directory structure for
Zend Framework 1.x](http://framework.zend.com/manual/1.12/en/project-structure.project.html). Each
component has it's place in the tree, and each directory exists for a reason:

- /controllers/ contains your optional Controller files, each of which applies to a single template.
You can have Controllers for all of your templates, some of them, or none of them. Controller files
should be named after the template they apply to and contain a class extending wireframe\Controller:

    - Controller class for home template should be named HomeController and placed in a file called
      /site/templates/controllers/HomeController.php.
    - Controller class for basic-page template should be named BasicPageController and placed in a
      file called /site/templates/controllers/BasicPageController.php.
    - etc.

- /lib/ should contain any additional code that doesn't fit into controllers: utility functions and
anything along those lines, third party libraries (except those loaded with Composer), and so on.

- /views/ contains everything related to the display side of your site: /views/scripts/ contains
view scripts, /views/layouts/ contains layout files, and /views/partials/ contains partials.

- /static/ contains all your static assets, such as CSS, JavaScript, font, and image files. It is
recommended that you create subdirectories for different types of files, but that is obviously up
to you to decide.

Here's the entire (default) directory structure:

```
.
├── admin.php
├── controllers
│   └── HomeController.php
├── errors
│   └── 500.html
├── lib
├── static
│   ├── css
│   │    └── main.css
│   ├── img
│   └── js
│       └── main.js
├── views
│   ├── layouts
│   │   └── default.php
│   ├── partials
│   │   └── menu
│   │       ├── breadcrumbs.php
│   │       └── top.php
│   └── scripts
│       ├── basic-page
│       │   └── default.php
│       └── home
│           ├── default.php
│           └── json.php
└── wireframe.php
```

## Simplified program flow

1. User requests an URL, which points to a Page on the site.
2. ProcessWire figures out the basic requirements to fulfil the request: which page it is for, which
   template to use, which template file to use, etc.
3. When the Alternate Template Filename points to the wireframe bootstrap file (wireframe.php in the
   /site/templates/ directory) wireframe gets bootstrapped and initiated (configured).
4. First wireframe checks for redirects: if a matching redirect rule is found, user is redirected to
   the target location instead of the Page getting rendered.
5. If no redirect was found, View component gets initialized with default parameters: layout, view
   script, object ontaining partial paths, data arguments, and placeholders. $view API variable is
   set to refer to the View object.
6. Wireframe looks for a Controller class for the template of the Page being rendered, and if one
   is found, it gets instantiated. The constructor method of the Controller automatically sets the
   Controller up and calls its init() method.
7. Wireframe chooses a view script for the View component. Unless the view script is "default", the
   PageRenderNoCache Session variable is set to avoid accidentally caching the output of a temporary
   (or alternative) view script as the main content of the page. Rules for choosing view script:
      a) By default a view script called "default" is used.
      b) If allow_get_view configuration setting has been enabled, GET parameters can be used to set
         the view script, but only to the extent allowed by said configuration setting.
      c) If view script has been set programmatically (in Controller or a hook) this value is used.
8. Bootstrap file calls the render() method of wireframe, which renders the Page using the Layout
   and view script set for the View component, and outputs resulting markup.

Controllers, layouts and view scripts are entirely up to the developer, but if you need to modify
the program flow in some other way, you can do that by hooking into various points of the program
flow described above. You can check which methods are hookable from the wireframe module file.

## Getting started

1. Install the wireframe ProcessWire module.

2. Set up the wireframe directories within the /site/templates/ directory, or install a new site
using the wireframe-site boilerplate site profile.

If your site already has identically named files or folders, you can rename the included files to
something else, as long as you also adjust the paths in config settings ($config->wireframe)
accordingly. See wireframe.module.php for more details.

3. Copy wireframe.php from the wireframe module directory to /site/templates/.

This is the file that bootstraps wireframe. If you want to pass variables to wireframe during init
or render phases, you can directly modify this file.

4. Set the value of the Alternate Template Filename setting of templates you want to route through
wireframe to 'wireframe'.

This will redirect requests for pages using those templates through the wireframe bootstrap file.

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