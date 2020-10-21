<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\{JSONPath, JSONPathException};
use PHPUnit\Framework\{ExpectationFailedException, TestCase};

use function fwrite;
use function json_decode;
use function json_encode;

use const STDERR;

class QueryTest extends TestCase
{
    /**
     * This method aims to test the current implementation against
     * all queries listed on https://cburgmer.github.io/json-path-comparison/
     *
     * Every test performed is allowed to fail and whenever an assertion fails,
     * a message will be printed to STDERR, so we know, what's going on.
     *
     * @see https://cburgmer.github.io/json-path-comparison
     * @dataProvider queryDataProvider
     * @param string $query
     * @param string $id
     * @param string $selector
     * @param string $data
     * @param string $consensus
     * @param bool $skip
     */
    public function testQueries(
        string $query,
        string $id,
        string $selector,
        string $data,
        string $consensus,
        bool $skip = false
    ): void {
        $url = sprintf('https://cburgmer.github.io/json-path-comparison/results/%s', $id);

        // Avoid "This test did not perform any assertions"
        // but do not use markTestSkipped, to prevent unnecessary
        // console outputs
        self::assertTrue(true);

        if (empty($consensus) || $skip) {
            /*$skipReason = empty($consensus) ? 'unknown consensus' : 'skip flag set';

            fwrite(STDERR, "==========================\n");
            fwrite(STDERR, "Query: {$query}\nSKIPPED ({$skipReason})\nMore information: {$url}\n");
            fwrite(STDERR, "==========================\n\n");*/

            return;
        }

        try {
            $results = json_encode((new JSONPath(json_decode($data, true)))->find($selector));

            self::assertEquals($consensus, $results);
        } catch (ExpectationFailedException $e) {
            $e = $e->getComparisonFailure();

            fwrite(STDERR, "==========================\n");
            fwrite(STDERR, "Query: {$query}\n\n{$e->toString()}\nMore information: $url\n");
            fwrite(STDERR, "==========================\n\n");
        } catch (JSONPathException $e) {
            fwrite(STDERR, "==========================\n");
            fwrite(STDERR, "Query: {$query}\n\n{$e->getMessage()}\n");
            fwrite(STDERR, "==========================\n\n");
        }
    }

    /**
     * Returns a list of queries, test data and expected results.
     *
     * A hand full of queries may run forever, thus they should
     * be skipped for now.
     *
     * Queries that are currently known as "problematic" are:
     *
     * - array_slice_with_negative_step_and_start_greater_than_end
     * - array_slice_with_open_end_and_negative_step
     * - array_slice_with_large_number_for_start
     * - array_slice_with_large_number_for_end
     * - array_slice_with_open_start_and_negative_step
     * - array_slice_with_negative_step_only
     *
     * The list is generated automatically, based on the results
     * at https://cburgmer.github.io/json-path-comparison.
     *
     * @return string[]
     */
    public function queryDataProvider(): array
    {
        return [
            [ // data set #0
                'Array Slice',
                'array_slice',
                '$[1:3]',
                '["first","second","third","forth","fifth"]',
                '["second","third"]'
            ],
            [ // data set #1
                'Array Slice On Exact Match',
                'array_slice_on_exact_match',
                '$[0:5]',
                '["first","second","third","forth","fifth"]',
                '["first","second","third","forth","fifth"]'
            ],
            [ // data set #2
                'Array Slice On Non Overlapping Array',
                'array_slice_on_non_overlapping_array',
                '$[7:10]',
                '["first","second","third"]',
                '[]'
            ],
            [ // data set #3
                'Array Slice On Object',
                'array_slice_on_object',
                '$[1:3]',
                '{":":42,"more":"string","a":1,"b":2,"c":3,"1:3":"nice"}',
                '[]'
            ],
            [ // data set #4
                'Array Slice On Partially Overlapping Array',
                'array_slice_on_partially_overlapping_array',
                '$[1:10]',
                '["first","second","third"]',
                '["second","third"]'
            ],
            [ // data set #5
                'Array Slice With Large Number For End',
                'array_slice_with_large_number_for_end',
                '$[2:113667776004]',
                '["first","second","third","forth","fifth"]',
                '["third","forth","fifth"]',
                true, // skip
            ],
            [ // data set #6
                'Array Slice With Large Number For End And Negative Step',
                'array_slice_with_large_number_for_end_and_negative_step',
                '$[2:-113667776004:-1]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #7
                'Array Slice With Large Number For Start',
                'array_slice_with_large_number_for_start',
                '$[-113667776004:2]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus,
                true, // skip
            ],
            [ // data set #8
                'Array Slice With Large Number For Start End Negative Step',
                'array_slice_with_large_number_for_start_end_negative_step',
                '$[113667776004:2:-1]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #9
                'Array Slice With Negative Start And End And Range Of -1',
                'array_slice_with_negative_start_and_end_and_range_of_-1',
                '$[-4:-5]',
                '[2,"a",4,5,100,"nice"]',
                '[]'
            ],
            [ // data set #10
                'Array Slice With Negative Start And End And Range Of 0',
                'array_slice_with_negative_start_and_end_and_range_of_0',
                '$[-4:-4]',
                '[2,"a",4,5,100,"nice"]',
                '[]'
            ],
            [ // data set #11
                'Array Slice With Negative Start And End And Range Of 1',
                'array_slice_with_negative_start_and_end_and_range_of_1',
                '$[-4:-3]',
                '[2,"a",4,5,100,"nice"]',
                '[4]'
            ],
            [ // data set #12
                'Array Slice With Negative Start And Positive End And Range Of -1',
                'array_slice_with_negative_start_and_positive_end_and_range_of_-1',
                '$[-4:1]',
                '[2,"a",4,5,100,"nice"]',
                '[]'
            ],
            [ // data set #13
                'Array Slice With Negative Start And Positive End And Range Of 0',
                'array_slice_with_negative_start_and_positive_end_and_range_of_0',
                '$[-4:2]',
                '[2,"a",4,5,100,"nice"]',
                '[]'
            ],
            [ // data set #14
                'Array Slice With Negative Start And Positive End And Range Of 1',
                'array_slice_with_negative_start_and_positive_end_and_range_of_1',
                '$[-4:3]',
                '[2,"a",4,5,100,"nice"]',
                '[4]'
            ],
            [ // data set #15
                'Array Slice With Negative Step',
                'array_slice_with_negative_step',
                '$[3:0:-2]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #16
                'Array Slice With Negative Step And Start Greater Than End',
                'array_slice_with_negative_step_and_start_greater_than_end',
                '$[0:3:-2]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus,
                true, // skip
            ],
            [ // data set #17
                'Array Slice With Negative Step On Partially Overlapping Array',
                'array_slice_with_negative_step_on_partially_overlapping_array',
                '$[7:3:-1]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #18
                'Array Slice With Negative Step Only',
                'array_slice_with_negative_step_only',
                '$[::-2]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus,
                true, // skip
            ],
            [ // data set #19
                'Array Slice With Open End',
                'array_slice_with_open_end',
                '$[1:]',
                '["first","second","third","forth","fifth"]',
                '["second","third","forth","fifth"]'
            ],
            [ // data set #20
                'Array Slice With Open End And Negative Step',
                'array_slice_with_open_end_and_negative_step',
                '$[3::-1]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus,
                true, // skip
            ],
            [ // data set #21
                'Array Slice With Open Start',
                'array_slice_with_open_start',
                '$[:2]',
                '["first","second","third","forth","fifth"]',
                '["first","second"]'
            ],
            [ // data set #22
                'Array Slice With Open Start And End',
                'array_slice_with_open_start_and_end',
                '$[:]',
                '["first","second"]',
                '["first","second"]'
            ],
            [ // data set #23
                'Array Slice With Open Start And End And Step Empty',
                'array_slice_with_open_start_and_end_and_step_empty',
                '$[::]',
                '["first","second"]',
                '["first","second"]'
            ],
            [ // data set #24
                'Array Slice With Open Start And End On Object',
                'array_slice_with_open_start_and_end_on_object',
                '$[:]',
                '{":":42,"more":"string"}',
                '', // unknown consensus
            ],
            [ // data set #25
                'Array Slice With Open Start And Negative Step',
                'array_slice_with_open_start_and_negative_step',
                '$[:2:-1]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus,
                true, // skip
            ],
            [ // data set #26
                'Array Slice With Positive Start And Negative End And Range Of -1',
                'array_slice_with_positive_start_and_negative_end_and_range_of_-1',
                '$[3:-4]',
                '[2,"a",4,5,100,"nice"]',
                '[]'
            ],
            [ // data set #27
                'Array Slice With Positive Start And Negative End And Range Of 0',
                'array_slice_with_positive_start_and_negative_end_and_range_of_0',
                '$[3:-3]',
                '[2,"a",4,5,100,"nice"]',
                '[]'
            ],
            [ // data set #28
                'Array Slice With Positive Start And Negative End And Range Of 1',
                'array_slice_with_positive_start_and_negative_end_and_range_of_1',
                '$[3:-2]',
                '[2,"a",4,5,100,"nice"]',
                '[5]'
            ],
            [ // data set #29
                'Array Slice With Range Of -1',
                'array_slice_with_range_of_-1',
                '$[2:1]',
                '["first","second","third","forth"]',
                '[]'
            ],
            [ // data set #30
                'Array Slice With Range Of 0',
                'array_slice_with_range_of_0',
                '$[0:0]',
                '["first","second"]',
                '[]'
            ],
            [ // data set #31
                'Array Slice With Range Of 1',
                'array_slice_with_range_of_1',
                '$[0:1]',
                '["first","second"]',
                '["first"]'
            ],
            [ // data set #32
                'Array Slice With Start -1 And Open End',
                'array_slice_with_start_-1_and_open_end',
                '$[-1:]',
                '["first","second","third"]',
                '["third"]'
            ],
            [ // data set #33
                'Array Slice With Start -2 And Open End',
                'array_slice_with_start_-2_and_open_end',
                '$[-2:]',
                '["first","second","third"]',
                '["second","third"]'
            ],
            [ // data set #34
                'Array Slice With Start Large Negative Number And Open End On Short Array',
                'array_slice_with_start_large_negative_number_and_open_end_on_short_array',
                '$[-4:]',
                '["first","second","third"]',
                '["first","second","third"]'
            ],
            [ // data set #35
                'Array Slice With Step',
                'array_slice_with_step',
                '$[0:3:2]',
                '["first","second","third","forth","fifth"]',
                '["first","third"]'
            ],
            [ // data set #36
                'Array Slice With Step 0',
                'array_slice_with_step_0',
                '$[0:3:0]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #37
                'Array Slice With Step 1',
                'array_slice_with_step_1',
                '$[0:3:1]',
                '["first","second","third","forth","fifth"]',
                '["first","second","third"]'
            ],
            [ // data set #38
                'Array Slice With Step And Leading Zeros',
                'array_slice_with_step_and_leading_zeros',
                '$[010:024:010]',
                '[0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25]',
                '[10,20]'
            ],
            [ // data set #39
                'Array Slice With Step But End Not Aligned',
                'array_slice_with_step_but_end_not_aligned',
                '$[0:4:2]',
                '["first","second","third","forth","fifth"]',
                '["first","third"]'
            ],
            [ // data set #40
                'Array Slice With Step Empty',
                'array_slice_with_step_empty',
                '$[1:3:]',
                '["first","second","third","forth","fifth"]',
                '["second","third"]'
            ],
            [ // data set #41
                'Array Slice With Step Only',
                'array_slice_with_step_only',
                '$[::2]',
                '["first","second","third","forth","fifth"]',
                '["first","third","fifth"]'
            ],
            [ // data set #42
                'Bracket Notation',
                'bracket_notation',
                '$[\'key\']',
                '{"key":"value"}',
                '["value"]'
            ],
            [ // data set #43
                'Bracket Notation After Recursive Descent',
                'bracket_notation_after_recursive_descent',
                '$..[0]',
                '["first",{"key":["first nested",{"more":[{"nested":["deepest","second"]},["more","values"]]}]}]',
                '["deepest","first nested","first","more",{"nested":["deepest","second"]}]'
            ],
            [ // data set #44
                'Bracket Notation On Object Without Key',
                'bracket_notation_on_object_without_key',
                '$[\'missing\']',
                '{"key":"value"}',
                '[]'
            ],
            [ // data set #45
                'Bracket Notation With NFC Path On NFD Key',
                'bracket_notation_with_NFC_path_on_NFD_key',
                '$[\'ü\']',
                '{"u\\u0308":42}',
                '[]'
            ],
            [ // data set #46
                'Bracket Notation With Dot',
                'bracket_notation_with_dot',
                '$[\'two.some\']',
                '{"one":{"key":"value"},"two":{"some":"more","key":"other value"},"two.some":"42"}',
                '["42"]'
            ],
            [ // data set #47
                'Bracket Notation With Double Quotes',
                'bracket_notation_with_double_quotes',
                '$["key"]',
                '{"key":"value"}',
                '["value"]'
            ],
            [ // data set #48
                'Bracket Notation With Empty Path',
                'bracket_notation_with_empty_path',
                '$[]',
                '{"":42,"\'\'":123,"\\"\\"":222}',
                '', // unknown consensus
            ],
            [ // data set #49
                'Bracket Notation With Empty String',
                'bracket_notation_with_empty_string',
                '$[\'\']',
                '{"":42,"\'\'":123,"\\"\\"":222}',
                '[42]'
            ],
            [ // data set #50
                'Bracket Notation With Empty String Doubled Quoted',
                'bracket_notation_with_empty_string_doubled_quoted',
                '$[""]',
                '{"":42,"\'\'":123,"\\"\\"":222}',
                '[42]'
            ],
            [ // data set #51
                'Bracket Notation With Negative Number On Short Array',
                'bracket_notation_with_negative_number_on_short_array',
                '$[-2]',
                '["one element"]',
                '[]'
            ],
            [ // data set #52
                'Bracket Notation With Number',
                'bracket_notation_with_number',
                '$[2]',
                '["first","second","third","forth","fifth"]',
                '["third"]'
            ],
            [ // data set #53
                'Bracket Notation With Number -1',
                'bracket_notation_with_number_-1',
                '$[-1]',
                '["first","second","third"]',
                '["third"]'
            ],
            [ // data set #54
                'Bracket Notation With Number -1 On Empty Array',
                'bracket_notation_with_number_-1_on_empty_array',
                '$[-1]',
                '[]',
                '[]'
            ],
            [ // data set #55
                'Bracket Notation With Number 0',
                'bracket_notation_with_number_0',
                '$[0]',
                '["first","second","third","forth","fifth"]',
                '["first"]'
            ],
            [ // data set #56
                'Bracket Notation With Number After Dot Notation With Wildcard On Nested Arrays With Different Length',
                'bracket_notation_with_number_after_dot_notation_with_wildcard_on_nested_arrays_with_different_length',
                '$.*[1]',
                '[[1],[2,3]]',
                '[3]'
            ],
            [ // data set #57
                'Bracket Notation With Number On Object',
                'bracket_notation_with_number_on_object',
                '$[0]',
                '{"0":"value"}',
                '', // unknown consensus
            ],
            [ // data set #58
                'Bracket Notation With Number On Short Array',
                'bracket_notation_with_number_on_short_array',
                '$[1]',
                '["one element"]',
                '[]'
            ],
            [ // data set #59
                'Bracket Notation With Number On String',
                'bracket_notation_with_number_on_string',
                '$[0]',
                '"Hello World"',
                '', // unknown consensus
            ],
            [ // data set #60
                'Bracket Notation With Quoted Array Slice Literal',
                'bracket_notation_with_quoted_array_slice_literal',
                '$[\':\']',
                '{":":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #61
                'Bracket Notation With Quoted Closing Bracket Literal',
                'bracket_notation_with_quoted_closing_bracket_literal',
                '$[\']\']',
                '{"]":42}',
                '[42]'
            ],
            [ // data set #62
                'Bracket Notation With Quoted Current Object Literal',
                'bracket_notation_with_quoted_current_object_literal',
                '$[\'@\']',
                '{"@":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #63
                'Bracket Notation With Quoted Dot Literal',
                'bracket_notation_with_quoted_dot_literal',
                '$[\'.\']',
                '{".":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #64
                'Bracket Notation With Quoted Dot Wildcard',
                'bracket_notation_with_quoted_dot_wildcard',
                '$[\'.*\']',
                '{"key":42,".*":1,"":10}',
                '[1]'
            ],
            [ // data set #65
                'Bracket Notation With Quoted Double Quote Literal',
                'bracket_notation_with_quoted_double_quote_literal',
                '$[\'"\']',
                '{"\\"":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #66
                'Bracket Notation With Quoted Escaped Backslash',
                'bracket_notation_with_quoted_escaped_backslash',
                '$[\'\\\\\']',
                '{"\\\\":"value"}',
                '', // unknown consensus
            ],
            [ // data set #67
                'Bracket Notation With Quoted Escaped Single Quote',
                'bracket_notation_with_quoted_escaped_single_quote',
                '$[\'\\\'\']',
                '{"\'":"value"}',
                '', // unknown consensus
            ],
            [ // data set #68
                'Bracket Notation With Quoted Number On Object',
                'bracket_notation_with_quoted_number_on_object',
                '$[\'0\']',
                '{"0":"value"}',
                '["value"]'
            ],
            [ // data set #69
                'Bracket Notation With Quoted Root Literal',
                'bracket_notation_with_quoted_root_literal',
                '$[\'$\']',
                '{"$":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #70
                'Bracket Notation With Quoted Special Characters Combined',
                'bracket_notation_with_quoted_special_characters_combined',
                '$[\':@."$,*\\\'\\\\\']',
                '{":@.\\"$,*\'\\\\":42}',
                '', // unknown consensus
            ],
            [ // data set #71
                'Bracket Notation With Quoted String And Unescaped Single Quote',
                'bracket_notation_with_quoted_string_and_unescaped_single_quote',
                '$[\'single\'quote\']',
                '{"single\'quote":"value"}',
                '', // unknown consensus
            ],
            [ // data set #72
                'Bracket Notation With Quoted Union Literal',
                'bracket_notation_with_quoted_union_literal',
                '$[\',\']',
                '{",":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #73
                'Bracket Notation With Quoted Wildcard Literal',
                'bracket_notation_with_quoted_wildcard_literal',
                '$[\'*\']',
                '{"*":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #74
                'Bracket Notation With Quoted Wildcard Literal On Object Without Key',
                'bracket_notation_with_quoted_wildcard_literal_on_object_without_key',
                '$[\'*\']',
                '{"another":"entry"}',
                '', // unknown consensus
            ],
            [ // data set #75
                'Bracket Notation With String Including Dot Wildcard',
                'bracket_notation_with_string_including_dot_wildcard',
                '$[\'ni.*\']',
                '{"nice":42,"ni.*":1,"mice":100}',
                '[1]'
            ],
            [ // data set #76
                'Bracket Notation With Two Literals Separated By Dot',
                'bracket_notation_with_two_literals_separated_by_dot',
                '$[\'two\'.\'some\']',
                '{"one":{"key":"value"},"two":{"some":"more","key":"other value"},"two.some":"42","two\'.\'some":"43' .
                '"}',
                '', // unknown consensus
            ],
            [ // data set #77
                'Bracket Notation With Two Literals Separated By Dot Without Quotes',
                'bracket_notation_with_two_literals_separated_by_dot_without_quotes',
                '$[two.some]',
                '{"one":{"key":"value"},"two":{"some":"more","key":"other value"},"two.some":"42"}',
                '', // unknown consensus
            ],
            [ // data set #78
                'Bracket Notation With Wildcard After Array Slice',
                'bracket_notation_with_wildcard_after_array_slice',
                '$[0:2][*]',
                '[[1,2],["a","b"],[0,0]]',
                '[1,2,"a","b"]'
            ],
            [ // data set #79
                'Bracket Notation With Wildcard After Dot Notation After Bracket Notation With Wildcard',
                'bracket_notation_with_wildcard_after_dot_notation_after_bracket_notation_with_wildcard',
                '$[*].bar[*]',
                '[{"bar":[42]}]',
                '[42]'
            ],
            [ // data set #80
                'Bracket Notation With Wildcard After Recursive Descent',
                'bracket_notation_with_wildcard_after_recursive_descent',
                '$..[*]',
                '{"key":"value","another key":{"complex":"string","primitives":[0,1]}}',
                '["string","value",0,1,[0,1],{"complex":"string","primitives":[0,1]}]'
            ],
            [ // data set #81
                'Bracket Notation With Wildcard On Array',
                'bracket_notation_with_wildcard_on_array',
                '$[*]',
                '["string",42,{"key":"value"},[0,1]]',
                '["string",42,{"key":"value"},[0,1]]'
            ],
            [ // data set #82
                'Bracket Notation With Wildcard On Empty Array',
                'bracket_notation_with_wildcard_on_empty_array',
                '$[*]',
                '[]',
                '[]'
            ],
            [ // data set #83
                'Bracket Notation With Wildcard On Empty Object',
                'bracket_notation_with_wildcard_on_empty_object',
                '$[*]',
                '{}',
                '[]'
            ],
            [ // data set #84
                'Bracket Notation With Wildcard On Null Value Array',
                'bracket_notation_with_wildcard_on_null_value_array',
                '$[*]',
                '[40,null,42]',
                '[40,null,42]'
            ],
            [ // data set #85
                'Bracket Notation With Wildcard On Object',
                'bracket_notation_with_wildcard_on_object',
                '$[*]',
                '{"some":"string","int":42,"object":{"key":"value"},"array":[0,1]}',
                '["string",42,[0,1],{"key":"value"}]'
            ],
            [ // data set #86
                'Bracket Notation Without Quotes',
                'bracket_notation_without_quotes',
                '$[key]',
                '{"key":"value"}',
                '', // unknown consensus
            ],
            [ // data set #87
                'Dot Bracket Notation',
                'dot_bracket_notation',
                '$.[\'key\']',
                '{"key":"value","other":{"key":[{"key":42}]}}',
                '', // unknown consensus
            ],
            [ // data set #88
                'Dot Bracket Notation With Double Quotes',
                'dot_bracket_notation_with_double_quotes',
                '$.["key"]',
                '{"key":"value","other":{"key":[{"key":42}]}}',
                '', // unknown consensus
            ],
            [ // data set #89
                'Dot Bracket Notation Without Quotes',
                'dot_bracket_notation_without_quotes',
                '$.[key]',
                '{"key":"value","other":{"key":[{"key":42}]}}',
                '', // unknown consensus
            ],
            [ // data set #90
                'Dot Notation',
                'dot_notation',
                '$.key',
                '{"key":"value"}',
                '["value"]'
            ],
            [ // data set #91
                'Dot Notation After Array Slice',
                'dot_notation_after_array_slice',
                '$[0:2].key',
                '[{"key":"ey"},{"key":"bee"},{"key":"see"}]',
                '["ey","bee"]'
            ],
            [ // data set #92
                'Dot Notation After Bracket Notation After Recursive Descent',
                'dot_notation_after_bracket_notation_after_recursive_descent',
                '$..[1].key',
                '{"k":[{"key":"some value"},{"key":42}],"kk":[[{"key":100},{"key":200},{"key":300}],[{"key":400},{"k' .
                'ey":500},{"key":600}]],"key":[0,1]}',
                '[200,42,500]'
            ],
            [ // data set #93
                'Dot Notation After Bracket Notation With Wildcard',
                'dot_notation_after_bracket_notation_with_wildcard',
                '$[*].a',
                '[{"a":1},{"a":1}]',
                '[1,1]'
            ],
            [ // data set #94
                'Dot Notation After Bracket Notation With Wildcard On One Matching',
                'dot_notation_after_bracket_notation_with_wildcard_on_one_matching',
                '$[*].a',
                '[{"a":1}]',
                '[1]'
            ],
            [ // data set #95
                'Dot Notation After Bracket Notation With Wildcard On Some Matching',
                'dot_notation_after_bracket_notation_with_wildcard_on_some_matching',
                '$[*].a',
                '[{"a":1},{"b":1}]',
                '[1]'
            ],
            [ // data set #96
                'Dot Notation After Filter Expression',
                'dot_notation_after_filter_expression',
                '$[?(@.id==42)].name',
                '[{"id":42,"name":"forty-two"},{"id":1,"name":"one"}]',
                '["forty-two"]'
            ],
            [ // data set #97
                'Dot Notation After Recursive Descent',
                'dot_notation_after_recursive_descent',
                '$..key',
                '{"object":{"key":"value","array":[{"key":"something"},{"key":{"key":"russian dolls"}}]},"key":"top"' .
                '}',
                '["russian dolls","something","top","value",{"key":"russian dolls"}]'
            ],
            [ // data set #98
                'Dot Notation After Recursive Descent After Dot Notation',
                'dot_notation_after_recursive_descent_after_dot_notation',
                '$.store..price',
                '{"store":{"book":[{"category":"reference","author":"Nigel Rees","title":"Sayings of the Century","p' .
                'rice":8.95},{"category":"fiction","author":"Evelyn Waugh","title":"Sword of Honour","price":12.99},' .
                '{"category":"fiction","author":"Herman Melville","title":"Moby Dick","isbn":"0-553-21311-3","price"' .
                ':8.99},{"category":"fiction","author":"J. R. R. Tolkien","title":"The Lord of the Rings","isbn":"0-' .
                '395-19395-8","price":22.99}],"bicycle":{"color":"red","price":19.95}}}',
                '[12.99,19.95,22.99,8.95,8.99]'
            ],
            [ // data set #99
                'Dot Notation After Union',
                'dot_notation_after_union',
                '$[0,2].key',
                '[{"key":"ey"},{"key":"bee"},{"key":"see"}]',
                '["ey","see"]'
            ],
            [ // data set #100
                'Dot Notation After Union With Keys',
                'dot_notation_after_union_with_keys',
                '$[\'one\',\'three\'].key',
                '{"one":{"key":"value"},"two":{"k":"v"},"three":{"some":"more","key":"other value"}}',
                '["value","other value"]'
            ],
            [ // data set #101
                'Dot Notation On Array',
                'dot_notation_on_array',
                '$.key',
                '[0,1]',
                '[]'
            ],
            [ // data set #102
                'Dot Notation On Array Value',
                'dot_notation_on_array_value',
                '$.key',
                '{"key":["first","second"]}',
                '[["first","second"]]'
            ],
            [ // data set #103
                'Dot Notation On Array With Containing Object Matching Key',
                'dot_notation_on_array_with_containing_object_matching_key',
                '$.id',
                '[{"id":2}]',
                '[]'
            ],
            [ // data set #104
                'Dot Notation On Empty Object Value',
                'dot_notation_on_empty_object_value',
                '$.key',
                '{"key":{}}',
                '[{}]'
            ],
            [ // data set #105
                'Dot Notation On Null Value',
                'dot_notation_on_null_value',
                '$.key',
                '{"key":null}',
                '[null]'
            ],
            [ // data set #106
                'Dot Notation On Object Without Key',
                'dot_notation_on_object_without_key',
                '$.missing',
                '{"key":"value"}',
                '[]'
            ],
            [ // data set #107
                'Dot Notation With Dash',
                'dot_notation_with_dash',
                '$.key-dash',
                '{"key-dash":"value"}',
                '["value"]'
            ],
            [ // data set #108
                'Dot Notation With Double Quotes',
                'dot_notation_with_double_quotes',
                '$."key"',
                '{"key":"value","\\"key\\"":42}',
                '', // unknown consensus
            ],
            [ // data set #109
                'Dot Notation With Double Quotes After Recursive Descent',
                'dot_notation_with_double_quotes_after_recursive_descent',
                '$.."key"',
                '{"object":{"key":"value","\\"key\\"":100,"array":[{"key":"something","\\"key\\"":0},{"key":{"key":"' .
                'russian dolls"},"\\"key\\"":{"\\"key\\"":99}}]},"key":"top","\\"key\\"":42}',
                '', // unknown consensus
            ],
            [ // data set #110
                'Dot Notation With Empty Path',
                'dot_notation_with_empty_path',
                '$.',
                '{"key":42,"":9001,"\'\'":"nice"}',
                '', // unknown consensus
            ],
            [ // data set #111
                'Dot Notation With Key Named In',
                'dot_notation_with_key_named_in',
                '$.in',
                '{"in":"value"}',
                '["value"]'
            ],
            [ // data set #112
                'Dot Notation With Key Named Length',
                'dot_notation_with_key_named_length',
                '$.length',
                '{"length":"value"}',
                '["value"]'
            ],
            [ // data set #113
                'Dot Notation With Key Named Length On Array',
                'dot_notation_with_key_named_length_on_array',
                '$.length',
                '[4,5,6]',
                '[]'
            ],
            [ // data set #114
                'Dot Notation With Key Named Null',
                'dot_notation_with_key_named_null',
                '$.null',
                '{"null":"value"}',
                '["value"]'
            ],
            [ // data set #115
                'Dot Notation With Key Named True',
                'dot_notation_with_key_named_true',
                '$.true',
                '{"true":"value"}',
                '["value"]'
            ],
            [ // data set #116
                'Dot Notation With Key Root Literal',
                'dot_notation_with_key_root_literal',
                '$.$',
                '{"$":"value"}',
                '', // unknown consensus
            ],
            [ // data set #117
                'Dot Notation With Non ASCII Key',
                'dot_notation_with_non_ASCII_key',
                '$.屬性',
                '{"\\u5c6c\\u6027":"value"}',
                '["value"]'
            ],
            [ // data set #118
                'Dot Notation With Number',
                'dot_notation_with_number',
                '$.2',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #119
                'Dot Notation With Number -1',
                'dot_notation_with_number_-1',
                '$.-1',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #120
                'Dot Notation With Number On Object',
                'dot_notation_with_number_on_object',
                '$.2',
                '{"a":"first","2":"second","b":"third"}',
                '["second"]'
            ],
            [ // data set #121
                'Dot Notation With Single Quotes',
                'dot_notation_with_single_quotes',
                '$.\'key\'',
                '{"key":"value","\'key\'":42}',
                '', // unknown consensus
            ],
            [ // data set #122
                'Dot Notation With Single Quotes After Recursive Descent',
                'dot_notation_with_single_quotes_after_recursive_descent',
                '$..\'key\'',
                '{"object":{"key":"value","\'key\'":100,"array":[{"key":"something","\'key\'":0},{"key":{"key":"russ' .
                'ian dolls"},"\'key\'":{"\'key\'":99}}]},"key":"top","\'key\'":42}',
                '', // unknown consensus
            ],
            [ // data set #123
                'Dot Notation With Single Quotes And Dot',
                'dot_notation_with_single_quotes_and_dot',
                '$.\'some.key\'',
                '{"some.key":42,"some":{"key":"value"},"\'some.key\'":43}',
                '', // unknown consensus
            ],
            [ // data set #124
                'Dot Notation With Wildcard After Dot Notation After Dot Notation With Wildcard',
                'dot_notation_with_wildcard_after_dot_notation_after_dot_notation_with_wildcard',
                '$.*.bar.*',
                '[{"bar":[42]}]',
                '[42]'
            ],
            [ // data set #125
                'Dot Notation With Wildcard After Dot Notation With Wildcard On Nested Arrays',
                'dot_notation_with_wildcard_after_dot_notation_with_wildcard_on_nested_arrays',
                '$.*.*',
                '[[1,2,3],[4,5,6]]',
                '[1,2,3,4,5,6]'
            ],
            [ // data set #126
                'Dot Notation With Wildcard After Recursive Descent',
                'dot_notation_with_wildcard_after_recursive_descent',
                '$..*',
                '{"key":"value","another key":{"complex":"string","primitives":[0,1]}}',
                '["string","value",0,1,[0,1],{"complex":"string","primitives":[0,1]}]'
            ],
            [ // data set #127
                'Dot Notation With Wildcard After Recursive Descent On Null Value Array',
                'dot_notation_with_wildcard_after_recursive_descent_on_null_value_array',
                '$..*',
                '[40,null,42]',
                '[40,42,null]'
            ],
            [ // data set #128
                'Dot Notation With Wildcard After Recursive Descent On Scalar',
                'dot_notation_with_wildcard_after_recursive_descent_on_scalar',
                '$..*',
                '42',
                '[]'
            ],
            [ // data set #129
                'Dot Notation With Wildcard On Array',
                'dot_notation_with_wildcard_on_array',
                '$.*',
                '["string",42,{"key":"value"},[0,1]]',
                '["string",42,{"key":"value"},[0,1]]'
            ],
            [ // data set #130
                'Dot Notation With Wildcard On Empty Array',
                'dot_notation_with_wildcard_on_empty_array',
                '$.*',
                '[]',
                '[]'
            ],
            [ // data set #131
                'Dot Notation With Wildcard On Empty Object',
                'dot_notation_with_wildcard_on_empty_object',
                '$.*',
                '{}',
                '[]'
            ],
            [ // data set #132
                'Dot Notation With Wildcard On Object',
                'dot_notation_with_wildcard_on_object',
                '$.*',
                '{"some":"string","int":42,"object":{"key":"value"},"array":[0,1]}',
                '["string",42,[0,1],{"key":"value"}]'
            ],
            [ // data set #133
                'Dot Notation Without Root',
                'dot_notation_without_root',
                'key',
                '{"key":"value"}',
                '', // unknown consensus
            ],
            [ // data set #134
                'Filter Expression After Dot Notation With Wildcard After Recursive Descent',
                'filter_expression_after_dot_notation_with_wildcard_after_recursive_descent',
                '$..*[?(@.id>2)]',
                '[{"complext":{"one":[{"name":"first","id":1},{"name":"next","id":2},{"name":"another","id":3},{"nam' .
                'e":"more","id":4}],"more":{"name":"next to last","id":5}}},{"name":"last","id":6}]',
                '', // unknown consensus
            ],
            [ // data set #135
                'Filter Expression After Recursive Descent',
                'filter_expression_after_recursive_descent',
                '$..[?(@.id==2)]',
                '{"id":2,"more":[{"id":2},{"more":{"id":2}},{"id":{"id":2}},[{"id":2}]]}',
                '', // unknown consensus
            ],
            [ // data set #136
                'Filter Expression On Object',
                'filter_expression_on_object',
                '$[?(@.key)]',
                '{"key":42,"another":{"key":1}}',
                '', // unknown consensus
            ],
            [ // data set #137
                'Filter Expression With Addition',
                'filter_expression_with_addition',
                '$[?(@.key+50==100)]',
                '[{"key":60},{"key":50},{"key":10},{"key":-50},{"key+50":100}]',
                '', // unknown consensus
            ],
            [ // data set #138
                'Filter Expression With Boolean And Operator',
                'filter_expression_with_boolean_and_operator',
                '$[?(@.key>42 && @.key<44)]',
                '[{"key":42},{"key":43},{"key":44}]',
                '', // unknown consensus
            ],
            [ // data set #139
                'Filter Expression With Boolean And Operator And Value False',
                'filter_expression_with_boolean_and_operator_and_value_false',
                '$[?(@.key>0 && false)]',
                '[{"key":1},{"key":3},{"key":"nice"},{"key":true},{"key":null},{"key":false},{"key":{}},{"key":[]},{' .
                '"key":-1},{"key":0},{"key":""}]',
                '', // unknown consensus
            ],
            [ // data set #140
                'Filter Expression With Boolean And Operator And Value True',
                'filter_expression_with_boolean_and_operator_and_value_true',
                '$[?(@.key>0 && true)]',
                '[{"key":1},{"key":3},{"key":"nice"},{"key":true},{"key":null},{"key":false},{"key":{}},{"key":[]},{' .
                '"key":-1},{"key":0},{"key":""}]',
                '', // unknown consensus
            ],
            [ // data set #141
                'Filter Expression With Boolean Or Operator',
                'filter_expression_with_boolean_or_operator',
                '$[?(@.key>43 || @.key<43)]',
                '[{"key":42},{"key":43},{"key":44}]',
                '', // unknown consensus
            ],
            [ // data set #142
                'Filter Expression With Boolean Or Operator And Value False',
                'filter_expression_with_boolean_or_operator_and_value_false',
                '$[?(@.key>0 || false)]',
                '[{"key":1},{"key":3},{"key":"nice"},{"key":true},{"key":null},{"key":false},{"key":{}},{"key":[]},{' .
                '"key":-1},{"key":0},{"key":""}]',
                '', // unknown consensus
            ],
            [ // data set #143
                'Filter Expression With Boolean Or Operator And Value True',
                'filter_expression_with_boolean_or_operator_and_value_true',
                '$[?(@.key>0 || true)]',
                '[{"key":1},{"key":3},{"key":"nice"},{"key":true},{"key":null},{"key":false},{"key":{}},{"key":[]},{' .
                '"key":-1},{"key":0},{"key":""}]',
                '', // unknown consensus
            ],
            [ // data set #144
                'Filter Expression With Bracket Notation',
                'filter_expression_with_bracket_notation',
                '$[?(@[\'key\']==42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"key":100},' .
                '{"some":"value"}]',
                '[{"key":42}]'
            ],
            [ // data set #145
                'Filter Expression With Bracket Notation And Current Object Literal',
                'filter_expression_with_bracket_notation_and_current_object_literal',
                '$[?(@[\'@key\']==42)]',
                '[{"@key":0},{"@key":42},{"key":42},{"@key":43},{"some":"value"}]',
                '[{"@key":42}]'
            ],
            [ // data set #146
                'Filter Expression With Bracket Notation With -1',
                'filter_expression_with_bracket_notation_with_-1',
                '$[?(@[-1]==2)]',
                '[[2,3],["a"],[0,2],[2]]',
                '', // unknown consensus
            ],
            [ // data set #147
                'Filter Expression With Bracket Notation With Number',
                'filter_expression_with_bracket_notation_with_number',
                '$[?(@[1]==\'b\')]',
                '[["a","b"],["x","y"]]',
                '[["a","b"]]'
            ],
            [ // data set #148
                'Filter Expression With Bracket Notation With Number On Object',
                'filter_expression_with_bracket_notation_with_number_on_object',
                '$[?(@[1]==\'b\')]',
                '{"1":["a","b"],"2":["x","y"]}',
                '', // unknown consensus
            ],
            [ // data set #149
                'Filter Expression With Current Object',
                'filter_expression_with_current_object',
                '$[?(@)]',
                '["some value",null,"value",0,1,-1,"",[],{},false,true]',
                '', // unknown consensus
            ],
            [ // data set #150
                'Filter Expression With Different Grouped Operators',
                'filter_expression_with_different_grouped_operators',
                '$[?(@.a && (@.b || @.c))]',
                '[{"a":true},{"a":true,"b":true},{"a":true,"b":true,"c":true},{"b":true,"c":true},{"a":true,"c":true' .
                '},{"c":true},{"b":true}]',
                '', // unknown consensus
            ],
            [ // data set #151
                'Filter Expression With Different Ungrouped Operators',
                'filter_expression_with_different_ungrouped_operators',
                '$[?(@.a && @.b || @.c)]',
                '[{"a":true,"b":true},{"a":true,"b":true,"c":true},{"b":true,"c":true},{"a":true,"c":true},{"a":true' .
                '},{"b":true},{"c":true},{"d":true},{}]',
                '', // unknown consensus
            ],
            [ // data set #152
                'Filter Expression With Division',
                'filter_expression_with_division',
                '$[?(@.key/10==5)]',
                '[{"key":60},{"key":50},{"key":10},{"key":-50},{"key\\/10":5}]',
                '', // unknown consensus
            ],
            [ // data set #153
                'Filter Expression With Empty Expression',
                'filter_expression_with_empty_expression',
                '$[?()]',
                '[1,{"key":42},"value",null]',
                '', // unknown consensus
            ],
            [ // data set #154
                'Filter Expression With Equals',
                'filter_expression_with_equals',
                '$[?(@.key==42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"' .
                'key":100},{"key":"some"},{"key":"42"},{"key":null},{"key":420},{"key":""},{"key":{}},{"key":[]},{"k' .
                'ey":[42]},{"key":{"key":42}},{"key":{"some":42}},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #155
                'Filter Expression With Equals Array',
                'filter_expression_with_equals_array',
                '$[?(@.d==["v1","v2"])]',
                '[{"d":["v1","v2"]},{"d":["a","b"]},{"d":"v1"},{"d":"v2"},{"d":{}},{"d":[]},{"d":null},{"d":-1},{"d"' .
                ':0},{"d":1},{"d":"[\'v1\',\'v2\']"},{"d":"[\'v1\', \'v2\']"},{"d":"v1,v2"},{"d":"[\\"v1\\", \\"v2\\' .
                '"]"},{"d":"[\\"v1\\",\\"v2\\"]"}]',
                '', // unknown consensus
            ],
            [ // data set #156
                'Filter Expression With Equals Array For Array Slice With Range 1',
                'filter_expression_with_equals_array_for_array_slice_with_range_1',
                '$[?(@[0:1]==[1])]',
                '[[1,2,3],[1],[2,3],1,2]',
                '', // unknown consensus
            ],
            [ // data set #157
                'Filter Expression With Equals Array For Dot Notation With Star',
                'filter_expression_with_equals_array_for_dot_notation_with_star',
                '$[?(@.*==[1,2])]',
                '[[1,2],[2,3],[1],[2],[1,2,3],1,2,3]',
                '', // unknown consensus
            ],
            [ // data set #158
                'Filter Expression With Equals Array With Single Quotes',
                'filter_expression_with_equals_array_with_single_quotes',
                '$[?(@.d==[\'v1\',\'v2\'])]',
                '[{"d":["v1","v2"]},{"d":["a","b"]},{"d":"v1"},{"d":"v2"},{"d":{}},{"d":[]},{"d":null},{"d":-1},{"d"' .
                ':0},{"d":1},{"d":"[\'v1\',\'v2\']"},{"d":"[\'v1\', \'v2\']"},{"d":"v1,v2"},{"d":"[\\"v1\\", \\"v2\\' .
                '"]"},{"d":"[\\"v1\\",\\"v2\\"]"}]',
                '', // unknown consensus
            ],
            [ // data set #159
                'Filter Expression With Equals Boolean Expression Value',
                'filter_expression_with_equals_boolean_expression_value',
                '$[?((@.key<44)==false)]',
                '[{"key":42},{"key":43},{"key":44}]',
                '', // unknown consensus
            ],
            [ // data set #160
                'Filter Expression With Equals False',
                'filter_expression_with_equals_false',
                '$[?(@.key==false)]',
                '[{"some":"some value"},{"key":true},{"key":false},{"key":null},{"key":"value"},{"key":""},{"key":0}' .
                ',{"key":1},{"key":-1},{"key":42},{"key":{}},{"key":[]}]',
                '', // unknown consensus
            ],
            [ // data set #161
                'Filter Expression With Equals Null',
                'filter_expression_with_equals_null',
                '$[?(@.key==null)]',
                '[{"some":"some value"},{"key":true},{"key":false},{"key":null},{"key":"value"},{"key":""},{"key":0}' .
                ',{"key":1},{"key":-1},{"key":42},{"key":{}},{"key":[]}]',
                '', // unknown consensus
            ],
            [ // data set #162
                'Filter Expression With Equals Number For Array Slice With Range 1',
                'filter_expression_with_equals_number_for_array_slice_with_range_1',
                '$[?(@[0:1]==1)]',
                '[[1,2,3],[1],[2,3],1,2]',
                '', // unknown consensus
            ],
            [ // data set #163
                'Filter Expression With Equals Number For Bracket Notation With Star',
                'filter_expression_with_equals_number_for_bracket_notation_with_star',
                '$[?(@[*]==2)]',
                '[[1,2],[2,3],[1],[2],[1,2,3],1,2,3]',
                '', // unknown consensus
            ],
            [ // data set #164
                'Filter Expression With Equals Number For Dot Notation With Star',
                'filter_expression_with_equals_number_for_dot_notation_with_star',
                '$[?(@.*==2)]',
                '[[1,2],[2,3],[1],[2],[1,2,3],1,2,3]',
                '', // unknown consensus
            ],
            [ // data set #165
                'Filter Expression With Equals Number With Fraction',
                'filter_expression_with_equals_number_with_fraction',
                '$[?(@.key==-0.123e2)]',
                '[{"key":-12.3},{"key":-0.123},{"key":-12},{"key":12.3},{"key":2},{"key":"-0.123e2"}]',
                '', // unknown consensus
            ],
            [ // data set #166
                'Filter Expression With Equals Number With Leading Zeros',
                'filter_expression_with_equals_number_with_leading_zeros',
                '$[?(@.key==010)]',
                '[{"key":"010"},{"key":"10"},{"key":10},{"key":0},{"key":8}]',
                '', // unknown consensus
            ],
            [ // data set #167
                'Filter Expression With Equals Object',
                'filter_expression_with_equals_object',
                '$[?(@.d=={"k":"v"})]',
                '[{"d":{"k":"v"}},{"d":{"a":"b"}},{"d":"k"},{"d":"v"},{"d":{}},{"d":[]},{"d":null},{"d":-1},{"d":0},' .
                '{"d":1},{"d":"[object Object]"},{"d":"{\\"k\\": \\"v\\"}"},{"d":"{\\"k\\":\\"v\\"}"},"v"]',
                '', // unknown consensus
            ],
            [ // data set #168
                'Filter Expression With Equals On Array Of Numbers',
                'filter_expression_with_equals_on_array_of_numbers',
                '$[?(@==42)]',
                '[0,42,-1,41,43,42.0001,41.9999,null,100]',
                '', // unknown consensus
            ],
            [ // data set #169
                'Filter Expression With Equals On Array Without Match',
                'filter_expression_with_equals_on_array_without_match',
                '$[?(@.key==43)]',
                '[{"key":42}]',
                '[]'
            ],
            [ // data set #170
                'Filter Expression With Equals On Object',
                'filter_expression_with_equals_on_object',
                '$[?(@.key==42)]',
                '{"a":{"key":0},"b":{"key":42},"c":{"key":-1},"d":{"key":41},"e":{"key":43},"f":{"key":42.0001},"g":' .
                '{"key":41.9999},"h":{"key":100},"i":{"some":"value"}}',
                '', // unknown consensus
            ],
            [ // data set #171
                'Filter Expression With Equals On Object With Key Matching Query',
                'filter_expression_with_equals_on_object_with_key_matching_query',
                '$[?(@.id==2)]',
                '{"id":2}',
                '', // unknown consensus
            ],
            [ // data set #172
                'Filter Expression With Equals String',
                'filter_expression_with_equals_string',
                '$[?(@.key=="value")]',
                '[{"key":"some"},{"key":"value"},{"key":null},{"key":0},{"key":1},{"key":-1},{"key":""},{"key":{}},{' .
                '"key":[]},{"key":"valuemore"},{"key":"morevalue"},{"key":["value"]},{"key":{"some":"value"}},{"key"' .
                ':{"key":"value"}},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #173
                'Filter Expression With Equals String With Current Object Literal',
                'filter_expression_with_equals_string_with_current_object_literal',
                '$[?(@.key=="hi@example.com")]',
                '[{"key":"some"},{"key":"value"},{"key":"hi@example.com"}]',
                '[{"key":"hi@example.com"}]'
            ],
            [ // data set #174
                'Filter Expression With Equals String With Dot Literal',
                'filter_expression_with_equals_string_with_dot_literal',
                '$[?(@.key=="some.value")]',
                '[{"key":"some"},{"key":"value"},{"key":"some.value"}]',
                '[{"key":"some.value"}]'
            ],
            [ // data set #175
                'Filter Expression With Equals String With Single Quotes',
                'filter_expression_with_equals_string_with_single_quotes',
                '$[?(@.key==\'value\')]',
                '[{"key":"some"},{"key":"value"}]',
                '[{"key":"value"}]'
            ],
            [ // data set #176
                'Filter Expression With Equals True',
                'filter_expression_with_equals_true',
                '$[?(@.key==true)]',
                '[{"some":"some value"},{"key":true},{"key":false},{"key":null},{"key":"value"},{"key":""},{"key":0}' .
                ',{"key":1},{"key":-1},{"key":42},{"key":{}},{"key":[]}]',
                '', // unknown consensus
            ],
            [ // data set #177
                'Filter Expression With Equals With Root Reference',
                'filter_expression_with_equals_with_root_reference',
                '$.items[?(@.key==$.value)]',
                '{"value":42,"items":[{"key":10},{"key":42},{"key":50}]}',
                '', // unknown consensus
            ],
            [ // data set #178
                'Filter Expression With Greater Than',
                'filter_expression_with_greater_than',
                '$[?(@.key>42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"key":100},' .
                '{"key":"43"},{"key":"42"},{"key":"41"},{"key":"value"},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #179
                'Filter Expression With Greater Than Or Equal',
                'filter_expression_with_greater_than_or_equal',
                '$[?(@.key>=42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"key":100},' .
                '{"key":"43"},{"key":"42"},{"key":"41"},{"key":"value"},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #180
                'Filter Expression With In Array Of Values',
                'filter_expression_with_in_array_of_values',
                '$[?(@.d in [2, 3])]',
                '[{"d":1},{"d":2},{"d":1},{"d":3},{"d":4}]',
                '', // unknown consensus
            ],
            [ // data set #181
                'Filter Expression With In Current Object',
                'filter_expression_with_in_current_object',
                '$[?(2 in @.d)]',
                '[{"d":[1,2,3]},{"d":[2]},{"d":[1]},{"d":[3,4]},{"d":[4,2]}]',
                '', // unknown consensus
            ],
            [ // data set #182
                'Filter Expression With Less Than',
                'filter_expression_with_less_than',
                '$[?(@.key<42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"key":100},' .
                '{"key":"43"},{"key":"42"},{"key":"41"},{"key":"value"},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #183
                'Filter Expression With Less Than Or Equal',
                'filter_expression_with_less_than_or_equal',
                '$[?(@.key<=42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"key":100},' .
                '{"key":"43"},{"key":"42"},{"key":"41"},{"key":"value"},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #184
                'Filter Expression With Multiplication',
                'filter_expression_with_multiplication',
                '$[?(@.key*2==100)]',
                '[{"key":60},{"key":50},{"key":10},{"key":-50},{"key*2":100}]',
                '', // unknown consensus
            ],
            [ // data set #185
                'Filter Expression With Negation And Equals',
                'filter_expression_with_negation_and_equals',
                '$[?(!(@.key==42))]',
                '[{"key":0},{"key":42},{"key":-1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"key":100},' .
                '{"key":"43"},{"key":"42"},{"key":"41"},{"key":"value"},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #186
                'Filter Expression With Negation And Less Than',
                'filter_expression_with_negation_and_less_than',
                '$[?(!(@.key<42))]',
                '[{"key":0},{"key":42},{"key":-1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"key":100},' .
                '{"key":"43"},{"key":"42"},{"key":"41"},{"key":"value"},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #187
                'Filter Expression With Not Equals',
                'filter_expression_with_not_equals',
                '$[?(@.key!=42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"' .
                'key":100},{"key":"some"},{"key":"42"},{"key":null},{"key":420},{"key":""},{"key":{}},{"key":[]},{"k' .
                'ey":[42]},{"key":{"key":42}},{"key":{"some":42}},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #188
                'Filter Expression With Regular Expression',
                'filter_expression_with_regular_expression',
                '$[?(@.name=~/hello.*/)]',
                '[{"name":"hullo world"},{"name":"hello world"},{"name":"yes hello world"},{"name":"HELLO WORLD"},{"' .
                'name":"good bye"}]',
                '', // unknown consensus
            ],
            [ // data set #189
                'Filter Expression With Set Wise Comparison To Scalar',
                'filter_expression_with_set_wise_comparison_to_scalar',
                '$[?(@[*]>=4)]',
                '[[1,2],[3,4],[5,6]]',
                '', // unknown consensus
            ],
            [ // data set #190
                'Filter Expression With Set Wise Comparison To Set',
                'filter_expression_with_set_wise_comparison_to_set',
                '$.x[?(@[*]>=$.y[*])]',
                '{"x":[[1,2],[3,4],[5,6]],"y":[3,4,5]}',
                '', // unknown consensus
            ],
            [ // data set #191
                'Filter Expression With Single Equal',
                'filter_expression_with_single_equal',
                '$[?(@.key=42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"' .
                'key":100},{"key":"some"},{"key":"42"},{"key":null},{"key":420},{"key":""},{"key":{}},{"key":[]},{"k' .
                'ey":[42]},{"key":{"key":42}},{"key":{"some":42}},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #192
                'Filter Expression With Subfilter',
                'filter_expression_with_subfilter',
                '$[?(@.a[?(@.price>10)])]',
                '[{"a":[{"price":1},{"price":3}]},{"a":[{"price":11}]},{"a":[{"price":8},{"price":12},{"price":3}]},' .
                '{"a":[]}]',
                '', // unknown consensus
            ],
            [ // data set #193
                'Filter Expression With Subpaths',
                'filter_expression_with_subpaths',
                '$[?(@.address.city==\'Berlin\')]',
                '[{"address":{"city":"Berlin"}},{"address":{"city":"London"}}]',
                '[{"address":{"city":"Berlin"}}]'
            ],
            [ // data set #194
                'Filter Expression With Subtraction',
                'filter_expression_with_subtraction',
                '$[?(@.key-50==-100)]',
                '[{"key":60},{"key":50},{"key":10},{"key":-50},{"key-50":-100}]',
                '', // unknown consensus
            ],
            [ // data set #195
                'Filter Expression With Tautological Comparison',
                'filter_expression_with_tautological_comparison',
                '$[?(1==1)]',
                '[1,3,"nice",true,null,false,{},[],-1,0,""]',
                '', // unknown consensus
            ],
            [ // data set #196
                'Filter Expression With Triple Equal',
                'filter_expression_with_triple_equal',
                '$[?(@.key===42)]',
                '[{"key":0},{"key":42},{"key":-1},{"key":1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"' .
                'key":100},{"key":"some"},{"key":"42"},{"key":null},{"key":420},{"key":""},{"key":{}},{"key":[]},{"k' .
                'ey":[42]},{"key":{"key":42}},{"key":{"some":42}},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #197
                'Filter Expression With Value',
                'filter_expression_with_value',
                '$[?(@.key)]',
                '[{"some":"some value"},{"key":true},{"key":false},{"key":null},{"key":"value"},{"key":""},{"key":0}' .
                ',{"key":1},{"key":-1},{"key":42},{"key":{}},{"key":[]}]',
                '', // unknown consensus
            ],
            [ // data set #198
                'Filter Expression With Value After Dot Notation With Wildcard On Array Of Objects',
                'filter_expression_with_value_after_dot_notation_with_wildcard_on_array_of_objects',
                '$.*[?(@.key)]',
                '[{"some":"some value"},{"key":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #199
                'Filter Expression With Value After Recursive Descent',
                'filter_expression_with_value_after_recursive_descent',
                '$..[?(@.id)]',
                '{"id":2,"more":[{"id":2},{"more":{"id":2}},{"id":{"id":2}},[{"id":2}]]}',
                '', // unknown consensus
            ],
            [ // data set #200
                'Filter Expression With Value False',
                'filter_expression_with_value_false',
                '$[?(false)]',
                '[1,3,"nice",true,null,false,{},[],-1,0,""]',
                '', // unknown consensus
            ],
            [ // data set #201
                'Filter Expression With Value From Recursive Descent',
                'filter_expression_with_value_from_recursive_descent',
                '$[?(@..child)]',
                '[{"key":[{"child":1},{"child":2}]},{"key":[{"child":2}]},{"key":[{}]},{"key":[{"something":42}]},{}' .
                ']',
                '', // unknown consensus
            ],
            [ // data set #202
                'Filter Expression With Value Null',
                'filter_expression_with_value_null',
                '$[?(null)]',
                '[1,3,"nice",true,null,false,{},[],-1,0,""]',
                '', // unknown consensus
            ],
            [ // data set #203
                'Filter Expression With Value True',
                'filter_expression_with_value_true',
                '$[?(true)]',
                '[1,3,"nice",true,null,false,{},[],-1,0,""]',
                '', // unknown consensus
            ],
            [ // data set #204
                'Filter Expression Without Parens',
                'filter_expression_without_parens',
                '$[?@.key==42]',
                '[{"key":0},{"key":42},{"key":-1},{"key":1},{"key":41},{"key":43},{"key":42.0001},{"key":41.9999},{"' .
                'key":100},{"key":"some"},{"key":"42"},{"key":null},{"key":420},{"key":""},{"key":{}},{"key":[]},{"k' .
                'ey":[42]},{"key":{"key":42}},{"key":{"some":42}},{"some":"value"}]',
                '', // unknown consensus
            ],
            [ // data set #205
                'Filter Expression Without Value',
                'filter_expression_without_value',
                '$[?(!@.key)]',
                '[{"some":"some value"},{"key":true},{"key":false},{"key":null},{"key":"value"},{"key":""},{"key":0}' .
                ',{"key":1},{"key":-1},{"key":42},{"key":{}},{"key":[]}]',
                '', // unknown consensus
            ],
            [ // data set #206
                'Parens Notation',
                'parens_notation',
                '$(key,more)',
                '{"key":1,"some":2,"more":3}',
                '', // unknown consensus
            ],
            [ // data set #207
                'Recursive Descent',
                'recursive_descent',
                '$..',
                '[{"a":{"b":"c"}},[0,1]]',
                '', // unknown consensus
            ],
            [ // data set #208
                'Recursive Descent After Dot Notation',
                'recursive_descent_after_dot_notation',
                '$.key..',
                '{"some key":"value","key":{"complex":"string","primitives":[0,1]}}',
                '', // unknown consensus
            ],
            [ // data set #209
                'Root',
                'root',
                '$',
                '{"key":"value","another key":{"complex":["a",1]}}',
                '[{"another key":{"complex":["a",1]},"key":"value"}]'
            ],
            [ // data set #210
                'Root On Scalar',
                'root_on_scalar',
                '$',
                '42',
                '[42]'
            ],
            [ // data set #211
                'Root On Scalar False',
                'root_on_scalar_false',
                '$',
                'false',
                '[false]'
            ],
            [ // data set #212
                'Root On Scalar True',
                'root_on_scalar_true',
                '$',
                'true',
                '[true]'
            ],
            [ // data set #213
                'Script Expression',
                'script_expression',
                '$[(@.length-1)]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
            [ // data set #214
                'Union',
                'union',
                '$[0,1]',
                '["first","second","third"]',
                '["first","second"]'
            ],
            [ // data set #215
                'Union With Filter',
                'union_with_filter',
                '$[?(@.key<3),?(@.key>6)]',
                '[{"key":1},{"key":8},{"key":3},{"key":10},{"key":7},{"key":2},{"key":6},{"key":4}]',
                '', // unknown consensus
            ],
            [ // data set #216
                'Union With Keys',
                'union_with_keys',
                '$[\'key\',\'another\']',
                '{"key":"value","another":"entry"}',
                '["value","entry"]'
            ],
            [ // data set #217
                'Union With Keys After Array Slice',
                'union_with_keys_after_array_slice',
                '$[:][\'c\',\'d\']',
                '[{"c":"cc1","d":"dd1","e":"ee1"},{"c":"cc2","d":"dd2","e":"ee2"}]',
                '["cc1","dd1","cc2","dd2"]'
            ],
            [ // data set #218
                'Union With Keys After Bracket Notation',
                'union_with_keys_after_bracket_notation',
                '$[0][\'c\',\'d\']',
                '[{"c":"cc1","d":"dd1","e":"ee1"},{"c":"cc2","d":"dd2","e":"ee2"}]',
                '["cc1","dd1"]'
            ],
            [ // data set #219
                'Union With Keys After Dot Notation With Wildcard',
                'union_with_keys_after_dot_notation_with_wildcard',
                '$.*[\'c\',\'d\']',
                '[{"c":"cc1","d":"dd1","e":"ee1"},{"c":"cc2","d":"dd2","e":"ee2"}]',
                '', // unknown consensus
            ],
            [ // data set #220
                'Union With Keys After Recursive Descent',
                'union_with_keys_after_recursive_descent',
                '$..[\'c\',\'d\']',
                '[{"c":"cc1","d":"dd1","e":"ee1"},{"c":"cc2","child":{"d":"dd2"}},{"c":"cc3"},{"d":"dd4"},{"child":{' .
                '"c":"cc5"}}]',
                '', // unknown consensus
            ],
            [ // data set #221
                'Union With Keys On Object Without Key',
                'union_with_keys_on_object_without_key',
                '$[\'missing\',\'key\']',
                '{"key":"value","another":"entry"}',
                '["value"]'
            ],
            [ // data set #222
                'Union With Numbers In Decreasing Order',
                'union_with_numbers_in_decreasing_order',
                '$[4,1]',
                '[1,2,3,4,5]',
                '[5,2]'
            ],
            [ // data set #223
                'Union With Repeated Matches After Dot Notation With Wildcard',
                'union_with_repeated_matches_after_dot_notation_with_wildcard',
                '$.*[0,:5]',
                '{"a":["string",null,true],"b":[false,"string",5.4]}',
                '', // unknown consensus
            ],
            [ // data set #224
                'Union With Slice And Number',
                'union_with_slice_and_number',
                '$[1:3,4]',
                '[1,2,3,4,5]',
                '', // unknown consensus
            ],
            [ // data set #225
                'Union With Spaces',
                'union_with_spaces',
                '$[ 0 , 1 ]',
                '["first","second","third"]',
                '["first","second"]'
            ],
            [ // data set #226
                'Union With Wildcard And Number',
                'union_with_wildcard_and_number',
                '$[*,1]',
                '["first","second","third","forth","fifth"]',
                '', // unknown consensus
            ],
        ];
    }
}
