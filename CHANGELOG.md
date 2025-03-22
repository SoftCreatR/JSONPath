# Changelog

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
