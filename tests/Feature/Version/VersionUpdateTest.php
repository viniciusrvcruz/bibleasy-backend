<?php

use App\Enums\VersionLanguageEnum;
use App\Models\Version;

describe('Version Update', function () {
    it('updates version metadata successfully', function () {
        $this->actAsAdmin();

        $version = Version::factory()->create([
            'name' => 'Old Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
            'copyright' => 'Old Copyright',
        ]);

        $response = $this->putJson("/api/admin/versions/{$version->id}", [
            'name' => 'New Name',
            'language' => VersionLanguageEnum::PORTUGUESE_BR->value,
            'copyright' => 'New Copyright',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'New Name',
            'language' => VersionLanguageEnum::PORTUGUESE_BR->value,
            'copyright' => 'New Copyright',
        ]);

        $this->assertDatabaseHas('versions', [
            'id' => $version->id,
            'name' => 'New Name',
            'language' => VersionLanguageEnum::PORTUGUESE_BR->value,
        ]);
    });

    it('requires authentication', function () {
        $version = Version::factory()->create();

        $response = $this->putJson("/api/admin/versions/{$version->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(401);
    });

    it('prevents regular user from updating', function () {
        $this->actAsUser();

        $version = Version::factory()->create();

        $response = $this->putJson("/api/admin/versions/{$version->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(401);
    });
});
