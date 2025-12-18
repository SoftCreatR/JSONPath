<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;

class SliceFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     */
    public function filter(array|object $collection): array
    {
        $length = \count($collection);
        $start = $this->token->value['start'];
        $end = $this->token->value['end'];
        $step = $this->token->value['step'] ?? 1;
        $result = [];

        if ($step === 0) {
            return $result;
        }

        if ($step > 0) {
            [$start, $end] = $this->normalizeForPositiveStep($length, $start, $end);

            for ($i = $start; $i < $end; $i += $step) {
                if (AccessHelper::keyExists($collection, $i, $this->magicIsAllowed)) {
                    $result[] = $collection[$i];
                }
            }

            return $result;
        }

        [$start, $end] = $this->normalizeForNegativeStep($length, $start, $end);

        for ($i = $start; $i > $end; $i += $step) {
            if (AccessHelper::keyExists($collection, $i, $this->magicIsAllowed)) {
                $result[] = $collection[$i];
            }
        }

        return $result;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function normalizeForPositiveStep(int $length, ?int $start, ?int $end): array
    {
        if ($start === null) {
            $start = 0;
        } elseif ($start < 0) {
            $start += $length;
        }

        if ($start < 0) {
            $start = 0;
        } elseif ($start > $length) {
            $start = $length;
        }

        if ($end === null) {
            $end = $length;
        } elseif ($end < 0) {
            $end += $length;
        }

        if ($end < 0) {
            $end = 0;
        } elseif ($end > $length) {
            $end = $length;
        }

        return [$start, $end];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function normalizeForNegativeStep(int $length, ?int $start, ?int $end): array
    {
        if ($start === null) {
            $start = $length - 1;
        } else {
            if ($start < 0) {
                $start += $length;
            }

            if ($start < 0) {
                $start = -1;
            } elseif ($start >= $length) {
                $start = $length - 1;
            }
        }

        if ($end === null) {
            $end = -1;
        } else {
            if ($end < 0) {
                $end += $length;
            }

            if ($end < 0) {
                $end = -1;
            } elseif ($end >= $length) {
                $end = $length - 1;
            }
        }

        return [$start, $end];
    }
}
