<?php

namespace App\Tests\Functional\Admin;

/**
 * Tableau de bord du back office : les 8 KPI, en libellés simples, dans la langue du compte de l'admin.
 */
final class AdminDashboardTest extends AdminTestCase
{
    private const KPIS = ['signups', 'active', 'looks', 'items-per-user', 'shareable', 'relationships', 'items-by-type', 'suspended'];

    public function testTheDashboardShowsTheEightKpis(): void
    {
        $this->logInAdmin();

        $crawler = $this->client->request('GET', '/admin');

        $this->assertStatus(200);
        foreach (self::KPIS as $kpi) {
            self::assertCount(1, $crawler->filter(\sprintf('[data-kpi="%s"]', $kpi)), $kpi);
        }
        self::assertSelectorTextContains('[data-kpi="suspended"] .kpi-number', '1');
        self::assertSelectorTextContains('[data-kpi="relationships"]', '83 % des demandes sont acceptées. En attente : 1.');
        self::assertSelectorTextContains('[data-kpi="items-by-type"]', 'Média');
        self::assertCount(8, $crawler->filter('[data-kpi="signups"] .kpi-bars li'));
    }

    public function testTheDashboardIsInTheLanguageOfTheAdmin(): void
    {
        $this->logInAdmin()->setLocale('de');
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/admin');

        self::assertSelectorTextContains('[data-kpi="relationships"] h2', 'Angenommene Freundschaftsanfragen');
        self::assertSelectorTextContains('[data-kpi="items-by-type"]', 'Medien');
        // Semaines au format allemand : « Okt. » (anglais « Oct », français « oct. »)
        self::assertStringContainsString('Okt.', $crawler->filter('[data-kpi="signups"] .kpi-bars')->text());
    }

    public function testNoDetailOfAWardrobeOnTheDashboard(): void
    {
        $this->logInAdmin();

        $text = $this->client->request('GET', '/admin')->text();

        foreach (['camille', 'Maison', 'Penderie', 'Carnet intime', 'T-shirt blanc'] as $private) {
            self::assertStringNotContainsString($private, $text);
        }
    }
}
