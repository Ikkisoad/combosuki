<?php

namespace Tests\Unit;

use App\Support\AliasGenerator;
use Tests\TestCase;

class AliasGeneratorTest extends TestCase
{
    public function test_multi_word_name_becomes_uppercased_initials(): void
    {
        $this->assertSame('RH', AliasGenerator::initials('Ryu Hoshi', 5));
    }

    public function test_single_word_name_falls_back_to_truncated_uppercase(): void
    {
        $this->assertSame('CHUNL', AliasGenerator::initials('Chunli', 5));
        $this->assertSame('CH', AliasGenerator::initials('Chunli', 2));
    }

    public function test_extra_and_surrounding_whitespace_is_collapsed(): void
    {
        $this->assertSame('RH', AliasGenerator::initials('  Ryu   Hoshi  ', 5));
    }

    public function test_multi_byte_single_word_name_truncates_by_character_not_byte(): void
    {
        $this->assertSame('波動拳', AliasGenerator::initials('波動拳', 3));
    }

    public function test_multi_byte_multi_word_name_takes_first_character_of_each_word(): void
    {
        $this->assertSame('リケ', AliasGenerator::initials('リュウ ケン', 5));
    }

    public function test_parse_list_dedupes_case_insensitively_preserving_first_seen_casing(): void
    {
        $this->assertSame(['Ryu', 'KEN'], AliasGenerator::parseList('Ryu, ryu, KEN'));
    }

    public function test_parse_list_trims_whitespace_around_each_entry(): void
    {
        $this->assertSame(['Ryu', 'Ken'], AliasGenerator::parseList('  Ryu  ,  Ken  '));
    }

    public function test_parse_list_skips_empty_entries_from_double_or_trailing_commas(): void
    {
        $this->assertSame(['Ryu', 'Ken'], AliasGenerator::parseList('Ryu,,Ken,'));
    }

    public function test_parse_list_returns_empty_array_for_empty_string(): void
    {
        $this->assertSame([], AliasGenerator::parseList(''));
    }
}
