# Mince

Minify and combine your js and css files in a web project: a command line tool written in PHP.

## Installation

### Requirements
 - PHP
 - jsmin
 - csstidy

Checkout or download the files into a location on your server or machine. The
following are good locations: `/usr/share/php/mince-1.1` or
`/usr/local/share/php/mince-1.1`

### Suggested Installation

    cd /usr/local/share/php
    git clone git://github.com/sumpygump/mince.git mince-1.1

Add a symlink to the mince program somewhere in your path

    ln -s /usr/local/share/php/mince-1.1/mince /usr/local/bin/mince
    chmod a+x /usr/local/bin/mince

## Usage

Inside your project folder, create a file called .minceconf. That file will be
in yaml format and contain a list of the minify and/or combine rules.

Example file:

    minify:
      - public/css/global.css
      - public/css/admin.css
      - public/js/somefile.js
      - public/js/anotherfile.js
    combine:
      public/css/global.cmb.css:
        - public/css/global.min.css
        - public/css/admin.min.css
      public/js/project.cmb.js:
        - public/js/somefile.min.js
        - public/js/anotherfile.min.js

This will tell mince to minify the files listed. The minified file written will
be the filename `<originalname>` + `.min` + `<extension>`. For example,
`global.css` will be minified to `global.min.css`.

This will also tell mince to combine the minified files into one file. For
example, the first file created will be `public/css/global.cmb.css`, and will
consist of the contents of the files `public/css/global.min.css` and
`public/css/admin.min.css`.

Be sure to use paths from the root of the project (where mince will be run).

To perform the mincing, just run "mince" from the command line. It will look
for the file named `.minceconf` and perform the directives indicated therein.

## Options

 - `mince -v` will run mince with verbose output.
 - `mince -q` will run mince in quiet mode (no output).
 - `mince --file=filename.yml` will tell mince to look for the minceconf file
   `filename.yml` instead of the default `.minceconf`.
