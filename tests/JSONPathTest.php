<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */
declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use Flow\JSONPath\JSONPath;
use function is_object;
use function json_decode;
use function json_encode;
use function random_int;

class JSONPathTest extends TestCase
{
    /**
     * $.store.books[0].title
     *
     * @throws Exception
     */
    public function testChildOperators(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.books[0].title');

        self::assertEquals('Sayings of the Century', $result[0]);
    }

    /**
     * @throws Exception
     */
    public function testIndexesObject(): void
    {
        $result = (new JSONPath($this->exampleIndexedObject(random_int(0, 1))))->find('$.store.books[3].title');

        self::assertEquals('Sword of Honour', $result[0]);
    }

    /**
     * $['store']['books'][0]['title']
     *
     * @throws Exception
     */
    public function testChildOperatorsAlt(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find("$['store']['books'][0]['title']");

        self::assertEquals('Sayings of the Century', $result[0]);
    }

    /**
     * $.array[start:end:step]
     *
     * @throws Exception
     */
    public function testFilterSliceA(): void
    {
        // Copy all items... similar to a wildcard
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find("$['store']['books'][:].title");

        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick', 'The Lord of the Rings'], $result->getData());
    }

    /**
     * Positive end indexes
     * $[0:2]
     *
     * @throws Exception
     */
    public function testFilterSlice_PositiveEndIndexes(): void
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[0:0]');
        self::assertEquals([], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[0:1]');
        self::assertEquals(['first'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[0:2]');
        self::assertEquals(['first', 'second'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[:2]');
        self::assertEquals(['first', 'second'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[1:2]');
        self::assertEquals(['second'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[0:3:1]');
        self::assertEquals(['first', 'second', 'third'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[0:3:0]');
        self::assertEquals(['first', 'second', 'third'], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testFilterSlice_NegativeStartIndexes(): void
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[-2:]');
        self::assertEquals(['fourth', 'fifth'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[-1:]');
        self::assertEquals(['fifth'], $result->getData());
    }

    /**
     * Negative end indexes
     * $[:-2]
     *
     * @throws Exception
     */
    public function testFilterSlice_NegativeEndIndexes(): void
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[:-2]');
        self::assertEquals(['first', 'second', 'third'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[0:-2]');
        self::assertEquals(['first', 'second', 'third'], $result->getData());
    }

    /**
     * Negative end indexes
     * $[:-2]
     *
     * @throws Exception
     */
    public function testFilterSlice_NegativeStartAndEndIndexes(): void
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[-2:-1]');
        self::assertEquals(['fourth'], $result->getData());

        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[-4:-2]');
        self::assertEquals(['second', 'third'], $result->getData());
    }

    /**
     * Negative end indexes
     * $[:-2]
     *
     * @throws Exception
     */
    public function testFilterSlice_NegativeStartAndPositiveEnd(): void
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[-2:2]');

        self::assertEquals([], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testFilterSlice_StepBy2(): void
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[0:4:2]');

        self::assertEquals(['first', 'third'], $result->getData());
    }

    /**
     * The Last item
     * $[-1]
     *
     * @throws Exception
     */
    public function testFilterLastIndex(): void
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[-1]');

        self::assertEquals(['fifth'], $result->getData());
    }

    /**
     * Array index slice only end
     * $[:2]
     *
     * @throws Exception
     */
    public function testFilterSliceG(): void
    {
        // Fetch up to the second index
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[:2]');

        self::assertEquals(['first', 'second'], $result->getData());
    }

    /**
     * $.store.books[(@.length-1)].title
     *
     * This notation is only partially implemented eg. hacked in
     *
     * @throws Exception
     */
    public function testChildQuery(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.books[(@.length-1)].title');

        self::assertEquals(['The Lord of the Rings'], $result->getData());
    }

    /**
     * $.store.books[?(@.price < 10)].title
     * Filter books that have a price less than 10
     *
     * @throws Exception
     */
    public function testQueryMatchLessThan(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.books[?(@.price < 10)].title');

        self::assertEquals(['Sayings of the Century', 'Moby Dick'], $result->getData());
    }

    /**
     * $.store.books[?(@.price > 10)].title
     * Filter books that have a price more than 10
     *
     * @throws Exception
     */
    public function testQueryMatchMoreThan(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.books[?(@.price > 10)].title');

        self::assertEquals(['Sword of Honour', 'The Lord of the Rings'], $result->getData());
    }

    /**
     * $.store.books[?(@.price <= 12.99)].title
     * Filter books that have a price less or equal to 12.99
     *
     * @throws Exception
     */
    public function testQueryMatchLessOrEqual(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.books[?(@.price <= 12.99)].title');

        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick'], $result->getData());
    }

    /**
     * $.store.books[?(@.price >= 12.99)].title
     * Filter books that have a price less or equal to 12.99
     *
     * @throws Exception
     */
    public function testQueryMatchEqualOrMore(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.books[?(@.price >= 12.99)].title');

        self::assertEquals(['Sword of Honour', 'The Lord of the Rings'], $result->getData());
    }

    /**
     * $..books[?(@.author == "J. R. R. Tolkien")]
     * Filter books that have a title equal to "..."
     *
     * @throws Exception
     */
    public function testQueryMatchEquals(): void
    {
        $results = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.author == "J. R. R. Tolkien")].title');

        self::assertEquals('The Lord of the Rings', $results[0]);
    }

    /**
     * $..books[?(@.author = 1)]
     * Filter books that have a title equal to "..."
     *
     * @throws Exception
     */
    public function testQueryMatchEqualsWithUnquotedInteger(): void
    {
        $results = (new JSONPath($this->exampleDataWithSimpleIntegers(random_int(0, 1))))->find('$..features[?(@.value = 1)]');

        self::assertEquals('foo', $results[0]->name);
        self::assertEquals('baz', $results[1]->name);
    }

    /**
     * $..books[?(@.author != "J. R. R. Tolkien")]
     * Filter books that have a title not equal to "..."
     *
     * @throws Exception
     */
    public function testQueryMatchNotEqualsTo(): void
    {
        $results = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.author != "J. R. R. Tolkien")].title');
        self::assertcount(3, $results);
        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick'], [$results[0], $results[1], $results[2]]);

        $results = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.author !== "J. R. R. Tolkien")].title');
        self::assertcount(3, $results);
        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick'], [$results[0], $results[1], $results[2]]);

        $results = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.author <> "J. R. R. Tolkien")].title');
        self::assertcount(3, $results);
        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick'], [$results[0], $results[1], $results[2]]);
    }

    /**
     * $..books[?(@.author in ["J. R. R. Tolkien", "Nigel Rees"])]
     * Filter books that have a title in ["...", "..."]
     *
     * @throws Exception
     */
    public function testQueryMatchIn(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.author in ["J. R. R. Tolkien", "Nigel Rees"])].title');

        self::assertEquals(['Sayings of the Century', 'The Lord of the Rings'], $result->getData());
    }

    /**
     * $..books[?(@.author nin ["J. R. R. Tolkien", "Nigel Rees"])]
     * Filter books that don't have a title in ["...", "..."]
     *
     * @throws Exception
     */
    public function testQueryMatchNin(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.author nin ["J. R. R. Tolkien", "Nigel Rees"])].title');

        self::assertEquals(['Sword of Honour', 'Moby Dick'], $result->getData());
    }

    /**
     * $..books[?(@.author nin ["J. R. R. Tolkien", "Nigel Rees"])]
     * Filter books that don't have a title in ["...", "..."]
     *
     * @throws Exception
     */
    public function testQueryMatchNotIn(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.author !in ["J. R. R. Tolkien", "Nigel Rees"])].title');

        self::assertEquals(['Sword of Honour', 'Moby Dick'], $result->getData());
    }

    /**
     * $.store.books[*].author
     *
     * @throws Exception
     */
    public function testWildcardAltNotation(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.books[*].author');

        self::assertEquals(['Nigel Rees', 'Evelyn Waugh', 'Herman Melville', 'J. R. R. Tolkien'], $result->getData());
    }

    /**
     * $..author
     *
     * @throws Exception
     */
    public function testRecursiveChildSearch(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..author');

        self::assertEquals(['Nigel Rees', 'Evelyn Waugh', 'Herman Melville', 'J. R. R. Tolkien'], $result->getData());
    }

    /**
     * $.store.*
     * all things in store
     * the structure of the example data makes this test look weird
     *
     * @throws Exception
     */
    public function testWildCard(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store.*');
        if (is_object($result[0][0])) {
            self::assertEquals('Sayings of the Century', $result[0][0]->title);
        } else {
            self::assertEquals('Sayings of the Century', $result[0][0]['title']);
        }

        if (is_object($result[1])) {
            self::assertEquals('red', $result[1]->color);
        } else {
            self::assertEquals('red', $result[1]['color']);
        }
    }

    /**
     * $.store..price
     * the price of everything in the store.
     *
     * @throws Exception
     */
    public function testRecursiveChildSearchAlt(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$.store..price');

        self::assertEquals([8.95, 12.99, 8.99, 22.99, 19.95], $result->getData());
    }

    /**
     * $..books[2]
     * the third book
     *
     * @throws Exception
     */
    public function testRecursiveChildSearchWithChildIndex(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[2].title');

        self::assertEquals(['Moby Dick'], $result->getData());
    }

    /**
     * $..books[(@.length-1)]
     *
     * @throws Exception
     */
    public function testRecursiveChildSearchWithChildQuery(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[(@.length-1)].title');

        self::assertEquals(['The Lord of the Rings'], $result->getData());
    }

    /**
     * $..books[-1:]
     * Return the last results
     *
     * @throws Exception
     */
    public function testRecursiveChildSearchWithSliceFilter(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[-1:].title');

        self::assertEquals(['The Lord of the Rings'], $result->getData());
    }

    /**
     * $..books[?(@.isbn)]
     * filter all books with isbn number
     *
     * @throws Exception
     */
    public function testRecursiveWithQueryMatch(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..books[?(@.isbn)].isbn');

        self::assertEquals(['0-553-21311-3', '0-395-19395-8'], $result->getData());
    }

    /**
     * .data.tokens[?(@.Employee.FirstName)]
     * Verify that it is possible to filter with a key containing punctuation
     *
     * @throws Exception
     */
    public function testRecursiveWithQueryMatchWithDots(): void
    {
        $result = (new JSONPath($this->exampleDataWithDots(random_int(0, 1))))->find(".data.tokens[?(@.Employee.FirstName)]");
        $result = json_decode(json_encode($result), true);

        self::assertEquals([['Employee.FirstName' => 'Jack']], $result);
    }

    /**
     * $..*
     * All members of JSON structure
     *
     * @throws Exception
     */
    public function testRecursiveWithWildcard(): void
    {
        $result = (new JSONPath($this->exampleData(random_int(0, 1))))->find('$..*');
        $result = json_decode(json_encode($result), true);

        self::assertEquals('Sayings of the Century', $result[0]['books'][0]['title']);
        self::assertEquals(19.95, $result[26]);
    }

    /**
     * Tests direct key access.
     *
     * @throws Exception
     */
    public function testSimpleArrayAccess(): void
    {
        $result = (new JSONPath(['title' => 'test title']))->find('title');

        self::assertEquals(['test title'], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testFilteringOnNoneArrays(): void
    {
        $data = ['foo' => 'asdf'];
        $result = (new JSONPath($data))->find('$.foo.bar');

        self::assertEquals([], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testMagicMethods(): void
    {
        $fooClass = new JSONPathTestClass();
        $results = (new JSONPath($fooClass, JSONPath::ALLOW_MAGIC))->find('$.foo');

        self::assertEquals(['bar'], $results->getData());
    }

    /**
     * @throws Exception
     */
    public function testMatchWithComplexSquareBrackets(): void
    {
        $result = (new JSONPath($this->exampleDataExtra()))->find(
            "$['http://www.w3.org/2000/01/rdf-schema#label'][?(@['@language']='en')]['@language']"
        );

        self::assertEquals(["en"], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testQueryMatchWithRecursive(): void
    {
        $locations = $this->exampleDataLocations();
        $result = (new JSONPath($locations))->find("..[?(@.type == 'suburb')].name");

        self::assertEquals(["Rosebank"], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testFirst(): void
    {
        $result = (new JSONPath($this->exampleDataExtra()))->find("$['http://www.w3.org/2000/01/rdf-schema#label'].*");

        self::assertEquals(["@language" => "en"], $result->first()->getData());
    }

    /**
     * @throws Exception
     */
    public function testLast(): void
    {
        $result = (new JSONPath($this->exampleDataExtra()))->find("$['http://www.w3.org/2000/01/rdf-schema#label'].*");

        self::assertEquals(["@language" => "de"], $result->last()->getData());
    }

    /**
     * @throws Exception
     */
    public function testSlashesInIndex(): void
    {
        $result = (new JSONPath($this->exampleDataWithSlashes()))->find("$['mediatypes']['image/png']");

        self::assertEquals(["/core/img/filetypes/image.png"], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testCyrillicText(): void
    {
        $result = (new JSONPath(["трололо" => 1]))->find("$['трололо']");
        self::assertEquals([1], $result->getData());

        $result = (new JSONPath(["трололо" => 1]))->find("$.трололо");
        self::assertEquals([1], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testOffsetUnset(): void
    {
        $data = [
            "route" => [
                ["name" => "A", "type" => "type of A"],
                ["name" => "B", "type" => "type of B"],
            ],
        ];
        $data = json_encode($data);
        $jsonIterator = new JSONPath(json_decode($data, random_int(0, 1) === 1));

        /** @var JSONPath $route */
        $route = $jsonIterator->offsetGet('route');
        $route->offsetUnset(0);
        $first = $route->first();

        self::assertEquals("B", $first['name']);
    }

    public function testFirstKey(): void
    {
        // Array test for array
        $jsonPath = new JSONPath(['a' => 'A', 'b', 'B']);
        $firstKey = $jsonPath->firstKey();

        self::assertEquals('a', $firstKey);

        // Array test for object
        $jsonPath = new JSONPath((object)['a' => 'A', 'b', 'B']);
        $firstKey = $jsonPath->firstKey();

        self::assertEquals('a', $firstKey);
    }

    public function testLastKey(): void
    {
        // Array test for array
        $jsonPath = new JSONPath(['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $lastKey = $jsonPath->lastKey();

        self::assertEquals('c', $lastKey);

        // Array test for object
        $jsonPath = new JSONPath((object)['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $lastKey = $jsonPath->lastKey();

        self::assertEquals('c', $lastKey);
    }

    /**
     * Test: ensure trailing comma is stripped during parsing
     *
     * @throws Exception
     */
    public function testTrailingComma(): void
    {
        $jsonPath = new JSONPath(json_decode('{
           "store":{
              "book":[
                 {
                    "category":"reference",
                    "author":"Nigel Rees",
                    "title":"Sayings of the Century",
                    "price":8.95
                 },
                 {
                    "category":"fiction",
                    "author":"Evelyn Waugh",
                    "title":"Sword of Honour",
                    "price":12.99
                 },
                 {
                    "category":"fiction",
                    "author":"Herman Melville",
                    "title":"Moby Dick",
                    "isbn":"0-553-21311-3",
                    "price":8.99
                 },
                 {
                    "category":"fiction",
                    "author":"J. R. R. Tolkien",
                    "title":"The Lord of the Rings",
                    "isbn":"0-395-19395-8",
                    "price":22.99
                 }
              ],
              "bicycle":{
                 "color":"red",
                 "price":19.95
              }
           },
           "expensive":10
        }', random_int(0, 1) === 1));
        $result = $jsonPath->find("$..book[0,1,2,]");

        self::assertCount(3, $result);
    }

    /**
     * Test: ensure negative indexes return -n from last index
     *
     * @throws Exception
     */
    public function testNegativeIndex(): void
    {
        $jsonPath = new JSONPath(json_decode('{
           "store":{
              "book":[
                 {
                    "category":"reference",
                    "author":"Nigel Rees",
                    "title":"Sayings of the Century",
                    "price":8.95
                 },
                 {
                    "category":"fiction",
                    "author":"Evelyn Waugh",
                    "title":"Sword of Honour",
                    "price":12.99
                 },
                 {
                    "category":"fiction",
                    "author":"Herman Melville",
                    "title":"Moby Dick",
                    "isbn":"0-553-21311-3",
                    "price":8.99
                 },
                 {
                    "category":"fiction",
                    "author":"J. R. R. Tolkien",
                    "title":"The Lord of the Rings",
                    "isbn":"0-395-19395-8",
                    "price":22.99
                 }
              ],
              "bicycle":{
                 "color":"red",
                 "price":19.95
              }
           },
           "expensive":10
        }', random_int(0, 1) === 1));
        $result = $jsonPath->find("$..book[-2]");

        self::assertEquals("Herman Melville", $result[0]['author']);
    }

    /**
     * @throws Exception
     */
    public function testQueryAccessWithNumericalIndexes(): void
    {
        $jsonPath = new JSONPath(json_decode('{
           "result":{
              "list":[
                 {
                    "time":1477526400,
                    "o":"11.51000"
                 },
                 {
                    "time":1477612800,
                    "o":"11.49870"
                 }
              ]
           }
        }', random_int(0, 1) === 1));
        $result = $jsonPath->find("$.result.list[?(@.o == \"11.51000\")]");

        self::assertEquals("11.51000", $result[0]->o);

        $jsonPath = new JSONPath(json_decode('{
           "result":{
              "list":[
                 [
                    1477526400,
                    "11.51000"
                 ],
                 [
                    1477612800,
                    "11.49870"
                 ]
              ]
           }
        }', random_int(0, 1) === 1));
        $result = $jsonPath->find("$.result.list[?(@[1] == \"11.51000\")]");

        self::assertEquals("11.51000", $result[0][1]);
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleData(int $asArray = 1)
    {
        $json = '{
           "store":{
              "books":[
                 {
                    "category":"reference",
                    "author":"Nigel Rees",
                    "title":"Sayings of the Century",
                    "price":8.95
                 },
                 {
                    "category":"fiction",
                    "author":"Evelyn Waugh",
                    "title":"Sword of Honour",
                    "price":12.99
                 },
                 {
                    "category":"fiction",
                    "author":"Herman Melville",
                    "title":"Moby Dick",
                    "isbn":"0-553-21311-3",
                    "price":8.99
                 },
                 {
                    "category":"fiction",
                    "author":"J. R. R. Tolkien",
                    "title":"The Lord of the Rings",
                    "isbn":"0-395-19395-8",
                    "price":22.99
                 }
              ],
              "bicycle":{
                 "color":"red",
                 "price":19.95
              }
           }
        }';

        return json_decode($json, $asArray === 1);
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleDataExtra(int $asArray = 1)
    {
        $json = '{
           "http://www.w3.org/2000/01/rdf-schema#label":[
              {
                 "@language":"en"
              },
              {
                 "@language":"de"
              }
           ]
        }';

        return json_decode($json, $asArray === 1);
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleDataLocations(int $asArray = 1)
    {
        $json = '{
           "name":"Gauteng",
           "type":"province",
           "child":{
              "name":"Johannesburg",
              "type":"city",
              "child":{
                 "name":"Rosebank",
                 "type":"suburb"
              }
           }
        }';

        return json_decode($json, $asArray === 1);
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleDataWithSlashes(int $asArray = 1)
    {
        $json = '{
           "features":[
              
           ],
           "mediatypes":{
              "image/png":"/core/img/filetypes/image.png",
              "image/jpeg":"/core/img/filetypes/image.png",
              "image/gif":"/core/img/filetypes/image.png",
              "application/postscript":"/core/img/filetypes/image-vector.png"
           }
        }';

        return json_decode($json, $asArray === 1);
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleDataWithDots(int $asArray = 1)
    {
        $json = '
            {
                "data": {
                    "tokens": [
                        {
                          "Employee.FirstName": "Jack"
                        },
                        {
                          "Employee.LastName": "Daniels"
                        },
                        {
                          "Employee.Email": "jd@example.com"
                        }
                    ]
                }
            }
        ';

        return json_decode($json, $asArray === 1);
    }

    public function exampleIndexedObject(int $asArray = 1)
    {
        $json = '
        {
           "store":{
              "books":{
                 "4": {
                    "category":"reference",
                    "author":"Nigel Rees",
                    "title":"Sayings of the Century",
                    "price":8.95
                 },
                 "3": {
                    "category":"fiction",
                    "author":"Evelyn Waugh",
                    "title":"Sword of Honour",
                    "price":12.99
                 },
                 "2": {
                    "category":"fiction",
                    "author":"Herman Melville",
                    "title":"Moby Dick",
                    "isbn":"0-553-21311-3",
                    "price":8.99
                 },
                 "1": {
                    "category":"fiction",
                    "author":"J. R. R. Tolkien",
                    "title":"The Lord of the Rings",
                    "isbn":"0-395-19395-8",
                    "price":22.99
                 }
              }
           }
        }';

        return json_decode($json, $asArray === 1);
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleDataWithSimpleIntegers(int $asArray = 1)
    {
        $json = '{
           "features":[
              {
                 "name":"foo",
                 "value":1
              },
              {
                 "name":"bar",
                 "value":2
              },
              {
                 "name":"baz",
                 "value":1
              }
           ]
        }';

        return json_decode($json, $asArray === 1);
    }
}

class JSONPathTestClass
{
    protected $attributes = [
        'foo' => 'bar',
    ];

    /** @noinspection MagicMethodsValidityInspection */
    public function __get($key): ?string
    {
        return $this->attributes[$key] ?? null;
    }
}
