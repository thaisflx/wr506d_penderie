<?php

namespace App\Tests\Unit\Translation;

use App\Enum\CategoryType;
use App\Enum\ItemStatus;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Enum\ReservationStatus;
use App\Enum\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Fichiers de traduction du back (translations/) : l'anglais et l'allemand ont exactement les clés du français,
 * sans valeur vide, et chaque clé utilisée dans le code existe (docs/decisions.md, « Internationalisation »).
 */
final class TranslationFilesTest extends TestCase
{
    private const DIR = __DIR__.'/../../../translations';
    private const DOMAINS = ['messages', 'validators', 'security'];
    private const LOCALES = ['en', 'de'];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function domainLocaleProvider(): iterable
    {
        foreach (self::DOMAINS as $domain) {
            foreach (self::LOCALES as $locale) {
                yield "$domain.$locale" => [$domain, $locale];
            }
        }
    }

    #[DataProvider('domainLocaleProvider')]
    public function testSameKeysAsFrench(string $domain, string $locale): void
    {
        $french = array_keys($this->messages($domain, 'fr'));
        $translated = array_keys($this->messages($domain, $locale));

        self::assertSame([], array_values(array_diff($french, $translated)), "Clés manquantes dans $domain.$locale.yaml");
        self::assertSame([], array_values(array_diff($translated, $french)), "Clés en trop dans $domain.$locale.yaml (absentes du français)");
    }

    #[DataProvider('domainLocaleProvider')]
    public function testNoEmptyValue(string $domain, string $locale): void
    {
        foreach (['fr', $locale] as $checked) {
            $empty = array_keys(array_filter($this->messages($domain, $checked), static fn (string $value) => '' === trim($value)));
            self::assertSame([], $empty, "Valeurs vides dans $domain.$checked.yaml");
        }
    }

    public function testEveryEnumValueHasALabelInEveryLanguage(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['fr', ...self::LOCALES] as $locale) {
            $translator->addResource('yaml', self::DIR."/messages.$locale.yaml", $locale);
        }

        foreach ([Visibility::class, ItemStatus::class, CategoryType::class, RelationshipType::class, RelationshipStatus::class, ReservationStatus::class] as $enum) {
            foreach ($enum::cases() as $case) {
                foreach (['fr', ...self::LOCALES] as $locale) {
                    self::assertStringStartsNotWith('enum.', $case->trans($translator, $locale), \sprintf('%s::%s sans libellé en %s', $enum, $case->name, $locale));
                }
            }
        }
    }

    public function testEveryKeyUsedInTheCodeExistsInFrench(): void
    {
        $known = [];
        foreach (self::DOMAINS as $domain) {
            $known = [...$known, ...array_keys($this->messages($domain, 'fr'))];
        }

        $used = $this->keysUsedInCode();
        self::assertNotEmpty($used);
        self::assertSame([], array_values(array_diff($used, $known)), 'Clés utilisées dans src/ mais absentes des traductions françaises');
    }

    /**
     * Clés de traduction (« domaine.cle ») passées aux fonctions qui affichent un message : trans(), contraintes,
     * violations, exceptions et réponses d'erreur des contrôleurs.
     *
     * @return list<string>
     */
    private function keysUsedInCode(): array
    {
        $pattern = "/(?:trans|buildViolation|photoError|error|Exception)\\(\\s*'([a-z_]+(?:\\.[a-z_]+)+)'|(?:message|maxMessage|Message)\\s*[:=]\\s*'([a-z_]+(?:\\.[a-z_]+)+)'/";
        $keys = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../../src'));
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            preg_match_all($pattern, (string) file_get_contents($file->getPathname()), $matches);
            $keys = [...$keys, ...array_filter([...$matches[1], ...$matches[2]])];
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, string> clés à plat (« ootd.photo_required ») et leurs valeurs
     */
    private function messages(string $domain, string $locale): array
    {
        $file = self::DIR."/$domain.$locale.yaml";
        self::assertFileExists($file);

        return (new YamlFileLoader())->load($file, $locale, $domain)->all($domain);
    }
}
