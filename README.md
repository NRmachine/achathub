# AchatHub

Boutique e-commerce développée exclusivement avec Laravel (PHP), Blade et Bootstrap.

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan achathub:import
php artisan serve
```

Le site est disponible sur `http://localhost:8000`. Avec Laravel Herd, liez le dossier puis ouvrez `http://achathub.test`.

## Administration

- URL : `/admin`
- E-mail : `admin@achathub.fr`
- Mot de passe : défini directement dans l’environnement sécurisé de déploiement, jamais dans le dépôt.

L’administration permet de gérer le catalogue, la mise en avant des produits, les stocks, les commandes classiques et professionnelles, les profils clients, les paiements, la messagerie privée, les demandes RGPD et les contenus légaux. Les mots de passe des clients ne sont jamais affichés ni modifiables par un administrateur.

## Messagerie et conformité

- Messagerie privée unique entre AchatHub et chaque client ou revendeur.
- Historique des commandes et précommandes conservé après validation.
- Bandeau cookies avec acceptation ou refus.
- Conditions générales, confidentialité, cookies et mentions légales.
- Demandes d’accès, rectification, suppression, opposition et portabilité depuis le compte.

Complétez les informations juridiques réelles dans `/admin/reglages` avant l’ouverture commerciale.

## Agent fournisseur LCD Phone

L’écran `/admin/fournisseur` permet de découvrir des fiches LCD Phone, de suivre chaque variante et d’associer une référence fournisseur à un SKU AchatHub. Une suggestion de nom ne modifie jamais le catalogue : la synchronisation du stock exige une correspondance exacte ou une validation manuelle.

Les identifiants fournisseur sont conservés uniquement dans `.env` ou `.env.production` :

```dotenv
LCD_PHONE_EMAIL=
LCD_PHONE_PASSWORD=
LCD_PHONE_CATEGORY_URLS=https://lcd-phone.com/fr/49-accessoires
```

Commandes d’exploitation :

```bash
php artisan supplier:catalog-tree
php artisan supplier:crawl-catalog --path="Pièces Détachées > Apple > iPhone > iPhone 11" --pages=5 --products=100
php artisan supplier:crawl-catalog --nodes=2 --pages=2 --products=100
php artisan supplier:discover --pages=1 --limit=20
php artisan supplier:sync-stock
php artisan schedule:list
```

`supplier:catalog-tree` reproduit les identifiants et les chemins réels du fournisseur. Le crawler reprend à la prochaine page et à la prochaine fiche après une interruption. Une variante compatible avec plusieurs modèles conserve toutes ses associations sans être dupliquée.

Sur Vercel, l’agent travaille par petits lots dans la route sécurisée
`/internal/cron/fournisseur`. Le secret `CRON_SECRET` est envoyé automatiquement par
Vercel. Chaque fiche et chaque changement de stock restent historisés dans PostgreSQL.

## Production Vercel

La production AchatHub fonctionne uniquement sur Vercel :

- Laravel est construit depuis `Dockerfile.vercel` et écoute le port fourni par Vercel.
- PostgreSQL est fourni par l’intégration Neon du Vercel Marketplace.
- Sessions, cache, comptes, commandes et historique fournisseur sont stockés dans PostgreSQL.
- La file utilise `QUEUE_CONNECTION=sync` et les tâches fournisseur sont volontairement bornées.
- Le cron quotidien déclaré dans `vercel.json` fonctionne sur tous les plans. Un passage à une fréquence supérieure exige un plan Vercel compatible.

Copiez les variables de `.env.vercel.example` dans les environnements Vercel Production
et Preview. `APP_KEY`, `DATABASE_URL`, `CRON_SECRET`, les identifiants fournisseur et les
identifiants d’administration doivent rester des secrets.

Déploiement :

```bash
npx vercel deploy --prod --yes
```

## Migration des données

Créez d’abord le schéma PostgreSQL avec `php artisan migrate --force`, puis effectuez une
copie contrôlée de la base SQLite locale **AchatHub** :

```bash
php artisan achathub:transfer-database --source=sqlite --target=pgsql --truncate
```

La commande refuse explicitement toute source nommée Phone Life. Elle conserve les mots de
passe chiffrés, comptes, commandes, catalogues, profils pro, messages et historiques. Les
sessions et anciennes tâches de file ne sont pas copiées : tous les utilisateurs doivent
se reconnecter après la bascule.

AchatHub ne contient plus de proxy, de route réseau ni de service applicatif dépendant du VPS.
