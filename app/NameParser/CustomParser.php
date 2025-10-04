<?php

namespace App\NameParser;

use App\NameParser\Mapper\CustomFirstnameMapper;
use App\NameParser\Mapper\CustomMiddlenameMapper;
use TheIconic\NameParser\Mapper\InitialMapper;
use TheIconic\NameParser\Mapper\LastnameMapper;
use TheIconic\NameParser\Mapper\NicknameMapper;
use TheIconic\NameParser\Mapper\SalutationMapper;
use TheIconic\NameParser\Mapper\SuffixMapper;
use TheIconic\NameParser\Parser;

/**
 * Customizes the default parser to include the custom mappers.
 */
class CustomParser extends Parser
{
    /**
     * {@inheritdoc}
     */
    public function getMappers(): array
    {
        if (empty($this->mappers)) {
            $this->setMappers([
                new NicknameMapper($this->getNicknameDelimiters()),
                new SalutationMapper($this->getSalutations(), $this->getMaxSalutationIndex()),
                new SuffixMapper($this->getSuffixes()),
                new InitialMapper($this->getMaxCombinedInitials()),
                new LastnameMapper($this->getPrefixes()),
                new CustomMiddlenameMapper($this->getPrefixes()), // Map the middle name first.
                new CustomFirstnameMapper, // Map the first name last.
            ]);
        }

        return $this->mappers;
    }
}
