JSONPath for PHP 5.6 ![Test](https://github.com/SoftCreatR/JSONPath/workflows/Test/badge.svg?branch=php-56) 
=============

This is a [JSONPath](http://goessner.net/articles/JsonPath/) implementation for PHP based on Stefan Goessner's JSONPath script.

JSONPath is an XPath-like expression language for filtering, flattening and extracting data.

This project aims to be a clean and simple implementation with the following goals:

 - Object-oriented code (should be easier to manage or extend in future)
 - Expressions are parsed into tokens using code inspired by the Doctrine Lexer. The tokens are cached internally to avoid re-parsing the expressions.
 - There is no `eval()` in use
 - Any combination of objects/arrays/ArrayAccess-objects can be used as the data input which is great if you're de-serializing JSON in to objects or if you want to process your own data structures.
 
🔻 **Please note, that this branch (based on JSONPath 0.6.2) is protected. There are no intentions to make any updates here. Please consider upgrading to PHP 7.2 or newer.**

Installation
---

1. Add to the `require`-section of your composer.json: `"softcreatr/jsonpath": "dev-php-56"`
2. Execute `composer install`

JSONPath Examples
---

JSONPath                  | Result
--------------------------|-------------------------------------
`$.store.books[*].author` | the authors of all books in the store
`$..author`                | all authors
`$.store..price`           | the price of everything in the store.
`$..books[2]`              | the third book
`$..books[(@.length-1)]`   | the last book in order.
`$..books[-1:]`            | the last book in order.
`$..books[0,1]`            | the first two books
`$..books[:2]`             | the first two books
`$..books[::2]`            | every second book starting from first one
`$..books[1:6:3]`          | every third book starting from 1 till 6
`$..books[?(@.isbn)]`      | filter all books with isbn number
`$..books[?(@.price<10)]`  | filter all books cheapier than 10
`$..*`                     | all elements in the data (recursively extracted)


Expression syntax
---

Symbol                | Description
----------------------|-------------------------
`$`                   | The root object/element (not strictly necessary)
`@`                   | The current object/element
`.` or `[]`           | Child operator
`..`                  | Recursive descent
`*`                   | Wildcard. All child elements regardless their index.
`[,]`                 | Array indices as a set
`[start:end:step]`    | Array slice operator borrowed from ES4/Python.
`?()`                 | Filters a result set by a script expression
`()`                  | Uses the result of a script expression as the index

PHP Usage
---

```php
$data = ['people' => [['name' => 'Joe'], ['name' => 'Jane'], ['name' => 'John']]];
$result = (new JSONPath($data))->find('$.people.*.name'); // returns new JSONPath
// $result[0] === 'Joe'
// $result[1] === 'Jane'
// $result[2] === 'John'
```

### Magic method access

The options flag `JSONPath::ALLOW_MAGIC` will instruct JSONPath when retrieving a value to first check if an object
has a magic `__get()` method and will call this method if available. This feature is *iffy* and
not very predictable as:

-  wildcard and recursive features will only look at public properties and can't smell which properties are magically accessible
-  there is no `property_exists` check for magic methods so an object with a magic `__get()` will always return `true` when checking
   if the property exists
-   any errors thrown or unpredictable behaviour caused by fetching via `__get()` is your own problem to deal with

```php
$jsonPath = new JSONPath($myObject, JSONPath::ALLOW_MAGIC);
```

For more examples, check the JSONPathTest.php tests file.

Script expressions
-------

Script expressions are not supported as the original author intended because:

-   This would only be achievable through `eval` (boo).
-   Using the script engine from different languages defeats the purpose of having a single expression evaluate the same way in different
    languages which seems like a bit of a flaw if you're creating an abstract expression syntax.

So here are the types of query expressions that are supported:

	[?(@._KEY_ _OPERATOR_ _VALUE_)] // <, >, !=, and ==
	Eg.
	[?(@.title == "A string")] //
	[?(@.title = "A string")]
	// A single equals is not an assignment but the SQL-style of '=='
	
Known issues
------

- This project has not implemented multiple string indexes e.g. `$[name,year]` or `$["name","year"]`. I have no ETA on that feature, and it would require some re-writing of the parser that uses a very basic regex implementation.

Similar projects
----------------

[FlowCommunications/JSONPath](https://github.com/FlowCommunications/JSONPath) is the predecessor of this library by Stephen Frank

[Galbar/JsonPath-PHP](https://github.com/Galbar/JsonPath-PHP) is a PHP implementation that does a few things this project doesn't and is a strong alternative

[JMESPath](https://github.com/jmespath) does similiar things, is full of features and has a PHP implementation

The [Hash](http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html) utility from CakePHP does some similar things 

The original JsonPath implementations is available at [http://code.google.com/p/jsonpath]() and re-hosted for composer here [Peekmo/JsonPath](https://github.com/Peekmo/JsonPath).

[ObjectPath](http://objectpath.org) ([https://github.com/adriank/ObjectPath]()) appears to be a Python/JS implementation with a new name and extra features.

Changelog
---------

### 0.6.x
 - Dropped support for PHP < 7.2
 - Switched from (broken) PSR-0 to PSR-4
 - Updated PHPUnit to 8.5 / 9.4
 - Updated tests
 - Added missing PHPDoc blocks
 - Added return type hints
 - Moved from Travis to GitHub actions
 - Set `strict_types=1`

### 0.5.0
 - Fixed the slice notation (e.g. [0:2:5] etc.). **Breaks code relying on the broken implementation**

### 0.3.0
 - Added JSONPathToken class as value object
 - Lexer clean up and refactor
 - Updated the lexing and filtering of the recursive token ("..") to allow for a combination of recursion
   and filters, e.g. $..[?(@.type == 'suburb')].name

### 0.2.1 - 0.2.5
 - Various bug fixes and clean up

### 0.2.0
 - Added a heap of array access features for more creative iterating and chaining possibilities

### 0.1.x
 - Init

License
---------

[MIT](LICENSE)
