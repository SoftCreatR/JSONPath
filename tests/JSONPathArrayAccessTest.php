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
use function json_decode;
use function random_int;

class JSONPathArrayAccessTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testChaining(): void
    {
        $data = $this->exampleData(random_int(0, 1));
        $conferences = (new JSONPath($data))->find('.conferences.*');
        $teams = $conferences->find('..teams.*');

        self::assertEquals('Dodger', $teams[0]['name']);
        self::assertEquals('Mets', $teams[1]['name']);

        $teams = (new JSONPath($data))->find('.conferences.*')->find('..teams.*');

        self::assertEquals('Dodger', $teams[0]['name']);
        self::assertEquals('Mets', $teams[1]['name']);

        $teams = (new JSONPath($data))->find('.conferences..teams.*');

        self::assertEquals('Dodger', $teams[0]['name']);
        self::assertEquals('Mets', $teams[1]['name']);
    }

    /**
     * @throws Exception
     */
    public function testIterating(): void
    {
        $data = $this->exampleData(random_int(0, 1));
        $conferences = (new JSONPath($data))->find('.conferences.*');
        $names = [];

        foreach ($conferences as $conference) {
            $players = $conference->find('.teams.*.players[?(@.active=yes)]');

            foreach ($players as $player) {
                $names[] = $player->name;
            }
        }

        self::assertEquals(['Joe Face', 'something'], $names);
    }

    /**
     * @throws Exception
     */
    public function testDifferentStylesOfAccess(): void
    {
        $data = (new JSONPath($this->exampleData(random_int(0, 1))));

        self::assertArrayHasKey('conferences', $data);

        $conferences = $data->__get('conferences')->getData();

        if (is_array($conferences[0])) {
            self::assertEquals('Western Conference', $conferences[0]['name']);
        } else {
            self::assertEquals('Western Conference', $conferences[0]->name);
        }
    }

    /**
     * @param int $asArray
     * @return array|object
     */
    public function exampleData(int $asArray = 1)
    {
        $json = '{
           "name":"Major League Baseball",
           "abbr":"MLB",
           "conferences":[
              {
                 "name":"Western Conference",
                 "abbr":"West",
                 "teams":[
                    {
                       "name":"Dodger",
                       "city":"Los Angeles",
                       "whatever":"else",
                       "players":[
                          {
                             "name":"Bob Smith",
                             "number":22
                          },
                          {
                             "name":"Joe Face",
                             "number":23,
                             "active":"yes"
                          }
                       ]
                    }
                 ]
              },
              {
                 "name":"Eastern Conference",
                 "abbr":"East",
                 "teams":[
                    {
                       "name":"Mets",
                       "city":"New York",
                       "whatever":"else",
                       "players":[
                          {
                             "name":"something",
                             "number":14,
                             "active":"yes"
                          },
                          {
                             "name":"something",
                             "number":15
                          }
                       ]
                    }
                 ]
              }
           ]
        }';

        return json_decode($json, $asArray === 1);
    }
}
