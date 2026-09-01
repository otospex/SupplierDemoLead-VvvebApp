<?php

define('V_VERSION', 'test');

$mapperFile = __DIR__ . '/../system/draft-mapper.php';
$creatorFile = __DIR__ . '/../system/draft-creator.php';
if (! is_file($mapperFile) || ! is_file($creatorFile)) {
    fwrite(STDERR, "FAIL: draft mapper or creator is missing.\n");
    exit(1);
}

require_once $mapperFile;
require_once $creatorFile;

use Vvveb\Plugins\SolutionsDirectory\System\DraftCreator;

$failures = 0;

function expectDraft(bool $condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

final class MemoryDraftStore {
    public array $drafts = [];
    public int $creates = 0;

    function findBySubmission(int $submissionId): ?int {
        return $this->drafts[$submissionId]['post_id'] ?? null;
    }

    function create(array $draft): int {
        $this->creates++;
        $postId = 40 + $this->creates;
        $this->drafts[(int) $draft['meta']['submission_id']] = $draft + ['post_id' => $postId];
        return $postId;
    }
}

$config = [
    'post_type' => 'solution',
    'post_template' => 'content/solution.html',
    'meta_namespace' => 'solution',
    'taxonomies' => ['categorie' => 'categorie', 'alternative_a' => 'alternative-a'],
    'language_id' => 2,
    'site_id' => 1,
    'admin_id' => 7,
];

$fields = [
    'kind' => 'logiciel',
    'solution_name' => 'Étoile Suite',
    'website' => 'https://etoile.example.fr',
    'organisation' => 'Étoile SAS',
    'hq_country' => 'FR',
    'contact_name' => 'Camille Martin',
    'email' => 'camille@etoile.example.fr',
    'contact_role' => 'Direction produit',
    'categories' => ['bureautique', 'fichiers-et-partage'],
    'alternative_to' => ['microsoft-365', 'google-workspace'],
    'alternative_to_other' => 'Suite historique interne',
    'pitch' => 'Suite documentaire proposée aux organisations françaises.',
    'advantages' => "Édition collaborative\nExports documentés à examiner",
    'hosting_countries' => 'FR, DE',
    'qualifications' => 'Qualification déclarée — périmètre et date à vérifier',
    'pricing_model' => 'sur-devis',
    'partner_interest' => '1',
];

$store = new MemoryDraftStore();
$creator = new DraftCreator($store, $config);
$first = $creator->createOrFind(314, $fields);
$draft = $store->drafts[314] ?? [];

expectDraft($first === ['post_id' => 41, 'created' => true], 'first action must create one draft.');
expectDraft(($draft['post']['status'] ?? '') === 'draft', 'created solution must stay draft.');
expectDraft(($draft['post']['type'] ?? '') === 'solution', 'post type must come from config.');
expectDraft(($draft['post']['template'] ?? '') === 'content/solution.html', 'solution template must come from config.');
expectDraft(($draft['content']['name'] ?? '') === 'Étoile Suite', 'solution name must map to post content.');
expectDraft(($draft['content']['slug'] ?? '') === 'etoile-suite', 'solution name must produce a stable slug.');
expectDraft(($draft['content']['excerpt'] ?? '') === $fields['pitch'], 'pitch must map to excerpt.');
expectDraft(str_contains($draft['content']['content'] ?? '', 'Édition collaborative'), 'advantages must be pre-formatted into the review body.');
expectDraft(str_contains($draft['content']['content'] ?? '', 'périmètre et date à vérifier'), 'qualifications must be pre-formatted into the review body.');
expectDraft(($draft['meta']['verification_status'] ?? '') === 'declare', 'new submissions must start declared, not verified.');
expectDraft(($draft['meta']['commercial_relationship'] ?? '') === 'aucune', 'partner interest must not create a commercial relationship.');
expectDraft(($draft['meta']['submitted_by_email'] ?? '') === $fields['email'], 'submitter email must map to private admin meta.');
expectDraft(($draft['meta']['submission_id'] ?? null) === '314', 'submission id must be stored for idempotence.');
expectDraft(($draft['terms']['categorie'] ?? []) === $fields['categories'], 'category term slugs must be linked.');
expectDraft(($draft['terms']['alternative-a'] ?? []) === $fields['alternative_to'], 'alternative term slugs must be linked.');

$second = $creator->createOrFind(314, ['solution_name' => 'Tentative de doublon']);
expectDraft($second === ['post_id' => 41, 'created' => false], 'second action must return the existing draft.');
expectDraft($store->creates === 1, 'idempotent action must create exactly one post.');

if ($failures > 0) {
    fwrite(STDERR, "draft-action tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "draft-action tests: PASS\n");
