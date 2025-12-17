<?php

use App\Enums\VersionLanguageEnum;
use App\Models\Version;

describe('Version Index', function () {
    it('lists all versions', function () {
        Version::factory()->count(3)->create();

        $response = $this->getJson('/api/versions');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonStructure([['id', 'name', 'language', 'copyright', 'chapters_count', 'verses_count']]);
    });

    it('filters versions by language', function () {
        Version::factory()->create(['language' => VersionLanguageEnum::ENGLISH->value]);
        Version::factory()->create(['language' => VersionLanguageEnum::PORTUGUESE_BR->value]);
        Version::factory()->create(['language' => VersionLanguageEnum::PORTUGUESE_BR->value]);

        $response = $this->getJson('/api/versions?language=' . VersionLanguageEnum::PORTUGUESE_BR->value);

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    });

    it('returns empty array when no versions exist', function () {
        $response = $this->getJson('/api/versions');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    });
});
