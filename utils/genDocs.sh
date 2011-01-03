#!/bin/sh
# Used to generate the PHPDocs for the site.

rm -rdf docs/ && phpdoc -d src/ -t docs/ -o HTML:Smarty:PHP -dn Core -ti "Sag Documentation"
