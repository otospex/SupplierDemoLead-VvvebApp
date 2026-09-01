<?php

define('V_VERSION', 'test');

$presenterFile = __DIR__ . '/../system/solution-presenter.php';
$repositoryFile = __DIR__ . '/../system/solution-repository.php';
if (! is_file($presenterFile) || ! is_file($repositoryFile)) {
    fwrite(STDERR, "FAIL: solution presenter is missing.\n");
    exit(1);
}

require_once $presenterFile;
require_once $repositoryFile;

use Vvveb\Plugins\SolutionsDirectory\System\SolutionPresenter;
use Vvveb\Plugins\SolutionsDirectory\System\SolutionRepository;

$failures = 0;

function expectSolution(bool $condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

$rows = [
    [
        'post_id' => 3,
        'status' => 'draft',
        'name' => 'Brouillon secret',
        'slug' => 'brouillon-secret',
        'excerpt' => 'Ne doit jamais apparaître.',
        'kind' => 'logiciel',
        'verification_status' => 'verifie',
        'reviewed_at' => '2026-09-30',
        'website' => 'https://draft.example.test',
    ],
    [
        'post_id' => 2,
        'status' => 'publish',
        'name' => 'Zéphyr',
        'slug' => 'zephyr',
        'excerpt' => 'Une solution déclarée.',
        'kind' => 'hebergeur',
        'verification_status' => 'declare',
        'reviewed_at' => '2026-08-01',
        'website' => 'https://zephyr.example.test',
    ],
    [
        'post_id' => 1,
        'status' => 'publish',
        'name' => 'Alizé',
        'slug' => 'alize',
        'excerpt' => 'Une solution vérifiée.',
        'kind' => 'logiciel',
        'verification_status' => 'verifie',
        'reviewed_at' => '2026-09-01',
        'website' => 'https://alize.example.test',
    ],
];

$html = SolutionPresenter::listing($rows);

expectSolution(! str_contains($html, 'Brouillon secret'), 'draft solutions must be invisible in public rendering.');
expectSolution(strpos($html, 'Alizé') < strpos($html, 'Zéphyr'), 'solutions must be ordered by reviewed_at descending, then name.');
expectSolution(str_contains($html, 'Déclaré par l&rsquo;éditeur'), 'declared solutions need the declared badge.');
expectSolution(str_contains($html, 'Vérifié par Indépendant Digital le 01/09/2026'), 'verified solutions need a dated verification badge.');
expectSolution((bool) preg_match('#href="https://zephyr\.example\.test"[^>]+rel="nofollow noopener"#', $html), 'declared outbound links need nofollow noopener.');
expectSolution((bool) preg_match('#href="https://alize\.example\.test"[^>]+rel="noopener"#', $html), 'verified outbound links need noopener.');
expectSolution(! (bool) preg_match('#href="https://alize\.example\.test"[^>]+rel="[^"]*nofollow#', $html), 'verified outbound links must not keep nofollow.');

$empty = SolutionPresenter::listing([]);
expectSolution(str_contains($empty, 'Aucune solution publiée ne correspond à ces critères.'), 'empty results need a useful sentence.');
expectSolution(str_contains($empty, '/annuaire/referencer-une-solution'), 'empty results need the registration link.');

final class CapturingSolutionDb {
    public string $quote = '`';
    public array $queries = [];

    function execute(string $sql, array $params = []) {
        $this->queries[] = $sql;
        return true;
    }

    function fetchAll($statement): array {
        return [];
    }
}

$capturingDb = new CapturingSolutionDb();
$repository = new SolutionRepository([
    'post_type' => 'solution',
    'meta_namespace' => 'solution',
    'taxonomies' => ['categorie' => 'categorie', 'alternative_a' => 'alternative-a'],
], $capturingDb);
$repository->published(['language_id' => 2, 'site_id' => 1], 12);
expectSolution(str_contains($capturingDb->queries[0] ?? '', 'pm.`key`'), 'post meta key must use the active database driver quote.');
$repository->terms('categorie', 2, 1);
expectSolution(str_contains($capturingDb->queries[1] ?? '', 't.site_id = :site_id'), 'taxonomy options must stay scoped to the active site.');

if (! method_exists(SolutionPresenter::class, 'registrationForm')) {
    expectSolution(false, 'registration form renderer is missing.');
} else {
    $form = SolutionPresenter::registrationForm(
        [['name' => 'Bureautique', 'slug' => 'bureautique']],
        [['name' => 'Microsoft 365', 'slug' => 'microsoft-365']],
        ['endpoint_slug' => 'solution-registration']
    );
    foreach (['kind', 'solution_name', 'website', 'organisation', 'hq_country', 'contact_name', 'email', 'contact_role', 'categories[]', 'alternative_to[]', 'alternative_to_other', 'pitch', 'advantages', 'hosting_countries', 'qualifications', 'pricing_model', 'partner_interest', 'accuracy_commitment', 'privacy_acknowledgement'] as $field) {
        expectSolution(str_contains($form, 'name="' . $field . '"'), "registration form is missing $field.");
    }
    expectSolution(str_contains($form, 'value="bureautique"'), 'registration categories must come from taxonomy data.');
    expectSolution(str_contains($form, 'value="microsoft-365"'), 'registration alternatives must come from taxonomy data.');
    expectSolution(substr_count($form, '<fieldset') === 3, 'registration form must use three fieldsets.');
}

if ($failures > 0) {
    fwrite(STDERR, "solutions-component tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "solutions-component tests: PASS\n");
