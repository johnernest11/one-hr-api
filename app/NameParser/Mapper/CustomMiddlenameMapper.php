<?php

namespace App\NameParser\Mapper;

use App\NameParser\Part\MiddlenamePrefix;
use TheIconic\NameParser\Mapper\MiddlenameMapper;
use TheIconic\NameParser\Part\AbstractPart;
use TheIconic\NameParser\Part\Initial;
use TheIconic\NameParser\Part\Lastname;
use TheIconic\NameParser\Part\LastnamePrefix;
use TheIconic\NameParser\Part\Middlename;
use TheIconic\NameParser\Part\Nickname;
use TheIconic\NameParser\Part\Salutation;
use TheIconic\NameParser\Part\Suffix;

/**
 * Customized to handle the middle name pattern in the Philippines wherein
 * the middle name is typically the mother's maiden surname.
 *
 * Therefore, the middle name has to be treated similar to the last name.
 */
class CustomMiddlenameMapper extends MiddlenameMapper
{
    protected $prefixes = [];

    protected $matchSinglePart = false;

    public function __construct(array $prefixes, bool $matchSinglePart = false)
    {
        $this->prefixes = $prefixes;
        $this->matchSinglePart = $matchSinglePart;
    }

    public function map(array $parts): array
    {
        // Should be skipped if there is initials
        if ($this->hasInitial($parts)) {
            return $parts;
        }

        if (! $this->matchSinglePart && count($parts) < 2) {
            return $parts;
        }

        return $this->mapParts($parts);
    }

    /**
     * we map the parts in reverse order because it makes more
     * sense to parse for the middlename starting from the end
     */
    protected function mapParts(array $parts): array
    {
        $k = $this->skipIgnoredParts($parts) + 1;
        $remapIgnored = true;

        while (--$k >= 0) {
            $part = $parts[$k];

            if ($part instanceof AbstractPart) {
                break;
            }

            if ($this->isFollowedByMiddlenamePart($parts, $k)) {
                if ($mapped = $this->mapAsPrefixIfPossible($parts, $k)) {
                    $parts[$k] = $mapped;

                    continue;
                }

                if ($this->shouldStopMapping($parts, $k)) {
                    break;
                }
            }

            $parts[$k] = new Middlename($part);
            $remapIgnored = false;
        }

        if ($remapIgnored) {
            $parts = $this->remapIgnored($parts);
        }

        return $parts;
    }

    /**
     * try to map this part as a middlename prefix or as a combined
     * middlename part containing a prefix
     */
    private function mapAsPrefixIfPossible(array $parts, int $k): ?Middlename
    {
        if ($this->isApplicablePrefix($parts, $k)) {
            return new MiddlenamePrefix($parts[$k], $this->prefixes[$this->getKey($parts[$k])]);
        }

        if ($this->isCombinedWithPrefix($parts[$k])) {
            return new Middlename($parts[$k]);
        }

        return null;
    }

    /**
     * check if the given part is a combined middlename part
     * that ends in a middlename prefix
     */
    private function isCombinedWithPrefix(string $part): bool
    {
        $pos = strpos($part, '-');

        if ($pos === false) {
            return false;
        }

        return $this->isPrefix(substr($part, $pos + 1));
    }

    /**
     * skip through the parts we want to ignore and return the start index
     */
    protected function skipIgnoredParts(array $parts): int
    {
        $k = count($parts);

        while (--$k >= 0) {
            if (! $this->isIgnoredPart($parts[$k])) {
                break;
            }
        }

        return $k;
    }

    /**
     * indicates if we should stop mapping at the given index $k
     *
     * the assumption is that middlename parts have already been found
     * but we want to see if we should add more parts
     */
    protected function shouldStopMapping(array $parts, int $k): bool
    {
        if ($k < 1) {
            return true;
        }

        $lastPart = $parts[$k + 1];

        if ($lastPart instanceof MiddlenamePrefix) {
            return true;
        }

        return strlen($lastPart->getValue()) >= 3;
    }

    /**
     * indicates if the given part should be ignored (skipped) during mapping.
     * we will be ignoring lastname in this case as well as any lastname prefixes.
     *
     * @return bool
     */
    protected function isIgnoredPart($part)
    {
        return $part instanceof Suffix || $part instanceof Nickname || $part instanceof Salutation || $part instanceof Lastname || $part instanceof LastnamePrefix || $part instanceof Initial;
    }

    /**
     * remap ignored parts as middlename
     *
     * if the mapping did not derive any lastname this is called to transform
     * any previously ignored parts into middlename parts
     */
    protected function remapIgnored(array $parts): array
    {
        $k = count($parts);

        while (--$k >= 0) {
            $part = $parts[$k];

            if (! $this->isIgnoredPart($part)) {
                break;
            }

            $parts[$k] = new Middlename($part);
        }

        return $parts;
    }

    protected function isFollowedByMiddlenamePart(array $parts, int $index): bool
    {
        $next = $this->skipNicknameParts($parts, $index + 1);

        return isset($parts[$next]) && $parts[$next] instanceof Middlename;
    }

    /**
     * @param  int  $index
     */
    protected function hasInitial(array $parts): bool
    {
        $length = count($parts);

        for ($i = 0; $i < $length; $i++) {
            if ($parts[$i] instanceof Initial) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assuming that the part at the given index is matched as a prefix,
     * determines if the prefix should be applied to the middlename.
     *
     * We only apply it to the middlename if we already have at least one
     * middlename part and there are other parts left in
     * the name (this effectively prioritises firstname over prefix matching).
     *
     * This expects the parts array and index to be in the original order.
     */
    protected function isApplicablePrefix(array $parts, int $index): bool
    {
        if (! $this->isPrefix($parts[$index])) {
            return false;
        }

        return $this->hasUnmappedPartsBefore($parts, $index);
    }

    /**
     * check if the given word is a lastname prefix
     *
     * @param  string  $word  the word to check
     */
    protected function isPrefix($word): bool
    {
        return array_key_exists($this->getKey($word), $this->prefixes);
    }

    /**
     * find the next non-nickname index in parts
     *
     * @return int|void
     */
    protected function skipNicknameParts($parts, $startIndex)
    {
        $total = count($parts);

        for ($i = $startIndex; $i < $total; $i++) {
            if (! ($parts[$i] instanceof Nickname)) {
                return $i;
            }
        }

        return $total - 1;
    }
}
