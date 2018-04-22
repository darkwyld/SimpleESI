SimpleESI
=========

Reference Manual
----------------

------------------------------------------------------------------------

### Class Traits

The file `SimpleESI.php` contains several traits, which can be used for customization of the class SimpleESI. The main purpose however is to trim down the class even further than its default, should this become necessary. There are currently two groups of traits, one for the debug messages and one for the database accesses. To choose a different trait than the defaults does one need to create a child class, which extends SimpleESI. For example:

```php
class ESInodb extends SimpleESI { use nodb; }
class ESInodbg extends SimpleESI { use nodebug; }
class SlimESI extends SimpleESI { use nodebug, nodb; }
```

#### Debugging Traits

##### `nodebug`

```php
class MyESI extends SimpleESI { use nodebug; }
```

Disables all debugging output and will not throw any exceptions either. Useful for a “don’t want to know and don’t care”-kind of application.

##### `debug`

```php
class MyESI extends SimpleESI { use debug; }
```

Enable minimal debugging support. Only errors (debug level 0) and fatal errors (debug level -1) will get printed and fatal errors will throw an exception.

##### `fulldebug` **(default)**

```php
class MyESI extends SimpleESI { use fulldebug; }
```

Provides full debugging support and throws exceptions on fatal errors. The amount of debug messages can be control with the property `debug_level`, and the higher the value is (i.e. `4`) the more information is printed. The output can be formatted as plain text or as colourized HTML text, which is control by the property `debug_html` (`true` for HTML, `false` for plain ASCII). For example:

```php
$esi->debug_level = 4;
$esi->debug_html = true;
```

The debug messages can be written into a file with the property `debug_file`, in which case the messages also show date and day time information. For example:

```php
$esi->debug_level = 2;
$esi->debug_file = “esi.log”;
```

##### List of debug messages and their level

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

#### Database Traits

##### `nodb`

##### `dirdb`

##### `sqlite3db` **(default)**

------------------------------------------------------------------------

### Class Properties

##### `esi_uri`

##### `oauth_uri`

##### `marker`

##### `paging`

##### `retries`

##### `error_throttle`

##### `error_exit`

------------------------------------------------------------------------

### Class Methods

##### `__construct`

##### `__destruct`

##### `get`

##### `single_get`

##### `pages_get`

##### `post`

##### `exec`

##### `auth`


