<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */

namespace Flow\JSONPath\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use Flow\JSONPath\JSONPath;
use function is_object;
use function json_decode;
use function json_encode;
use function mt_rand;

class JSONPathTest extends TestCase
{
    /**
     * $.store.books[0].title
     *
     * @throws Exception
     */
    public function testChildOperators()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$.store.books[0].title');

        self::assertEquals('Sayings of the Century', $result[0]);
    }

    /**
     * $['store']['books'][0]['title']
     *
     * @throws Exception
     */
    public function testChildOperatorsAlt()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find("$['store']['books'][0]['title']");

        self::assertEquals('Sayings of the Century', $result[0]);
    }

    /**
     * $.array[start:end:step]
     *
     * @throws Exception
     */
    public function testFilterSliceA()
    {
        // Copy all items... similar to a wildcard
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find("$['store']['books'][:].title");

        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick', 'The Lord of the Rings'], $result->getData());
    }

    /**
     * Positive end indexes
     * $[0:2]
     *
     * @throws Exception
     */
    public function testFilterSlice_PositiveEndIndexes()
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
    public function testFilterSlice_NegativeStartIndexes()
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
    public function testFilterSlice_NegativeEndIndexes()
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
    public function testFilterSlice_NegativeStartAndEndIndexes()
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
    public function testFilterSlice_NegativeStartAndPositiveEnd()
    {
        $result = (new JSONPath(['first', 'second', 'third', 'fourth', 'fifth']))->find('$[-2:2]');

        self::assertEquals([], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testFilterSlice_StepBy2()
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
    public function testFilterLastIndex()
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
    public function testFilterSliceG()
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
    public function testChildQuery()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$.store.books[(@.length-1)].title');

        self::assertEquals(['The Lord of the Rings'], $result->getData());
    }

    /**
     * $.store.books[?(@.price < 10)].title
     * Filter books that have a price less than 10
     *
     * @throws Exception
     */
    public function testQueryMatchLessThan()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$.store.books[?(@.price < 10)].title');

        self::assertEquals(['Sayings of the Century', 'Moby Dick'], $result->getData());
    }

    /**
     * $..books[?(@.author == "J. R. R. Tolkien")]
     * Filter books that have a title equal to "..."
     *
     * @throws Exception
     */
    public function testQueryMatchEquals()
    {
        $results = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[?(@.author == "J. R. R. Tolkien")].title');

        self::assertEquals('The Lord of the Rings', $results[0]);
    }

    /**
     * $..books[?(@.author = 1)]
     * Filter books that have a title equal to "..."
     *
     * @throws Exception
     */
    public function testQueryMatchEqualsWithUnquotedInteger()
    {
        $results = (new JSONPath($this->exampleDataWithSimpleIntegers(mt_rand(0, 1))))->find('$..features[?(@.value = 1)]');

        self::assertEquals('foo', $results[0]->name);
        self::assertEquals('baz', $results[1]->name);
    }

    /**
     * $..books[?(@.author != "J. R. R. Tolkien")]
     * Filter books that have a title not equal to "..."
     *
     * @throws Exception
     */
    public function testQueryMatchNotEqualsTo()
    {
        $results = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[?(@.author != "J. R. R. Tolkien")].title');
        self::assertCount(3, $results);
        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick'], [$results[0], $results[1], $results[2]]);

        $results = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[?(@.author !== "J. R. R. Tolkien")].title');
        self::assertcount(3, $results);
        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick'], [$results[0], $results[1], $results[2]]);

        $results = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[?(@.author <> "J. R. R. Tolkien")].title');
        self::assertcount(3, $results);
        self::assertEquals(['Sayings of the Century', 'Sword of Honour', 'Moby Dick'], [$results[0], $results[1], $results[2]]);
    }

    /**
     * $.store.books[*].author
     *
     * @throws Exception
     */
    public function testWildcardAltNotation()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$.store.books[*].author');

        self::assertEquals(['Nigel Rees', 'Evelyn Waugh', 'Herman Melville', 'J. R. R. Tolkien'], $result->getData());
    }

    /**
     * $..author
     *
     * @throws Exception
     */
    public function testRecursiveChildSearch()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..author');

        self::assertEquals(['Nigel Rees', 'Evelyn Waugh', 'Herman Melville', 'J. R. R. Tolkien'], $result->getData());
    }

    /**
     * $.store.*
     * all things in store
     * the structure of the example data makes this test look weird
     *
     * @throws Exception
     */
    public function testWildCard()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$.store.*');
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
    public function testRecursiveChildSearchAlt()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$.store..price');

        self::assertEquals([8.95, 12.99, 8.99, 22.99, 19.95], $result->getData());
    }

    /**
     * $..books[2]
     * the third book
     *
     * @throws Exception
     */
    public function testRecursiveChildSearchWithChildIndex()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[2].title');

        self::assertEquals(['Moby Dick'], $result->getData());
    }

    /**
     * $..books[(@.length-1)]
     *
     * @throws Exception
     */
    public function testRecursiveChildSearchWithChildQuery()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[(@.length-1)].title');

        self::assertEquals(['The Lord of the Rings'], $result->getData());
    }

    /**
     * $..books[-1:]
     * Return the last results
     *
     * @throws Exception
     */
    public function testRecursiveChildSearchWithSliceFilter()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[-1:].title');

        self::assertEquals(['The Lord of the Rings'], $result->getData());
    }

    /**
     * $..books[?(@.isbn)]
     * filter all books with isbn number
     *
     * @throws Exception
     */
    public function testRecursiveWithQueryMatch()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..books[?(@.isbn)].isbn');

        self::assertEquals(['0-553-21311-3', '0-395-19395-8'], $result->getData());
    }

    /**
     * .data.tokens[?(@.Employee.FirstName)]
     * Verify that it is possible to filter with a key containing punctuation
     *
     * @throws Exception
     */
    public function testRecursiveWithQueryMatchWithDots()
    {
        $result = (new JSONPath($this->exampleDataWithDots(mt_rand(0, 1))))->find(".data.tokens[?(@.Employee.FirstName)]");
        $result = json_decode(json_encode($result), true);

        self::assertEquals([['Employee.FirstName' => 'Jack']], $result);
    }

    /**
     * $..*
     * All members of JSON structure
     *
     * @throws Exception
     */
    public function testRecursiveWithWildcard()
    {
        $result = (new JSONPath($this->exampleData(mt_rand(0, 1))))->find('$..*');
        $result = json_decode(json_encode($result), true);

        self::assertEquals('Sayings of the Century', $result[0]['books'][0]['title']);
        self::assertEquals(19.95, $result[26]);
    }

    /**
     * Tests direct key access.
     *
     * @throws Exception
     */
    public function testSimpleArrayAccess()
    {
        $result = (new JSONPath(['title' => 'test title']))->find('title');

        self::assertEquals(['test title'], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testFilteringOnNoneArrays()
    {
        $data = ['foo' => 'asdf'];
        $result = (new JSONPath($data))->find('$.foo.bar');

        self::assertEquals([], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testMagicMethods()
    {
        $fooClass = new JSONPathTestClass();
        $results = (new JSONPath($fooClass, JSONPath::ALLOW_MAGIC))->find('$.foo');

        self::assertEquals(['bar'], $results->getData());
    }

    /**
     * @throws Exception
     */
    public function testMatchWithComplexSquareBrackets()
    {
        $result = (new JSONPath($this->exampleDataExtra()))->find(
            "$['http://www.w3.org/2000/01/rdf-schema#label'][?(@['@language']='en')]['@language']"
        );

        self::assertEquals(["en"], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testQueryMatchWithRecursive()
    {
        $locations = $this->exampleDataLocations();
        $result = (new JSONPath($locations))->find("..[?(@.type == 'suburb')].name");

        self::assertEquals(["Rosebank"], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testFirst()
    {
        $result = (new JSONPath($this->exampleDataExtra()))->find("$['http://www.w3.org/2000/01/rdf-schema#label'].*");

        self::assertEquals(["@language" => "en"], $result->first()->getData());
    }

    /**
     * @throws Exception
     */
    public function testLast()
    {
        $result = (new JSONPath($this->exampleDataExtra()))->find("$['http://www.w3.org/2000/01/rdf-schema#label'].*");

        self::assertEquals(["@language" => "de"], $result->last()->getData());
    }

    /**
     * @throws Exception
     */
    public function testSlashesInIndex()
    {
        $result = (new JSONPath($this->exampleDataWithSlashes()))->find("$['mediatypes']['image/png']");

        self::assertEquals(["/core/img/filetypes/image.png"], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testCyrillicText()
    {
        $result = (new JSONPath(["трололо" => 1]))->find("$['трололо']");
        self::assertEquals([1], $result->getData());

        $result = (new JSONPath(["трололо" => 1]))->find("$.трололо");
        self::assertEquals([1], $result->getData());
    }

    /**
     * @throws Exception
     */
    public function testOffsetUnset()
    {
        $data = [
            "route" => [
                ["name" => "A", "type" => "type of A"],
                ["name" => "B", "type" => "type of B"],
            ],
        ];
        $data = json_encode($data);
        $jsonIterator = new JSONPath(json_decode($data, mt_rand(0, 1) === 1));

        /** @var JSONPath $route */
        $route = $jsonIterator->offsetGet('route');
        $route->offsetUnset(0);
        $first = $route->first();

        self::assertEquals("B", $first['name']);
    }

    public function testFirstKey()
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

    public function testLastKey()
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
    public function testTrailingComma()
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
        }', mt_rand(0, 1) === 1));
        $result = $jsonPath->find("$..book[0,1,2,]");

        self::assertCount(3, $result);
    }

    /**
     * Test: ensure negative indexes return -n from last index
     *
     * @throws Exception
     */
    public function testNegativeIndex()
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
        }', mt_rand(0, 1) === 1));
        $result = $jsonPath->find("$..book[-2]");

        self::assertEquals("Herman Melville", $result[0]['author']);
    }

    /**
     * @throws Exception
     */
    public function testQueryAccessWithNumericalIndexes()
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
        }', mt_rand(0, 1) === 1));
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
        }', mt_rand(0, 1) === 1));
        $result = $jsonPath->find("$.result.list[?(@[1] == \"11.51000\")]");

        self::assertEquals("11.51000", $result[0][1]);
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleData($asArray = 1)
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
    public function exampleDataExtra($asArray = 1)
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
    public function exampleDataLocations($asArray = 1)
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
    public function exampleDataWithSlashes($asArray = 1)
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
    public function exampleDataWithDots($asArray = 1)
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

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleDataWithSimpleIntegers($asArray = 1)
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
    public function __get($key)
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
    }
}
