<?php

namespace App\Tests\Mobile;

use App\Entity\Announcement;
use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Enum\AnnouncementCategory;
use App\Entity\Enum\ArticleStatus;
use App\Entity\User;
use App\Service\Push\FcmClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * API de l'application mobile, de bout en bout, sur la base de test
 * (suffixe « _test »). Préparer une fois :
 *   php bin/console doctrine:database:create --env=test --if-not-exists
 *   php bin/console doctrine:migrations:migrate --env=test -n
 */
final class MobileApiTest extends WebTestCase
{
    private const PASSWORD = 'Mot-de-passe-de-test-1';

    private KernelBrowser $client;
    private int $categoryId;

    /** @var list<string> fichiers téléversés pendant le test, à supprimer */
    private array $uploads = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $connection = $this->em()->getConnection();
        // « _test », suivi au besoin de TEST_TOKEN : jamais la base de travail.
        self::assertMatchesRegularExpression('/_test(_[A-Za-z0-9]+)?$/', (string) $connection->getDatabase(), 'Les tests ne vident qu’une base de test.');

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['mobile_token', 'mobile_request_key', 'article_revision', 'article_tag', 'article_document', 'media', 'article', 'author', 'user', 'category', 'home_banner', 'announcement', 'ad_event', 'activity_log'] as $table) {
            $connection->executeStatement('DELETE FROM `'.$table.'`');
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        static::getContainer()->get('cache.app')->clear();

        $category = (new Category())->setName('Société')->setSlug('societe')->setColor('#0B6E4F');
        $this->em()->persist($category);
        $this->em()->flush();
        $this->categoryId = (int) $category->getId();

        foreach ([
            'journaliste' => ['ROLE_EDITOR'],
            'collegue' => ['ROLE_EDITOR'],
            'chef' => ['ROLE_EDITOR_IN_CHIEF'],
            'correcteur' => ['ROLE_PROOFREADER'],
            'admin' => ['ROLE_ADMIN'],
            'lecteur' => [],
        ] as $name => $roles) {
            $this->createUser($name, $roles);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->uploads as $url) {
            @unlink(\dirname(__DIR__, 2).'/public'.$url);
        }
        parent::tearDown();
    }

    // ---- Connexion --------------------------------------------------------

    public function testLoginAndSessionRules(): void
    {
        $this->call('POST', '/login', null, ['email' => 'journaliste@test.bj', 'password' => 'faux']);
        self::assertResponseStatusCodeSame(401);

        $this->call('POST', '/login', null, ['email' => 'lecteur@test.bj', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(403);

        $token = $this->login('journaliste');
        $me = $this->call('GET', '/me', $token);
        self::assertResponseIsSuccessful();
        self::assertContains('articles.write', $me['user']['permissions']);
        self::assertFalse($me['user']['admin']);

        $this->call('GET', '/me', null);
        self::assertResponseStatusCodeSame(401);
        $this->call('GET', '/me', 'bbm_inconnu');
        self::assertResponseStatusCodeSame(401);

        $this->call('POST', '/logout', $token);
        self::assertResponseIsSuccessful();
        $this->call('GET', '/me', $token);
        self::assertResponseStatusCodeSame(401);
    }

    public function testPasswordChangeOrDisabledAccountCutsThePhone(): void
    {
        $first = $this->login('journaliste');
        $this->user('journaliste')->setPassword('autre-empreinte');
        $this->em()->flush();
        $this->call('GET', '/me', $first);
        self::assertResponseStatusCodeSame(401);

        $second = $this->login('chef');
        $this->user('chef')->setEnabled(false);
        $this->em()->flush();
        $this->call('GET', '/me', $second);
        self::assertResponseStatusCodeSame(401);
    }

    public function testRepeatedFailuresAreThrottled(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->call('POST', '/login', null, ['email' => 'chef@test.bj', 'password' => 'faux']);
        }
        $this->call('POST', '/login', null, ['email' => 'chef@test.bj', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(429);
    }

    // ---- Circuit éditorial -------------------------------------------------

    public function testFullEditorialCircuit(): void
    {
        $journalist = $this->login('journaliste');
        $chief = $this->login('chef');
        $proofreader = $this->login('correcteur');

        $article = $this->createArticle($journalist, 'demande-0001', ['status' => 'published']);
        self::assertSame('draft', $article['status'], 'Le statut envoyé par le téléphone est ignoré.');
        self::assertContains('submit', $article['actions']);
        self::assertNotContains('publish', $article['actions']);

        $this->act($journalist, $article, 'publish');
        self::assertResponseStatusCodeSame(403);

        $article = $this->act($journalist, $article, 'submit')['article'];
        self::assertSame('in_review', $article['status']);
        self::assertFalse($article['canEdit']);
        $this->call('PUT', '/articles/'.$article['id'], $journalist, ['version' => $article['version'], 'title' => 'Modifié en douce']);
        self::assertResponseStatusCodeSame(403);

        $queue = $this->call('GET', '/articles?scope=review', $chief);
        self::assertSame($article['id'], $queue['items'][0]['id']);
        $article = $this->call('GET', '/articles/'.$article['id'], $chief)['article'];

        $this->act($chief, $article, 'reject_review');
        self::assertResponseStatusCodeSame(422);
        $article = $this->act($chief, $article, 'reject_review', ['reason' => 'Sources à préciser'])['article'];
        self::assertSame('draft', $article['status']);

        $inbox = $this->call('GET', '/inbox', $journalist);
        $returned = array_column($inbox['sections'], null, 'key')['returned'];
        self::assertSame('Sources à préciser', $returned['items'][0]['subtitle']);

        $article = $this->call('GET', '/articles/'.$article['id'], $journalist)['article'];
        $article = $this->act($journalist, $article, 'submit')['article'];
        $article = $this->call('GET', '/articles/'.$article['id'], $chief)['article'];
        $article = $this->act($chief, $article, 'approve_review')['article'];
        self::assertSame('in_correction', $article['status']);

        // Le correcteur retouche l'article d'un collègue sans le sortir du circuit.
        $article = $this->call('GET', '/articles/'.$article['id'], $proofreader)['article'];
        self::assertTrue($article['canEdit']);
        $article = $this->call('PUT', '/articles/'.$article['id'], $proofreader, ['version' => $article['version'], 'content' => '<p>Texte <strong>corrigé</strong>.</p>'])['article'];
        self::assertResponseIsSuccessful();
        self::assertSame('in_correction', $article['status']);
        self::assertFalse($article['pendingApproval']);
        self::assertSame('Journaliste Test', $article['author']);

        $article = $this->act($proofreader, $article, 'publish')['article'];
        self::assertSame('published', $article['status']);
        self::assertNotNull($article['publishedAt']);

        $article = $this->call('GET', '/articles/'.$article['id'], $journalist)['article'];
        self::assertFalse($article['canEdit']);
        self::assertFalse($article['canTrash']);
        $this->call('DELETE', '/articles/'.$article['id'], $journalist);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSchedulingNeedsAFutureDate(): void
    {
        $admin = $this->login('admin');
        $article = $this->createArticle($admin, 'demande-0002');

        $this->act($admin, $article, 'schedule', ['publishAt' => '2000-01-01T10:00:00+01:00']);
        self::assertResponseStatusCodeSame(422);

        $date = (new \DateTimeImmutable('+2 days', new \DateTimeZone('Africa/Porto-Novo')))->setTime(9, 30);
        $article = $this->act($admin, $article, 'schedule', ['publishAt' => $date->format(\DATE_ATOM)])['article'];
        self::assertSame('scheduled', $article['status']);
        self::assertSame($date->getTimestamp(), (new \DateTimeImmutable($article['publishedAt']))->getTimestamp());
    }

    public function testRetriesDoNotDuplicateNorOverwrite(): void
    {
        $journalist = $this->login('journaliste');
        $article = $this->createArticle($journalist, 'demande-0003');

        $again = $this->call('POST', '/articles', $journalist, ['clientId' => 'demande-0003', 'title' => 'Titre', 'content' => '<p>Texte</p>', 'categoryId' => $this->categoryId]);
        self::assertResponseIsSuccessful();
        self::assertSame($article['id'], $again['article']['id']);
        self::assertSame(1, $this->em()->getRepository(Article::class)->count([]));

        $change = ['version' => $article['version'], 'title' => 'Nouveau titre'];
        $this->call('PUT', '/articles/'.$article['id'], $journalist, $change);
        self::assertResponseIsSuccessful();
        // La réponse s'est perdue : le même envoi est accepté.
        $this->call('PUT', '/articles/'.$article['id'], $journalist, $change);
        self::assertResponseIsSuccessful();
        // Une autre modification fondée sur l'ancienne version ne passe pas.
        $conflict = $this->call('PUT', '/articles/'.$article['id'], $journalist, ['version' => $article['version'], 'title' => 'Écrasement']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Nouveau titre', $conflict['article']['title']);
    }

    public function testUnsafeOrUnsupportedHtmlIsRefused(): void
    {
        $journalist = $this->login('journaliste');
        foreach (['<p onclick="x()">a</p>', '<script>alert(1)</script>', '<p><a href="jav&#x09;ascript:alert(1)">a</a></p>', '<iframe src="https://exemple.bj"></iframe>', '<style>p{}</style><p>a</p>', '<p><img src="data:image/png;base64,AAAA"></p>'] as $i => $html) {
            $this->call('POST', '/articles', $journalist, ['clientId' => 'dangereux-'.$i, 'title' => 'Titre', 'content' => $html, 'categoryId' => $this->categoryId]);
            self::assertResponseStatusCodeSame(422, $html);
        }
        self::assertSame(0, $this->em()->getRepository(Article::class)->count([]));

        // Ce que produit réellement l'éditeur Quill 2 passe.
        $quill = '<h2>Titre</h2><p><strong>a</strong> <em>b</em> <u>c</u> <s>d</s> <a href="https://beninbouge.bj" rel="noopener noreferrer" target="_blank">lien</a></p><blockquote>q</blockquote><ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>un</li></ol><p><img src="/uploads/images/x.webp"></p>';
        $this->call('POST', '/articles', $journalist, ['clientId' => 'quill-0001', 'title' => 'Titre', 'content' => $quill, 'categoryId' => $this->categoryId]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testJournalistCannotTouchAColleaguesDraft(): void
    {
        $article = $this->createArticle($this->login('journaliste'), 'demande-0004');
        $colleague = $this->login('collegue');

        $this->call('PUT', '/articles/'.$article['id'], $colleague, ['version' => $article['version'], 'title' => 'Piraté']);
        self::assertResponseStatusCodeSame(403);
        $this->call('DELETE', '/articles/'.$article['id'], $colleague);
        self::assertResponseStatusCodeSame(403);
        $this->act($colleague, $article, 'submit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTrashIsSoftDelete(): void
    {
        $journalist = $this->login('journaliste');
        $article = $this->createArticle($journalist, 'demande-0005');
        $this->call('DELETE', '/articles/'.$article['id'], $journalist);
        self::assertResponseIsSuccessful();
        $this->call('GET', '/articles/'.$article['id'], $journalist);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM article WHERE deleted_at IS NOT NULL'));
        $this->call('POST', '/articles', $journalist, ['clientId' => 'demande-0005', 'title' => 'T', 'content' => '<p>t</p>', 'categoryId' => $this->categoryId]);
        self::assertResponseStatusCodeSame(410);
    }

    /** La faille corrigée : l'ancienne API ne contourne plus le circuit. */
    public function testPublicApiNoLongerBypassesTheWorkflow(): void
    {
        $article = $this->createArticle($this->login('journaliste'), 'demande-0006');
        $basic = ['PHP_AUTH_USER' => 'journaliste@test.bj', 'PHP_AUTH_PW' => self::PASSWORD];

        $this->client->request('PATCH', '/api/articles/'.$article['id'], server: $basic + ['CONTENT_TYPE' => 'application/merge-patch+json'], content: '{"status":"published"}');
        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
        $this->client->request('DELETE', '/api/articles/'.$article['id'], server: $basic);
        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
        $this->client->request('POST', '/api/articles', server: $basic + ['CONTENT_TYPE' => 'application/ld+json'], content: '{"title":"x","content":"y","status":"published"}');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/api/ad_slots', server: $basic + ['CONTENT_TYPE' => 'application/ld+json'], content: '{}');
        self::assertResponseStatusCodeSame(403);

        $this->em()->clear();
        self::assertSame(ArticleStatus::Draft, $this->em()->getRepository(Article::class)->find($article['id'])->getStatus());
    }

    // ---- Régie -------------------------------------------------------------

    public function testBannerLifecycleAndRights(): void
    {
        $journalist = $this->login('journaliste');
        $this->call('GET', '/banners', $journalist);
        self::assertResponseStatusCodeSame(403);
        // Par défaut le rôle Journaliste a « stats.view », comme au back-office.
        $this->call('GET', '/regie/stats', $journalist);
        self::assertResponseIsSuccessful();

        $admin = $this->login('admin');
        $fields = ['clientId' => 'pub-demande-1', 'title' => 'Rentrée', 'advertiser' => 'ACME', 'durationDays' => 15, 'amount' => 50000, 'enabled' => true];

        $this->call('POST', '/banners', $admin, $fields);
        self::assertResponseStatusCodeSame(422, 'Une image est obligatoire.');

        $data = $this->upload('/banners', $admin, $fields);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $banner = $data['banner'];
        $this->uploads[] = $banner['imageUrl'];
        self::assertSame('live', $banner['status']);
        self::assertSame(50000, $banner['amount']);

        $again = $this->upload('/banners', $admin, $fields);
        self::assertSame($banner['id'], $again['banner']['id']);

        $paused = $this->call('POST', '/banners/'.$banner['id'].'/enabled', $admin, ['enabled' => false])['banner'];
        self::assertSame('paused', $paused['status']);

        // Modification partielle : le reste (dont l'image) est conservé.
        $edited = $this->call('POST', '/banners/'.$banner['id'], $admin, ['title' => 'Rentrée 2', 'enabled' => true])['banner'];
        self::assertResponseIsSuccessful();
        self::assertSame('Rentrée 2', $edited['title']);
        self::assertSame($banner['imageUrl'], $edited['imageUrl']);
        self::assertSame('ACME', $edited['advertiser']);

        $this->call('POST', '/banners/'.$banner['id'], $admin, ['title' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->call('POST', '/banners/'.$banner['id'], $admin, ['linkUrl' => 'javascript:alert(1)']);
        self::assertResponseStatusCodeSame(422);

        $stats = $this->call('GET', '/regie/stats?days=7', $admin);
        self::assertResponseIsSuccessful();
        self::assertSame($banner['id'], $stats['banners'][0]['id']);

        // La page « Performance » du back-office utilise le même service.
        $this->client->request('GET', '/admin/regie/statistiques', server: ['PHP_AUTH_USER' => 'admin@test.bj', 'PHP_AUTH_PW' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Rentrée 2', (string) $this->client->getResponse()->getContent());
    }

    public function testAnnouncementsCreateApproveReject(): void
    {
        $admin = $this->login('admin');
        $fields = ['clientId' => 'annonce-demande-1', 'category' => 'emploi', 'title' => 'Recrutement comptable', 'organization' => 'ACME', 'description' => '<p>Poste à pourvoir rapidement à Cotonou.</p>', 'durationDays' => 10, 'withPopup' => true, 'withHomeCard' => false, 'showContact' => false];

        $this->call('POST', '/announcements', $admin, ['clientId' => 'annonce-demande-0', 'durationDays' => 10]);
        self::assertResponseStatusCodeSame(422);

        $created = $this->call('POST', '/announcements', $admin, $fields)['announcement'];
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertTrue($created['online']);
        self::assertTrue($created['withPopup']);
        self::assertSame($created['id'], $this->call('POST', '/announcements', $admin, $fields)['announcement']['id']);

        $paid = (new Announcement())
            ->setCategory(AnnouncementCategory::Event)->setTitle('Concert à Ouidah')->setOrganization('Asso')
            ->setDescription('Une description suffisamment longue pour passer.')->setDurationDays(5);
        $paid->markPaid(new \DateTimeImmutable('-3 days'));
        $this->em()->persist($paid);
        $this->em()->flush();

        $this->call('POST', '/announcements/'.$paid->getId().'/approve', $this->login('journaliste'));
        self::assertResponseStatusCodeSame(403);

        $queue = $this->call('GET', '/announcements?tab=review', $admin);
        self::assertSame(1, $queue['pendingCount']);
        self::assertTrue($queue['items'][0]['reviewOverdue']);

        $approved = $this->call('POST', '/announcements/'.$paid->getId().'/approve', $admin)['announcement'];
        self::assertTrue($approved['online']);
        $this->call('POST', '/announcements/'.$paid->getId().'/approve', $admin);
        self::assertResponseStatusCodeSame(409);
        $this->call('POST', '/announcements/'.$paid->getId().'/reject', $admin, []);
        self::assertResponseStatusCodeSame(422);
        $rejected = $this->call('POST', '/announcements/'.$paid->getId().'/reject', $admin, ['reason' => 'Contenu trompeur'])['announcement'];
        self::assertSame('rejected', $rejected['status']);
    }

    // ---- Images et notifications -------------------------------------------

    public function testImageUploadIsConvertedAndChecked(): void
    {
        $journalist = $this->login('journaliste');
        $this->client->request('POST', '/api/mobile/v1/uploads/images', files: ['image' => $this->png()], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$journalist]);
        self::assertResponseStatusCodeSame(201);
        $url = json_decode((string) $this->client->getResponse()->getContent(), true)['url'];
        $this->uploads[] = $url;
        self::assertStringEndsWith('.webp', $url);

        $path = (string) tempnam(sys_get_temp_dir(), 'bb');
        file_put_contents($path, '<?php echo 1;');
        $this->client->request('POST', '/api/mobile/v1/uploads/images', files: ['image' => new UploadedFile($path, 'image.png', 'image/png', null, true)], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$journalist]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testSubmittingNotifiesTheChiefEditorOnly(): void
    {
        $journalist = $this->login('journaliste');
        $chief = $this->login('chef');
        $proofreader = $this->login('correcteur');
        $this->call('POST', '/push-token', $journalist, ['pushToken' => 'tel-journaliste']);
        $this->call('POST', '/push-token', $chief, ['pushToken' => 'tel-chef']);
        $this->call('POST', '/push-token', $proofreader, ['pushToken' => 'tel-correcteur']);
        $article = $this->createArticle($journalist, 'demande-0007');

        $sent = [];
        $fcm = $this->createStub(FcmClient::class);
        $fcm->method('isConfigured')->willReturn(true);
        $fcm->method('send')->willReturnCallback(function (string $device, string $title) use (&$sent): bool {
            $sent[] = [$device, $title];

            return true;
        });
        // Noyau neuf : le faux service doit être posé avant tout usage du vrai.
        $this->client->disableReboot();
        $this->client->getKernel()->shutdown();
        $this->client->getKernel()->boot();
        static::getContainer()->set(FcmClient::class, $fcm);

        $this->act($journalist, $article, 'submit');
        self::assertResponseIsSuccessful();
        self::assertSame([['tel-chef', 'Article à relire']], $sent);
    }

    // ---- Outils ---------------------------------------------------------------

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @param list<string> $roles */
    private function createUser(string $name, array $roles): void
    {
        $user = (new User())->setEmail($name.'@test.bj')->setDisplayName(ucfirst($name).' Test')->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $this->em()->persist($user);
        $this->em()->flush();
    }

    private function user(string $name): User
    {
        return $this->em()->getRepository(User::class)->findOneBy(['email' => $name.'@test.bj']);
    }

    private function login(string $name): string
    {
        $data = $this->call('POST', '/login', null, ['email' => $name.'@test.bj', 'password' => self::PASSWORD, 'device' => 'Test']);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());

        return $data['token'];
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, ?string $token, ?array $body = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, '/api/mobile/v1'.$path, server: $server, content: null === $body ? null : (string) json_encode($body));

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function upload(string $path, string $token, array $fields): array
    {
        $this->client->request('POST', '/api/mobile/v1'.$path, ['data' => json_encode($fields)], ['imageFile' => $this->png()], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function png(): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'bb');
        $image = imagecreatetruecolor(32, 10);
        imagepng($image, $path);

        return new UploadedFile($path, 'image.png', 'image/png', null, true);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function createArticle(string $token, string $clientId, array $extra = []): array
    {
        $data = $this->call('POST', '/articles', $token, ['clientId' => $clientId, 'title' => 'Un marché rénové à Cotonou', 'content' => '<p>Texte de l\'article.</p>', 'categoryId' => $this->categoryId] + $extra);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());

        return $data['article'];
    }

    /**
     * @param array<string, mixed> $article
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function act(string $token, array $article, string $action, array $extra = []): array
    {
        return $this->call('POST', '/articles/'.$article['id'].'/actions/'.$action, $token, ['version' => $article['version']] + $extra);
    }
}
