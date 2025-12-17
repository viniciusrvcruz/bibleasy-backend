<?php

use App\Enums\VersionLanguageEnum;
use Database\Seeders\BookSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(BookSeeder::class);
});

describe('Version Import', function () {
    it('imports a valid bible version successfully', function () {
        $this->actAsAdmin();

        $data = array_fill(0, 66, [
            'chapters' => array_fill(0, 18, array_fill(0, 26, 'Sample verse text'))
        ]);
        $data[0]['chapters'][] = array_fill(0, 216, 'Sample verse text');

        $validJson = json_encode($data);

        $file = UploadedFile::fake()->createWithContent('bible.json', $validJson);

        $versionData = [
            'name' => 'Test Version',
            'language' => VersionLanguageEnum::ENGLISH->value,
            'copyright' => 'Public Domain',
        ];

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'importer' => 'thiago_bodruk',
            ...$versionData,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'language',
            'copyright',
            'chapters_count',
            'verses_count'
        ]);
        $response->assertJson([
            ...$versionData,
            'chapters_count' => 1189,
            'verses_count' => 31104,
        ]);
        
        $this->assertDatabaseHas('versions', [
            'name' => 'Test Version',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);
    });

    it('rejects import with invalid book count', function () {
        $this->actAsAdmin();

        $invalidJson = json_encode(array_fill(0, 50, [
            'chapters' => array_fill(0, 10, array_fill(0, 10, 'Verse'))
        ]));

        $file = UploadedFile::fake()->createWithContent('bible.json', $invalidJson);

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'importer' => 'thiago_bodruk',
            'name' => 'Invalid Version',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);


        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'invalid_books_count',
            'message' => 'Expected 66 books but got 50'
        ]);

        $this->assertDatabaseMissing('versions', ['name' => 'Invalid Version']);
    });

    it('requires authentication', function () {
        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'importer' => 'thiago_bodruk',
            'name' => 'Test',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(401);
    });

    it('prevents regular user from importing', function () {
        $this->actAsUser();

        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'importer' => 'thiago_bodruk',
            'name' => 'Test',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(401);
    });
});
