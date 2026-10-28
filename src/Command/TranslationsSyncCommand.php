<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\PageContenu;
use App\Service\Locale\LocaleRegistry;
use App\Service\Locale\Locales;
use App\Translation\ContentTranslator;
use App\Translation\HtmlTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Rattrape les traductions automatiques manquantes : contenu déjà saisi (offres, pages, boutique…) et textes du site
 * absents des fichiers de traduction. À lancer après avoir configuré DEEPL_API_KEY ou activé une nouvelle langue ;
 * seuls les textes jamais traduits sont envoyés à DeepL.
 */
#[AsCommand(name: 'app:translations:sync', description: 'Traduit (DeepL) les textes pas encore traduits et les enregistre en base')]
class TranslationsSyncCommand extends Command
{
    /** Zones du fichier de traduction réservées à l'administration : non traduites sauf avec --admin. */
    private const ADMIN_KEY_PREFIXES = ['admin.', 'settings.', 'dashboard.', 'notif.'];

    public function __construct(
        private readonly ContentTranslator $content,
        private readonly LocaleRegistry $locales,
        private readonly TranslatorInterface $translator,
        private readonly HtmlTranslator $html,
        private readonly EntityManagerInterface $em,
        private readonly HttpKernelInterface $kernel,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limiter à ces langues (par défaut : les langues actives)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Traduire aussi les textes de l\'administration')
            ->addOption('contenu-seulement', null, InputOption::VALUE_NONE, 'Ne traduire que le contenu saisi (pas les textes du site)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->content->isAvailable()) {
            $io->error('Aucune clé DeepL : renseignez DEEPL_API_KEY dans .env.local.');

            return Command::FAILURE;
        }

        /** @var list<string> $locales */
        $locales = $input->getOption('locale') ?: array_keys(array_diff_key($this->locales->enabled(), [Locales::DEFAULT => true]));
        $total   = $this->content->translateAllContent($locales);
        $io->writeln(sprintf('Contenu saisi : %d traduction(s) enregistrée(s).', $total));

        if (!$input->getOption('contenu-seulement')) {
            $texts = $this->siteTexts((bool) $input->getOption('admin'));
            // Textes écrits en dur dans les gabarits : relevés sur les pages publiques rendues en français.
            foreach ($this->pageTexts() as $text) {
                $texts[$text] ??= $text;
            }
            $count = 0;
            foreach ($locales as $locale) {
                // Un texte déjà traduit à la main dans le fichier de la langue n'est pas renvoyé à DeepL.
                $missing = [];
                foreach ($texts as $id => $french) {
                    if (!$this->translator instanceof TranslatorBagInterface || !$this->translator->getCatalogue($locale)->defines((string) $id)) {
                        $missing[] = $french;
                    }
                }
                $count += $this->content->translateTexts($missing, false, [$locale]);
            }
            $io->writeln(sprintf('Textes du site : %d traduction(s) enregistrée(s) (%d texte(s) source).', $count, count($texts)));
            $total += $count;
        }

        $io->success(sprintf('%d traduction(s) ajoutée(s).', $total));

        return Command::SUCCESS;
    }

    /**
     * Textes français du site : valeurs du fichier messages.fr.yaml et phrases utilisées comme clé dans les gabarits.
     *
     * @return array<string, string> identifiant (clé ou phrase) => texte français
     */
    private function siteTexts(bool $withAdmin): array
    {
        $texts = [];
        if ($this->translator instanceof TranslatorBagInterface) {
            foreach ($this->translator->getCatalogue('fr')->all('messages') as $key => $value) {
                if (!$withAdmin && $this->isAdminKey((string) $key)) {
                    continue;
                }
                if (!str_contains((string) $value, '|')) {
                    $texts[(string) $key] = (string) $value;
                }
            }
        }

        $finder = (new Finder())->files()->in($this->projectDir.'/templates')->name('*.twig');
        if (!$withAdmin) {
            $finder->notPath('admin');
        }
        foreach ($finder as $file) {
            if (preg_match_all("/\\{\\{\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\|\\s*trans\\b/", $file->getContents(), $matches)) {
                foreach ($matches[1] as $sentence) {
                    $sentence = stripslashes($sentence);
                    if (1 !== preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $sentence) && !str_contains($sentence, '|')) {
                        $texts[$sentence] = $sentence;
                    }
                }
            }
        }

        return $texts;
    }

    /**
     * Textes français affichés par les pages publiques (rendues en interne, en français).
     *
     * @return list<string>
     */
    private function pageTexts(): array
    {
        $urls = ['/', '/club/histoire', '/club/encadrement', '/club/infrastructure', '/contact', '/boutique', '/boutique/panier',
            '/mentions-legales', '/politique-de-confidentialite', '/conditions-de-vente', '/cookies', '/mon-compte/connexion',
            '/mon-compte/inscription', '/reset-password'];
        foreach ($this->em->getRepository(PageContenu::class)->findAll() as $page) {
            $urls[] = '/page/'.$page->getSlug();
        }
        foreach ($this->em->getRepository(Article::class)->findBy(['statut' => 'active']) as $article) {
            $urls[] = '/boutique/article/'.$article->getSlug();
        }

        $texts = [];
        foreach ($urls as $url) {
            $response = $this->kernel->handle(Request::create($url, 'GET'), HttpKernelInterface::MAIN_REQUEST, true);
            if ($response->isSuccessful()) {
                foreach ($this->html->collect((string) $response->getContent()) as $text) {
                    $texts[$text] = true;
                }
            }
        }

        return array_map('strval', array_keys($texts));
    }

    private function isAdminKey(string $key): bool
    {
        foreach (self::ADMIN_KEY_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
