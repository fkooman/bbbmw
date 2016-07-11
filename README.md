# Introduction
This project aims to create a connector for BigBlueButton to allow scheduling
conferences for groups you are a member of. It uses federated authentication 
(e.g. with SAML) using simpleSAMLphp and retrieves group membership 
information using the VOOT protocol.

This connector interacts with an unmodified BigBlueButton using the API 
provided by the BigBlueButton service.

The connector is needed because BigBlueButton does not have any notion of 
users and groups, only conferences, moderators and attendees.

For simpleSAMLphp a system wide installation of simpleSAMLphp is recommended. 
See `docs/SURFconext.txt` for more information on configuring simpleSAMLphp 
for use with SURFconext, other identity federations will differ somewhat in 
configuration.

# Installation

You need Git and [Composer](https://getcomposer.org) to install the software.

    $ git clone https://github.com/fkooman/bbbmw.git
    $ sh docs/configure.sh
    $ /path/to/composer.phar install

If you want to use `VootGroups` run the following command to 
initialize the database:

    $ php docs/init_oauth_db.php

Copy `config.php.default` to `config.php`:

    $ cp config.php.default config.php

Now you can modify it to match your configuration. Set at least the URL to 
the BigBlueButton API and the salt as configured in BBB.

If you want to use a simple test configuration with plain username and password
authentication use SimpleAuth and SimpleGroups and configure them in 
`config.php`.

# SELinux Configuration
Set the correct SELinux labels (only for Fedora/Red Hat systems?)

    $ chcon -R -t httpd_sys_rw_content_t data/ tpl_c/

Allow HTTP to access the network (to contact BBB)

    $ su -c 'setsebool -P httpd_can_network_connect on'

(The -P flag makes the setting persistent across reboots)
