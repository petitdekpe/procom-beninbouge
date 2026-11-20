<?php

namespace App\Security;

/**
 * Catalogue des permissions de l'administration. Un rôle (App\Entity\Role)
 * est un ensemble de permissions, modifiable depuis /admin/roles ; un
 * utilisateur cumule les permissions de tous ses rôles
 * (App\Security\PermissionChecker). Le rôle ROLE_ADMIN a toutes les
 * permissions, toujours.
 *
 * Les contrôleurs d'admin se protègent avec ces valeurs, par exemple
 * #[IsGranted('recaps.manage')] ou is_granted('stats.view') dans Twig
 * (App\Security\PermissionVoter).
 */
enum Permission: string
{
    /** Pseudo-permission : vraie dès qu'un rôle donne au moins une permission. */
    public const ADMIN_ACCESS = 'admin.access';

    // Contenu
    case ArticlesWrite = 'articles.write';
    case ArticlesPublish = 'articles.publish';
    case RecapsManage = 'recaps.manage';
    case TaxonomyManage = 'taxonomy.manage';
    case MediaManage = 'media.manage';
    case PagesManage = 'pages.manage';

    // Rédaction
    case WorkflowReview = 'workflow.review';
    case WorkflowProofread = 'workflow.proofread';
    case CommentsModerate = 'comments.moderate';
    case ValidationsManage = 'validations.manage';

    // Régie
    case AnnouncementsManage = 'announcements.manage';
    case AnnouncementsPricing = 'announcements.pricing';
    case AdsManage = 'ads.manage';

    // Audience
    case StatsView = 'stats.view';
    case ContactManage = 'contact.manage';

    // Site
    case HomeManage = 'home.manage';
    case TeamManage = 'team.manage';
    case SettingsManage = 'settings.manage';

    // Accès
    case AuthorsManage = 'authors.manage';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';

    public function label(): string
    {
        return match ($this) {
            self::ArticlesWrite => 'Écrire et modifier des articles',
            self::ArticlesPublish => 'Dépublier, archiver et désarchiver des articles',
            self::RecapsManage => 'Gérer les récaps (vidéos verticales)',
            self::TaxonomyManage => 'Gérer catégories, mots-clés et dossiers',
            self::MediaManage => 'Gérer la médiathèque',
            self::PagesManage => 'Gérer les pages et les projets',
            self::WorkflowReview => 'Relire les articles (rédacteur en chef)',
            self::WorkflowProofread => 'Corriger les articles (correcteur)',
            self::CommentsModerate => 'Modérer les commentaires',
            self::ValidationsManage => 'Valider les demandes (auteurs, articles à approuver)',
            self::AnnouncementsManage => 'Gérer les annonces et opportunités',
            self::AnnouncementsPricing => 'Modifier les tarifs des annonces',
            self::AdsManage => 'Gérer les publicités du slider',
            self::StatsView => 'Consulter les statistiques',
            self::ContactManage => 'Lire les messages de contact et la newsletter',
            self::HomeManage => 'Organiser la page d\'accueil',
            self::TeamManage => 'Gérer la page Équipe',
            self::SettingsManage => 'Modifier les paramètres du site',
            self::AuthorsManage => 'Gérer les auteurs',
            self::UsersManage => 'Gérer les utilisateurs',
            self::RolesManage => 'Gérer les rôles et permissions',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ArticlesWrite, self::ArticlesPublish, self::RecapsManage, self::TaxonomyManage, self::MediaManage, self::PagesManage => 'Contenu',
            self::WorkflowReview, self::WorkflowProofread, self::CommentsModerate, self::ValidationsManage => 'Rédaction',
            self::AnnouncementsManage, self::AnnouncementsPricing, self::AdsManage => 'Régie',
            self::StatsView, self::ContactManage => 'Audience',
            self::HomeManage, self::TeamManage, self::SettingsManage => 'Site',
            self::AuthorsManage, self::UsersManage, self::RolesManage => 'Équipe et accès',
        };
    }

    /**
     * @return array<string, list<self>> permissions par groupe, dans l'ordre d'affichage
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }

}
