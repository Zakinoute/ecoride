#!/bin/bash
set -e

# Force MPM prefork (résout "More than one MPM loaded")
find /etc/apache2/mods-enabled/ -name "mpm_*.conf" -o -name "mpm_*.load" | xargs rm -f
a2enmod mpm_prefork
a2enmod rewrite

exec apache2-foreground
