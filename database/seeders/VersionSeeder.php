<?php

namespace Database\Seeders;

use App\Enums\BookAbbreviationEnum;
use App\Enums\VersionLanguageEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Version;
use App\Models\Verse;
use Illuminate\Database\Seeder;

class VersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a version for development
        $version = Version::create([
            'abbreviation' => 'DEV',
            'name' => 'Versão de Desenvolvimento',
            'language' => VersionLanguageEnum::PORTUGUESE_BR->value,
            'copyright' => 'Versão mockada para desenvolvimento e testes',
        ]);

        // Map of book abbreviations to Portuguese names
        $bookNames = [
            BookAbbreviationEnum::GEN->value => 'Gênesis',
            BookAbbreviationEnum::EXO->value => 'Êxodo',
            BookAbbreviationEnum::LEV->value => 'Levítico',
            BookAbbreviationEnum::NUM->value => 'Números',
            BookAbbreviationEnum::DEU->value => 'Deuteronômio',
            BookAbbreviationEnum::JOS->value => 'Josué',
            BookAbbreviationEnum::JDG->value => 'Juízes',
            BookAbbreviationEnum::RUT->value => 'Rute',
            BookAbbreviationEnum::SA1->value => '1 Samuel',
            BookAbbreviationEnum::SA2->value => '2 Samuel',
            BookAbbreviationEnum::KI1->value => '1 Reis',
            BookAbbreviationEnum::KI2->value => '2 Reis',
            BookAbbreviationEnum::CH1->value => '1 Crônicas',
            BookAbbreviationEnum::CH2->value => '2 Crônicas',
            BookAbbreviationEnum::EZR->value => 'Esdras',
            BookAbbreviationEnum::NEH->value => 'Neemias',
            BookAbbreviationEnum::EST->value => 'Ester',
            BookAbbreviationEnum::JOB->value => 'Jó',
            BookAbbreviationEnum::PSA->value => 'Salmos',
            BookAbbreviationEnum::PRO->value => 'Provérbios',
            BookAbbreviationEnum::ECC->value => 'Eclesiastes',
            BookAbbreviationEnum::SNG->value => 'Cânticos',
            BookAbbreviationEnum::ISA->value => 'Isaías',
            BookAbbreviationEnum::JER->value => 'Jeremias',
            BookAbbreviationEnum::LAM->value => 'Lamentações',
            BookAbbreviationEnum::EZK->value => 'Ezequiel',
            BookAbbreviationEnum::DAN->value => 'Daniel',
            BookAbbreviationEnum::HOS->value => 'Oséias',
            BookAbbreviationEnum::JOL->value => 'Joel',
            BookAbbreviationEnum::AMO->value => 'Amós',
            BookAbbreviationEnum::OBA->value => 'Obadias',
            BookAbbreviationEnum::JON->value => 'Jonas',
            BookAbbreviationEnum::MIC->value => 'Miquéias',
            BookAbbreviationEnum::NAM->value => 'Naum',
            BookAbbreviationEnum::HAB->value => 'Habacuque',
            BookAbbreviationEnum::ZEP->value => 'Sofonias',
            BookAbbreviationEnum::HAG->value => 'Ageu',
            BookAbbreviationEnum::ZEC->value => 'Zacarias',
            BookAbbreviationEnum::MAL->value => 'Malaquias',
            BookAbbreviationEnum::MAT->value => 'Mateus',
            BookAbbreviationEnum::MRK->value => 'Marcos',
            BookAbbreviationEnum::LUK->value => 'Lucas',
            BookAbbreviationEnum::JHN->value => 'João',
            BookAbbreviationEnum::ACT->value => 'Atos',
            BookAbbreviationEnum::ROM->value => 'Romanos',
            BookAbbreviationEnum::CO1->value => '1 Coríntios',
            BookAbbreviationEnum::CO2->value => '2 Coríntios',
            BookAbbreviationEnum::GAL->value => 'Gálatas',
            BookAbbreviationEnum::EPH->value => 'Efésios',
            BookAbbreviationEnum::PHP->value => 'Filipenses',
            BookAbbreviationEnum::COL->value => 'Colossenses',
            BookAbbreviationEnum::TH1->value => '1 Tessalonicenses',
            BookAbbreviationEnum::TH2->value => '2 Tessalonicenses',
            BookAbbreviationEnum::TI1->value => '1 Timóteo',
            BookAbbreviationEnum::TI2->value => '2 Timóteo',
            BookAbbreviationEnum::TIT->value => 'Tito',
            BookAbbreviationEnum::PHM->value => 'Filemom',
            BookAbbreviationEnum::HEB->value => 'Hebreus',
            BookAbbreviationEnum::JAS->value => 'Tiago',
            BookAbbreviationEnum::PE1->value => '1 Pedro',
            BookAbbreviationEnum::PE2->value => '2 Pedro',
            BookAbbreviationEnum::JN1->value => '1 João',
            BookAbbreviationEnum::JN2->value => '2 João',
            BookAbbreviationEnum::JN3->value => '3 João',
            BookAbbreviationEnum::JUD->value => 'Judas',
            BookAbbreviationEnum::REV->value => 'Apocalipse',
        ];

        // Mock texts for verses
        $mockTexts = [
            'princípio, criou Deus os céus e a terra.',
            'A terra, porém, estava sem forma e vazia; havia trevas sobre a face do abismo, e o Espírito de Deus pairava por sobre as águas.',
            'Disse Deus: Haja luz; e houve luz.',
            'E viu Deus que a luz era boa; e fez separação entre a luz e as trevas.',
            'Chamou Deus à luz Dia e às trevas, Noite. Houve tarde e manhã, o primeiro dia.',
            'E disse Deus: Haja firmamento no meio das águas e separação entre águas e águas.',
            'Fez, pois, Deus o firmamento e separou as águas que estavam debaixo do firmamento das que estavam por cima do firmamento. E assim foi.',
            'E chamou Deus ao firmamento Céus. Houve tarde e manhã, o segundo dia.',
            'E disse Deus: Ajuntem-se as águas debaixo dos céus num só lugar, e apareça a porção seca. E assim foi.',
            'À porção seca chamou Deus Terra e ao ajuntamento das águas, Mares. E viu Deus que isso era bom.',
            'E disse Deus: Produza a terra relva, ervas que deem semente e árvores frutíferas que deem fruto segundo a sua espécie, cuja semente esteja nele, sobre a terra. E assim foi.',
            'A terra produziu relva, ervas que davam semente segundo a sua espécie e árvores que davam fruto, cuja semente estava nele, conforme a sua espécie. E viu Deus que isso era bom.',
            'Houve tarde e manhã, o terceiro dia.',
            'E disse Deus: Haja luzeiros no firmamento dos céus, para fazerem separação entre o dia e a noite; e sejam eles para sinais, para estações, para dias e anos.',
            'E sejam para luzeiros no firmamento dos céus, para alumiar a terra. E assim foi.',
            'Fez Deus os dois grandes luzeiros: o maior para governar o dia, e o menor para governar a noite; e fez também as estrelas.',
            'E os colocou no firmamento dos céus para alumiar a terra,',
            'para governar o dia e a noite e fazer separação entre a luz e as trevas. E viu Deus que isso era bom.',
            'Houve tarde e manhã, o quarto dia.',
            'E disse Deus: Povoem-se as águas de enxames de seres viventes; e voem as aves sobre a terra, sob o firmamento dos céus.',
        ];

        $order = 1;
        foreach (BookAbbreviationEnum::cases() as $abbreviation) {
            $bookName = $bookNames[$abbreviation->value] ?? $abbreviation->value;

            // Create book
            $book = Book::create([
                'version_id' => $version->id,
                'name' => $bookName,
                'abbreviation' => $abbreviation,
                'order' => $order++,
            ]);

            // Create a few chapters per book (simplified for development)
            // Most books will have 2-3 chapters, some will have just 1
            $chaptersCount = $this->getChaptersCount($abbreviation);

            for ($chapterNum = 1; $chapterNum <= $chaptersCount; $chapterNum++) {
                $chapter = Chapter::create([
                    'book_id' => $book->id,
                    'number' => $chapterNum,
                ]);

                // Create a few verses per chapter (5-10 verses)
                $versesCount = rand(5, 10);

                for ($verseNum = 1; $verseNum <= $versesCount; $verseNum++) {
                    Verse::create([
                        'chapter_id' => $chapter->id,
                        'number' => $verseNum,
                        'text' => $mockTexts[array_rand($mockTexts)],
                    ]);
                }
            }
        }
    }

    /**
     * Get the number of chapters for a book (simplified for development)
     */
    private function getChaptersCount(BookAbbreviationEnum $abbreviation): int
    {
        // Some books with just 1 chapter
        $singleChapterBooks = [
            BookAbbreviationEnum::OBA,
            BookAbbreviationEnum::JON,
            BookAbbreviationEnum::JUD,
            BookAbbreviationEnum::PHM,
            BookAbbreviationEnum::JN2,
            BookAbbreviationEnum::JN3,
        ];

        if (in_array($abbreviation, $singleChapterBooks)) {
            return 1;
        }

        // Some books with 2 chapters
        $twoChapterBooks = [
            BookAbbreviationEnum::RUT,
            BookAbbreviationEnum::PE2,
        ];

        if (in_array($abbreviation, $twoChapterBooks)) {
            return 2;
        }

        // Most books will have 3 chapters for development purposes
        return 3;
    }
}

