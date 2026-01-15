# Changelog

### 1.0.1
- Aligned the query runner and lexer with the JSONPath comparison suite: JSON documents are now decoded as objects to preserve `{}` vs `[]`, unsupported selectors no longer abort the runner, and dot-notation now accepts quoted keys with dots/spaces/leading `@`.
- Hardened filter parsing: boolean-only filters (`?(true|false|null)`), literal short-circuiting (`&& false`, `|| true`), and empty filters now return the expected collections instead of throwing.
- Slice filters gracefully skip non-countable objects.

### 1.0.0
- Rebuilt the test suite from scratch: removed bulky baseline fixtures and added compact unit/integration coverage for every filter (index, union, query, recursive, slice), lexer edge cases, and JSONPath core helpers. Runs reflection-free and deprecation-free.
- Achieved and enforced 100% code coverage across AccessHelper, all filters, lexer, tokens, and JSONPath core while keeping phpstan and coding standards clean.
- Added a lightweight manual query runner with curated examples to exercise selectors quickly without external datasets.
- Major compatibility push toward the unofficial JSONPath standard: unions support slices/queries/wildcards, trailing commas parse correctly, negative indexes and bracket-escaped keys (quotes, brackets, wildcards, special chars) are honored, filters compare path-to-path and root references, equality/deep-equality/regex/in/nin semantics align with expectations, and null existence/value handling follows RFC behavior.
- New feature highlights from this cycle:
  - Multi-key unions with and without quotes: `$[name,year]` and `$["name","year"]`.
  - Robust bracket notation for special/escaped keys, including `']'`, `'*'`, `$`, backslashes, and mixed punctuation.
  - Trailing comma support in unions/slices (e.g. `$..books[0,1,2,]`).
  - Negative index handling aligned with spec (short arrays return empty; -1 works where valid).
  - Filter improvements: path-to-path/root comparisons, deep equality across scalars/objects/arrays/null/empties, regex matching, `in`/`nin`/`!in`, tautological expressions, and `?@` existence behavior per RFC.
  - Unions combining slices/queries/wildcards now return complete results (e.g. `$[1:3,4]`, `$[*,1]`).

### 0.11.0
🔻 Breaking changes ahead:

- Dropped support for PHP < 8.5
- `JSONPathToken` now uses a `TokenType` enum and the constructor signature changed accordingly.
- `JSONPath` options flag is now an `int` bitmask (was `bool`), requiring callers to pass integer flags.
- `SliceFilter` returns an empty result for non-positive step values (previously iterated indefinitely).
- `QueryResultFilter` now throws a `JSONPathException` for unsupported operators instead of silently proceeding.
- Access helper behavior is stricter: `arrayValues` throws on invalid types; ArrayAccess lookups check `offsetExists` before reading; traversables and objects are handled distinctly.
- Adopted PHP 8.5 features: `TokenType` enum, readonly value object for tokens, typed flags/options, and `#[\Override]` usage.
- CI now runs on PHP 8.5 with required extensions; code style workflow updated accordingly.
- Added coverage for AccessHelper edge cases (magic getters, ArrayAccess, traversables, negative indexes), QueryResultFilter arithmetic branches, and SliceFilter negative/null bounds.
- Fixed empty-expression handling in lexer and improved safety in AccessHelper traversable lookups.
- Added PHPStan static analysis to the toolchain and addressed its findings.

### 0.10.1
- Fixed ignore whitespace after comparison value in filter expression

### 0.10.0
- Fixed query/selector Filter Expression With Current Object
- Fixed query/selector Filter Expression With Different Grouped Operators
- Fixed query/selector Filter Expression With equals_on_array_of_numbers
- Fixed query/selector Filter Expression With Negation and Equals
- Fixed query/selector Filter Expression With Negation and Less Than
- Fixed query/selector Filter Expression Without Value
- Fixed query/selector Filter Expression With Boolean AND Operator (#42)
- Fixed query/selector Filter Expression With Boolean OR Operator (#43)
- Fixed query/selector Filter Expression With Equals (#45)
- Fixed query/selector Filter Expression With Equals false (#46)
- Fixed query/selector Filter Expression With Equals null (#47)
- Fixed query/selector Filter Expression With Equals Number With Fraction (#48)
- Fixed query/selector Filter Expression With Equals true (#50)
- Fixed query/selector Filter Expression With Greater Than (#52)
- Fixed query/selector Filter Expression With Greater Than or Equal (#53)
- Fixed query/selector Filter Expression With Less Than (#54)
- Fixed query/selector Filter Expression With Less Than or Equal (#55)
- Fixed query/selector Filter Expression With Not Equals (#56)
- Fixed query/selector Filter Expression With Value (#57)
- Fixed query/selector script_expression (Expected test result corrected)
- Added additional NULL related query tests from JSONPath RFC

### 0.9.0
🔻 Breaking changes ahead:

- Dropped support for PHP < 8.1

### 0.8.3
- Change `getData()` so that it can be mixed instead of array

### 0.8.2
- AccessHelper & RecursiveFilter now return a plain `object`, rather than an `ArrayAccess` object

### 0.8.1
- Removed strict_types
- Applied some PSR-12 related changes
- Small code optimizations

### 0.8.0
🔻 Breaking changes ahead:

 - Dropped support for PHP < 8.0
 - Removed deprecated method `JSONPath->data()`

### 0.7.5
 - Added support for $.length
 - Added trim to explode to support both 1,2,3 and 1, 2, 3 inputs
 - Dropped in_array strict equality check to be in line with the other standard equality checks such as (== and !=)

### 0.7.4
 - Removed PHPUnit from conflicting packages

### 0.7.3
 - Fixed PHP 7.4+ compatibility issues

### 0.7.2
 - Fixed query/selector "Array Slice With Start Large Negative Number And Open End On Short Array" (#7)
 - Fixed query/selector "Union With Keys" (#22)
 - Fixed query/selector "Dot Notation After Union With Keys" (#15)
 - Fixed query/selector "Union With Keys After Array Slice" (#23)
 - Fixed query/selector "Union With Keys After Bracket Notation" (#24)
 - Fixed query/selector "Union With Keys On Object Without Key" (#25)

### 0.7.1
 - Fixed issues with empty tokens (`['']` and `[""]`)
 - Fixed TypeError in AccessHelper::keyExists 
 - Improved QueryTest

### 0.7.0
🔻 Breaking changes ahead:

 - Made JSONPath::__construct final
 - Added missing type hints
 - Partially reduced complexity
 - Performed some code optimizations
 - Updated composer.json for proper PHPUnit/PHP usage
 - Added support for regular expression operator (`=~`)
 - Added QueryTest to perform tests against all queries from https://cburgmer.github.io/json-path-comparison/
 - Switched Code Style from PSR-2 to PSR-12

### 0.6.4
 - Removed unnecessary type casting, that caused problems under certain circumstances
 - Added support for `nin` operator
 - Added support for greater than or equal operator (`>=`)
 - Added support for less or equal operator (`<=`)

### 0.6.3
 - Added support for `in` operator
 - Fixed evaluation on indexed object

### 0.6.x
 - Dropped support for PHP < 7.1
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
