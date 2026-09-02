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

// Three empty states. An unfiltered directory is not "missing criteria", it is
// simply not open yet; a filter that excluded everything is; and a filtered view
// of a directory that holds nothing at all is the first case again, not the
// second, because blaming the reader's filter would be untrue.
$launchSentence = 'L&rsquo;annuaire ouvre ses premières fiches prochainement.';
$noMatchSentence = 'Aucune solution publiée ne correspond à ces critères.';

$empty = SolutionPresenter::listing([]);
expectSolution(str_contains($empty, $launchSentence), 'an unfiltered empty directory must say it is not open yet.');
expectSolution(! str_contains($empty, 'ces critères'), 'an unfiltered empty directory must not blame criteria nobody chose.');
expectSolution(str_contains($empty, '/annuaire/referencer-une-solution'), 'empty results need the registration link.');

$emptyFiltered = SolutionPresenter::listing([], ['filtered' => true, 'directory_empty' => false]);
expectSolution(str_contains($emptyFiltered, $noMatchSentence), 'a filtered empty result over a populated directory must name the criteria.');
expectSolution(! str_contains($emptyFiltered, $launchSentence), 'a populated directory must not claim it is still opening.');

$emptyFilteredEmptyDirectory = SolutionPresenter::listing([], ['filtered' => true, 'directory_empty' => true]);
expectSolution(str_contains($emptyFilteredEmptyDirectory, $launchSentence), 'a filter over an empty directory must report the empty directory, not a failed filter.');
expectSolution(! str_contains($emptyFilteredEmptyDirectory, 'ces critères'), 'an empty directory must not blame the reader\'s filter.');

// A term page is a narrowed view even without an explicit filter flag.
$emptyTerm = SolutionPresenter::listing([], ['term_name' => 'Microsoft 365', 'taxonomy' => 'alternative-a', 'directory_empty' => false]);
expectSolution(str_contains($emptyTerm, $noMatchSentence), 'an empty term page over a populated directory must name the criteria.');

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

// --- Spec 5.4: alternatives are the OR of both neighbourhoods --------------
final class UnionSolutionDb {
    public string $quote = '`';
    public array $calls = [];
    private array $rowsByTaxonomy;
    private array $pending = [];

    function __construct(array $rowsByTaxonomy) {
        $this->rowsByTaxonomy = $rowsByTaxonomy;
    }

    function execute(string $sql, array $params = []) {
        $this->calls[] = $params;
        if (isset($params['alternative_a_taxonomy'])) {
            $this->pending = $this->rowsByTaxonomy['alternative-a'] ?? [];
        } elseif (isset($params['categorie_taxonomy'])) {
            $this->pending = $this->rowsByTaxonomy['categorie'] ?? [];
        } else {
            $this->pending = [];
        }

        return true;
    }

    function fetchAll($statement): array {
        return $this->pending;
    }
}

$repositoryConfig = [
    'post_type' => 'solution',
    'meta_namespace' => 'solution',
    'taxonomies' => ['categorie' => 'categorie', 'alternative_a' => 'alternative-a'],
];
$sharedNeighbour = ['post_id' => 4, 'status' => 'publish', 'language_id' => 2, 'name' => 'Alpha', 'slug' => 'alpha', 'reviewed_at' => '2026-09-05'];
$unionDb = new UnionSolutionDb([
    'alternative-a' => [
        ['post_id' => 2, 'status' => 'publish', 'language_id' => 2, 'name' => 'Zéphyr', 'slug' => 'zephyr', 'reviewed_at' => '2026-08-01'],
        $sharedNeighbour,
    ],
    'categorie' => [
        $sharedNeighbour,
        ['post_id' => 5, 'status' => 'publish', 'language_id' => 2, 'name' => 'Delta', 'slug' => 'delta', 'reviewed_at' => '2026-07-01'],
    ],
]);
$unionRepository = new SolutionRepository($repositoryConfig, $unionDb);
$subject = [
    'post_id' => 1,
    'language_id' => 2,
    'alternative_a' => [['name' => 'Microsoft 365', 'slug' => 'microsoft-365']],
    'categories' => [['name' => 'Bureautique', 'slug' => 'bureautique']],
];

$union = $unionRepository->alternatives($subject, 5, 1);
expectSolution(count($union) === 3, 'alternatives must union both neighbourhoods and dedupe (got ' . count($union) . ').');
expectSolution(array_column($union, 'post_id') === [4, 2, 5], 'unioned alternatives must stay ordered by reviewed_at desc then name.');
$taxonomyFilters = array_values(array_filter(array_map(
    static fn (array $params): string => $params['alternative_a_taxonomy'] ?? ($params['categorie_taxonomy'] ?? ''),
    $unionDb->calls
)));
expectSolution(in_array('alternative-a', $taxonomyFilters, true) && in_array('categorie', $taxonomyFilters, true), 'both neighbourhoods must be queried.');
foreach ($unionDb->calls as $call) {
    if (isset($call['alternative_a_taxonomy']) || isset($call['categorie_taxonomy'])) {
        expectSolution(($call['exclude_post_id'] ?? 0) === 1, 'the solution itself must be excluded from its alternatives.');
    }
}

$capped = (new SolutionRepository($repositoryConfig, new UnionSolutionDb([
    'alternative-a' => [
        ['post_id' => 2, 'status' => 'publish', 'language_id' => 2, 'name' => 'Zéphyr', 'slug' => 'zephyr', 'reviewed_at' => '2026-08-01'],
        $sharedNeighbour,
    ],
    'categorie' => [
        ['post_id' => 5, 'status' => 'publish', 'language_id' => 2, 'name' => 'Delta', 'slug' => 'delta', 'reviewed_at' => '2026-07-01'],
    ],
])))->alternatives($subject, 2, 1);
expectSolution(count($capped) === 2 && array_column($capped, 'post_id') === [4, 2], 'unioned alternatives must respect the limit.');

$categoryOnly = new UnionSolutionDb(['categorie' => [['post_id' => 5, 'status' => 'publish', 'language_id' => 2, 'name' => 'Delta', 'slug' => 'delta', 'reviewed_at' => '2026-07-01']]]);
$categoryOnlyRows = (new SolutionRepository($repositoryConfig, $categoryOnly))->alternatives(
    ['post_id' => 1, 'language_id' => 2, 'categories' => [['name' => 'Bureautique', 'slug' => 'bureautique']]],
    5,
    1
);
expectSolution(count($categoryOnlyRows) === 1, 'a solution with categories only still gets neighbours.');
expectSolution(
    [] === array_filter($categoryOnly->calls, static fn (array $params): bool => isset($params['alternative_a_taxonomy'])),
    'an empty neighbourhood must not be queried.'
);
expectSolution($unionRepository->alternatives(['post_id' => 1, 'language_id' => 2], 5, 1) === [], 'a solution with no terms has no alternatives.');

// --- Detail page: disclosure wording, JSON-LD URL, configurable paths -------
$partner = [
    'post_id' => 9,
    'status' => 'publish',
    'language_id' => 2,
    'name' => 'AIFEL',
    'slug' => 'aifel',
    'excerpt' => 'Collaboration sécurisée.',
    'content' => '<p>Corps relu.</p>',
    'kind' => 'logiciel',
    'verification_status' => 'verifie',
    'reviewed_at' => '2026-09-01',
    'website' => 'https://aifel.example.fr',
    'commercial_relationship' => 'partenaire-non-exclusif',
    'categories' => [['name' => 'Visioconférence', 'slug' => 'visioconference']],
];
$detail = SolutionPresenter::detail($partner);
expectSolution(
    str_contains($detail, 'AIFEL est un partenaire commercial non exclusif d&rsquo;Indépendant Digital.'),
    'the disclosure must name the solution (2026-08-27 spec §6 wording).'
);
expectSolution(
    str_contains($detail, 'AIFEL est évalué selon la même méthode que les autres solutions.'),
    'the disclosure must close on the same-method sentence naming the solution.'
);
expectSolution(! str_contains($detail, 'Cette solution est un partenaire'), 'the disclosure must not fall back to a generic subject.');

preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $detail, $jsonLdMatch);
$jsonLd = json_decode($jsonLdMatch[1] ?? '{}', true);
expectSolution(($jsonLd['url'] ?? '') === 'https://aifel.example.fr', 'JSON-LD must publish the validated website URL.');
preg_match('#<a class="sd-link-arrow" href="([^"]+)" rel#', $detail, $visibleLink);
expectSolution(($visibleLink[1] ?? '') === ($jsonLd['url'] ?? ''), 'JSON-LD and the visible link must carry the same URL.');

$hostileDetail = SolutionPresenter::detail(['status' => 'publish', 'name' => 'Piège', 'slug' => 'piege', 'kind' => 'logiciel', 'website' => 'javascript:alert(1)']);
preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $hostileDetail, $hostileMatch);
$hostileJsonLd = json_decode($hostileMatch[1] ?? '{}', true);
expectSolution(! isset($hostileJsonLd['url']), 'JSON-LD must omit a URL the page refuses to link to.');
expectSolution(! str_contains($hostileDetail, 'javascript:'), 'a non-http scheme must never reach the rendered page.');

SolutionPresenter::configure([
    'directory_url' => '/repertoire',
    'solution_url' => '/fiche/',
    'registration_url' => '/repertoire/proposer',
    'contact_url' => '/page/nous-ecrire',
    'privacy_url' => '/page/vie-privee',
]);
$configured = SolutionPresenter::detail($partner);
expectSolution(str_contains($configured, 'href="/repertoire"'), 'the breadcrumb must follow the configured directory URL.');
expectSolution(str_contains($configured, 'href="/repertoire/categorie/visioconference"'), 'term chips must follow the configured directory URL.');
expectSolution(str_contains($configured, 'href="/page/nous-ecrire"'), 'the error-report link must follow the configured contact URL.');
expectSolution(str_contains(SolutionPresenter::listing([]), '/repertoire/proposer'), 'the empty state must follow the configured registration URL.');
expectSolution(
    str_contains(SolutionPresenter::listing([$partner]), 'href="/fiche/aifel"'),
    'cards must follow the configured solution URL.'
);
$configuredForm = SolutionPresenter::registrationForm([], [], ['endpoint_slug' => 'solution-registration']);
expectSolution(str_contains($configuredForm, 'href="/page/vie-privee"'), 'the privacy link must follow the configured URL.');
expectSolution(! str_contains($configuredForm, 'data-success-msg'), 'the inert success-message attribute must be gone.');
SolutionPresenter::configure([]);
expectSolution(str_contains(SolutionPresenter::listing([]), '/annuaire/referencer-une-solution'), 'defaults must survive an empty configuration.');
if ($failures > 0) {
    fwrite(STDERR, "solutions-component tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "solutions-component tests: PASS\n");