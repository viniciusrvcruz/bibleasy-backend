<?php

use App\Utils\JsonDecode;

describe('JsonDecode', function () {
    it('decodes valid json array to array', function () {
        $json = json_encode(['key' => 'value', 'nested' => [1, 2]]);

        $result = JsonDecode::toArray($json);

        expect($result)->toBe(['key' => 'value', 'nested' => [1, 2]]);
    });

    it('decodes valid json object to associative array', function () {
        $json = json_encode(['books' => [['id' => 'GEN', 'title' => 'Gênesis']]]);

        $result = JsonDecode::toArray($json);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('books')
            ->and($result['books'])->toHaveCount(1)
            ->and($result['books'][0]['id'])->toBe('GEN');
    });

    it('strips UTF-8 BOM from content', function () {
        $bom = pack('H*', 'EFBBBF');
        $json = $bom . json_encode(['data' => true]);

        $result = JsonDecode::toArray($json);

        expect($result)->toBe(['data' => true]);
    });

    it('returns null for malformed json', function () {
        expect(JsonDecode::toArray('not json at all'))->toBeNull();
    });

    it('returns null when json decodes to non-array', function () {
        expect(JsonDecode::toArray('123'))->toBeNull();
    });

    it('returns null for empty string', function () {
        expect(JsonDecode::toArray(''))->toBeNull();
    });
});
