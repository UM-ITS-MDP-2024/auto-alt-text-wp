# UMITSMDP
Auto-Captioning

## Installation

Please Download the plugin .zip file on the release page, and open your wordpress website->plugin->install plugin

## Trouble Shot
If the normal installation doesn't work, please check the following for trouble shot:

Install [php package manager](https://getcomposer.org/) first and then run at this directory with the commandline
```bash
cd wp-content/plugins/auto-alt-text-wp
composer update
```
Also, check if you define the OPENAI_API_KEY in a newline in `wp-config.php` like this:
```
define( 'OPENAI_API_KEY',   '<put_your_key_here>');
```
And you may need to enable some php extensions for `C:\MAMP\bin\php\php<version you are using>\` (I chose php 8.1.0) for `php.ini-development` and `php.ini-production`

search for `openssl` and `fileinfo`, and delete the `;` at the begin of the lines to uncomment them, like this
```
extension=openssl
extension=fileinfo
```
