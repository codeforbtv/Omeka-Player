<?php
/**
 * Stream Only Omeka Player Plugin
 * Constants
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

//  Item Type
const SO_ITEM_TYPE_NAME = 'StreamOnly';
const SO_ITEM_TYPE_DESCRIPTION = 'Audio files stored in this Item Type cannot be downloaded';
const SO_PLUGIN_NAME = "StreamOnly Plugin";
const SO_PLUGIN_MODEL = "StreamOnlyModel";

// Elements
const ELEMENT_TRANSCRIPTION = 'Transcription';
const ELEMENT_TRANSCRIPTION_DESCRIPTION = 'Any written text transcribed from a sound';
const ELEMENT_TRANSCRIPTION_FIELD = 'transcription';
const ELEMENT_ORIGINAL_FORMAT = 'Original Format';
const ELEMENT_ORIGINAL_FORMAT_DESCRIPTION  = 'The type of object, such as painting, sculpture, paper, photo, and additional data';
const ELEMENT_ORIGINAL_FORMAT_FIELD = 'original_format';
const ELEMENT_DURATION = 'Duration';
const ELEMENT_DURATION_DESCRIPTION  = 'Length of time involved (seconds, minutes, hours, days, class periods, etc.';
const ELEMENT_DURATION_FIELD = 'duration';
const ELEMENT_BIT_RATE = 'Bit Rate/Frequency';
const ELEMENT_BIT_RATE_DESCRIPTION  = 'Rate at which bits are transferred (i.e. 96 kbit/s would be FM quality audio';
const ELEMENT_BIT_RATE_FIELD = 'bit_rate/frequency';
const ELEMENT_LICENSE_COUNT = 'Stream Only License Count';
const ELEMENT_LICENSE_COUNT_DESCRIPTION = 'Number of simultaneous listeners permitted by licensing';
const ELEMENT_LICENSE_COUNT_FIELD = 'stream-only-license-count';
const ELEMENT_FOLDER = 'Stream Only Directory';
const ELEMENT_FOLDER_DESCRIPTION =
    'The folder (relative to the account root) '.
    'where protected audio files will be stored. ' .
    'The default folder is set during configuration of the plugin, ' .
    'but can be overridden on an Item-by-Item basis. ' .
    '***Use only those folders provided by the System Administrator.***';
const ELEMENT_FOLDER_FIELD = 'stream-only-directory';
const ELEMENT_TIMEOUT = "Timeout";
const ELEMENT_TIMEOUT_DESCRIPTION = "Number of seconds the protected file is made available to a site visitor";
const ELEMENT_TIMEOUT_FIELD = 'timeout';

// Names of options
const OPTION_LICENSES = 'so_default_license_count';
const OPTION_FOLDER = 'so_default_folder';
const OPTION_TIMEOUT = 'so_timeout';

// Defaults for options
const DEFAULT_LICENSES = 1;
const DEFAULT_FOLDER = 'so_files';
CONST DEFAULT_TIMEOUT = 300;

// .htaccess rule
const HTACCESS = '.htaccess-old';
const HTACCESS_TMP = '.htaccess-tmp';
const REWRITE_CONDITION = "RewriteCond %{REQUEST_FILENAME} -f\n";
const REWRITE_RULE = "RewriteRule ^plugins/StreamOnly/scripts/.*\.php$ - [L]\n";


// .htaccess file for files/m3u/ directory
const SO_DENY_ACCESS =
"Options -Indexes

AddType audio/mpeg mp3

# New-fangled directions
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

# Old-timey directives
<IfModule !mod_authz_core.c>
    Order Deny,Allow
    Deny from all
</IfModule>";

const SO_XSENDFILE_ON =
"XSendFile on";