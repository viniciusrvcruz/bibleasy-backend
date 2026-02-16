<?php

use App\Enums\VersionLanguageEnum;
use App\Enums\VersionTextSourceEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

beforeEach(function () {
    $this->allBookNames = [
        'Gênesis', 'Êxodo', 'Levítico', 'Números', 'Deuteronômio', 'Josué', 'Juízes', 'Rute',
        '1 Samuel', '2 Samuel', '1 Reis', '2 Reis', '1 Crônicas', '2 Crônicas', 'Esdras', 'Neemias',
        'Ester', 'Jó', 'Salmos', 'Provérbios', 'Eclesiastes', 'Cânticos', 'Isaías', 'Jeremias',
        'Lamentações', 'Ezequiel', 'Daniel', 'Oséias', 'Joel', 'Amós', 'Obadias', 'Jonas',
        'Miquéias', 'Naum', 'Habacuque', 'Sofonias', 'Ageu', 'Zacarias', 'Malaquias', 'Mateus',
        'Marcos', 'Lucas', 'João', 'Atos', 'Romanos', '1 Coríntios', '2 Coríntios', 'Gálatas',
        'Efésios', 'Filipenses', 'Colossenses', '1 Tessalonicenses', '2 Tessalonicenses', '1 Timóteo',
        '2 Timóteo', 'Tito', 'Filemom', 'Hebreus', 'Tiago', '1 Pedro', '2 Pedro', '1 João',
        '2 João', '3 João', 'Judas', 'Apocalipse'
    ];

    $this->validBibleData = function () {
        $data = [];
        for ($i = 0; $i < 66; $i++) {
            $data[] = [
                'name' => $this->allBookNames[$i],
                'chapters' => array_fill(0, 18, array_fill(0, 26, 'Sample verse text'))
            ];
        }

        $data[0]['chapters'][] = array_fill(0, 216, 'Sample verse text');

        return json_encode($data);
    };
});

describe('Version Import', function () {
    it('imports a valid bible version successfully', function () {
        $this->actAsAdmin();

        $validJson = ($this->validBibleData)();

        $file = UploadedFile::fake()->createWithContent('bible.json', $validJson);

        $versionData = [
            'abbreviation' => 'Test Version',
            'name' => 'Test Version Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
            'copyright' => 'Public Domain',
            'text_source' => VersionTextSourceEnum::DATABASE->value,
        ];

        $response = $this->postJson('/api/admin/versions', [
            'files' => [$file],
            'adapter' => 'json_thiago_bodruk',
            ...$versionData,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'abbreviation',
            'name',
            'language',
            'copyright',
        ]);
        $response->assertJson([
            ...Arr::except($versionData, 'text_source'),
        ]);
        
        $this->assertDatabaseHas('versions', [
            'abbreviation' => 'Test Version',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);
    });

    it('requires authentication', function () {
        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'files' => [$file],
            'adapter' => 'json_thiago_bodruk',
            'abbreviation' => 'Test',
            'name' => 'Test Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(401);
    });

    it('prevents regular user from importing', function () {
        $this->actAsUser();

        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'files' => [$file],
            'adapter' => 'json_thiago_bodruk',
            'abbreviation' => 'Test',
            'name' => 'Test Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(401);
    });

    it('rejects import with missing chapters', function () {
        $this->actAsAdmin();
        
        $invalidJson = json_encode(array_map(function ($name) {
            return [
                'name' => $name,
                'chapters' => []
            ];
        }, $this->allBookNames));

        $file = UploadedFile::fake()->createWithContent('bible.json', $invalidJson);

        $response = $this->postJson('/api/admin/versions', [
            'files' => [$file],
            'adapter' => 'json_thiago_bodruk',
            'abbreviation' => 'Invalid Version',
            'name' => 'Invalid Version Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
            'text_source' => VersionTextSourceEnum::DATABASE->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'missing_chapters']);
    });

    it('rejects import with empty verses', function () {
        $this->actAsAdmin();
        
        $invalidJson = json_encode(array_map(function ($name) {
            return [
                'name' => $name,
                'chapters' => [['   ', '']]
            ];
        }, $this->allBookNames));

        $file = UploadedFile::fake()->createWithContent('bible.json', $invalidJson);

        $response = $this->postJson('/api/admin/versions', [
            'files' => [$file],
            'adapter' => 'json_thiago_bodruk',
            'abbreviation' => 'Invalid Version',
            'name' => 'Invalid Version Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
            'text_source' => VersionTextSourceEnum::DATABASE->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'empty_verse']);
    });

    it('validates adapter format', function () {
        $this->actAsAdmin();

        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'files' => [$file],
            'adapter' => 'invalid_format',
            'name' => 'Test',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['adapter']);
    });

});
