# JSONPath for PHP 8.3+

[![Build](https://img.shields.io/github/actions/workflow/status/SoftCreatR/JSONPath/.github/workflows/Test.yml?branch=main)](https://github.com/SoftCreatR/JSONPath/actions/workflows/Test.yml) [![Latest Release](https://img.shields.io/packagist/v/SoftCreatR/JSONPath?color=blue&label=Latest%20Release)](https://packagist.org/packages/softcreatr/jsonpath)
[![MIT licensed](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE) [![Plant Tree](https://img.shields.io/badge/dynamic/json?color=brightgreen&label=Plant%20Tree&query=%24.total&url=https%3A%2F%2Fpublic.ecologi.com%2Fusers%2Fsoftcreatr%2Ftrees)](https://ecologi.com/softcreatr?r=61212ab3fc69b8eb8a2014f4)
[![Codecov branch](https://img.shields.io/codecov/c/github/SoftCreatR/JSONPath)](https://codecov.io/gh/SoftCreatR/JSONPath)

This is a [JSONPath](https://www.rfc-editor.org/rfc/rfc9535) implementation for PHP that targets portable RFC semantics and the JSONPath comparison consensus while keeping the API small, cached, and `eval`-free.

## Highlights

- PHP 8.3+ with typed class constants, enums, readonly tokens, `#[Override]`, and no `eval`; CI covers PHP 8.3 through the PHP 8.6 nightly.
- Works with arrays, objects, and `ArrayAccess`/traversables in any combination.
- Supports child and descendant selectors, wildcards, quoted name unions, indexes, negative indexes, and array slices.
- Filters support existence tests (including descendant and nested filters), path-to-path/root comparisons, deep equality, and distinct RFC `Nothing` versus JSON `null` semantics.
- Malformed and non-portable legacy syntax is rejected instead of being interpreted as a different valid query.

## Installation

Requires PHP 8.3 or newer.

```bash
composer require softcreatr/jsonpath:"^2.0"
```

## Development

Useful commands:

```bash
composer exec phpunit
composer phpstan
composer cs
```

## JSONPath Examples

JSONPath                  | Result
--------------------------|-------------------------------------
`$.store.books[*].author` | the authors of all books in the store
`$..author`               | all authors
`$.store..price`          | the price of everything in the store.
`$..books[2]`             | the third book
`$..books[-1:]`           | the last book in order.
`$..books[0,1]`           | the first two books
`$..books['title','year']`| multiple keys in a union
`$..books[:2]`            | the first two books
`$..books[::2]`           | every second book starting from first one
`$..books[1:6:3]`         | every third book starting from 1 till 6
`$..books[?(@.isbn)]`     | filter all books with isbn number
`$..books[?(@.price<10)]` | filter all books cheaper than 10
`$..*`                    | all elements in the data (recursively extracted)


Expression syntax
---

Symbol                | Description
----------------------|-------------------------
`$`                   | The root object/element
`@`                   | The current object/element inside a filter
`.` or `[]`           | Child operator
`..`                  | Recursive descent
`*`                   | Wildcard. All child elements regardless their index.
`[,]`                 | Array indexes or quoted member names as a set
`[start:end:step]`    | Array slice operator borrowed from ES4/Python.
`?()`                 | Filters a node list by an existence or comparison expression

## PHP Usage

#### Using arrays

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$data = ['people' => [
    ['name' => 'Sascha'],
    ['name' => 'Bianca'],
    ['name' => 'Alexander'],
    ['name' => 'Maximilian'],
]];

print_r((new \Flow\JSONPath\JSONPath($data))->find('$.people.*.name')->getData());

/*
Array
(
    [0] => Sascha
    [1] => Bianca
    [2] => Alexander
    [3] => Maximilian
)
*/
```

#### Using objects

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$data = json_decode('{"name":"Sascha Greuel","birthdate":"1987-12-16","city":"Gladbeck","country":"Germany"}', false);

print_r((new \Flow\JSONPath\JSONPath($data))->find('$')->getData()[0]);

/*
stdClass Object
(
    [name] => Sascha Greuel
    [birthdate] => 1987-12-16
    [city] => Gladbeck
    [country] => Germany
)
*/
```

### Magic method access

The options flag `JSONPath::ALLOW_MAGIC` will instruct JSONPath when retrieving a value to first check if an object
has a magic `__get()` method and will call this method if available. This feature is *iffy* and
not very predictable as:

-  wildcard and recursive features will only look at public properties and can't smell which properties are magically accessible
-  there is no `property_exists` check for magic methods so an object with a magic `__get()` will always return `true` when checking
   if the property exists
-   any errors thrown or unpredictable behavior caused by fetching via `__get()` is your own problem to deal with

```php
<?php

use Flow\JSONPath\JSONPath;

$myObject = (new Foo())->get('bar');
$jsonPath = new JSONPath($myObject, JSONPath::ALLOW_MAGIC);
```

## Filter expressions

Filter evaluation is intentionally limited to portable, `eval`-free operations:

- Comparisons support `==`, `!=`, `<`, `<=`, `>`, and `>=`.
- Logical expressions support `!`, `&&`, `||`, and grouped subexpressions.
- Singular current/root queries can be compared; non-singular, descendant, and nested-filter queries can be used as existence tests.
- Missing nodes use RFC `Nothing` semantics and are not conflated with JSON `null`.

Examples:

```
[?(@.title == "A string")]      // equality
[?(@.price < 10)]               // numeric comparison
[?(@.key == @.other)]           // path-to-path comparison
[?(@.key == $.rootValue)]       // root reference
[?(@.isbn)]                     // singular existence test
[?(@..child)]                   // descendant existence test
[?(@.items[?(@.price > 10)])]   // nested-filter existence test
[?(@['weird-key']=="ok")]       // bracket-escaped member
```

Script expressions, synthetic `.length`, regular-expression/membership operators, array/object literals in comparisons, and the RFC function extensions (`length()`, `count()`, `match()`, `search()`, and `value()`) are not supported. Unsupported syntax raises `JSONPathException`.

A full list of comparison cases can be found in the [JSONPath Comparison Cheatsheet](https://cburgmer.github.io/json-path-comparison/).

## Similar projects

[FlowCommunications/JSONPath](https://github.com/FlowCommunications/JSONPath) is the predecessor of this library by Stephen Frank

Other / Similar implementations can be found in the [Wiki](https://github.com/SoftCreatR/JSONPath/wiki/Other-Implementations).

## Changelog

A list of changes can be found in the [CHANGELOG.md](CHANGELOG.md) file. 

## License 🌳

[MIT](LICENSE.md) © [1-2.dev](https://1-2.dev)

This package is Treeware. If you use it in production, then we ask that you [**buy the world a tree**](https://ecologi.com/softcreatr?r=61212ab3fc69b8eb8a2014f4) to thank us for our work. By contributing to the ecologi project, you’ll be creating employment for local families and restoring wildlife habitats.

## Contributors ✨

<table>
<tr>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/SoftCreatR>
            <img src=https://avatars.githubusercontent.com/u/81188?v=4 width="100;"  alt=Sascha Greuel/>
            <br />
            <sub style="font-size:14px"><b>Sascha Greuel</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/lucasnetau>
            <img src=https://avatars.githubusercontent.com/u/9331242?v=4 width="100;"  alt=James Lucas/>
            <br />
            <sub style="font-size:14px"><b>James Lucas</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/Schrank>
            <img src=https://avatars.githubusercontent.com/u/379680?v=4 width="100;"  alt=Fabian Blechschmidt/>
            <br />
            <sub style="font-size:14px"><b>Fabian Blechschmidt</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/mpesari>
            <img src=https://avatars.githubusercontent.com/u/11061725?v=4 width="100;"  alt=Mikko Pesari/>
            <br />
            <sub style="font-size:14px"><b>Mikko Pesari</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/warlof>
            <img src=https://avatars.githubusercontent.com/u/648753?v=4 width="100;"  alt=warlof/>
            <br />
            <sub style="font-size:14px"><b>warlof</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/SG5>
            <img src=https://avatars.githubusercontent.com/u/3931761?v=4 width="100;"  alt=Sergey G/>
            <br />
            <sub style="font-size:14px"><b>Sergey G</b></sub>
        </a>
    </td>
</tr>
<tr>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/drealecs>
            <img src=https://avatars.githubusercontent.com/u/209984?v=4 width="100;"  alt=Alexandru Pătrănescu/>
            <br />
            <sub style="font-size:14px"><b>Alexandru Pătrănescu</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/oleg-andreyev>
            <img src=https://avatars.githubusercontent.com/u/1244112?v=4 width="100;"  alt=Oleg Andreyev/>
            <br />
            <sub style="font-size:14px"><b>Oleg Andreyev</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/rcjsuen>
            <img src=https://avatars.githubusercontent.com/u/15629116?v=4 width="100;"  alt=Remy Suen/>
            <br />
            <sub style="font-size:14px"><b>Remy Suen</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href=https://github.com/esomething>
            <img src=https://avatars.githubusercontent.com/u/64032?v=4 width="100;"  alt=esomething/>
            <br />
            <sub style="font-size:14px"><b>esomething</b></sub>
        </a>
    </td>
</tr>
</table>
