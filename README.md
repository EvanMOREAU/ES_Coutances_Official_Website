# ⚽ ES Coutances — Site Officiel

Site web officiel de l'**Entente Sportive Coutançaise**, club de football basé à Coutances (50200), Normandie.  
Développé par **Evan MOREAU**.

---

## 🛠️ Stack technique

| Technologie | Version | Rôle |
|---|---|---|
| PHP | 8.2+ | Langage serveur |
| Symfony | 7.x | Framework principal |
| Doctrine ORM | 3.x | ORM / Base de données |
| MySQL / MariaDB | 8.x | Base de données |
| EasyAdmin | 5.x | Back-office administration |
| VichUploader | — | Gestion des uploads d'images |
| Symfony Mailer | — | Envoi d'emails (contact) |
| Symfony Cache | — | Cache des appels API Facebook |
| Symfony HttpClient | — | Appels API externes (Facebook Graph) |
| Twig | 3.x | Moteur de templates |
| CSS natif | — | Pas de Bootstrap — Grid/Flexbox pur |
| Font Awesome | 6.5 | Icônes |
| Barlow Condensed + Inter | — | Typographies (Google Fonts) |

---

## 📁 Structure du projet

```
escoutances_symfony/
├── config/
│   ├── packages/
│   │   ├── security.yaml
│   │   └── vich_uploader.yaml
│   └── services.yaml
├── public/
│   ├── index.php
│   ├── images/
│   │   └── favicon.png
│   └── uploads/            ← gitignore
│       ├── slides/
│       ├── offres/
│       ├── membres/
│       └── partenaires/
├── src/
│   ├── Command/
│   │   ├── CreateUserCommand.php
│   │   └── InitPagesCommand.php
│   ├── Controller/
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── CategorieCrudController.php
│   │   │   ├── EquipeCrudController.php
│   │   │   ├── MembreCrudController.php
│   │   │   ├── OffreEmploiCrudController.php
│   │   │   ├── PageContenuCrudController.php
│   │   │   ├── PartenaireCrudController.php
│   │   │   ├── SlideCarouselCrudController.php
│   │   │   └── UserCrudController.php
│   │   ├── ClubController.php
│   │   ├── ContactController.php
│   │   ├── DefaultController.php
│   │   ├── GalerieController.php
│   │   └── NavController.php
│   ├── Entity/
│   │   ├── Categorie.php
│   │   ├── Equipe.php
│   │   ├── Membre.php
│   │   ├── OffreEmploi.php
│   │   ├── PageContenu.php
│   │   ├── Partenaire.php
│   │   ├── SlideCarousel.php
│   │   └── User.php
│   ├── Form/
│   │   └── ContactType.php
│   ├── Repository/
│   ├── Security/
│   │   └── UserVoter.php
│   └── Service/
│       ├── FacebookService.php
│       └── OrdreService.php
├── templates/
│   ├── admin/
│   │   └── dashboard.html.twig
│   ├── club/
│   │   ├── _layout.html.twig
│   │   ├── encadrement.html.twig
│   │   ├── histoire.html.twig
│   │   └── infrastructure.html.twig
│   ├── contact/
│   │   └── index.html.twig
│   ├── default/
│   │   └── index.html.twig
│   ├── galerie/
│   │   └── index.html.twig
│   ├── login/
│   │   └── index.html.twig
│   ├── nav/
│   │   ├── _equipes_dropdown.html.twig
│   │   └── _equipes_mobile.html.twig
│   └── base.html.twig
├── .env
├── .env.local          ← JAMAIS commité
├── .gitignore
├── LICENSE
└── composer.json
```

---

## 🗃️ Entités Doctrine

| Entité | Description |
|---|---|
| `User` | Utilisateurs du back-office |
| `Equipe` | Équipes du club (catégorie, niveau, lien FFF) |
| `OffreEmploi` | Offres d'emploi / formations |
| `SlideCarousel` | Slides du carousel hero |
| `PageContenu` | Pages éditables (Histoire, Infrastructure) |
| `Membre` | Membres de l'encadrement |
| `Categorie` | Catégories d'encadrement (Senior, Académie...) |
| `Partenaire` | Partenaires & sponsors (nom, logo, url) |

---

## 👥 Rôles utilisateurs

| Rôle | Accès |
|---|---|
| `ROLE_DEV` | Accès total + gestion des comptes développeurs + gestion des équipes |
| `ROLE_ADMIN` | Accès à tout le back-office sauf comptes dev |
| `ROLE_EDITOR` | Accès limité |
| `ROLE_USER` | Rôle de base (hérité par tous) |

**Hiérarchie :** `ROLE_DEV` → `ROLE_ADMIN` → `ROLE_EDITOR` → `ROLE_USER`

**Accès admin :** raccourci clavier `Ctrl + Shift + A` depuis n'importe quelle page du site.

**Sécurité utilisateurs :**
- Seul un `ROLE_DEV` peut modifier ou supprimer un compte `ROLE_DEV`
- Le bouton Delete/Edit est masqué visuellement pour les non-dev (via `UserVoter`)
- Les comptes dev apparaissent toujours en premier dans la liste

---

## ⚙️ Installation

### Prérequis

- PHP 8.2+
- Composer
- MySQL / MariaDB
- Symfony CLI (optionnel)

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/EvanMOREAU/Football_Website_Symfony.git
cd Football_Website_Symfony

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec tes valeurs

# 4. Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Initialiser les pages de contenu
php bin/console app:init-pages

# 6. Créer le premier utilisateur développeur
php bin/console app:create-user dev@exemple.fr MotDePasse "Prénom Nom" ROLE_DEV

# 7. Créer les dossiers d'uploads
mkdir -p public/uploads/slides public/uploads/offres public/uploads/membres public/uploads/partenaires

# 8. Vider le cache
php -d memory_limit=512M bin/console cache:clear

# 9. Lancer le serveur
symfony server:start
```

---

## 🔐 Variables d'environnement

```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/escoutances"
MAILER_DSN=smtp://localhost:1025
FACEBOOK_PAGE_ID=ententesportivecoutancaise
FACEBOOK_ACCESS_TOKEN=ton_token_long_lived_ici
```

> ⚠️ **Ne jamais commiter `.env.local`**

---

## 📱 Pages du site

| URL | Description |
|---|---|
| `/` | Page d'accueil |
| `/club/histoire` | Histoire du club (éditable) |
| `/club/encadrement` | Encadrement par catégorie |
| `/club/infrastructure` | Infrastructure (éditable) |
| `/contact` | Formulaire de contact |
| `/galerie` | Galerie photos avec lightbox |
| `/admin` | Back-office EasyAdmin |
| `/admin/login` | Page de connexion admin |

---

## 🔧 Commandes utiles

```bash
# Vider le cache
php -d memory_limit=512M bin/console cache:clear

# Créer un utilisateur
php bin/console app:create-user email@exemple.fr MotDePasse "Prénom Nom" ROLE_DEV

# Migration après modification d'entité
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Lister les routes
php bin/console debug:router
```

---

## 🖼️ Gestion des images

| Mapping | Dossier | Utilisé pour |
|---|---|---|
| `slide_image` | `/uploads/slides/` | Carousel hero |
| `offre_image` | `/uploads/offres/` | Offres d'emploi |
| `membre_photo` | `/uploads/membres/` | Encadrement |
| `partenaire_logo` | `/uploads/partenaires/` | Partenaires & sponsors |

---

## ⚡ Optimisations en place

- **`loading="lazy"`** sur toutes les images hors viewport initial
- **`loading="eager"`** sur les slides hero et le logo
- **Cache HTTP 5 min** sur la page d'accueil
- **Cache Symfony 30 min** sur les appels API Facebook
- **Eager loading Doctrine** sur les membres par catégorie
- **OrdreService** — gestion automatique et unique des ordres d'affichage

---

## 🔢 Gestion de l'ordre d'affichage (OrdreService)

- **Auto-incrémentation** à la création (dernier ordre + 1)
- **Unicité garantie** — si un ordre est déjà pris, les suivants sont décalés de +1
- **Entités concernées** : `Equipe`, `Membre`, `Partenaire`, `SlideCarousel`, `OffreEmploi`, `Categorie`

---

## 📘 API Facebook Graph (Non fonctionnel)

- Posts récupérés via l'API Graph v19
- Cache 30 minutes (Symfony Cache)
- Fallback : boutons réseaux sociaux si l'API échoue
- Token à renouveler tous les ~60 jours sur [developers.facebook.com/tools/explorer](https://developers.facebook.com/tools/explorer)

---

## 🚀 Déploiement en production

```bash
APP_ENV=prod
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console doctrine:migrations:migrate --env=prod
```

---

## 👨‍💻 Développeur

**Evan MOREAU**  
📧 evan.moreau@etik.com

---

## 📄 Licence

Projet propriétaire — © 2026 Evan MOREAU.  
Tous droits réservés. Voir le fichier [LICENSE](./LICENSE) pour les détails.
