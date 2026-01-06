# Implementação de Section Titles (Títulos de Seção)

## Visão Geral

Este documento descreve a estrutura proposta para implementar títulos de seção para versículos bíblicos. Os títulos de seção são usados por algumas versões da Bíblia para organizar o conteúdo em seções temáticas (ex: "Parábola do Semeador", "O Sermão da Montanha", etc.).

## Estrutura da Tabela

### `section_titles`

A tabela `section_titles` armazena títulos de seção que se aplicam a um range de versículos dentro de um capítulo.

```sql
CREATE TABLE section_titles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chapter_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_verse_number INT NOT NULL,
    end_verse_number INT NOT NULL,
    order INT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
    INDEX idx_chapter_id (chapter_id),
    INDEX idx_chapter_range (chapter_id, start_verse_number, end_verse_number),
    CHECK (start_verse_number <= end_verse_number)
);
```

### Campos

- `id`: Identificador único do título
- `chapter_id`: Referência ao capítulo ao qual o título pertence
- `title`: Texto do título da seção
- `start_verse_number`: Número do versículo inicial do range
- `end_verse_number`: Número do versículo final do range
- `order`: Ordem do título dentro do capítulo (caso haja múltiplos títulos)
- `created_at`, `updated_at`: Timestamps padrão do Laravel

## Relacionamentos

### Modelo `SectionTitle`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionTitle extends Model
{
    protected $fillable = [
        'chapter_id',
        'title',
        'start_verse_number',
        'end_verse_number',
        'order',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Verifica se um versículo está dentro do range deste título
     */
    public function coversVerse(int $verseNumber): bool
    {
        return $verseNumber >= $this->start_verse_number 
            && $verseNumber <= $this->end_verse_number;
    }
}
```

### Atualização do Modelo `Chapter`

```php
// Adicionar ao modelo Chapter
public function sectionTitles(): HasMany
{
    return $this->hasMany(SectionTitle::class)->orderBy('order');
}

/**
 * Obtém o título de seção que cobre um versículo específico
 */
public function getSectionTitleForVerse(int $verseNumber): ?SectionTitle
{
    return $this->sectionTitles()
        ->where('start_verse_number', '<=', $verseNumber)
        ->where('end_verse_number', '>=', $verseNumber)
        ->first();
}
```

## Casos de Uso

### 1. Buscar título de seção para um versículo

```php
$chapter = Chapter::with('sectionTitles')->find($chapterId);
$verseNumber = 5;

$sectionTitle = $chapter->getSectionTitleForVerse($verseNumber);
if ($sectionTitle) {
    echo $sectionTitle->title; // "Parábola do Semeador"
}
```

### 2. Listar todos os títulos de seção de um capítulo

```php
$chapter = Chapter::with('sectionTitles')->find($chapterId);

foreach ($chapter->sectionTitles as $title) {
    echo "{$title->title} (versículos {$title->start_verse_number}-{$title->end_verse_number})\n";
}
```

### 3. Buscar versículos de uma seção específica

```php
$sectionTitle = SectionTitle::find($titleId);

$verses = Verse::where('chapter_id', $sectionTitle->chapter_id)
    ->whereBetween('number', [
        $sectionTitle->start_verse_number,
        $sectionTitle->end_verse_number
    ])
    ->get();
```

## Migração Sugerida

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->integer('start_verse_number');
            $table->integer('end_verse_number');
            $table->integer('order');
            $table->timestamps();
            
            $table->index('chapter_id');
            $table->index(['chapter_id', 'start_verse_number', 'end_verse_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_titles');
    }
};
```

## Observações Importantes

1. **Desacoplamento**: Os títulos de seção não estão diretamente acoplados aos versículos. Eles são definidos por range dentro de um capítulo.

2. **Múltiplos Títulos**: Um capítulo pode ter múltiplos títulos de seção. O campo `order` garante a ordem correta.

3. **Ranges Não Sobrepostos**: Embora não seja uma constraint obrigatória, é recomendado que os ranges não se sobreponham para evitar ambiguidade.

4. **Performance**: Os índices em `chapter_id` e no range (`chapter_id`, `start_verse_number`, `end_verse_number`) garantem consultas eficientes.

5. **Validação**: A constraint CHECK garante que `start_verse_number <= end_verse_number`.

## Exemplo de Dados

```php
// Capítulo 13 de Mateus - Parábolas
SectionTitle::create([
    'chapter_id' => $matthewChapter13->id,
    'title' => 'Parábola do Semeador',
    'start_verse_number' => 1,
    'end_verse_number' => 9,
    'order' => 1,
]);

SectionTitle::create([
    'chapter_id' => $matthewChapter13->id,
    'title' => 'Por que Jesus falava por parábolas',
    'start_verse_number' => 10,
    'end_verse_number' => 17,
    'order' => 2,
]);

SectionTitle::create([
    'chapter_id' => $matthewChapter13->id,
    'title' => 'Explicação da Parábola do Semeador',
    'start_verse_number' => 18,
    'end_verse_number' => 23,
    'order' => 3,
]);
```

