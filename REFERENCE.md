SimpleESI
=========

Reference Manual
----------------

------------------------------------------------------------------------

This manual describes the class SimpleESI and is divided in several sections. The first section describes the traits that can be used together with the class and that are being provided by the file `SimpleESI.php`. The second section describes the member variables and the last section describes the member methods of the class SimpleESI.

The notation marks data types with `()`, mandatory arguments are marked with `<>` and optional arguments are marked with `[]`. For example:

```php
(type) variable
(type) method(<(type) $mandatory>, [(type) $optional])
```

------------------------------------------------------------------------

### 1. Class Traits

The file `SimpleESI.php` contains several traits, which can be used for customizing the SimpleESI class. The main purpose however is to trim down the class further than its default, should this become necessary. There are currently two groups of traits, one for the debug messages and one for the database accesses. To choose a different trait does one need to create a child class, which extends SimpleESI. For example:

```php
class ESInodb extends SimpleESI { use nodb; }
class ESInodbg extends SimpleESI { use nodebug; }
class SlimESI extends SimpleESI { use nodebug, nodb; }
```

#### 1.1. Debugging Traits

##### 1.1.1. `nodebug`

*Provides no public variables or methods.*

The trait disables all debugging output and will not throw any exceptions. It is useful for when one does not want to know or care about any of the messages.

```php
class MyESI extends SimpleESI { use nodebug; }
$esi = new MyESI;
```

##### 1.1.2. `debug` **(default)**

*Public variables:* `(int) debug_level`, `(bool) debug_html`, `(string) debug_file`

*Provides no public methods.*

Provides full debugging support and throws exceptions on fatal errors. The level of detail can be control with the variable `->debug_level`, and the higher the value is (i.e. `4`) the more information is printed. The output can be formatted as plain text or as colourized HTML text, which is control by the variable `->debug_html`, where `true` stands for HTML output and `false` for plain ASCII. For example:

```php
$esi = new SimpleESI;
$esi->debug_level = 4;
$esi->debug_html = true;
```

The debug messages can be written into a file with the variable `->debug_file`, in which case the messages also show date and day time information. For example:

```php
$esi->debug_level = 2;
$esi->debug_file = 'esi.log';
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

*Public methods:* `(null) meta(<(string) $key>, [(mixed) $value])`

Disables all uses of a database. No response caching is performed and no meta database is being provided. The trait is useful for when no database is available on the system, for a read-only filesystem, or for debugging and to test how an application performs without any caching or database access. Calls to the `meta()`-method will not result in an error, but is only implemented as a stub. It does not perform any function.

```php
class MyESI extends SimpleESI { use nodb; }
$esi = new MyESI;
```

##### 1.2.2. `dirdb`

*Public variables:* `(bool) caching`

*Public methods:* `(mixed) meta(<(string) $key>, [(mixed) $value])`

The trait implements a database by using directories and files. The directory structure is similar to the ESI resource paths. The filenames contain query arguments and end with an identifier, which in case of authenticated requests is identical to the character id and meant to keep cached responses of authorized calls between different characters separate. A file's modification time is used to store the expiration time of its resource and therefore is set to a time in the future. The cache can be controlled at runtime through the variable `->caching`, which allows an application to enable or disable it at will. Caching is enabled by default.

```php
class MyESI extends SimpleESI { use dirdb; }
$esi = new MyESI;
$esi->caching = false;
```

The `meta()`-method implements a “key/value”-database. When called with one argument, a key, does it query the database for the value associated with the key:

```php
$value = $esi->meta('key');
```

The key has to be a string and represents a file on the drive, whose content represents the value. The file's content is interpreted by the PHP function `unserialize()` and so can return any data type supported by it. When called with two arguments, a key and a value, does it assign the value to the key by storing the value in a file with the key as its name:

```php
$esi->meta('key', $value);
```

The content of `$value` is turned into a string by the PHP function `serialize()` before storing it. It allows to store different data types, including self-referencing arrays and class objects. For more information please see the PHP manual on `serialize()`/`unserialize()`.

Note: it is possible to use the directory seperator `/` as a part of a key, which will place the corresponding file into a subdirectory. It also means both `..` and `.` work for changing directories as expected, and need to be avoided when keys are meant to stay compatible to the `sqlite3db`-trait.

##### 1.2.3. `sqlite3db` **(default)**

*Public variables:* `(bool) caching`

*Public methods:* `(mixed) meta(<(string) $key>, [(mixed) $value])`

The trait implements a database by using SQLite3, which consists of two SQL tables, `cache` and `meta`, for caching responses and for providing a “key/value”-database. When authorized requests are being cached are these stored together with the character's id to keep responses between different users separate. Caching can be controlled through the variable `->caching`, and by setting it to either `true` or `false` in order to enable and disable the mechanism at runtime. Caching is enabled by default.

```php
$esi = new SimpleESI;
$esi->caching = false;
```

The `meta()`-method implements a “key/value”-database analogue to the `dirdb`-trait above. When called with one argument, a key, does it query the database for the value associated with the key:

```php
$value = $esi->meta('key');
```

The key has to be a string and represents a column in the `meta` table. The value is interpreted by the PHP function `unserialize()` in the same way as it is done in the `dirdb`-trait. When called with two arguments, a key and a value, does it assign the value to the key by storing both key and value in the SQL table:

```php
$esi->meta('key', $value);
```

The content of `$value` is turned into a string by the PHP function `serialise()` before storing it in the database.

Note: keys should not contain `..` and `.` when these are meant to be compatible with the `dirdb`-trait.

------------------------------------------------------------------------

### 2. Class Variables

##### 2.1. `(string) esi_uri`

The URI of the ESI server. Can be changed when necessary and must end with a `/`-character. The default is `https://esi.tech.ccp.is/latest/`.

```php
$esi->esi_uri = 'https://esi.tech.ccp.is/dev/';
```

##### 2.2. `(string) oauth_uri`

The URI of the SSO OAuth2 server. Can be changed when necessary and must end with a `/`-character. The default is `https://login.eveonline.com/oauth/`.

```php
$esi->oauth_uri = 'https://sisilogin.testeveonline.com/oauth/';
```

##### 2.3. `(string) marker`

The character, or string, used in a pattern to the `get()`-method. The default is the `~`-character.

```php
$esi->marker = '#';
```

##### 2.4. `(bool) paging`

A toggle to disable and enable the behaviour of the `exec()`-method when responses are segmented into pages. The default is `true`, causing the `exec()`-method to request all pages automatically.

```php
$esi->paging = false;
```

##### 2.5. `(int) retries`

The number of additional attempts to make when requests fail. The default is `3`.

```php
$esi->retries = 5;
```

##### 2.6. `(int) error_throttle`

The number of errors remaining (according to the `X-ESI-Error-Limit-Remain:`-header) at which to begin throttling out-going traffic. The default is `80`. The ESI error limit is a number, starting at `100`, which is returned by ESI for each request and represents a count-down after which an application is denied access (for a limited time). It is meant to control the traffic to the ESI server and to give each application a chance to back off in cases where there is a problem. The number is not necessarily an indication for an application error. A count-down can occur for various reasons. The error count is valid for a limited time after which it is reset to `100`. SimpleESI uses a non-linear function to implement a dynamic behaviour. When the reported error limit drops to or below the `->error_throttle` value will it pause briefly for a few milliseconds before sending out a new request. This pause will grow longer the closer the count-down gets to `0` and the larger the remaining time window is.

```php
$esi->error_throttle = 90;
```

##### 2.7. `(int) error_exit`

The number of errors remaining at which to raise an exception in the application. The default is `20`. If the exception is not caught will it exit the application. It is meant to prevent the count-down from reaching `0`, at which the ESI server will reject further requests. Setting this value below `0` will turn this behaviour off and offers a good chance for your application to be noticed at CCP.

```php
$esi->error_exit = -1;
```

------------------------------------------------------------------------

### 3. Class Methods

##### 3.1. `(object) __construct([(string) $name], [(string) $useragent])`

The constructor can take up to two arguments. The `$name`-argument can be used to name the database, and the second argument `$useragent` can be used to set the `X-User-Agent:`-header. The default database name is `'esi'`, which in case of the `sqlite3db`-trait names the database file `'esi.sq3'`, and in case of the `dirdb`-trait names the top-level directory `'esi.dir'`. Examples are:

```php
$esi = new SimpleESI;
$esi2 = new SimpleESI('esi2');
$myesi = new SimpleESI('myesi', 'My App 1.0');
```

##### 3.2. `(void) __destruct()`

The destructor prints a level 1 debug message to give information about the life span of the object. For example:

```
[SimpleESI] 000.009s 0b (1) Elapsed.
```

##### 3.3. `(object) get(<(mixed) &$variable>, <...(mixed) $arguments>)`

Alternative notation:

```php
(object) get(<(mixed) &$variable>,
             [(array) $values],
             <(string) $request>,
             [(array) $query],
             [(int) $expires],
             [(array) $authorization],
             [(callable) $callback])
```

The `get()`-method takes a variable number of arguments, and depending on the type of arguments will it queue one or more requests. The first argument, here named `$variable`, needs to be a variable and cannot be a value or an expression. A reference to the variable is stored along with any requests and used in assigning the response(s) to the variable during an execution by the `exec()`-method. When the second argument to the `get()`-method is a string then it is taken as the `$request`-string and a single request will be queued. For example:

```php
$esi->get($var, 'universe/types/1230/');
```

When instead of a string an array `$values` is being passed, then a third argument will be taken as the `$request`-pattern and the values of the array `$values` will be used to create multiple requests by substituting these each with the `~`-character in the `$request`-pattern. The responses are assigned as an array, where each value of the `$values`-array becomes the key to the corresponding response. For example:

```php
$esi->get($var, [123, 456, 789], 'universe/types/~/');
```

This is equivalent to:

```php
$esi->get($var[123], 'universe/types/123/')
    ->get($var[456], 'universe/types/456/')
    ->get($var[789], 'universe/types/789/');
```

When an associative array is being passed after the `$request`-string or -pattern, then it is used to form and append a query string to the request(s). The elements of the associative `$query`-array are URL-encoded according to RFC3986, and in the case of a pattern substitution is the query string be appended to the pattern and the values of the `$value`-array are URL-encoded before a substitution is being made. For example:

```php
$esi->get($var1, 'universe/types/1230/', ['language' => 'de']);
$esi->get($var2, ['fr', 'ru'], 'universe/types/1230/', ['language' => '~']);
$esi->get($var3, 'search/?categories=region&strict=1&', ['search' => 'The Forge']);
$esi->get($var4, ['Domain', 'Sinq Laison'], 'search/?categories=region&strict=1&', ['search' => '~']);
```

This is equivalent to:

```php
$esi->get($var1, 'universe/types/1230/?language=de');
$esi->get($var2['fr'], 'universe/types/1230/?language=fr')
    ->get($var2['ru'], 'universe/types/1230/?language=ru');
$esi->get($var3, 'search/?categories=region&strict=1&search=The%20Forge');
$esi->get($var4['Domain'], 'search/?categories=region&strict=1&search=Domain')
    ->get($var4['Sinq Laison'], 'search/?categories=region&strict=1&search=Sinq%20Laison');
```

When an integer `$expires` is being passed after the `$request`-string, or -pattern, or after the `$query`-array then it is used as a time in seconds by which to offset a resource's expiration time. A positive value extends an expiration time temporarily, while a negative value shortens it. The value only affects the cache lookup, but it does not alter the actual expiration time of a resource. For example:

```php
$esi->get($var1, 'universe/types/1230/', 60*60);
$esi->get($var2, [123, 456, 789], 'universe/types/~/', 60*60);
$esi->get($var3, ['fr', 'ru'], 'universe/types/1230/', ['language' => '~'], 60*60);
```

When the `$expires` argument is being followed by an array, here named `$authoriz``ation`, then this array is taken as a request's authorization data. Authorization data is usually obtained by the `auth()`-method. The array is expected to contain two keys, `'header'` and `'cid'`, which hold the authorization header to be used in the request, as well as the character id to be used in the cache lookup. For example:

```php
$esi->get($var1, 'universe/types/1230/', 0, $authorization);
$esi->get($var2, [123, 456, 789], 'universe/types/~/', 0, $authorization);
$esi->get($var3, ['fr', 'ru'], 'universe/types/1230/', ['language' => '~'], 60*60, $authorization);
```

When a last argument `$callback` is being passed, and after the `$request`-string or -pattern, which is neither of the type int or of the type array, then it is taken as the name of a function or a callable closure that is to be executed the moment a response is received. For example:

```php
$esi->get($var1, 'universe/types/1230/', 'callback1');
$esi->get($var2, [123, 456, 789], 'universe/types/~/', function($esi, $rq) { /* function body */ });
$esi->get($var3, ['fr', 'ru'], 'universe/types/1230/', ['language' => '~'], 60*60, $auth, 'callback2');
```

##### Callback Functions

Callback functions get two arguments. The first argument is the executing SimpleESI object and the second is an object containing a request's data:

```php
(void) function callback($esi, $rq) { /* function body */ };
```

<span style="font-style: normal">The `<span style="font-style: normal">`$esi``<span style="font-style: normal">-object can be used to queue further requests `<span style="font-style: normal">during an execution. T`<span style="font-style: normal">he `<span style="font-style: normal">`$rq``<span style="font-style: normal">-`<span style="font-style: normal">object` holds the<span style="font-style: normal"> information about a request and its response. `<span style="font-style: normal">When a request was queued with the `<span style="font-style: normal">`get()``<span style="font-style: normal">-method then`<span style="font-style: normal"> `<span style="font-style: normal">this object`<span style="font-style: normal"> `<span style="font-style: normal">has `<span style="font-style: normal">got `<span style="font-style: normal">the following `<span style="font-style: normal">member `<span style="font-style: normal">variable`<span style="font-style: normal">s`<span style="font-style: normal">:`

```php
[ 'rq' => (string),
  'ci' => (int),
  'ex' => (int),
  'lm' => (int),
  'vl' => (reference),
  'pn' => (int),
  'pi' => (int),
  'ah' => (string),
  'cb' => (callable),
  'rt' => (int) ]
```

`$rq->rq`<span style="font-style: normal"> is the request string including query arguments. `<span style="font-style: normal">I`<span style="font-style: normal">.e.: `<span style="font-style: normal">`'universe/types/1230/'``. `$rq->ci`<span style="font-style: normal"> is the cache id used in separating cache responses for different users. For unauthorized requests is this value `<span style="font-style: normal">`0``<span style="font-style: normal"> and for authorized request is `<span style="font-style: normal">it `<span style="font-style: normal">the character's id. ``$rq->ex`<span style="font-style: normal"> is the expiration time in seconds since 1970. ``$rq->lm`<span style="font-style: normal"> is the “Last Modified”-time in seconds since 1970. ``$rq->vl`<span style="font-style: normal"> is a reference to the variable `<span style="font-style: normal">that holds the response. In case of multiple responses and paged responses is it a reference to the array element itself. ``$rq->pn`<span style="font-style: normal"> is the total number of pages of segmented responses, or `<span style="font-style: normal">`null``<span style="font-style: normal"> when unsegmented. ``$rq->pi`<span style="font-style: normal"> is the page index of a response when it was `<span style="font-style: normal">requested`<span style="font-style: normal"> automatically, starting at offset `<span style="font-style: normal">`1``<span style="font-style: normal"> for page 2`<span style="font-style: normal">, and otherwise i`<span style="font-style: normal">s`<span style="font-style: normal"> i`<span style="font-style: normal">t`<span style="font-style: normal"> `<span style="font-style: normal">`null``<span style="font-style: normal">. ``$rq->ah`<span style="font-style: normal"> is the authorization header used in `<span style="font-style: normal">a`<span style="font-style: normal"> request, or `<span style="font-style: normal">`null``<span style="font-style: normal"> for unauthorized requests. i.e.: ``'Authorization: Bearer ...'`. `$rq->rt`<span style="font-style: normal"> is the number of retries made to receive the request. `<span style="font-style: normal">A c`<span style="font-style: normal">allback can `<span style="font-style: normal">either be the name of a function`<span style="font-style: normal">:`

```php
$Veldspar = null;

$esi->get($var, 'universe/types/1230/', 'callback')->exec();

function callback($esi, $rq) {
   global $Veldspar;
   $Veldspar = $rq->vl['description'];
   echo 'Last Modified: '.date('r', $rq->lm).PHP_EOL;
}
```

… or a nameless function, also known as a closure:

```php
$Veldspar = null;

$esi->get($var, 'universe/types/1230/', function($esi, $rq) use ($Veldspar) {
    $Veldspar = $rq->vl['description'];
    echo 'Last Modified: '.date('r', $rq->lm).PHP_EOL;
})->exec();
```

##### Paged Responses

##### 3.4. `(void) single_get(<(mixed) &$variable>, <(string) $request>, [(int) $expires], [(int) $charid], [(string) $authheader], [(callable) $callback])`

##### 3.5. `(void) pages_get(<(mixed) &$variable>, <(string) $request>, <(int) $startpage>, <(int) $endpage>, <(int) $expires>, [(int) $charid], [(string) $authheader], [(callable) $callback])`

##### 3.6. `(object) post(<(mixed) &$variable>, <(string) $request>, <(mixed) $data>, [(array) $authorization], [(callable) $callback])`

##### 3.7. `(object) exec()`

##### 3.8. `(bool) auth(<(array) &$authorization>, [(string) $code])`


