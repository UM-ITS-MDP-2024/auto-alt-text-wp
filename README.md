# UMITSMDP
Auto-Captioning

## Note

This plugin uses Azure OpenAI service, please add those defined variables to `wp-config.php` file in wordpress directory

```
define( 'AZURE_API_BASE', 'YOUR_BASE_URL_HRERE' ); // example: https://api.umgpt.umich.edu/azure-openai-api
define( 'AZURE_API_KEY', 'YOUR_KEY_HERE' ); 
define( 'OPENAI_ORGANIZATION', 'YOUR_ORGANIZATION_HERE' );
define( 'API_VERSION', 'API_VERSION_HERE' ); // example: 2024-06-01
```

## Installation

Please Download the plugin .zip file on the release page, and open your wordpress website->plugin->install plugin

## Trouble Shot
If the normal installation doesn't work, please check the following for trouble shot:

Install [php package manager](https://getcomposer.org/) first and then run at this directory with the commandline
```bash
cd wp-content/plugins/auto-alt-text-wp
composer update
```
And you may need to enable some php extensions for `C:\MAMP\bin\php\php<version you are using>\` (I chose php 8.1.0) for `php.ini-development` and `php.ini-production`

search for `openssl` and `fileinfo`, and delete the `;` at the begin of the lines to uncomment them, like this
```
extension=openssl
extension=fileinfo
```
