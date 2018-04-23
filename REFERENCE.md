SimpleESI
=========

Reference Manual
----------------

------------------------------------------------------------------------

### 1. Class Traits

The file `SimpleESI.php` contains several traits, which can be used for customizing the SimpleESI class. The main purpose however is to trim down the class even further than its default, should this become necessary. There are currently two groups of traits, one for the debug messages and one for the database accesses. To choose a different trait does one need to create a child class, which extends SimpleESI. For example:

```php
class ESInodb extends SimpleESI { use nodb; }
class ESInodbg extends SimpleESI { use nodebug; }
class SlimESI extends SimpleESI { use nodebug, nodb; }
```

#### 1.1. Debugging Traits

##### 1.1.1. `nodebug`

*Provides no public variables or methods.*

```php
class MyESI extends SimpleESI { use nodebug; }
```

The trait disables all debugging output and will not throw any exceptions either. It is useful for when one does not want to know or care about any of the messages.

##### 1.1.2. `debug`

*Provides no public variables or methods.*

```php
class MyESI extends SimpleESI { use debug; }
```

It enables minimal debugging support. Only errors (debug level 0) and fatal errors (debug level -1) will be printed. Fatal errors will throw an exception.

##### 1.1.3. `fulldebug` **(default)**

*Public variables:* `debug_level` (int), `debug_html` (bool), `debug_file` (string)

*Provides no public methods.*

```php
class MyESI extends SimpleESI { use fulldebug; }
```

Provides full debugging support and throws exceptions on fatal errors. The level of detail can be control with the property `debug_level`, and the higher the value is (i.e. `4`) the more information is printed. The output can be formatted as plain text or as colourized HTML text, which is control by the property `debug_html`, where `true` stands for HTML output and `false` for plain ASCII. For example:

```php
$esi->debug_level = 4;
$esi->debug_html = true;
```

The debug messages can be written into a file with the property `debug_file`, in which case the messages also show date and day time information. For example:

```php
$esi->debug_level = 2;
$esi->debug_file = ‘esi.log’;
```

##### The following is a list of debug messages and their level:

```
-1, 'Could not create cache directory'
-1, 'Could not create cache subdirectory'
-1, 'Could not create meta directory'
-1, 'Could not create meta subdirectory'
-1, 'Could not write/update cache file'
-1, 'Could not write/update meta file'
-1, 'Error limit reached: X'
0, 'Authorization error: no character identification received.'
0, 'Authorization error: no tokens received.'
0, 'cURL error: X'
0, 'Error response: X'
0, 'No response: X'
0, 'Unexpected cURL result: X'
0, 'Unexpected response: X'
1, 'Elapsed.'
2, 'Error limit: X, window: Y'
2, 'Retry (# X): Y'
2, 'Throttling traffic.'
3, 'Authorization tokens received.'
3, 'Character identification received.'
3, 'Received: X'
3, 'Refreshing authorization.'
3, 'Requesting authorization.'
3, 'Requesting character identification.'
3, 'Requesting pages X to Y of: Z'
3, 'Requesting (POST): X'
3, 'Requesting: X'
4, 'Cached: X'
4, 'cURL option X is not supported.'
5, 'Header: X'
```

#### 1.2. Database Traits

##### 1.2.1. `nodb`

*Provides no public variables.*

*Public methods:* `meta`

```php
class MyESI extends SimpleESI { use nodb; }
```

Disables all uses of a database. No response caching is performed and no meta database is being provided. The trait is useful for when no database is available on the system, or for debugging and to test how an application performs without any caching or database access. Calls to the `meta()`-method will not result in an error, but is only implemented as a stub. It does not perform any function.

##### 1.2.2. `dirdb`

*Public variables:* `caching` (bool)

*Public methods:* `meta`

```php
class MyESI extends SimpleESI { use dirdb; }
```

The trait implements a database by using directories and files. The directory structure is similar to the ESI resource paths. The filenames contain query arguments and end with an identifier, which in case of authenticated requests is identical to the character id and meant to keep cached responses of authorized calls between different characters separate. A file’s modification time is used to store the expiration time of its resource and therefore is set to a time in the future. The cache can be controlled at runtime through the variable `caching`, which allows an application to enable or disable it at will.

```php
$esi->caching = false;
```

The `meta()`-method implements a “key/value”-database. When called with one argument, a key, does it query the database for the value associated with the key:

```php
$value = $esi->meta(‘key’);
```

The key has to be a string and represents a file on the drive, whose content represents the value. The file’s content is interpreted by the PHP function `unserialize()` and so can return any data type supported by it. When called with two arguments, a key and a value, does it assign the value to the key by storing the value in a file with the key as its name:

```php
$esi->meta(‘key’, $value);
```

The content of `$value` is turned into a string by the PHP function `serialize()` before storing it. It allows to store different data types, including self-referencing arrays and class objects. For more information please see the PHP manual on `serialize()`/`unserialize()`.

Note: it is possible to use the directory seperator `/` as a part of a key, which will place the corresponding file into a subdirectory. It also means both `..` and `.` work for changing directories as expected, and need to be avoided when keys are meant to stay compatible to the `sqlite3db`-trait.

##### 1.2.3. `sqlite3db` **(default)**

*Public variables:* `caching` (bool)

*Public methods:* `meta`

```php
class MyESI extends SimpleESI { use sqlite3db; }
```

The trait implements a database by using SQLite3, which consists of two SQL tables, `cache` and `meta`, for caching responses and for providing a “key/value”-database. When authorized requests are being cached are these stored together with the character’s id to keep responses between different users separate. Caching can be controlled through the variable `caching`, and by setting it to either `true` or `false` in order to enable and disable the mechanism at runtime.

```php
$esi->caching = false;
```

The `meta()`-method implements a “key/value”-database analogue to the `dirdb`-trait above. When called with one argument, a key, does it query the database for the value associated with the key:

```php
$value = $esi->meta(‘key’);
```

The key has to be a string and represents a column in the `meta` table. The value is interpreted by the PHP function `unserialize()` in the same way as it is done in the `dirdb`-trait. When called with two arguments, a key and a value, does it assign the value to the key by storing both key and value in the SQL table:

```php
$esi->meta(‘key’, $value);
```

The content of `$value` is turned into a string by the PHP function `serialise()` before storing it in the database.

Note: keys should not contain `..` and `.` when these are meant to be compatible with the `dirdb`-trait.

------------------------------------------------------------------------

### 2. Class Properties

##### 2.1. `esi_uri` (string)

```php
$esi->esi_uri = 'https://esi.tech.ccp.is/dev/’;
```

The URI of the ESI server. Can be changed when necessary and must end with a `/`-character. The default is `https://esi.tech.ccp.is/latest/`.

##### 2.2. `oauth_uri` (string)

```php
$esi->oauth_uri = 'https://sisilogin.testeveonline.com/oauth/’;
```

The URI of the SSO OAuth2 server. Can be changed when necessary and must end with a `/`-character. The default is `https://login.eveonline.com/oauth/`.

##### 2.3. `marker` (string)

```php
$esi->marker = ‘#’;
```

The character, or string, used in a pattern to the `get()`-method. The default is the `~`-character.

##### 2.4. `paging` (bool)

```php
$esi->paging = false;
```

A toggle to disable and enable the behaviour of the `exec()`-method when responses are segmented into pages. The default is `true`, causing the `exec()`-method to request all pages automatically.

##### 2.5. `retries` (int)

```php
$esi->retries = 5;
```

The number of additional attempts to make when requests fail. The default is `3`.

##### 2.6. `error_throttle` (int)

```php
$esi->error_throttle = 90;
```

The number of errors remaining (according to the `X-ESI-Error-Limit-Remain:`-header) at which to begin throttling out-going traffic. The default is `80`. The ESI error limit is a number, starting at `100`, which is returned by ESI for each request and represents a count-down after which an application is denied access (for a limited time). It is meant to control the traffic to the ESI server and to give each application a chance to back off in cases where there is a problem. The number is not necessarily an indication for an application error. A count-down can occur for various reasons. The error count is valid for a limited time after which it is reset to `100`. SimpleESI uses a non-linear function to implement a dynamic behaviour. When the reported error limit drops to or below the `error_throttle` value will it pause briefly for a few milliseconds before sending out a new request. This pause will grow longer the closer the count-down gets to `0` and the larger the remaining time window is.

##### 2.7. `error_exit` (int)

```php
$esi->error_exit = -1;
```

The number of errors remaining at which to raise an exception in the application. The default is `20`. If the exception is not caught will it exit the application. It is meant to prevent the count-down from reaching `0`, at which the ESI server will reject further requests. Setting this value below `0` will turn this behaviour off and offers a good chance for your application to be noticed at CCP.

------------------------------------------------------------------------

### 3. Class Methods

##### 3.1. `__construct`

##### 3.2. `__destruct`

##### 3.3. `get`

##### 3.4. `single_get`

##### 3.5. `pages_get`

##### 3.6. `post`

##### 3.7. `exec`

##### 3.8. `auth`


